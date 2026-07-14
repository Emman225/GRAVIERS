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
        'montant_point',
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
