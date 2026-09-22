<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une ligne d'écriture : un compte, un sens, un montant — et l'analytique pour un produit. */
class LigneEcritureComptable extends Model
{
    public const CLIENT    = 'CLIENT';
    public const PRODUIT   = 'PRODUIT';
    public const TRANSPORT = 'TRANSPORT';
    public const REMISE    = 'REMISE';
    public const TVA       = 'TVA';
    public const AIRSI     = 'AIRSI';

    protected $table = 'ligne_ecriture_comptable';

    protected $fillable = [
        'ecriture_comptable_id', 'rang', 'rubrique', 'compte_comptable_id', 'numero_compte', 'compte_tiers',
        'compte_analytique_id', 'numero_analytique', 'categorie_id', 'produit_id', 'libelle', 'debit', 'credit', 'lettre', 'lettree_le',
    ];

    protected $casts = ['debit' => 'float', 'credit' => 'float', 'rang' => 'integer'];

    public function ecriture()
    {
        return $this->belongsTo(EcritureComptable::class, 'ecriture_comptable_id');
    }

    public function compte()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id')->withTrashed();
    }

    public function compteAnalytique()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_analytique_id')->withTrashed();
    }

    public function famille()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id')->withTrashed();
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class, 'produit_id')->withTrashed();
    }
}
