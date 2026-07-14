<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class StatutMetier extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'statut_metier';

    protected $fillable = [
        'domaine',
        'libelle',
        'badge_class',
        'description',
        'ordre',
        'statut',
    ];

    protected $casts = [
        'statut' => 'boolean',
        'ordre'  => 'integer',
    ];

    public const DOMAINES = [
        'creance_terme'        => 'Créances clients à terme',
        'comptant'             => 'Commandes comptant',
        'dette_fournisseur'    => 'Dettes fournisseurs',
        'dette_livreur'        => 'Dettes livreurs',
        'commission_apporteur' => 'Commissions apporteurs',
    ];

    public const BADGE_CHOICES = [
        'bg-info text-white'      => 'Info (bleu clair)',
        'bg-primary text-white'   => 'Primary (bleu)',
        'bg-success text-white'   => 'Success (vert)',
        'bg-warning text-dark'    => 'Warning (orange)',
        'bg-danger text-white'    => 'Danger (rouge)',
        'bg-dark text-white'      => 'Dark (noir)',
        'bg-secondary text-white' => 'Secondary (gris)',
        'bg-light text-dark'      => 'Light (gris clair)',
    ];

    public function scopePourDomaine($query, string $domaine)
    {
        return $query->where('domaine', $domaine)->where('statut', 1)->orderBy('ordre');
    }

    /**
     * Renvoie la classe Bootstrap de badge pour un libellé donné dans un domaine donné.
     * Utilise un cache mémoire (5 minutes) pour éviter les requêtes répétées.
     *
     * Exemple : StatutMetier::badgeFor('À échoir', 'creance_terme')
     *           => 'bg-info text-white'
     */
    public static function badgeFor(?string $libelle, string $domaine, string $fallback = 'bg-light text-dark'): string
    {
        if (! $libelle) return $fallback;

        $map = Cache::remember("statut_metier:{$domaine}", now()->addMinutes(5), function () use ($domaine) {
            return self::where('domaine', $domaine)
                ->where('statut', 1)
                ->pluck('badge_class', 'libelle')
                ->toArray();
        });

        return $map[$libelle] ?? $fallback;
    }

    /**
     * Vide le cache (à appeler après chaque modification).
     */
    public static function flushCache(): void
    {
        foreach (array_keys(self::DOMAINES) as $domaine) {
            Cache::forget("statut_metier:{$domaine}");
        }
    }

    protected static function booted(): void
    {
        static::saved(fn() => self::flushCache());
        static::deleted(fn() => self::flushCache());
    }
}
