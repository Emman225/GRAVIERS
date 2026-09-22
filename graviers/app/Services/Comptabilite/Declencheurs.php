<?php

namespace App\Services\Comptabilite;

use App\Models\AvanceClient;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use Illuminate\Support\Facades\Log;

/**
 * CE QUI DÉCLENCHE UNE ÉCRITURE DE TRÉSORERIE (lot 118, 21/09/2026).
 *
 * Branché une fois, au démarrage (AppServiceProvider), sur les MODÈLES et non
 * dans les contrôleurs : un règlement naît et se valide depuis une dizaine
 * d'endroits (quatre guichets, le paiement en ligne et ses rattrapages, les
 * avances, trois guichets de dettes, les demandes de paiement). JAMAIS
 * bloquant : une écriture qui ne se produit pas ne doit faire échouer ni un
 * encaissement ni un règlement ; la commande comptabilite:produire-ecritures
 * rattrape ce qui manque.
 *
 * Piège connu : les guichets valident les lignes par une mise à jour de masse
 * (LignePaiement::where(...)->update(...)), qui ne lève AUCUN événement de
 * modèle. C'est l'enregistrement du Paiement, juste après, qui produit alors
 * les écritures de toutes ses lignes.
 */
class Declencheurs
{
    public static function brancher(): void
    {
        Paiement::saved(function (Paiement $paiement) {
            if ((int) $paiement->statut !== 1 || !($paiement->wasRecentlyCreated || $paiement->wasChanged('statut'))) {
                return;
            }
            foreach (LignePaiement::where('paiement_id', $paiement->id)->get() as $ligne) {
                self::sansBloquer(fn () => MoteurTresorerie::produirePourLigneDePaiement($ligne), 'ligne de paiement ' . $ligne->id);
            }
        });

        LignePaiement::saved(function (LignePaiement $ligne) {
            if ($ligne->wasRecentlyCreated || $ligne->wasChanged(['statut', 'montant', 'mode_paiement_id'])) {
                self::sansBloquer(fn () => MoteurTresorerie::produirePourLigneDePaiement($ligne), 'ligne de paiement ' . $ligne->id);
            }
        });

        AvanceClient::saved(function (AvanceClient $avance) {
            if ($avance->wasRecentlyCreated || $avance->wasChanged('statut')) {
                self::sansBloquer(fn () => MoteurTresorerie::produirePourAvance($avance), 'avance ' . $avance->id);
            }
        });

        foreach ([PaiementFournisseur::class, PaiementLivreur::class, PaiementApporteur::class] as $classe) {
            $classe::saved(function ($reglement) {
                if ($reglement->wasRecentlyCreated || $reglement->wasChanged(['statut', 'etat_reglement'])) {
                    self::sansBloquer(fn () => MoteurTresorerie::produirePourReglementPartenaire($reglement), $reglement->getTable() . ' ' . $reglement->id);
                }
            });
        }

        Location::saved(function (Location $location) {
            if ($location->wasChanged(['caution', 'caution_retenue', 'date_retour', 'etat_location'])
                || ($location->wasRecentlyCreated && (float) ($location->caution ?? 0) > 0)) {
                self::sansBloquer(fn () => MoteurTresorerie::produirePourCaution($location), 'caution de la location ' . $location->id);
            }
        });
    }

    /** Tables du module posées ? Vérifié une fois par requête, et seulement quand un règlement bouge. */
    private static ?bool $pret = null;

    private static function sansBloquer(callable $action, string $quoi): void
    {
        try {
            // Archive posée mais migration pas encore passée : on ne tente rien, et on ne remplit pas les journaux d'erreurs.
            self::$pret ??= \Illuminate\Support\Facades\Schema::hasColumn('ecriture_comptable', 'tiers_type');
            if (!self::$pret) {
                return;
            }

            $action();
        } catch (\Throwable $e) {
            Log::warning('Écriture de trésorerie non produite (' . $quoi . ') : ' . $e->getMessage());
        }
    }
}
