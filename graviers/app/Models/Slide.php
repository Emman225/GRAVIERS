<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Diapositive du carrousel de la page d'accueil.
 *
 * Même mécanique que Banniere : statut 1/0 pour l'affichage, corbeille par
 * suppression douce, tri par num_ordre.
 */
class Slide extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'slide';

    protected $fillable = [
        'image',
        'badge_texte',
        'badge_type',
        'titre',
        'titre_accent',
        'description',
        'caracteristiques',
        'deco_valeur',
        'deco_libelle',
        'bouton1_texte',
        'bouton1_lien',
        'bouton2_texte',
        'bouton2_lien',
        'num_ordre',
        'statut',
    ];

    protected $casts = [
        'num_ordre' => 'integer',
        'statut'    => 'integer',
    ];

    /** Les diapositives réellement affichées sur le site, dans l'ordre voulu. */
    public static function liste()
    {
        return Slide::where('statut', Help::$STATUT_ACTIF)
            ->orderBy('num_ordre', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * URL de l'image de fond.
     *
     * Deux origines cohabitent : les visuels livrés avec le thème, sous
     * public/frontend, et ceux téléversés par un administrateur, sous
     * public/storage. On distingue les deux par le préfixe du chemin plutôt que
     * par une colonne supplémentaire.
     */
    public function urlImage(): string
    {
        $chemin = (string) $this->image;

        if ($chemin === '') {
            return '';
        }

        return str_starts_with($chemin, 'frontend/')
            ? asset($chemin)
            : asset('storage/' . $chemin);
    }

    /** Les arguments à puces, saisis un par ligne. */
    public function caracteristiquesListe(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->caracteristiques))
            ->map(fn ($l) => trim($l))
            ->filter()
            ->values()
            ->all();
    }

    /** Icône de la pastille, déduite de son type. */
    public function iconeBadge(): string
    {
        return match ($this->badge_type) {
            'NEW'   => 'fi-rs-star',
            'PROMO' => 'fi-rs-gift',
            'HOT'   => 'fi-rs-percentage',
            default => 'fi-rs-shield-check',
        };
    }

    /** Classe CSS de la pastille. Le type NEUTRE n'en ajoute aucune. */
    public function classeBadge(): string
    {
        return match ($this->badge_type) {
            'NEW'   => 'slider-badge badge-new',
            'PROMO' => 'slider-badge badge-promo',
            'HOT'   => 'slider-badge badge-hot',
            default => 'slider-badge',
        };
    }
}
