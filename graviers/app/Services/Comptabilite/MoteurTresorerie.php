<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\Apporteur;
use App\Models\AvanceClient;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\DemandeLivraison;
use App\Models\DemandePaiement;
use App\Models\EcritureComptable;
use App\Models\Fournisseur;
use App\Models\JournalComptable;
use App\Models\LignePaiement;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Paiement;
use App\Models\PaiementApporteur;
use App\Models\PaiementFournisseur;
use App\Models\PaiementLivreur;
use App\Models\RubriqueComptable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LES ÉCRITURES DE TRÉSORERIE (module « Écritures comptables », phase 2b, lot 118).
 *
 * Règles arrêtées avec le responsable le 21/09/2026 :
 *  - chaque moyen de paiement a son journal : l'écriture va dans le journal de
 *    l'instrument RÉEL de la ligne de règlement (espèces, chèque, virement,
 *    Mobile Money), jamais dans celui du lieu « en agence » ;
 *  - livreurs et apporteurs ont leurs comptes fournisseurs dédiés, et la
 *    contrepartie est une charge ; même scénario, facultatif, pour les achats
 *    de marchandises ;
 *  - les cautions des locations se suivent dans un compte de dépôts.
 *
 * Ce qui compte comme argent : une ligne de règlement client au statut 1
 * (c'est ce que tout le site additionne) ; un dépôt d'avance DISPONIBLE ; un
 * règlement de partenaire validé deux fois ET effectué (ou d'avant le circuit
 * de preuve). L'imputation d'une avance n'est PAS un encaissement — l'argent
 * est entré au dépôt — : c'est un virement du compte d'avances au compte du
 * client, dans le journal des opérations diverses.
 */
class MoteurTresorerie
{
    public const ENCAISSEMENT      = 'ENCAISSEMENT';
    public const AVANCE_DEPOT      = 'AVANCE_DEPOT';
    public const AVANCE_IMPUTATION = 'AVANCE_IMPUTATION';
    public const DECAISSEMENT      = 'DECAISSEMENT';
    public const CAUTION_RECUE     = 'CAUTION_RECUE';
    public const CAUTION_RENDUE    = 'CAUTION_RENDUE';

    /** table du règlement => [type de tiers, classe du tiers, clé étrangère, collectif, charge, préfixe d'identifiant] */
    private const PARTENAIRES = [
        'paiement_fournisseur' => ['fournisseur', Fournisseur::class, 'fournisseur_id', RubriqueComptable::FOURNISSEURS, RubriqueComptable::ACHATS_FOURNISSEURS, 'DEC-F-'],
        'paiement_livreur'     => ['livreur', Livreur::class, 'livreur_id', RubriqueComptable::LIVREURS, RubriqueComptable::CHARGES_LIVREURS, 'DEC-L-'],
        'paiement_apporteur'   => ['apporteur', Apporteur::class, 'apporteur_id', RubriqueComptable::APPORTEURS, RubriqueComptable::CHARGES_APPORTEURS, 'DEC-A-'],
    ];

    // ================================================================== encaissements

    public static function produirePourLigneDePaiement(LignePaiement $ligne): ?EcritureComptable
    {
        $existante = self::vivante('ligne_paiement', $ligne->id);
        $paiement = $ligne->paiement_id ? Paiement::withTrashed()->find($ligne->paiement_id) : null;

        $valable = (int) $ligne->statut === 1 && !$ligne->trashed() && $paiement && !$paiement->trashed() && (float) $ligne->montant > 0;
        if (!$valable) {
            return self::plusValable($existante);
        }
        if ($existante && $existante->estExportee()) {
            return $existante;
        }

        $a = new Preparation();
        $client = $paiement->client_id ? Client::withTrashed()->find($paiement->client_id) : null;
        $nomClient = self::nomDuClient($client);
        $montant = (float) $ligne->montant;
        $piece = (string) ($paiement->numero_recu ?: $paiement->code ?: 'REG-' . $paiement->id);
        $affaire = self::numeroAffaire($paiement->service, $paiement->service_id);
        [$collectif] = $a->rubrique(RubriqueComptable::CLIENTS);
        $tiers = $a->tiers($client?->compte_tiers, $nomClient, 'Client sans compte tiers.');

        $estImputation = str_starts_with((string) $paiement->code, 'PAV-') || str_starts_with((string) $ligne->moyen_paiement, 'Avance client');

        if ($estImputation) {
            // L'avance change de compte, aucune trésorerie ne bouge.
            $journal = $a->journalDesOperationsDiverses();
            [$avances] = $a->rubrique(RubriqueComptable::AVANCES_CLIENTS);
            $lignes = [
                ['rubrique' => 'AVANCE', 'compte' => $avances, 'tiers' => $tiers, 'libelle' => 'Avance imputée — ' . $nomClient, 'montant' => $montant, 'sens' => 'D'],
                ['rubrique' => 'CLIENT', 'compte' => $collectif, 'tiers' => $tiers, 'libelle' => $nomClient, 'montant' => $montant, 'sens' => 'C'],
            ];
            $origine = self::AVANCE_IMPUTATION;
            $libelle = 'Imputation d\'avance ' . $piece . ' — ' . $nomClient . ($affaire ? ' — affaire ' . $affaire : '');
        } else {
            $mode = ModePaiement::withTrashed()->find($ligne->mode_paiement_id);
            [$journal, $tresorerie] = $a->journalDuMode($mode, $ligne->moyen_paiement);
            $lignes = [
                ['rubrique' => 'TRESORERIE', 'compte' => $tresorerie, 'libelle' => ($mode?->libelle ?: 'Règlement') . ($ligne->reference ? ' — réf. ' . $ligne->reference : ''), 'montant' => $montant, 'sens' => 'D'],
                ['rubrique' => 'CLIENT', 'compte' => $collectif, 'tiers' => $tiers, 'libelle' => $nomClient, 'montant' => $montant, 'sens' => 'C'],
            ];
            $origine = self::ENCAISSEMENT;
            $libelle = 'Règlement ' . $piece . ' — ' . $nomClient . ($affaire ? ' — affaire ' . $affaire : '');
        }

        $ecriture = Ecrivain::enregistrer($existante, ($estImputation ? 'AVI-' : 'ENC-') . $ligne->id, [
            'origine' => $origine, 'source_type' => 'ligne_paiement', 'source_id' => $ligne->id, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => self::jour($ligne->date_paiement ?: $paiement->created_at),
            'piece' => $piece, 'reference_fne' => null, 'libelle' => $libelle,
            'service' => $paiement->service, 'service_id' => $paiement->service_id, 'numero_affaire' => $affaire,
            'client_id' => $client?->id, 'tiers_type' => 'client', 'tiers_id' => $client?->id,
        ], $lignes, $a->anomalies, $paiement->facture_id);

        Lettrage::lettrerAffaire($paiement->service, $paiement->service_id);

        return $ecriture;
    }

    // ================================================================== avances : le dépôt

    public static function produirePourAvance(AvanceClient $avance): ?EcritureComptable
    {
        $existante = self::vivante('avance_client', $avance->id);
        $valable = (int) $avance->statut === AvanceClient::DISPONIBLE && !$avance->trashed() && (float) $avance->montant > 0;
        if (!$valable) {
            return self::plusValable($existante);
        }
        if ($existante && $existante->estExportee()) {
            return $existante;
        }

        $a = new Preparation();
        $client = $avance->client_id ? Client::withTrashed()->find($avance->client_id) : null;
        $nomClient = self::nomDuClient($client);
        $mode = ModePaiement::withTrashed()->find($avance->mode_paiement_id);
        [$journal, $tresorerie] = $a->journalDuMode($mode, $avance->moyen_paiement);
        [$avances] = $a->rubrique(RubriqueComptable::AVANCES_CLIENTS);
        $tiers = $a->tiers($client?->compte_tiers, $nomClient, 'Client sans compte tiers.');
        $montant = (float) $avance->montant;
        $piece = (string) ($avance->numero_recu ?: 'AVANCE-' . $avance->id);

        return Ecrivain::enregistrer($existante, 'AVD-' . $avance->id, [
            'origine' => self::AVANCE_DEPOT, 'source_type' => 'avance_client', 'source_id' => $avance->id, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => self::jour($avance->date_depot ?: $avance->created_at),
            'piece' => $piece, 'libelle' => 'Avance reçue ' . $piece . ' — ' . $nomClient,
            'client_id' => $client?->id, 'tiers_type' => 'client', 'tiers_id' => $client?->id,
        ], [
            ['rubrique' => 'TRESORERIE', 'compte' => $tresorerie, 'libelle' => ($mode?->libelle ?: 'Dépôt') . ($avance->reference ? ' — réf. ' . $avance->reference : ''), 'montant' => $montant, 'sens' => 'D'],
            ['rubrique' => 'AVANCE', 'compte' => $avances, 'tiers' => $tiers, 'libelle' => 'Avance de ' . $nomClient, 'montant' => $montant, 'sens' => 'C'],
        ], $a->anomalies);
    }

    // ================================================================== décaissements

    /** @param PaiementFournisseur|PaiementLivreur|PaiementApporteur $reglement */
    public static function produirePourReglementPartenaire(Model $reglement): ?EcritureComptable
    {
        $table = $reglement->getTable();
        if (!isset(self::PARTENAIRES[$table])) {
            return null;
        }
        [$type, $classe, $cle, $codeCollectif, $codeCharge, $prefixe] = self::PARTENAIRES[$table];

        $existante = self::vivante($table, $reglement->id);
        // L'argent est sorti : validé deux fois ET effectué — ou règlement d'avant le circuit de preuve.
        $etat = $reglement->etat_reglement;
        $valable = (int) $reglement->statut === 1 && !$reglement->trashed() && (float) $reglement->montant > 0
            && ($etat === null || $etat === '' || $etat === DemandePaiement::EFFECTUEE);
        if (!$valable) {
            return self::plusValable($existante);
        }
        if ($existante && $existante->estExportee()) {
            return $existante;
        }

        $a = new Preparation();
        $partenaire = self::trouver($classe, $reglement->{$cle});
        $nom = self::nomDuPartenaire($type, $partenaire);
        $mode = ModePaiement::withTrashed()->find($reglement->mode_paiement_id);
        [$journal, $tresorerie] = $a->journalDuMode($mode, null);
        [$collectif] = $a->rubrique($codeCollectif);
        $tiers = $a->tiers($partenaire?->compte_tiers, $nom, ucfirst($type) . ' sans compte tiers.');
        $montant = (float) $reglement->montant;
        $piece = (string) ($reglement->reference ?: strtoupper(substr($type, 0, 3)) . '-' . $reglement->id);

        // La contrepartie du compte fournisseur est une charge. Facultative pour les achats de marchandises.
        $rubriqueCharge = RubriqueComptable::pour($codeCharge);
        $lignes = [];
        if (!$rubriqueCharge->estFacultative() || $rubriqueCharge->compte_comptable_id) {
            [$charge, $analytique] = $a->rubrique($codeCharge);
            $lignes[] = ['rubrique' => 'CHARGE', 'compte' => $charge, 'analytique' => $analytique, 'libelle' => $rubriqueCharge->libelle . ' — ' . $nom, 'montant' => $montant, 'sens' => 'D'];
            $lignes[] = ['rubrique' => 'FOURNISSEUR', 'compte' => $collectif, 'tiers' => $tiers, 'libelle' => $nom, 'montant' => $montant, 'sens' => 'C'];
        }
        $lignes[] = ['rubrique' => 'FOURNISSEUR', 'compte' => $collectif, 'tiers' => $tiers, 'libelle' => $nom, 'montant' => $montant, 'sens' => 'D'];
        $lignes[] = ['rubrique' => 'TRESORERIE', 'compte' => $tresorerie, 'libelle' => ($mode?->libelle ?: 'Règlement') . ($reglement->reference ? ' — réf. ' . $reglement->reference : ''), 'montant' => $montant, 'sens' => 'C'];

        return Ecrivain::enregistrer($existante, $prefixe . $reglement->id, [
            'origine' => self::DECAISSEMENT, 'source_type' => $table, 'source_id' => $reglement->id, 'version' => 1,
            'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
            'date_ecriture' => self::jour($reglement->date_effectuee ?: $reglement->date_paiement ?: $reglement->created_at),
            'piece' => $piece, 'libelle' => 'Règlement ' . $type . ' ' . $piece . ' — ' . $nom,
            'tiers_type' => $type, 'tiers_id' => $partenaire?->id,
        ], $lignes, $a->anomalies);
    }

    // ================================================================== cautions des locations

    /** @return array<int,EcritureComptable> la réception, puis le retour s'il a eu lieu */
    public static function produirePourCaution(Location $location): array
    {
        $produites = [];
        $caution = (float) ($location->caution ?? 0);
        $vivanteLocation = in_array($location->etat_location, [\Help::$LOCATION_EN_COURS, \Help::$LOCATION_TERMINE], true) && !$location->trashed();

        foreach ([
            'location_caution_recue'  => $caution > 0 && $vivanteLocation,
            'location_caution_rendue' => $caution > 0 && $vivanteLocation && $location->date_retour !== null,
        ] as $source => $valable) {
            $existante = self::vivante($source, $location->id);
            if (!$valable) {
                self::plusValable($existante);
                continue;
            }
            if ($existante && $existante->estExportee()) {
                $produites[] = $existante;
                continue;
            }

            $a = new Preparation();
            $client = $location->client_id ? Client::withTrashed()->find($location->client_id) : null;
            $nomClient = self::nomDuClient($client);
            [$journal, $tresorerie] = $a->journalDesCautions();
            [$depots] = $a->rubrique(RubriqueComptable::CAUTIONS);
            $recue = $source === 'location_caution_recue';

            if ($recue) {
                $lignes = [
                    ['rubrique' => 'TRESORERIE', 'compte' => $tresorerie, 'libelle' => 'Caution reçue — ' . $nomClient, 'montant' => $caution, 'sens' => 'D'],
                    ['rubrique' => 'CAUTION', 'compte' => $depots, 'libelle' => 'Caution de la location ' . $location->numero . ' — ' . $nomClient, 'montant' => $caution, 'sens' => 'C'],
                ];
            } else {
                $retenue = min($caution, max(0.0, (float) ($location->caution_retenue ?? 0)));
                $lignes = [['rubrique' => 'CAUTION', 'compte' => $depots, 'libelle' => 'Caution de la location ' . $location->numero . ' — ' . $nomClient, 'montant' => $caution, 'sens' => 'D']];
                if ($caution - $retenue > 0) {
                    $lignes[] = ['rubrique' => 'TRESORERIE', 'compte' => $tresorerie, 'libelle' => 'Caution rendue — ' . $nomClient, 'montant' => $caution - $retenue, 'sens' => 'C'];
                }
                if ($retenue > 0) {
                    [$produit] = $a->rubrique(RubriqueComptable::CAUTIONS_RETENUES);
                    $lignes[] = ['rubrique' => 'CAUTION_RETENUE', 'compte' => $produit, 'libelle' => 'Caution retenue' . ($location->motif_retenue ? ' — ' . $location->motif_retenue : ''), 'montant' => $retenue, 'sens' => 'C'];
                }
            }

            $produites[] = Ecrivain::enregistrer($existante, ($recue ? 'CAU-R-' : 'CAU-S-') . $location->id, [
                'origine' => $recue ? self::CAUTION_RECUE : self::CAUTION_RENDUE, 'source_type' => $source, 'source_id' => $location->id, 'version' => 1,
                'journal_comptable_id' => $journal?->id, 'journal_code' => $journal?->code,
                'date_ecriture' => self::jour($recue ? ($location->date_location ?: $location->created_at) : $location->date_retour),
                'piece' => 'CAUTION-' . $location->numero,
                'libelle' => ($recue ? 'Caution reçue' : 'Caution rendue') . ' — location ' . $location->numero . ' — ' . $nomClient,
                'service' => \Help::$LOCATION, 'service_id' => $location->id, 'numero_affaire' => (string) $location->numero,
                'client_id' => $client?->id, 'tiers_type' => 'client', 'tiers_id' => $client?->id,
            ], $lignes, $a->anomalies);
        }

        return $produites;
    }

    // ================================================================== reprise

    /** Tout ce qui n'a pas encore son écriture, sur une période de dates d'opération. Sans danger à rejouer. */
    public static function toutProduire(?string $du = null, ?string $au = null): array
    {
        $bilan = ['encaissements' => 0, 'avances' => 0, 'decaissements' => 0, 'cautions' => 0, 'echecs' => 0];
        $borner = function ($requete, string $colonne) use ($du, $au) {
            if ($du) {
                $requete->whereDate($colonne, '>=', $du);
            }
            if ($au) {
                $requete->whereDate($colonne, '<=', $au);
            }

            return $requete;
        };
        $essayer = function (callable $action, string $compteur) use (&$bilan) {
            try {
                $resultat = $action();
                $bilan[$compteur] += is_array($resultat) ? count($resultat) : ($resultat ? 1 : 0);
            } catch (\Throwable $e) {
                $bilan['echecs']++;
                \Illuminate\Support\Facades\Log::warning('Écriture de trésorerie non produite : ' . $e->getMessage());
            }
        };

        $borner(LignePaiement::where('statut', 1), 'date_paiement')->orderBy('id')->chunkById(200, function ($lignes) use ($essayer) {
            foreach ($lignes as $ligne) {
                $essayer(fn () => self::produirePourLigneDePaiement($ligne), 'encaissements');
            }
        });
        $borner(AvanceClient::where('statut', AvanceClient::DISPONIBLE), 'date_depot')->orderBy('id')->chunkById(200, function ($avances) use ($essayer) {
            foreach ($avances as $avance) {
                $essayer(fn () => self::produirePourAvance($avance), 'avances');
            }
        });
        foreach ([PaiementFournisseur::class, PaiementLivreur::class, PaiementApporteur::class] as $classe) {
            $borner($classe::where('statut', 1), 'date_paiement')->orderBy('id')->chunkById(200, function ($reglements) use ($essayer) {
                foreach ($reglements as $reglement) {
                    $essayer(fn () => self::produirePourReglementPartenaire($reglement), 'decaissements');
                }
            });
        }
        $borner(Location::where('caution', '>', 0), 'date_location')->orderBy('id')->chunkById(200, function ($locations) use ($essayer) {
            foreach ($locations as $location) {
                $essayer(fn () => self::produirePourCaution($location), 'cautions');
            }
        });

        return $bilan;
    }

    /** Reproduit les écritures de trésorerie en anomalie : à lancer après une correction du paramétrage. */
    public static function reprendreLesAnomalies(): array
    {
        $bilan = ['reprises' => 0, 'reglees' => 0];
        $enAnomalie = EcritureComptable::where('etat', EcritureComptable::ETAT_ANOMALIE)->where('source_type', '!=', 'facture')
            ->whereNull('annulee_par_id')->get();

        foreach ($enAnomalie as $ecriture) {
            $apres = self::reproduire($ecriture->source_type, (int) $ecriture->source_id);
            $bilan['reprises']++;
            if ($apres && $apres->etat !== EcritureComptable::ETAT_ANOMALIE) {
                $bilan['reglees']++;
            }
        }

        return $bilan;
    }

    private static function reproduire(string $sourceType, int $id): ?EcritureComptable
    {
        switch ($sourceType) {
            case 'ligne_paiement':
                $ligne = LignePaiement::withTrashed()->find($id);

                return $ligne ? self::produirePourLigneDePaiement($ligne) : null;
            case 'avance_client':
                $avance = AvanceClient::withTrashed()->find($id);

                return $avance ? self::produirePourAvance($avance) : null;
            case 'location_caution_recue':
            case 'location_caution_rendue':
                $location = Location::withTrashed()->find($id);
                if (!$location) {
                    return null;
                }
                self::produirePourCaution($location);

                return self::vivante($sourceType, $id);
            default:
                $classe = ['paiement_fournisseur' => PaiementFournisseur::class, 'paiement_livreur' => PaiementLivreur::class,
                           'paiement_apporteur' => PaiementApporteur::class][$sourceType] ?? null;
                $reglement = $classe ? $classe::withTrashed()->find($id) : null;

                return $reglement ? self::produirePourReglementPartenaire($reglement) : null;
        }
    }

    // ================================================================== outils

    private static function vivante(string $sourceType, int $id): ?EcritureComptable
    {
        return EcritureComptable::where('source_type', $sourceType)->where('source_id', $id)
            ->whereNull('annulee_par_id')->where('origine', '!=', EcritureComptable::ORIGINE_ANNULATION)
            ->orderByDesc('id')->first();
    }

    private static function plusValable(?EcritureComptable $existante): ?EcritureComptable
    {
        if ($existante) {
            Ecrivain::retirer($existante);
        }

        return null;
    }

    private static function jour($date): string
    {
        return \Carbon\Carbon::parse($date ?: now())->toDateString();
    }

    private static function trouver(string $classe, $id)
    {
        if (!$id) {
            return null;
        }
        $requete = $classe::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($classe), true)) {
            $requete->withTrashed();
        }

        return $requete->find($id);
    }

    private static function nomDuClient($client): string
    {
        return (string) ($client?->display_name ?: ($client ? 'Client n° ' . $client->id : 'Client inconnu'));
    }

    public static function nomDuPartenaire(string $type, $partenaire): string
    {
        if (!$partenaire) {
            return ucfirst($type) . ' inconnu';
        }
        if ($type === 'fournisseur') {
            return ParametrageComptable::nomDuFournisseur($partenaire);
        }
        $nom = trim((string) ($partenaire->user?->nom_prenoms ?? ''));

        return $nom !== '' ? $nom : ucfirst($type) . ' n° ' . $partenaire->id . ($partenaire->code ? ' (' . $partenaire->code . ')' : '');
    }

    private static function numeroAffaire(?string $service, $id): ?string
    {
        $classe = [\Help::$COMMANDE => Commande::class, \Help::$LOCATION => Location::class, \Help::$LIVRAISON => DemandeLivraison::class][$service] ?? null;
        $affaire = $classe ? self::trouver($classe, $id) : null;

        return $affaire ? (string) $affaire->numero : null;
    }
}
