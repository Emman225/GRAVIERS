<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CE QUE DALAKOUN VERSE AU LIVREUR, PAR TRANCHE.
 *
 * Structure identique à la grille client (`cout_livraison`) : unité, quantité,
 * distance. C'est tout l'intérêt — la marge d'une course devient la soustraction
 * de deux lignes qui se correspondent, au lieu de la comparaison de deux façons
 * de compter qui n'ont rien à voir.
 *
 * Le tarif d'une tranche couvre TOUT le chargement : la tranche est déjà indexée
 * sur la quantité, et la multiplier par les rotations compterait le volume deux
 * fois.
 */
class CoutLivraisonLivreur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cout_livraison_livreur';

    protected $fillable = [
        'livreur_id',
        'unite_produit_id',
        'ville_id',
        'unite_min',
        'unite_max',
        'distance_min_km',
        'distance_max_km',
        'prix',
    ];

    protected $casts = [
        'unite_min'       => 'float',
        'unite_max'       => 'float',
        'distance_min_km' => 'float',
        'distance_max_km' => 'float',
        'prix'            => 'float',
    ];

    public function livreur()
    {
        return $this->belongsTo(Livreur::class);
    }

    public function uniteProduit()
    {
        return $this->belongsTo(UniteProduit::class, 'unite_produit_id');
    }

    /**
     * La table est-elle en place ?
     *
     * Les fichiers sont déposés avant que la migration ne soit lancée — c'est le
     * mode de déploiement ici. Or cette table est lue à chaque affectation de
     * livreur : son absence ne doit pas empêcher de traiter une commande, elle
     * doit simplement laisser le tarif actuel s'appliquer.
     */
    public static function tableExiste(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('cout_livraison_livreur');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * La tranche qui couvre ce cas, ou null.
     *
     * Mêmes bornes inclusives que `CoutLivraison::lireSurCle()` : les deux
     * grilles doivent découper le monde exactement pareil, sinon une course
     * pourrait trouver un tarif d'un côté et pas de l'autre.
     */
    public static function lireSurCle(int $livreurId, ?int $uniteProduitId, float $unite, float $distance): ?self
    {
        if (!$uniteProduitId || !static::tableExiste()) {
            return null;
        }

        try {
            return static::where('livreur_id', $livreurId)
                ->where('unite_produit_id', $uniteProduitId)
                ->where('unite_min', '<=', $unite)
                ->where('unite_max', '>=', $unite)
                ->where('distance_min_km', '<=', $distance)
                ->where('distance_max_km', '>=', $distance)
                ->where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))
                ->whereNull('deleted_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tranches qui en recouvrent une autre chez le MÊME livreur.
     *
     * Deux tranches qui se croisent rendent le tarif indéterminé : la recherche
     * prend la première venue, sans règle. Même garde-fou que la grille client.
     */
    public static function tranchesEnConflit(array $valeurs, ?int $ignorerId = null)
    {
        return static::where('livreur_id', $valeurs['livreur_id'])
            ->where('unite_produit_id', $valeurs['unite_produit_id'])
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->where('unite_min', '<=', $valeurs['unite_max'])
            ->where('unite_max', '>=', $valeurs['unite_min'])
            ->where('distance_min_km', '<=', $valeurs['distance_max_km'])
            ->where('distance_max_km', '>=', $valeurs['distance_min_km'])
            ->get();
    }

    /** Le tarif CLIENT de la même tranche, pour afficher la marge en face. */
    public function prixClient(): ?float
    {
        $tranche = CoutLivraison::where('unite_produit_id', $this->unite_produit_id)
            ->where('unite_min', '<=', $this->unite_min)
            ->where('unite_max', '>=', $this->unite_max)
            ->where('distance_min_km', '<=', $this->distance_min_km)
            ->where('distance_max_km', '>=', $this->distance_max_km)
            ->where(fn ($q) => $q->whereNull('ville_id')->orWhere('ville_id', '<=', 0))
            ->first();

        return $tranche ? (float) $tranche->prix_km : null;
    }

    public function marge(): ?float
    {
        $client = $this->prixClient();

        return $client === null ? null : $client - (float) $this->prix;
    }

    public function tauxMarge(): ?float
    {
        $client = $this->prixClient();

        if (!$client || $client <= 0) {
            return null;
        }

        return round(($client - (float) $this->prix) / $client * 100, 1);
    }
}
