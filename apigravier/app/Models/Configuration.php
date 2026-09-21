<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuration extends Model
{
    use HasFactory;
    protected $table = 'configuration';
    protected $fillable = [
        'tva',
        // La TVA s'applique-t-elle au transport ? Le reglage se pose sur le
        // site, l'API le lit : les deux canaux doivent chiffrer pareil.
        'tva_transport',
        // Taux de l'AIRSI (10/09/2026).
        'taux_airsi',
        'montant_point',
        'montant_minimum_a_payer',
        'devise',
        'raison_sociale',
        'ncc',
        'regime_imposition',
        'centre_impots',
        'rccm',
        'ref_bancaires',
        'cnps',
        'capital_social',
        'adresse_siege',
        'telephone',
        'email_entreprise',
        'nom_etablissement',
        'nom_pdv',
    ];
}
