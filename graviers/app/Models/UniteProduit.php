<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UniteProduit extends Model
{
    /**
     * COMBIEN DE TONNES VAUT UNE UNITÉ DE CETTE MESURE — ou null si la question
     * n'a pas de sens.
     *
     * La capacité d'un camion s'exprime en tonnes. Pour savoir combien de
     * voyages demande une commande, il faut donc pouvoir ramener sa quantité à
     * des tonnes. C'est possible pour une tonne et pour un kilogramme ; cela ne
     * l'est pour aucune des autres unités du catalogue — un sac, une barre, un
     * mètre cube, un jour de location n'ont pas de poids connu de la base.
     *
     * Renvoyer null vaut mieux qu'un facteur inventé : c'est en divisant des
     * barres par des tonnes qu'une commande de 11 400 barres se transformait en
     * 285 voyages, et la paie du livreur avec.
     */
    public function facteurEnTonnes(): ?float
    {
        return self::facteurPour($this->abreviation);
    }

    /** Le même calcul depuis une abréviation seule. */
    public static function facteurPour(?string $abreviation): ?float
    {
        return match (strtoupper(trim((string) $abreviation))) {
            'T'  => 1.0,
            'KG' => 0.001,
            default => null,
        };
    }

    /**
     * La quantité ramenée en tonnes, ou null si l'unité ne s'y prête pas.
     */
    public static function enTonnes(?int $uniteProduitId, float $quantite): ?float
    {
        if (!$uniteProduitId) {
            return null;
        }

        try {
            $unite = static::find($uniteProduitId);
        } catch (\Throwable $e) {
            return null;
        }

        $facteur = $unite?->facteurEnTonnes();

        return $facteur === null ? null : $quantite * $facteur;
    }

    use HasFactory;
    protected $table = 'unite_produit';
    protected $fillable = [
        'abreviation',
        'libelle',
    ];
}
