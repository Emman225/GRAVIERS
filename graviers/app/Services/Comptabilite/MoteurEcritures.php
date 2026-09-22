<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\Categorie;
use App\Models\CompteComptable;
use App\Models\EcritureComptable;
use App\Models\Facture;
use App\Models\JournalComptable;
use App\Models\LigneEcritureComptable;
use App\Models\RubriqueComptable;
use Illuminate\Support\Facades\DB;

/**
 * LE MOTEUR D'ÉCRITURES (module « Écritures comptables », phase 2, lot 117).
 *
 * Une facture normalisée → une écriture équilibrée : le compte tiers du client
 * au débit, les ventes par grande famille (compte général) et par produit
 * (compte analytique), le transport, la TVA et l'AIRSI au crédit, la remise au
 * débit. Un avoir produit l'écriture inverse. Total débit = total crédit =
 * montant de la facture, sinon l'écriture est EN ANOMALIE et ne s'exporte pas.
 *
 * Trois règles ne souffrent pas d'exception :
 *  - une facture n'a qu'UNE écriture vivante (identifiant stable) ;
 *  - une écriture exportée n'est jamais modifiée — corriger() l'annule par une
 *    écriture inverse puis en produit une nouvelle ;
 *  - rien ici ne doit faire échouer une certification : l'appelant attrape tout.
 */
class MoteurEcritures
{
    /** Écart d'arrondi toléré entre la facture et la somme de ses lignes ; au-delà, c'est une anomalie. */
    public const TOLERANCE = 5.0;

    // ================================================================== production

    public static function produirePourFacture(Facture $facture): ?EcritureComptable
    {
        if ($facture->fne_status !== 'certified') {
            return null;
        }

        $existante = EcritureComptable::vivantesDeLaFacture($facture->id)->orderByDesc('id')->first();
        if ($existante && $existante->estExportee()) {
            return $existante;   // exportée : intouchable
        }

        $ecriture = self::ecrire($facture, $existante, $existante ? $existante->version : self::prochaineVersion($facture));
        Lettrage::lettrerAffaire($facture->service, $facture->service_id);

        return $ecriture;
    }

    /**
     * La facture a changé après l'export de son écriture : on ne retouche rien,
     * on contre-passe puis on réécrit. Sans export, une simple reproduction suffit.
     */
    public static function corriger(Facture $facture): ?EcritureComptable
    {
        $existante = EcritureComptable::vivantesDeLaFacture($facture->id)->orderByDesc('id')->first();
        if (!$existante || !$existante->estExportee()) {
            return self::produirePourFacture($facture);
        }

        $nouvelle = DB::transaction(function () use ($facture, $existante) {
            self::annuler($existante);

            return self::ecrire($facture, null, $existante->version + 1);
        });
        Lettrage::lettrerAffaire($facture->service, $facture->service_id);

        return $nouvelle;
    }

    /** L'écriture inverse, ligne pour ligne, datée du jour. */
    public static function annuler(EcritureComptable $ecriture): EcritureComptable
    {
        if ($ecriture->annulee_par_id) {
            return EcritureComptable::findOrFail($ecriture->annulee_par_id);
        }
        if ($ecriture->origine === EcritureComptable::ORIGINE_ANNULATION) {
            throw new \LogicException("Une écriture d'annulation ne s'annule pas.");
        }

        return DB::transaction(function () use ($ecriture) {
            $annulation = EcritureComptable::create([
                'identifiant'          => $ecriture->identifiant . '-ANN',
                'origine'              => EcritureComptable::ORIGINE_ANNULATION,
                'source_type'          => $ecriture->source_type,
                'source_id'            => $ecriture->source_id,
                'version'              => $ecriture->version,
                'annulation_de_id'     => $ecriture->id,
                'journal_comptable_id' => $ecriture->journal_comptable_id,
                'journal_code'         => $ecriture->journal_code,
                'date_ecriture'        => now()->toDateString(),
                'piece'                => $ecriture->piece,
                'reference_fne'        => $ecriture->reference_fne,
                'libelle'              => mb_substr('Annulation — ' . $ecriture->libelle, 0, 190),
                'service'              => $ecriture->service,
                'service_id'           => $ecriture->service_id,
                'numero_affaire'       => $ecriture->numero_affaire,
                'client_id'            => $ecriture->client_id,
                'total_debit'          => $ecriture->total_credit,
                'total_credit'         => $ecriture->total_debit,
                'etat'                 => EcritureComptable::ETAT_A_EXPORTER,
            ]);

            foreach ($ecriture->lignes as $ligne) {
                $copie = $ligne->replicate(['ecriture_comptable_id', 'debit', 'credit']);
                $copie->ecriture_comptable_id = $annulation->id;
                $copie->debit  = $ligne->credit;
                $copie->credit = $ligne->debit;
                $copie->save();
            }

            $ecriture->annulee_par_id = $annulation->id;
            $ecriture->save();

            return $annulation;
        });
    }

    /** Reproduit les écritures en anomalie : à lancer après une correction du paramétrage. */
    public static function reprendreLesAnomalies(): array
    {
        $bilan = ['reprises' => 0, 'reglees' => 0];
        $ids = EcritureComptable::where('etat', EcritureComptable::ETAT_ANOMALIE)->where('source_type', 'facture')
            ->whereNull('annulee_par_id')->pluck('source_id');

        foreach (Facture::withTrashed()->whereIn('id', $ids)->get() as $facture) {
            $ecriture = self::produirePourFacture($facture);
            $bilan['reprises']++;
            if ($ecriture && $ecriture->etat !== EcritureComptable::ETAT_ANOMALIE) {
                $bilan['reglees']++;
            }
        }

        return $bilan;
    }

    // ================================================================== fabrication

    private static function prochaineVersion(Facture $facture): int
    {
        return (int) EcritureComptable::where('source_type', 'facture')->where('source_id', $facture->id)->max('version') + 1;
    }

    private static function ecrire(Facture $facture, ?EcritureComptable $existante, int $version): EcritureComptable
    {
        $composition = CompositionFacture::de($facture);
        $estAvoir = $facture->estUnAvoir();
        $anomalies = [];
        $signaler = function (string $code, string $objet, string $colonne, string $cause, ?string $onglet = null, ?int $rang = null) use (&$anomalies) {
            $anomalies[] = compact('code', 'objet', 'colonne', 'cause', 'onglet', 'rang');
        };

        foreach ($composition['problemes'] as $probleme) {
            $signaler(
                $estAvoir && str_contains($probleme, 'origine') ? AnomalieComptable::ORIGINE_INTROUVABLE : AnomalieComptable::AFFAIRE_INTROUVABLE,
                'Facture n° ' . $facture->numero, 'Commande / Location', $probleme
            );
        }

        // ---- l'en-tête
        $journal = JournalComptable::actifs()->where('type', JournalComptable::TYPE_VENTES)->orderBy('id')->first();
        if (!$journal) {
            $signaler(AnomalieComptable::JOURNAL_ABSENT, 'Journal des ventes', 'Type', 'Aucun journal actif de type « Ventes ».', 'journaux');
        }
        $date = $facture->fne_certified_at ?: $facture->created_at ?: now();
        if (!$facture->fne_certified_at) {
            $signaler(AnomalieComptable::FACTURE_SANS_DATE, 'Facture n° ' . $facture->numero, 'Date certification',
                "La facture est certifiée mais n'a pas de date de certification : la date de création a été prise.");
        }
        $client = $composition['client'];
        $nomClient = (string) ($client?->display_name ?: ($client ? 'Client n° ' . $client->id : 'Client inconnu'));

        // ---- les lignes
        $rubriques = RubriqueComptable::toutes()->keyBy('code');
        $comptes = CompteComptable::actifs()->get()->keyBy('id');
        $familles = Categorie::where('statut', \Help::$STATUT_ACTIF)->get()->keyBy('id');
        $lignes = [];

        $compteDeRubrique = function (string $code) use ($rubriques, $comptes, $signaler) {
            $rubrique = $rubriques[$code] ?? null;
            $compte = $rubrique ? ($comptes[$rubrique->compte_comptable_id] ?? null) : null;
            if (!$compte || !$compte->estGeneral()) {
                $signaler(AnomalieComptable::RUBRIQUE_SANS_COMPTE, $rubrique?->libelle ?? $code, 'Compte général',
                    'Aucun compte général actif pour cette rubrique de facture.', 'rubriques');
                $compte = null;
            }
            $analytique = ($rubrique && $rubrique->accepteUnAnalytique()) ? ($comptes[$rubrique->compte_analytique_id] ?? null) : null;

            return [$compte, $analytique];
        };

        // 1. le client
        [$collectif] = $compteDeRubrique(RubriqueComptable::CLIENTS);
        $tiers = trim((string) ($client?->compte_tiers ?? ''));
        if ($tiers === '') {
            $signaler(AnomalieComptable::CLIENT_SANS_COMPTE_TIERS, $nomClient, 'Compte tiers', 'Client sans compte tiers.', 'tiers');
        }
        $lignes[] = [
            'rubrique' => LigneEcritureComptable::CLIENT, 'compte' => $collectif, 'tiers' => $tiers ?: null,
            'libelle' => $nomClient, 'montant' => $composition['total'], 'sens' => 'D',
        ];

        // 2. les produits, regroupés : une ligne par compte analytique
        $parProduit = [];
        $transport = 0.0;
        foreach ($composition['lignes'] as $ligne) {
            if ($ligne['rubrique'] === CompositionFacture::TRANSPORT) {
                $transport += $ligne['ht'];
                continue;
            }
            $cle = $ligne['produit'] ? 'p' . $ligne['produit']->id : 'x' . count($parProduit);
            if (!isset($parProduit[$cle])) {
                $parProduit[$cle] = ['produit' => $ligne['produit'], 'libelle' => $ligne['produit']?->nom ?? $ligne['libelle'], 'ht' => 0.0];
            }
            $parProduit[$cle]['ht'] += $ligne['ht'];
        }

        foreach ($parProduit as $groupe) {
            $produit = $groupe['produit'];
            $rang = count($lignes) + 1;
            $famille = $compte = $analytique = null;

            if (!$produit) {
                $signaler(AnomalieComptable::LIGNE_SANS_PRODUIT, $groupe['libelle'], 'Produit',
                    'Cette ligne de la facture ne se rattache à aucun produit du catalogue.', null, $rang);
            } else {
                $nom = trim($produit->nom . ($produit->reference ? ' (' . $produit->reference . ')' : ''));
                $famille = $produit->categorie_comptable_id ? ($familles[$produit->categorie_comptable_id] ?? null) : null;
                if (!$famille) {
                    $signaler(AnomalieComptable::PRODUIT_SANS_FAMILLE, $nom, 'Grande famille', 'Produit sans grande famille comptable.', 'produits', $rang);
                } else {
                    $compte = $comptes[$famille->compte_comptable_id] ?? null;
                    if (!$compte || !$compte->estGeneral()) {
                        $signaler(AnomalieComptable::FAMILLE_SANS_COMPTE, $famille->nom, 'Compte général',
                            'Aucun compte général actif en face de cette grande famille.', 'familles', $rang);
                        $compte = null;
                    }
                }
                $analytique = $comptes[$produit->compte_analytique_id] ?? null;
                if (!$analytique || $analytique->estGeneral()) {
                    $signaler(AnomalieComptable::PRODUIT_SANS_ANALYTIQUE, $nom, 'Compte analytique', 'Produit sans compte analytique actif.', 'produits', $rang);
                    $analytique = null;
                }
            }

            $lignes[] = [
                'rubrique' => LigneEcritureComptable::PRODUIT, 'compte' => $compte, 'analytique' => $analytique,
                'famille' => $famille, 'produit' => $produit, 'libelle' => $groupe['libelle'], 'montant' => $groupe['ht'], 'sens' => 'C',
            ];
        }

        // 3. le transport
        if ($transport > 0) {
            [$compte, $analytique] = $compteDeRubrique(RubriqueComptable::TRANSPORT);
            $lignes[] = ['rubrique' => LigneEcritureComptable::TRANSPORT, 'compte' => $compte, 'analytique' => $analytique,
                'libelle' => 'Transport facturé', 'montant' => $transport, 'sens' => 'C'];
        }

        // 4. l'écart d'arrondi : sur la dernière ligne de vente, comme l'AIRSI porte celui de la DGI
        $somme = array_sum(array_map(fn ($l) => $l['sens'] === 'C' ? $l['montant'] : 0.0, $lignes))
            - $composition['remise'] + $composition['tva'] + $composition['airsi'];
        $ecart = round($composition['total'] - $somme, 2);
        if (abs($ecart) > 0.004) {
            $derniere = count($lignes) - 1;
            if (abs($ecart) <= self::TOLERANCE && $derniere >= 1 && $lignes[$derniere]['montant'] + $ecart > 0) {
                $lignes[$derniere]['montant'] += $ecart;
            } else {
                $signaler(AnomalieComptable::ECART_DE_TOTAL, 'Facture n° ' . $facture->numero, 'Montant',
                    'Écart de ' . number_format($ecart, 0, ',', ' ') . ' F entre le montant de la facture ('
                    . number_format($composition['total'], 0, ',', ' ') . ' F) et la somme de ses lignes ('
                    . number_format($somme, 0, ',', ' ') . ' F).');
            }
        }

        // 5. la remise (sens inverse des ventes), la TVA, l'AIRSI
        if ($composition['remise'] > 0) {
            [$compte, $analytique] = $compteDeRubrique(RubriqueComptable::REMISES);
            $lignes[] = ['rubrique' => LigneEcritureComptable::REMISE, 'compte' => $compte, 'analytique' => $analytique,
                'libelle' => 'Remise accordée', 'montant' => $composition['remise'], 'sens' => 'D'];
        }
        if ($composition['tva'] > 0) {
            [$compte] = $compteDeRubrique(RubriqueComptable::TVA_COLLECTEE);
            $lignes[] = ['rubrique' => LigneEcritureComptable::TVA, 'compte' => $compte,
                'libelle' => 'TVA facturée sur ventes', 'montant' => $composition['tva'], 'sens' => 'C'];
        }
        if ($composition['airsi'] > 0) {
            [$compte] = $compteDeRubrique(RubriqueComptable::AIRSI);
            $lignes[] = ['rubrique' => LigneEcritureComptable::AIRSI, 'compte' => $compte,
                'libelle' => 'AIRSI collecté', 'montant' => $composition['airsi'], 'sens' => 'C'];
        }

        // ---- l'enregistrement
        return DB::transaction(function () use ($facture, $existante, $version, $composition, $estAvoir, $journal, $date, $nomClient, $client, $lignes, $anomalies) {
            $debit = $credit = 0.0;
            $aEcrire = [];
            foreach ($lignes as $i => $ligne) {
                // Un avoir est l'écriture inverse de la facture : chaque ligne change de colonne.
                $auDebit = ($ligne['sens'] === 'D') !== $estAvoir;
                $montant = round((float) $ligne['montant'], 2);
                $auDebit ? $debit += $montant : $credit += $montant;
                $aEcrire[] = [
                    'rang'                 => $i + 1,
                    'rubrique'             => $ligne['rubrique'],
                    'compte_comptable_id'  => $ligne['compte']?->id,
                    'numero_compte'        => $ligne['compte']?->numero,
                    'compte_tiers'         => $ligne['tiers'] ?? null,
                    'compte_analytique_id' => ($ligne['analytique'] ?? null)?->id,
                    'numero_analytique'    => ($ligne['analytique'] ?? null)?->numero,
                    'categorie_id'         => ($ligne['famille'] ?? null)?->id,
                    'produit_id'           => ($ligne['produit'] ?? null)?->id,
                    'libelle'              => mb_substr($ligne['libelle'], 0, 190),
                    'debit'                => $auDebit ? $montant : 0,
                    'credit'               => $auDebit ? 0 : $montant,
                ];
            }

            $donnees = [
                'origine'              => $estAvoir ? EcritureComptable::ORIGINE_AVOIR : EcritureComptable::ORIGINE_FACTURE,
                'source_type'          => 'facture',
                'source_id'            => $facture->id,
                'version'              => $version,
                'journal_comptable_id' => $journal?->id,
                'journal_code'         => $journal?->code,
                'date_ecriture'        => \Carbon\Carbon::parse($date)->toDateString(),
                'piece'                => (string) $facture->numero,
                'reference_fne'        => $facture->fne_reference ?: $facture->numero_fne,
                'libelle'              => self::libelle($facture, $estAvoir, $nomClient, $composition['numero_affaire']),
                'service'              => $facture->service,
                'service_id'           => $facture->service_id,
                'numero_affaire'       => $composition['numero_affaire'],
                'client_id'            => $client?->id ?? $facture->client_id,
                'total_debit'          => round($debit, 2),
                'total_credit'         => round($credit, 2),
                'etat'                 => $anomalies ? EcritureComptable::ETAT_ANOMALIE : EcritureComptable::ETAT_A_EXPORTER,
            ];

            if ($existante) {
                $existante->update($donnees);
                $existante->lignes()->delete();
                $ecriture = $existante;
            } else {
                $ecriture = EcritureComptable::create($donnees + [
                    'identifiant' => 'FAC-' . $facture->id . ($version > 1 ? '-V' . $version : ''),
                ]);
            }
            foreach ($aEcrire as $ligne) {
                $ecriture->lignes()->create($ligne);
            }

            // Les anomalies : celles qui ont disparu sont datées (historique), les nouvelles s'ajoutent.
            $ouvertes = $ecriture->anomaliesOuvertes()->get();
            $cle = fn ($code, $objet, $rang) => $code . '|' . $objet . '|' . ($rang ?? '');
            $actuelles = [];
            foreach ($anomalies as $anomalie) {
                $actuelles[$cle($anomalie['code'], mb_substr($anomalie['objet'], 0, 190), $anomalie['rang'])] = $anomalie;
            }
            foreach ($ouvertes as $ouverte) {
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
                    'facture_id'            => $facture->id,
                    'rang_ligne'            => $anomalie['rang'],
                    'code'                  => $anomalie['code'],
                    'objet'                 => mb_substr($anomalie['objet'], 0, 190),
                    'colonne'               => $anomalie['colonne'],
                    'cause'                 => mb_substr($anomalie['cause'], 0, 255),
                    'onglet'                => $anomalie['onglet'],
                ]);
            }

            return $ecriture->fresh(['lignes']);
        });
    }

    private static function libelle(Facture $facture, bool $estAvoir, string $nomClient, ?string $numeroAffaire): string
    {
        $libelle = strtr(ParametrageComptable::formatLibelle(), [
            '{type}'    => $estAvoir ? 'Avoir' : 'Facture',
            '{numero}'  => (string) $facture->numero,
            '{fne}'     => (string) ($facture->fne_reference ?: $facture->numero_fne),
            '{client}'  => $nomClient,
            '{affaire}' => (string) $numeroAffaire,
        ]);

        return mb_substr(trim($libelle), 0, 190);
    }
}
