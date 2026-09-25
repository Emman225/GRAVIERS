<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Qui a créé, modifié ou supprimé quoi dans le paramétrage comptable, et quand. */
class HistoriqueParametrageComptable extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'CREATION'      => 'Création',
        'MODIFICATION'  => 'Modification',
        'SUPPRESSION'   => 'Suppression',
        'ACTIVATION'    => 'Activation',
        'DESACTIVATION' => 'Désactivation',
        'IMPORT'        => 'Import',
    ];

    public const OBJETS = [
        'compte'    => 'Compte',
        'journal'   => 'Journal',
        'famille'   => 'Grande famille',
        'produit'   => 'Produit',
        'rubrique'  => 'Rubrique de facture',
        'client'    => 'Compte tiers client',
        'fournisseur' => 'Compte tiers fournisseur',
        'livreur'   => 'Compte tiers livreur',
        'apporteur' => 'Compte tiers apporteur',
        'mode'      => 'Mode de règlement',
        'reglage'   => 'Réglage',
        'import'    => 'Import du paramétrage',
    ];

    protected $table = 'historique_parametrage_comptable';

    protected $fillable = ['user_id', 'objet', 'objet_id', 'designation', 'action', 'avant', 'apres'];

    protected $casts = ['avant' => 'array', 'apres' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getLibelleActionAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getLibelleObjetAttribute(): string
    {
        return self::OBJETS[$this->objet] ?? $this->objet;
    }

    /** « compte général : 701100 → 701200 » — ce qui a changé, en clair. */
    public function getResumeAttribute(): string
    {
        $avant = $this->avant ?: [];
        $apres = $this->apres ?: [];
        $morceaux = [];

        foreach (array_unique(array_merge(array_keys($avant), array_keys($apres))) as $cle) {
            $a = $avant[$cle] ?? null;
            $b = $apres[$cle] ?? null;
            if ($a === $b) {
                continue;
            }
            $vide = '(vide)';
            $morceaux[] = $this->action === 'CREATION'
                ? $cle . ' : ' . ($b ?? $vide)
                : $cle . ' : ' . ($a ?? $vide) . ' → ' . ($b ?? $vide);
        }

        return implode(' ; ', $morceaux);
    }
}
