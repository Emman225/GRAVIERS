<?php

namespace App\Services;

use App\Models\AvanceClient;
use App\Models\DemandeLivraison;
use App\Models\Location;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Configuration;
use App\Models\Facture;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Models\User;
use Help;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * LES AVANCES DES CLIENTS (point 19 du cahier du 07/09/2026).
 *
 * Un client dépose au guichet une somme sans commande ; chacune de ses
 * commandes suivantes réglées « en agence » s'en déduit d'elle-même, du dépôt
 * le plus ancien au plus récent. Le reliquat non couvert reste dû au guichet
 * (client ordinaire) ou suit le crédit du client (client à terme).
 *
 * Règles arrêtées par le client le 07/09/2026 :
 *   - pas de dépôt tant qu'une affaire n'est pas soldée : le client règle
 *     d'abord, et c'est le SURPLUS versé qui devient une avance ;
 *   - une avance ne se rembourse pas, elle s'utilise ; le client en est
 *     informé sur le reçu et par courriel ;
 *   - seul le « Paiement en agence » la consomme ;
 *   - le dépôt se fait au guichet, avec la double validation.
 */
class Avances
{
    public const MOYEN   = 'Avance client';
    public const MENTION = "Cette avance n'est pas remboursable : elle est à utiliser sur vos "
        . "prochaines commandes réglées en agence, qui s'en déduiront automatiquement.";

    /** Solde d'avance d'un client, 0 sans client. */
    public static function soldeDisponible(?Client $client): float
    {
        return $client ? AvanceClient::soldeDisponible((int) $client->id) : 0.0;
    }

    /**
     * CE QUE LE CLIENT DOIT ENCORE RÉGLER EN AGENCE (10/09/2026) : le reste
     * dû de ses affaires vivantes réglées hors ligne — commandes, locations,
     * demandes de livraison. Affiché sur son tableau de bord à côté de
     * l'avance disponible : l'une se déduit de l'autre.
     */
    public static function creditsEnAgence(Client $client): float
    {
        return self::creditsEnAgenceDetail($client)['reste'];
    }

    /**
     * Le détail de ce que le client doit régler en agence, sur ses affaires
     * vivantes réglées hors ligne et non soldées : le dû, ce qui est payé
     * (validé), ce qui attend la seconde validation au guichet, et le reste.
     * Le tableau de bord suit ainsi chaque versement jusqu'au solde.
     *
     * @return array{du: float, paye: float, en_attente: float, reste: float}
     */
    public static function creditsEnAgenceDetail(Client $client): array
    {
        $du = 0.0;
        $paye = 0.0;
        $enAttente = 0.0;

        $cumuler = function ($affaire) use (&$du, &$paye, &$enAttente) {
            if ((float) $affaire->montantRestantDu() < 1) {
                return; // soldée : elle sort du bloc
            }
            $du        += max(0, (float) $affaire->montantAPayer());
            $paye      += max(0, (float) $affaire->montantPayeComptant());
            $enAttente += max(0, (float) $affaire->montantEnAttenteValidation());
        };

        Commande::where('client_id', $client->id)
            ->where('statut', '!=', 0)
            ->get()
            ->filter(fn (Commande $c) => $c->affaireVivante()
                && (int) ($c->modePaiement?->en_ligne ?? 0) === 0)
            ->each($cumuler);

        Location::where('client_id', $client->id)
            ->get()
            ->filter(fn (Location $l) => $l->affaireVivante() && $l->reglementEnAgence())
            ->each($cumuler);

        DemandeLivraison::where('client_id', $client->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->get()
            ->filter(fn (DemandeLivraison $d) => $d->affaireVivante() && $d->reglementEnAgence())
            ->each($cumuler);

        return [
            'du'         => Help::arrondiFranc($du),
            'paye'       => Help::arrondiFranc($paye),
            'en_attente' => Help::arrondiFranc($enAttente),
            'reste'      => Help::arrondiFranc(max(0, $du - $paye)),
        ];
    }

    /**
     * Les affaires du client qui attendent encore un règlement : commandes
     * vivantes non soldées, factures non soldées (client à terme).
     * Renvoie des libellés prêts à afficher, vide si tout est réglé.
     */
    public static function affairesNonSoldees(Client $client): Collection
    {
        $lignes = collect();

        Commande::where('client_id', $client->id)
            ->where('statut', '!=', 0)
            ->get()
            ->filter(fn (Commande $c) => $c->affaireVivante() && $c->montantRestantDu() >= 1)
            ->each(function (Commande $c) use ($lignes) {
                $lignes->push('commande ' . $c->numero . ' (reste '
                    . Help::formatNombre(Help::arrondiFranc($c->montantRestantDu()), true) . ')');
            });

        if ((int) $client->client_a_terme === 1) {
            Facture::where('client_id', $client->id)
                ->get()
                ->filter(fn (Facture $f) => (float) $f->resteAEncaisser() >= 1)
                ->each(function (Facture $f) use ($lignes) {
                    $lignes->push('facture ' . $f->numero . ' (reste '
                        . Help::formatNombre(Help::arrondiFranc((float) $f->resteAEncaisser()), true) . ')');
                });
        }

        return $lignes;
    }

    /**
     * Numéro de reçu suivant pour un préfixe : RA-AAAA-NNN (dépôt),
     * AV-AAAA-NNN (règlement imputé sur une avance).
     */
    public static function prochainNumero(string $prefixe, string $table = 'avance_client'): string
    {
        $annee   = date('Y');
        $motif   = $prefixe . '-' . $annee . '-';
        $dernier = (int) DB::table($table)
            ->where('numero_recu', 'like', $motif . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, ' . (strlen($motif) + 1) . ') AS UNSIGNED)) AS n')
            ->value('n');

        return sprintf('%s%03d', $motif, $dernier + 1);
    }

    /**
     * Enregistre un dépôt, en attente de la seconde validation.
     *
     * $champs : mode_paiement_id, reference, libelle, date_depot, origine,
     * origine_recu, et les colonnes de validation posées par le guichet
     * (user_valide_id, date_validation_1…).
     */
    public static function deposer(Client $client, float $montant, array $champs, ?User $caissier, ?int $agenceId): AvanceClient
    {
        $mode = !empty($champs['mode_paiement_id']) ? ModePaiement::find($champs['mode_paiement_id']) : null;

        return AvanceClient::create(array_merge([
            'client_id'        => $client->id,
            'montant'          => Help::arrondiFranc($montant),
            'montant_consomme' => 0,
            'statut'           => AvanceClient::EN_ATTENTE,
            'numero_recu'      => self::prochainNumero('RA'),
            'mode_paiement_id' => $mode?->id,
            'moyen_paiement'   => $mode?->libelle,
            'reference'        => $champs['reference'] ?? null,
            'agence_id'        => $agenceId,
            'caissier_id'      => $caissier?->id,
            'libelle'          => $champs['libelle'] ?? null,
            'origine'          => $champs['origine'] ?? 'DEPOT',
            'origine_recu'     => $champs['origine_recu'] ?? null,
            'date_depot'       => $champs['date_depot'] ?? now(),
        ], array_intersect_key($champs, array_flip([
            'user_valide_id', 'user_valide2_id', 'date_validation_1', 'date_validation_2',
        ]))));
    }

    /**
     * Après la seconde validation : l'avance devient disponible, le dépôt
     * entre dans l'historique, et le client reçoit son reçu — qui lui dit que
     * l'avance n'est pas remboursable.
     */
    public static function activer(AvanceClient $avance, ?int $userId = null): void
    {
        $avance->update(['statut' => AvanceClient::DISPONIBLE]);

        MouvementAvance::create([
            'avance_client_id' => $avance->id,
            'client_id'        => $avance->client_id,
            'type'             => MouvementAvance::DEPOT,
            'montant'          => (float) $avance->montant,
            'user_id'          => $userId,
            'libelle'          => 'Dépôt validé — reçu ' . $avance->numero_recu,
        ]);

        self::envoyerRecu($avance);
    }

    /**
     * IMPUTE LES AVANCES DISPONIBLES DU CLIENT SUR UNE COMMANDE.
     *
     * Appelée à la création d'une commande réglée « en agence » (site et
     * API). Pour chaque dépôt disponible, du plus ancien au plus récent, un
     * règlement est créé pour min(solde du dépôt, reste dû) — déjà validé :
     * l'argent est en caisse depuis le dépôt, et les deux validateurs du
     * dépôt sont reportés sur le règlement. Chaque règlement a son reçu
     * (AV-AAAA-NNN) et produit les mêmes effets qu'un encaissement validé
     * au guichet (points du client, commission de l'apporteur, facturation
     * de ce qui est réglé).
     *
     * @return array{impute: float, reste: float, recus: string[]}
     */
    public static function imputerSurCommande(Commande $commande, ?int $userId = null): array
    {
        return self::imputerSurAffaire($commande, Help::$COMMANDE, $userId);
    }

    /**
     * MÊME RÈGLE POUR UNE LOCATION réglée « en agence » (10/09/2026) : le
     * règlement porte service = LOCATION, et produit les effets du guichet
     * des locations (drapeau soldée/partielle, commission, points).
     */
    public static function imputerSurLocation(Location $location, ?int $userId = null): array
    {
        return self::imputerSurAffaire($location, Help::$LOCATION, $userId);
    }

    /**
     * MÊME RÈGLE POUR UNE DEMANDE DE LIVRAISON réglée « en agence »
     * (10/09/2026) : le règlement porte service = LIVRAISON. Le guichet des
     * livraisons n'attache ni commission ni points à un encaissement : ici
     * non plus.
     */
    public static function imputerSurDemandeLivraison(DemandeLivraison $demande, ?int $userId = null): array
    {
        return self::imputerSurAffaire($demande, Help::$LIVRAISON, $userId);
    }

    /** Le nom de l'affaire dans les libellés et les messages, selon le service. */
    public static function nomAffaire(string $service): string
    {
        if ($service === Help::$LOCATION) {
            return 'location';
        }
        if ($service === Help::$LIVRAISON) {
            return 'demande de livraison';
        }

        return 'commande';
    }

    /**
     * Le cœur de l'imputation, commun aux trois affaires. Chacune expose
     * montantRestantDu(), client_id, numero et id.
     *
     * @param  Commande|Location|DemandeLivraison  $affaire
     * @return array{impute: float, reste: float, recus: string[]}
     */
    private static function imputerSurAffaire($affaire, string $service, ?int $userId = null): array
    {
        $resultat = ['impute' => 0.0, 'reste' => Help::arrondiFranc($affaire->montantRestantDu()), 'recus' => []];

        if (!$affaire->client_id || $resultat['reste'] < 1) {
            return $resultat;
        }

        $avances = AvanceClient::disponibles((int) $affaire->client_id);
        if ($avances->isEmpty()) {
            return $resultat;
        }

        $nom = self::nomAffaire($service);

        $crees = [];

        DB::transaction(function () use ($affaire, $service, $nom, $avances, $userId, &$resultat, &$crees) {
            $restant = $resultat['reste'];

            foreach ($avances as $avance) {
                if ($restant < 1) {
                    break;
                }
                $part = min($avance->solde(), $restant);
                if ($part < 1) {
                    continue;
                }

                $numeroRecu = self::prochainNumero('AV', 'paiement');
                $modeId     = $avance->mode_paiement_id
                    ?: (ModePaiement::listePourAgent()->first()?->id ?? ModePaiement::first()?->id);

                $paiement = Paiement::create([
                    'client_id'         => $affaire->client_id,
                    'code'              => 'PAV-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
                    'libelle'           => 'Imputation de l\'avance ' . $avance->numero_recu
                        . ' sur la ' . $nom . ' ' . $affaire->numero,
                    'montant_total'     => $part,
                    'montant_restant'   => 0,
                    'statut'            => 1,
                    'service'           => $service,
                    'service_id'        => $affaire->id,
                    'agence_id'         => $avance->agence_id,
                    'caissier_id'       => $avance->caissier_id,
                    'numero_recu'       => $numeroRecu,
                    // Les deux validateurs du dépôt valent pour le règlement
                    // qui en découle : personne n'a à revalider un argent
                    // déjà contrôlé.
                    'user_valide_id'    => $avance->user_valide_id,
                    'user_valide2_id'   => $avance->user_valide2_id,
                    'date_validation_1' => now(),
                    'date_validation_2' => now(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                LignePaiement::create([
                    'paiement_id'      => $paiement->id,
                    'mode_paiement_id' => $modeId,
                    'reference'        => $avance->numero_recu,
                    'moyen_paiement'   => self::MOYEN . ' (reçu ' . $avance->numero_recu . ')',
                    'date_paiement'    => now(),
                    'montant'          => $part,
                    'statut'           => 1,
                    'user_id'          => $avance->caissier_id,
                    'code_paiement'    => $paiement->code,
                    'service'          => $service,
                    'service_id'       => $affaire->id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $avance->montant_consomme = (float) $avance->montant_consomme + $part;
                $avance->save();

                MouvementAvance::create([
                    'avance_client_id' => $avance->id,
                    'client_id'        => $avance->client_id,
                    'type'             => MouvementAvance::DEDUCTION,
                    'montant'          => $part,
                    // `commande_id` désigne une COMMANDE : une location ou une
                    // demande de livraison se retrouve par le règlement
                    // (service / service_id), jamais par cette colonne.
                    'commande_id'      => $service === Help::$COMMANDE ? $affaire->id : null,
                    'paiement_id'      => $paiement->id,
                    'user_id'          => $userId,
                    'libelle'          => Help::phrase($nom) . ' ' . $affaire->numero . ' — règlement ' . $numeroRecu,
                ]);

                if ($service === Help::$COMMANDE) {
                    ReglementValide::appliquer($affaire, $paiement);
                } elseif ($service === Help::$LOCATION) {
                    ReglementValide::appliquerLocation($affaire, $paiement);
                }

                $restant -= $part;
                $resultat['impute'] += $part;
                $resultat['recus'][] = $numeroRecu;
                $crees[] = $paiement;
            }

            $resultat['reste'] = Help::arrondiFranc($affaire->montantRestantDu());
        });

        // LE REÇU DE CHAQUE RÈGLEMENT AV- PART AU CLIENT (11/09/2026) : le
        // même document que le guichet génère, en PDF, après la réponse. La
        // transaction est validée : un envoi qui échoue ne touche pas à
        // l'imputation (l'envoi lui-même est sous try/catch, celui-ci aussi).
        foreach ($crees as $p) {
            try {
                RecuPaiement::envoyerParCourriel($p);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Reçu du règlement ' . $p->id . ' (avance) non envoyé : ' . $e->getMessage());
            }
        }

        return $resultat;
    }

    /**
     * Phrase à afficher au client après une imputation. $service dit sur
     * quoi l'avance a été imputée : commande (défaut), location, demande de
     * livraison.
     */
    public static function messageImputation(array $resultat, ?string $service = null): string
    {
        if ($resultat['impute'] < 1) {
            return '';
        }
        $nom = self::nomAffaire($service ?? Help::$COMMANDE);
        $texte = 'Votre avance a été imputée sur cette ' . $nom . ' : '
            . Help::formatNombre($resultat['impute'], true) . ' déduits.';
        $texte .= $resultat['reste'] >= 1
            ? ' Reste à régler en agence : ' . Help::formatNombre($resultat['reste'], true) . '.'
            : ' La ' . $nom . ' est entièrement réglée.';

        return $texte;
    }

    /** Les données du reçu de dépôt, pour le gabarit partagé (écran et PDF). */
    public static function donneesRecu(AvanceClient $avance): array
    {
        $avance->loadMissing(['client', 'agence', 'caissier']);
        $client = $avance->client;

        return [
            'titre'               => "REÇU D'AVANCE",
            'sousTitre'           => 'Dépôt d\'avance client'
                . ($avance->origine === 'SURPLUS' && $avance->origine_recu
                    ? ' — surplus de l\'encaissement ' . $avance->origine_recu : ''),
            'numeroRecu'          => $avance->numero_recu,
            'datePaiement'        => $avance->date_depot ?? $avance->created_at,
            'beneficiaireRole'    => 'Reçu de',
            'beneficiaireNom'     => $client?->display_name ?? '-',
            'beneficiaireContact' => $client?->contact1,
            'modePaiement'        => $avance->moyen_paiement ?: '-',
            'reference'           => $avance->reference,
            'caissier'            => $avance->caissier?->nom_prenoms,
            'agenceLabel'         => $avance->agence?->nom,
            'libelle'             => $avance->libelle,
            'montant'             => (float) $avance->montant,
            'montantLabel'        => 'Montant de l\'avance',
            'contexteInfos'       => [
                'Client n°'   => $client?->id,
                'Statut'      => $avance->libelleStatut(),
                'Déjà utilisé' => (float) $avance->montant_consomme >= 1
                    ? Help::formatNombre($avance->montant_consomme, true) : null,
                'Solde disponible' => Help::formatNombre($avance->solde(), true),
            ],
            'resumeFinancier'     => null,
            'mention'             => self::MENTION,
            'trancheNum'          => 1,
            'trancheTotal'        => 1,
            'retourUrl'           => route('show.avances.index'),
            'pdfUrl'              => route('show.avances.recuPdf', $avance->id),
            'couleurPrincipale'   => '#1c57a3',
            'signatureGauche'     => 'Signature Caissier',
            'signatureDroite'     => 'Signature Client',
            'config'              => Configuration::first(),
            'pdfMode'             => false,
        ];
    }

    public static function pdfRecu(AvanceClient $avance)
    {
        @ini_set('memory_limit', '512M');

        $data = self::donneesRecu($avance);
        $data['pdfMode'] = true;

        return \PDF::loadView('admin.shared.recu-paiement-pdf', $data)
            ->setPaper('A5', 'portrait')
            ->setOptions([
                'isRemoteEnabled'      => false,
                'isHtml5ParserEnabled' => true,
                'defaultFont'          => 'DejaVu Sans',
            ]);
    }

    /**
     * Envoie le reçu de dépôt au client (PDF), avec la mention « non
     * remboursable ». Une fois par dépôt, sauf demande explicite (bouton
     * « Informer le client »). Différé après la réponse, comme les autres
     * courriels ; jamais bloquant.
     */
    public static function envoyerRecu(AvanceClient $avance, bool $forcer = false, bool $immediat = false): bool
    {
        $avance = AvanceClient::find($avance->id) ?: $avance;

        if ($avance->recu_envoye_le && !$forcer) {
            return false;
        }

        $client = $avance->client;
        $email  = $client?->user?->email ?: ($client?->email ?: null);
        if (!$email) {
            return false;
        }

        $avance->recu_envoye_le = now();
        $avance->save();

        $envoi = function () use ($avance, $client, $email) {
            try {
                $pdf = self::pdfRecu($avance)->output();

                Mail::send(new \App\Mail\DocumentPdfMail(
                    ($client?->display_name ?? '') ?: 'Client',
                    $email,
                    "Reçu d'avance",
                    $avance->numero_recu,
                    $pdf,
                    'Recu_avance_' . $avance->numero_recu . '.pdf'
                ));
            } catch (\Throwable $e) {
                Log::error('Envoi du reçu d\'avance ' . $avance->id . ' impossible : ' . $e->getMessage());
                AvanceClient::where('id', $avance->id)->update(['recu_envoye_le' => null]);
            }
        };

        if ($immediat || app()->runningInConsole()) {
            $envoi();
        } else {
            app()->terminating($envoi);
        }

        return true;
    }
}
