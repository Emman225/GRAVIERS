<?php

namespace App\Services;

use App\Models\Apporteur;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeLivraison;
use App\Models\Location;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\LignePaiement;
use App\Models\ModePaiement;
use App\Models\MouvementAvance;
use App\Models\Paiement;
use App\Support\AffaireCommissionnable;
use Help;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES AVANCES DES CLIENTS, CÔTÉ APPLICATION (point 19, 07/09/2026).
 *
 * Même règle que le site (graviers/app/Services/Avances.php) : une commande
 * réglée « en agence » s'impute d'elle-même sur les avances disponibles du
 * client, du dépôt le plus ancien au plus récent. Chaque imputation crée un
 * règlement déjà validé (l'argent est en caisse depuis le dépôt), avec son
 * reçu AV-AAAA-NNN, et produit les mêmes effets qu'un règlement mobile
 * confirmé : points du client, commission de l'apporteur.
 *
 * Tant que la base n'est pas migrée côté site (table absente), rien ne se
 * passe : une commande mobile ne doit jamais échouer pour cela.
 */
class Avances
{
    public const MOYEN = 'Avance client';

    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasTable('avance_client') && Schema::hasTable('mouvement_avance');
            } catch (\Throwable $e) {
                $ok = false;
            }
        }
        return $ok;
    }

    public static function soldeDisponible(?Client $client): float
    {
        if (!$client || !$client->id || !self::disponible()) {
            return 0.0;
        }
        return AvanceClient::soldeDisponible((int) $client->id);
    }

    /** Numéro de reçu suivant : AV-AAAA-NNN dans `paiement`. */
    private static function prochainNumero(string $prefixe): string
    {
        $motif   = $prefixe . '-' . date('Y') . '-';
        $dernier = (int) DB::table('paiement')
            ->where('numero_recu', 'like', $motif . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(numero_recu, ' . (strlen($motif) + 1) . ') AS UNSIGNED)) AS n')
            ->value('n');

        return sprintf('%s%03d', $motif, $dernier + 1);
    }

    /**
     * @return array{impute: float, reste: float, recus: string[]}
     */
    public static function imputerSurCommande(Commande $commande, Client $client): array
    {
        // Les lignes et la TVA viennent d'être écrites : on relit la commande
        // pour chiffrer le dû sur des relations fraîches.
        $commande = Commande::find($commande->id) ?: $commande;

        return self::imputerSurAffaire($commande, Help::$COMMANDE, $client);
    }

    /**
     * MÊME RÈGLE POUR UNE LOCATION réglée « en agence » (10/09/2026) : le
     * règlement porte service = LOCATION, et produit les effets du guichet
     * des locations du site (drapeau soldée/partielle, commission, points).
     */
    public static function imputerSurLocation(Location $location, Client $client): array
    {
        $location = Location::find($location->id) ?: $location;

        return self::imputerSurAffaire($location, Help::$LOCATION, $client);
    }

    /**
     * MÊME RÈGLE POUR UNE DEMANDE DE LIVRAISON réglée « en agence »
     * (10/09/2026) : le règlement porte service = LIVRAISON. Le guichet des
     * livraisons n'attache ni commission ni points à un encaissement : ici
     * non plus.
     */
    public static function imputerSurDemandeLivraison(DemandeLivraison $demande, Client $client): array
    {
        $demande = DemandeLivraison::find($demande->id) ?: $demande;

        return self::imputerSurAffaire($demande, Help::$LIVRAISON, $client);
    }

    /**
     * CE QUE LE CLIENT DOIT ENCORE RÉGLER EN AGENCE (10/09/2026) — même lecture
     * que le site (App\Services\Avances::creditsEnAgenceDetail) : sur ses
     * affaires vivantes réglées hors ligne et non soldées, le dû, le payé
     * (validé), ce qui attend la seconde validation au guichet, et le reste.
     *
     * @return array{du: float, paye: float, en_attente: float, reste: float}
     */
    public static function creditsEnAgenceDetail(?Client $client): array
    {
        $vide = ['du' => 0.0, 'paye' => 0.0, 'en_attente' => 0.0, 'reste' => 0.0];
        if (!$client || !$client->id) {
            return $vide;
        }

        $enLigne = ModePaiement::where('en_ligne', 1)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $horsLigne = fn ($modeId) => !in_array((int) $modeId, $enLigne, true);

        $du = 0.0;
        $paye = 0.0;
        $enAttente = 0.0;
        $cumuler = function ($affaire, string $service) use (&$du, &$paye, &$enAttente) {
            $reste = (float) $affaire->montantRestantDu();
            if ($reste < 1) {
                return;
            }
            $du   += max(0, (float) $affaire->montantAPayer());
            $paye += max(0, (float) $affaire->montantPayeComptant());
            $enAttente += (float) LignePaiement::where('service', $service)
                ->where('service_id', $affaire->id)
                ->where('statut', 2)
                ->sum('montant');
        };

        Commande::where('client_id', $client->id)
            ->where('statut', '!=', 0)
            ->where('etat_commande', '!=', 'ANNULEE')
            ->get()
            ->filter(fn ($c) => $horsLigne($c->mode_paiement_id))
            ->each(fn ($c) => $cumuler($c, Help::$COMMANDE));

        Location::where('client_id', $client->id)
            ->where('etat_location', '!=', 'ANNULEE')
            ->get()
            ->filter(fn ($l) => $horsLigne($l->mode_paiement_id))
            ->each(fn ($l) => $cumuler($l, Help::$LOCATION));

        DemandeLivraison::where('client_id', $client->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('etat_commande', '!=', 'ANNULEE')
            ->get()
            ->filter(fn ($d) => $horsLigne($d->mode_paiement_id))
            ->each(fn ($d) => $cumuler($d, Help::$LIVRAISON));

        return [
            'du'         => round($du),
            'paye'       => round($paye),
            'en_attente' => round($enAttente),
            'reste'      => round(max(0, $du - $paye)),
        ];
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
     * Le cœur de l'imputation, commun aux trois affaires (elles exposent
     * toutes montantRestantDu(), numero et id).
     *
     * @param  Commande|Location|DemandeLivraison  $affaire
     * @return array{impute: float, reste: float, recus: string[]}
     */
    private static function imputerSurAffaire($affaire, string $service, Client $client): array
    {
        $resultat = ['impute' => 0.0, 'reste' => 0.0, 'recus' => []];

        if (!self::disponible()) {
            return $resultat;
        }

        $resultat['reste'] = round($affaire->montantRestantDu());
        if ($resultat['reste'] < 1) {
            return $resultat;
        }

        $avances = AvanceClient::disponibles((int) $client->id);
        if ($avances->isEmpty()) {
            return $resultat;
        }

        $nom     = self::nomAffaire($service);
        $restant = $resultat['reste'];
        $crees   = [];

        foreach ($avances as $avance) {
            if ($restant < 1) {
                break;
            }
            $part = min($avance->solde(), $restant);
            if ($part < 1) {
                continue;
            }

            $numeroRecu = self::prochainNumero('AV');

            $paiement = new Paiement();
            $paiement->client_id         = $client->id;
            $paiement->code              = 'PAV-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
            $paiement->libelle           = 'Imputation de l\'avance ' . $avance->numero_recu
                . ' sur la ' . $nom . ' ' . $affaire->numero;
            $paiement->montant_total     = $part;
            $paiement->montant_restant   = 0;
            $paiement->statut            = 1;
            $paiement->service           = $service;
            $paiement->service_id        = $affaire->id;
            $paiement->agence_id         = $avance->agence_id;
            $paiement->caissier_id       = $avance->caissier_id;
            $paiement->numero_recu       = $numeroRecu;
            $paiement->user_valide_id    = $avance->user_valide_id;
            $paiement->user_valide2_id   = $avance->user_valide2_id;
            $paiement->date_validation_1 = now();
            $paiement->date_validation_2 = now();
            $paiement->save();

            $modeId = $avance->mode_paiement_id ?: ModePaiement::where('statut', Help::$STATUT_ACTIF)->value('id');

            $ligne = new LignePaiement();
            $ligne->paiement_id      = $paiement->id;
            $ligne->mode_paiement_id = $modeId;
            $ligne->reference        = $avance->numero_recu;
            $ligne->moyen_paiement   = self::MOYEN . ' (reçu ' . $avance->numero_recu . ')';
            $ligne->date_paiement    = now();
            $ligne->montant          = $part;
            $ligne->statut           = 1;
            $ligne->user_id          = $avance->caissier_id;
            $ligne->code_paiement    = $paiement->code;
            $ligne->service          = $service;
            $ligne->service_id       = $affaire->id;
            $ligne->save();

            $avance->montant_consomme = (float) $avance->montant_consomme + $part;
            $avance->save();

            MouvementAvance::create([
                'avance_client_id' => $avance->id,
                'client_id'        => $client->id,
                'type'             => MouvementAvance::DEDUCTION,
                'montant'          => $part,
                // `commande_id` désigne une COMMANDE : une location ou une
                // demande de livraison se retrouve par le règlement.
                'commande_id'      => $service === Help::$COMMANDE ? $affaire->id : null,
                'paiement_id'      => $paiement->id,
                'user_id'          => null,
                'libelle'          => ucfirst($nom) . ' ' . $affaire->numero . ' — règlement ' . $numeroRecu . ' (application)',
            ]);

            if ($service === Help::$LIVRAISON) {
                // Une demande de livraison ne donne ni points ni commission
                // (même règle que le guichet des livraisons du site).
            } elseif ($service === Help::$LOCATION) {
                self::apresReglementLocation($client, $paiement, $affaire);
            } else {
                self::apresReglement($client, $paiement, $affaire);
            }

            $restant -= $part;
            $resultat['impute'] += $part;
            $resultat['recus'][] = $numeroRecu;
            $crees[] = $paiement;
        }

        $resultat['reste'] = max(0, round($restant));

        // LE REÇU DE CHAQUE RÈGLEMENT AV- PART AU CLIENT (11/09/2026), comme
        // après un paiement en ligne : le site envoie le PDF du guichet (jeton
        // interne), sinon l'API l'envoie en texte. Après la réponse, et jamais
        // bloquant : RecuPaiementDistant::envoyer avale toute erreur.
        foreach ($crees as $p) {
            $envoi = static function () use ($p) {
                try {
                    RecuPaiementDistant::envoyer($p);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Reçu du règlement ' . $p->id . ' (avance) non envoyé : ' . $e->getMessage());
                }
            };
            if (app()->runningInConsole()) {
                $envoi();
            } else {
                app()->terminating($envoi);
            }
        }

        return $resultat;
    }

    /**
     * Effets d'un règlement de LOCATION : mêmes que le guichet des locations
     * du site (LocationComptantController) — drapeau 2 = partielle / 3 =
     * soldée, commission au barème des locations (2,5 % sous 5 millions,
     * 5 % jusqu'à 20 millions, 7 % au-delà), points du client.
     */
    private static function apresReglementLocation(Client $client, Paiement $paiement, Location $location): void
    {
        $location->statut = $location->montantRestantDu() <= 0 ? 3 : 2;
        $location->save();

        $points = Help::pointsPour((float) $paiement->montant_total);
        if ($points > 0) {
            $client->point = (float) $client->point + $points;
            $client->save();
            $paiement->points_attribues = $points;
            $paiement->save();
        }

        if (!$client->code_parrain) {
            return;
        }
        $apporteur = Apporteur::where('code', $client->code_parrain)->first();
        if (!$apporteur) {
            return;
        }

        $montantTotalLoc = (float) $location->montant_total;
        if ($montantTotalLoc < 5000000) {
            $taux = 2.5;
        } elseif ($montantTotalLoc <= 20000000) {
            $taux = 5;
        } else {
            $taux = 7;
        }

        $com = new CommissionApporteur();
        $com->apporteur_id = $apporteur->id;
        $com->commande_id  = $location->id;
        $com->type_affaire = 'LOCATION';
        $com->montant      = round((float) $paiement->montant_total * $taux / 100);
        $com->statut       = 1;
        $com->save();

        $apporteur->solde = (float) $apporteur->solde + (float) $com->montant;
        $apporteur->save();
    }

    /** Points du client et commission de l'apporteur : même règle que le règlement mobile confirmé. */
    private static function apresReglement(Client $client, Paiement $paiement, Commande $commande): void
    {
        $points = Help::pointsPour((float) $paiement->montant_total);
        if ($points > 0) {
            $client->point = (float) $client->point + $points;
            $client->save();
            $paiement->points_attribues = $points;
            $paiement->save();
        }

        if (!$client->parrain_id) {
            return;
        }
        $apporteur = Apporteur::lire($client->parrain_id);
        if (!$apporteur || $apporteur->id <= 0) {
            return;
        }
        $typeAffaire = AffaireCommissionnable::typeSiRattachable(Help::$COMMANDE, $commande->id);
        if (!$typeAffaire) {
            return;
        }

        $taux = (float) ($apporteur->pourcentage ?? 0);
        if ($taux <= 0) {
            $taux = (float) (Configuration::first()?->taux_commission_standard ?? 3);
        }

        $com = new CommissionApporteur();
        $com->apporteur_id = $apporteur->id;
        $com->commande_id  = $commande->id;
        $com->type_affaire = $typeAffaire;
        $com->montant      = round((float) $paiement->montant_total * $taux / 100);
        $com->statut       = 1;
        $com->save();

        $apporteur->solde = (float) $apporteur->solde + (float) $com->montant;
        $apporteur->save();
    }

    /**
     * Phrase ajoutée au message de succès. $service dit sur quoi l'avance a
     * été imputée : commande (défaut), location, demande de livraison.
     */
    public static function messageImputation(array $resultat, ?string $service = null): string
    {
        if (($resultat['impute'] ?? 0) < 1) {
            return '';
        }
        $nom = self::nomAffaire($service ?? Help::$COMMANDE);
        $f = fn ($m) => number_format((float) $m, 0, '', ' ') . ' FCFA';
        $texte = ' Votre avance a été imputée sur cette ' . $nom . ' : ' . $f($resultat['impute']) . ' déduits.';
        $texte .= ($resultat['reste'] ?? 0) >= 1
            ? ' Reste à régler en agence : ' . $f($resultat['reste']) . '.'
            : ' La ' . $nom . ' est entièrement réglée.';

        return $texte;
    }
}
