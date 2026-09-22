<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un compte du plan de comptes de DALAKOUN : compte général (porté par une
 * grande famille, une rubrique de facture ou un journal) ou compte analytique
 * (porté par un produit).
 */
class CompteComptable extends Model
{
    use SoftDeletes;

    public const NATURE_GENERAL    = 'GENERAL';
    public const NATURE_ANALYTIQUE = 'ANALYTIQUE';

    public const NATURES = [
        self::NATURE_GENERAL    => 'Compte général',
        self::NATURE_ANALYTIQUE => 'Compte analytique',
    ];

    protected $table = 'compte_comptable';

    protected $fillable = ['nature', 'numero', 'libelle', 'statut'];

    protected $casts = ['statut' => 'integer'];

    public function scopeActifs($requete)
    {
        return $requete->where('statut', 1);
    }

    public function scopeGeneraux($requete)
    {
        return $requete->where('nature', self::NATURE_GENERAL);
    }

    public function scopeAnalytiques($requete)
    {
        return $requete->where('nature', self::NATURE_ANALYTIQUE);
    }

    public function estGeneral(): bool
    {
        return $this->nature === self::NATURE_GENERAL;
    }

    /** « 701100 — Ventes de granulats » : la désignation des listes et de l'historique. */
    public function getDesignationAttribute(): string
    {
        return $this->numero . ' — ' . $this->libelle;
    }

    /**
     * Où ce compte est-il employé ? Tableau « lieu => nombre », vide s'il est libre.
     * Un compte employé ne se supprime pas : il se désactive, pour que le
     * paramétrage et les écritures passées restent lisibles.
     */
    public function emplois(): array
    {
        $emplois = [
            'grande(s) famille(s)'     => DB::table('categorie')->whereNull('deleted_at')->where('compte_comptable_id', $this->id)->count(),
            'produit(s)'               => DB::table('produit')->whereNull('deleted_at')->where('compte_analytique_id', $this->id)->count(),
            'rubrique(s) de facture'   => DB::table('rubrique_comptable')
                ->where(function ($q) {
                    $q->where('compte_comptable_id', $this->id)->orWhere('compte_analytique_id', $this->id);
                })->count(),
            'journal(aux)'             => DB::table('journal_comptable')->whereNull('deleted_at')->where('compte_comptable_id', $this->id)->count(),
        ];

        // Les écritures arrivent avec la phase 2 : la table n'existe pas avant.
        if (Schema::hasTable('ligne_ecriture_comptable')) {
            $emplois['ligne(s) d\'écriture'] = DB::table('ligne_ecriture_comptable')
                ->where(function ($q) {
                    $q->where('compte_comptable_id', $this->id)->orWhere('compte_analytique_id', $this->id);
                })->count();
        }

        return array_filter($emplois);
    }
}
