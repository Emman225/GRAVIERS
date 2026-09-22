<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une grande rubrique d'une facture, hors produits : chacune a son compte
 * général au paramétrage. La liste est fermée — c'est le moteur d'écritures
 * qui sait quoi faire de chaque code —, seuls les comptes se règlent.
 */
class RubriqueComptable extends Model
{
    public const CLIENTS         = 'CLIENTS';
    public const FOURNISSEURS    = 'FOURNISSEURS';
    public const TVA_COLLECTEE   = 'TVA_COLLECTEE';
    public const AIRSI           = 'AIRSI';
    public const TRANSPORT       = 'TRANSPORT';
    public const REMISES         = 'REMISES';
    public const AVANCES_CLIENTS = 'AVANCES_CLIENTS';
    // Phase 2b (lot 118) : décaissements et cautions.
    public const LIVREURS            = 'LIVREURS';
    public const APPORTEURS          = 'APPORTEURS';
    public const CHARGES_LIVREURS    = 'CHARGES_LIVREURS';
    public const CHARGES_APPORTEURS  = 'CHARGES_APPORTEURS';
    public const ACHATS_FOURNISSEURS = 'ACHATS_FOURNISSEURS';
    public const CAUTIONS            = 'CAUTIONS';
    public const CAUTIONS_RETENUES   = 'CAUTIONS_RETENUES';

    /** code => [libellé, rôle dans l'écriture, accepte un compte analytique, facultative (absent = obligatoire)] */
    public const RUBRIQUES = [
        self::CLIENTS         => ['Clients (compte collectif)', 'Compte général auquel le compte tiers de chaque client est rattaché ; débité du montant de la facture.', false],
        self::TVA_COLLECTEE   => ['TVA facturée sur ventes', 'Créditée de la TVA de la marchandise et de celle du transport.', false],
        self::AIRSI           => ['AIRSI collecté', 'Crédité de l\'AIRSI retenu sur la facture d\'un client hors régime réel.', false],
        self::TRANSPORT       => ['Transport facturé', 'Crédité du coût de livraison d\'une vente ou d\'une location, et des demandes de livraison.', true],
        self::REMISES         => ['Remises accordées', 'Débité de la remise portée sur la facture.', true],
        self::AVANCES_CLIENTS => ['Avances reçues des clients', 'Crédité au dépôt d\'une avance, débité à son imputation sur une affaire.', false],
        self::FOURNISSEURS    => ['Fournisseurs (compte collectif)', 'Compte général auquel le compte tiers de chaque fournisseur est rattaché ; débité à chaque décaissement.', false],
        self::ACHATS_FOURNISSEURS => ['Achats de marchandises (charge)', 'Facultatif. Renseigné, chaque règlement d’un fournisseur constate aussi la charge : débit de ce compte, crédit du compte du fournisseur.', true, true],
        self::LIVREURS            => ['Livreurs (compte fournisseurs dédié)', 'Compte général auquel le compte tiers de chaque livreur est rattaché.', false],
        self::CHARGES_LIVREURS    => ['Transport confié aux livreurs (charge)', 'Contrepartie du compte des livreurs : débitée de chaque course réglée.', true],
        self::APPORTEURS          => ['Apporteurs d’affaires (compte fournisseurs dédié)', 'Compte général auquel le compte tiers de chaque apporteur est rattaché.', false],
        self::CHARGES_APPORTEURS  => ['Commissions des apporteurs (charge)', 'Contrepartie du compte des apporteurs : débitée de chaque commission réglée.', true],
        self::CAUTIONS            => ['Cautions reçues des clients (dépôts)', 'Créditée de la caution d’une location à sa réception, débitée à son retour.', false],
        self::CAUTIONS_RETENUES   => ['Cautions retenues (produit)', 'Créditée de la part de caution retenue au retour du matériel.', false],
    ];

    protected $table = 'rubrique_comptable';

    protected $fillable = ['code', 'compte_comptable_id', 'compte_analytique_id'];

    public function compte()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    public function compteAnalytique()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_analytique_id');
    }

    public function getLibelleAttribute(): string
    {
        return self::RUBRIQUES[$this->code][0] ?? $this->code;
    }

    public function getRoleAttribute(): string
    {
        return self::RUBRIQUES[$this->code][1] ?? '';
    }

    public function accepteUnAnalytique(): bool
    {
        return (bool) (self::RUBRIQUES[$this->code][2] ?? false);
    }

    /** Une rubrique facultative sans compte n'est pas une anomalie : elle décrit un scénario qu'on peut ne pas retenir. */
    public function estFacultative(): bool
    {
        return (bool) (self::RUBRIQUES[$this->code][3] ?? false);
    }

    /** Toutes les rubriques, dans l'ordre de la liste, créées au besoin. */
    public static function toutes()
    {
        $existantes = self::with(['compte', 'compteAnalytique'])->get()->keyBy('code');

        return collect(array_keys(self::RUBRIQUES))->map(function ($code) use ($existantes) {
            return $existantes[$code] ?? self::create(['code' => $code]);
        });
    }

    public static function pour(string $code): ?self
    {
        return self::toutes()->firstWhere('code', $code);
    }
}
