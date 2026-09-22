<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\EcritureComptable;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre une écriture préparée par un moteur : l'en-tête, les lignes, et
 * les anomalies — celles qui ont disparu sont datées, jamais effacées. Une
 * écriture exportée ne passe jamais par ici : c'est aux moteurs de s'arrêter
 * avant.
 *
 * Chaque ligne : ['rubrique', 'compte' => ?CompteComptable, 'tiers' => ?string,
 * 'analytique' => ?CompteComptable, 'famille' => ?Categorie (ou 'categorie_id'),
 * 'produit' => ?Produit (ou 'produit_id'), 'libelle', 'montant', 'sens' => 'D'|'C'].
 * Chaque anomalie : ['code', 'objet', 'colonne', 'cause', 'onglet', 'rang'].
 */
class Ecrivain
{
    public static function enregistrer(?EcritureComptable $existante, string $identifiant, array $entete, array $lignes, array $anomalies, ?int $factureId = null): EcritureComptable
    {
        return DB::transaction(function () use ($existante, $identifiant, $entete, $lignes, $anomalies, $factureId) {
            $debit = $credit = 0.0;
            $aEcrire = [];
            foreach (array_values($lignes) as $i => $ligne) {
                $montant = round((float) $ligne['montant'], 2);
                $auDebit = $ligne['sens'] === 'D';
                $auDebit ? $debit += $montant : $credit += $montant;
                $aEcrire[] = [
                    'rang'                 => $i + 1,
                    'rubrique'             => $ligne['rubrique'],
                    'compte_comptable_id'  => ($ligne['compte'] ?? null)?->id,
                    'numero_compte'        => ($ligne['compte'] ?? null)?->numero,
                    'compte_tiers'         => $ligne['tiers'] ?? null,
                    'compte_analytique_id' => ($ligne['analytique'] ?? null)?->id,
                    'numero_analytique'    => ($ligne['analytique'] ?? null)?->numero,
                    'categorie_id'         => ($ligne['famille'] ?? null)?->id ?? ($ligne['categorie_id'] ?? null),
                    'produit_id'           => ($ligne['produit'] ?? null)?->id ?? ($ligne['produit_id'] ?? null),
                    'libelle'              => mb_substr((string) $ligne['libelle'], 0, 190),
                    'debit'                => $auDebit ? $montant : 0,
                    'credit'               => $auDebit ? 0 : $montant,
                ];
            }

            if (abs($debit - $credit) > 0.004 || $debit <= 0) {
                $anomalies[] = [
                    'code' => AnomalieComptable::ECART_DE_TOTAL, 'objet' => (string) ($entete['piece'] ?? $identifiant), 'colonne' => 'Montant',
                    'cause' => 'Écriture non équilibrée : débit ' . number_format($debit, 0, ',', ' ') . ' F, crédit ' . number_format($credit, 0, ',', ' ') . ' F.',
                    'onglet' => null, 'rang' => null,
                ];
            }

            $entete['total_debit']  = round($debit, 2);
            $entete['total_credit'] = round($credit, 2);
            $entete['etat'] = $anomalies ? EcritureComptable::ETAT_ANOMALIE : EcritureComptable::ETAT_A_EXPORTER;
            $entete['libelle'] = mb_substr((string) $entete['libelle'], 0, 190);

            if ($existante) {
                $existante->update($entete);
                $existante->lignes()->delete();
                $ecriture = $existante;
            } else {
                $ecriture = EcritureComptable::create($entete + ['identifiant' => $identifiant]);
            }
            foreach ($aEcrire as $ligne) {
                $ecriture->lignes()->create($ligne);
            }

            $cle = fn ($code, $objet, $rang) => $code . '|' . $objet . '|' . ($rang ?? '');
            $actuelles = [];
            foreach ($anomalies as $anomalie) {
                $anomalie['objet'] = mb_substr((string) $anomalie['objet'], 0, 190);
                $actuelles[$cle($anomalie['code'], $anomalie['objet'], $anomalie['rang'] ?? null)] = $anomalie;
            }
            foreach ($ecriture->anomaliesOuvertes()->get() as $ouverte) {
                $k = $cle($ouverte->code, $ouverte->objet, $ouverte->rang_ligne);
                if (isset($actuelles[$k])) {
                    unset($actuelles[$k]);
                } else {
                    $ouverte->update(['resolue_le' => now()]);
                }
            }
            foreach ($actuelles as $anomalie) {
                AnomalieComptable::create([
                    'ecriture_comptable_id' => $ecriture->id,
                    'facture_id'            => $factureId,
                    'rang_ligne'            => $anomalie['rang'] ?? null,
                    'code'                  => $anomalie['code'],
                    'objet'                 => $anomalie['objet'],
                    'colonne'               => $anomalie['colonne'],
                    'cause'                 => mb_substr((string) $anomalie['cause'], 0, 255),
                    'onglet'                => $anomalie['onglet'] ?? null,
                ]);
            }

            return $ecriture->fresh(['lignes']);
        });
    }

    /** Une source qui n'est plus valable : l'écriture non exportée disparaît, l'exportée est contre-passée. */
    public static function retirer(EcritureComptable $ecriture): void
    {
        if ($ecriture->estExportee()) {
            MoteurEcritures::annuler($ecriture);

            return;
        }

        DB::transaction(function () use ($ecriture) {
            $ecriture->anomalies()->delete();
            $ecriture->lignes()->delete();
            $ecriture->delete();
        });
    }
}
