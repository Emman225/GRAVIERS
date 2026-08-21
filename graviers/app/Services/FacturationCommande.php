<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Models\Paiement;
use Help;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Facturation d'une commande à hauteur de ce qui a été RÉGLÉ.
 *
 * ---------------------------------------------------------------------------
 * LA RÈGLE
 * ---------------------------------------------------------------------------
 * Une commande est facturée à hauteur de ce qui a été payé ou enlevé — le plus
 * avancé des deux — sans jamais dépasser le total de la commande, ni facturer
 * deux fois :
 *
 *      cible = min(dû, max(réglé, enlevé))
 *      on émet une facture pour (cible − déjà facturé) si c'est positif.
 *
 * Cette classe couvre le versant PAIEMENT. Le versant ENLÈVEMENT reste assuré
 * par OrdersController::genererFacture(), qui facture les enlèvements choisis
 * au back-office ; un garde-fou y a été ajouté pour qu'il ne dépasse jamais le
 * total de la commande, sans quoi les deux chemins pourraient facturer deux
 * fois la même marchandise.
 *
 * ---------------------------------------------------------------------------
 * POURQUOI
 * ---------------------------------------------------------------------------
 * Un client qui payait sa commande d'avance n'avait AUCUNE facture tant que la
 * marchandise n'était pas enlevée. Son tableau de bord affichait « Réglé
 * d'avance — en attente de facturation », et il n'avait aucun document à
 * présenter pour de l'argent déjà versé. Décision de gestion du 11/08/2026 :
 * dès que le règlement est encaissé, la facture est établie et lui est
 * accessible.
 *
 * La CERTIFICATION DGI n'est PAS déclenchée ici : la facture est créée
 * localement, et sa validation auprès de la DGI reste l'action manuelle du
 * back-office. Une facture certifiée se corrige beaucoup plus difficilement
 * qu'une facture locale.
 */
class FacturationCommande
{
    /**
     * Émet, si nécessaire, la facture correspondant à ce qui a été réglé.
     *
     * Retourne la facture créée, ou null s'il n'y avait rien à facturer.
     * N'échoue jamais bruyamment : elle est appelée depuis des flux de
     * paiement, où une erreur de facturation ne doit sous aucun prétexte
     * empêcher l'encaissement d'aboutir.
     */
    public static function facturerCeQuiEstRegle(Commande $commande, bool $envoyerAuClient = true): ?Facture
    {
        try {
            $facture = DB::transaction(function () use ($commande) {
                // Relecture verrouillée : deux règlements simultanés sur la même
                // commande produiraient sinon deux factures pour la même somme.
                $commande = Commande::where('id', $commande->id)->lockForUpdate()->first();
                if (!$commande) {
                    return null;
                }

                $du          = round(self::montantDu($commande));
                $regle       = round(self::montantRegle($commande));
                $dejaFacture = round(self::montantDejaFacture($commande));

                // On ne facture jamais au-delà du dû, ni deux fois.
                $aFacturer = min($du, $regle) - $dejaFacture;

                // Le franc d'écart n'est pas une facture : les arrondis de TVA
                // en produisent, et une facture à 1 FCFA n'a aucun sens.
                if ($aFacturer < 1) {
                    return null;
                }

                // Part de remise et de livraison restant à imputer. Elle est
                // répartie au prorata de ce qui est facturé maintenant sur ce
                // qui restait à facturer : une commande soldée en une fois
                // reçoit donc la totalité du reliquat, sans dérive d'arrondi.
                $resteAFacturer = max(1.0, $du - $dejaFacture);
                $part           = min(1.0, $aFacturer / $resteAFacturer);

                $remiseRestante    = max(0, (float) ($commande->remise ?? 0) - self::sommeFactures($commande, 'remise_appliquee'));
                $livraisonRestante = max(0, (float) ($commande->cout_livraison_client ?? 0) - self::sommeFactures($commande, 'cout_livraison_applique'));

                $facture = Facture::create([
                    'numero'                  => Help::genererNumeroUnique('facture'),
                    'numero_fne'              => FneService::genererNumeroFne(),
                    'user_id'                 => $commande->client->user_id ?? null,
                    'client_id'               => $commande->client_id,
                    'service'                 => Help::$COMMANDE,
                    'service_id'              => $commande->id,
                    'montant'                 => $aFacturer,
                    'remise_appliquee'        => round($remiseRestante * $part),
                    'cout_livraison_applique' => round($livraisonRestante * $part),
                    'statut'                  => 2,
                    // Créée localement : la certification DGI reste manuelle.
                    'fne_status'              => 'pending',
                ]);

                self::rattacherLesReglements($commande, $facture);

                return $facture;
            });

            // L'envoi se fait APRÈS la validation en base, jamais dedans : un
            // serveur de messagerie lent retiendrait sinon la transaction
            // ouverte, et le verrou avec elle.
            if ($facture && $envoyerAuClient) {
                self::envoyerAuClient($commande, $facture);
            }

            return $facture;
        } catch (\Throwable $e) {
            // Un paiement encaissé ne doit jamais être perdu parce que sa
            // facture n'a pas pu être écrite. On trace et on laisse passer :
            // le back-office pourra facturer à la main.
            Log::error('Facturation automatique impossible pour la commande '
                . ($commande->numero ?? $commande->id) . ' : ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transmet la facture au client, en pièce jointe PDF.
     *
     * L'envoi ne peut en aucun cas faire échouer quoi que ce soit : il est
     * appelé depuis un flux de paiement, et un serveur de messagerie
     * indisponible ne doit jamais empêcher un encaissement d'aboutir. Chaque
     * étape est donc protégée, et un échec se contente d'une trace dans le
     * journal — la facture reste consultable dans l'espace du client.
     */
    private static function envoyerAuClient(Commande $commande, Facture $facture): void
    {
        $client = $commande->client;
        $email  = $client->email ?? $client->user->email ?? null;

        if (!$email) {
            return;
        }

        // L'envoi est repoussé APRÈS la réponse HTTP.
        //
        // La production tourne en QUEUE_CONNECTION=sync : un courriel n'est pas
        // mis en file, il part dans la requête en cours. Or celle-ci est ici la
        // confirmation d'un paiement. Fabriquer le PDF puis dialoguer avec le
        // serveur SMTP en SSL y ajouterait plusieurs secondes — au risque que
        // la passerelle de paiement considère son appel de retour comme perdu.
        //
        // Le traitement se fait donc dans un « terminating » : la réponse part
        // d'abord, le courriel ensuite. En console — commande de rattrapage —
        // ces fonctions s'exécutent à la fin, ce qui convient tout autant.
        app()->terminating(function () use ($commande, $facture, $client, $email) {
            self::envoyerMaintenant($commande, $facture, $client, $email);
        });
    }

    private static function envoyerMaintenant(Commande $commande, Facture $facture, $client, string $email): void
    {
        try {
            $donneesFne = FneService::getDonneesFne($facture, $client);

            Help::envoyerDocumentPdf(
                ($client?->display_name ?? '') ?: 'Client',
                $email,
                'Facture',
                $facture->numero,
                'document.factureCommande',
                array_merge([
                    'commande'    => $commande,
                    'facture'     => $facture,
                    'config'      => Configuration::first(),
                    'image'       => config('constantes.logo'),
                    'enlevements' => Enlevement::where('facture_id', $facture->id)->get(),
                    'livraison'   => (float) ($facture->cout_livraison_applique ?? 0),
                ], $donneesFne),
                'Facture_' . $facture->numero . '.pdf'
            );
        } catch (\Throwable $e) {
            Log::error('Envoi de la facture ' . $facture->numero . ' impossible : ' . $e->getMessage());
        }
    }

    /** Total réellement dû : HT des lignes + TVA + livraison − remise. */
    public static function montantDu(Commande $commande): float
    {
        return (float) $commande->montantAPayer();
    }

    /** Somme des règlements VALIDÉS rattachés à la commande. */
    public static function montantRegle(Commande $commande): float
    {
        $ligne = DB::selectOne(
            "SELECT COALESCE(SUM(li.montant), 0) AS montant
               FROM ligne_paiement li
               JOIN paiement p ON p.id = li.paiement_id
              WHERE p.service = ? AND p.service_id = ?
                AND p.statut = ? AND li.statut = ?
                AND p.deleted_at IS NULL AND li.deleted_at IS NULL",
            [Help::$COMMANDE, $commande->id, Help::$STATUT_ACTIF, Help::$STATUT_ACTIF]
        );

        return (float) ($ligne->montant ?? 0);
    }

    /** Somme des factures déjà émises sur la commande. */
    public static function montantDejaFacture(Commande $commande): float
    {
        return self::sommeFactures($commande, 'montant');
    }

    private static function sommeFactures(Commande $commande, string $colonne): float
    {
        return (float) Facture::where('service', Help::$COMMANDE)
            ->where('service_id', $commande->id)
            ->sum($colonne);
    }

    /**
     * Rattache à la facture les règlements de la commande qui n'en avaient pas.
     *
     * Sans cela, la facture naîtrait « à encaisser » alors que l'argent est
     * déjà là, et le client verrait simultanément une facture due et un montant
     * réglé d'avance — exactement ce que cette facturation vient supprimer.
     */
    private static function rattacherLesReglements(Commande $commande, Facture $facture): void
    {
        $restant = (float) $facture->montant;

        $avances = Paiement::whereNull('facture_id')
            ->where('service', Help::$COMMANDE)
            ->where('service_id', $commande->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->orderBy('id')
            ->get();

        foreach ($avances as $avance) {
            if ($restant <= 0) {
                break;
            }
            // Un règlement n'est rattaché que s'il tient dans le reste dû :
            // l'imputer au-delà ferait apparaître la facture comme trop payée.
            if ((float) $avance->montant_total <= $restant) {
                $avance->facture_id = $facture->id;
                $avance->save();
                $restant -= (float) $avance->montant_total;
            }
        }
    }
}
