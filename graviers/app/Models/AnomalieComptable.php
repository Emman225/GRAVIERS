<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ce qui empêche une écriture d'être exportée : la facture, l'écriture, la
 * ligne, la colonne à corriger et la cause. Une anomalie corrigée n'est pas
 * effacée, elle est datée : c'est l'historique des anomalies.
 */
class AnomalieComptable extends Model
{
    public const PRODUIT_SANS_FAMILLE     = 'PRODUIT_SANS_FAMILLE';
    public const FAMILLE_SANS_COMPTE      = 'FAMILLE_SANS_COMPTE';
    public const PRODUIT_SANS_ANALYTIQUE  = 'PRODUIT_SANS_ANALYTIQUE';
    public const RUBRIQUE_SANS_COMPTE     = 'RUBRIQUE_SANS_COMPTE';
    public const CLIENT_SANS_COMPTE_TIERS = 'CLIENT_SANS_COMPTE_TIERS';
    public const JOURNAL_ABSENT           = 'JOURNAL_ABSENT';
    public const LIGNE_SANS_PRODUIT       = 'LIGNE_SANS_PRODUIT';
    public const AFFAIRE_INTROUVABLE      = 'AFFAIRE_INTROUVABLE';
    public const ORIGINE_INTROUVABLE      = 'ORIGINE_INTROUVABLE';
    public const ECART_DE_TOTAL           = 'ECART_DE_TOTAL';
    public const FACTURE_SANS_DATE        = 'FACTURE_SANS_DATE';
    // Trésorerie (lot 118)
    public const MODE_SANS_JOURNAL        = 'MODE_SANS_JOURNAL';
    public const JOURNAL_SANS_COMPTE      = 'JOURNAL_SANS_COMPTE';
    public const TIERS_SANS_COMPTE        = 'TIERS_SANS_COMPTE';

    protected $table = 'anomalie_comptable';

    protected $fillable = [
        'ecriture_comptable_id', 'facture_id', 'rang_ligne', 'code', 'objet', 'colonne', 'cause', 'onglet', 'resolue_le',
    ];

    protected $casts = ['resolue_le' => 'datetime', 'rang_ligne' => 'integer'];

    public function ecriture()
    {
        return $this->belongsTo(EcritureComptable::class, 'ecriture_comptable_id');
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class, 'facture_id')->withTrashed();
    }
}
