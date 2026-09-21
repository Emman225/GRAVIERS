<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LE POURCENTAGE QUE DALAKOUN AJOUTE AU PRIX D'ACHAT — vu depuis l'API.
 *
 * Le taux se SAISIT et se VALIDE sur le site, sous double validation. L'API ne
 * fait que le lire : elle doit facturer au même prix que le site, sans quoi le
 * même article s'affiche à deux montants selon qu'on le regarde sur le web ou
 * dans l'application — et se facture au montant le plus bas des deux.
 *
 * Aucune écriture ici, volontairement : un taux qui pourrait être posé depuis
 * l'API échapperait à la double validation qui en fait tout l'intérêt.
 */
class PourcentageDalakoun extends Model
{
    protected $table = 'pourcentage_dalakoun';

    /** Validé : c'est ce taux qui fait les prix. */
    public const APPLIQUE = 1;

    /**
     * Le taux général en vigueur, en pourcentage. Zéro si aucun n'est validé.
     *
     * La table peut ne pas exister : les fichiers sont déposés avant que la
     * migration ne soit lancée, et ce taux est lu par TOUT le catalogue. Une
     * migration oubliée ne doit pas éteindre la boutique mobile — elle doit
     * seulement laisser les prix inchangés.
     */
    public static function tauxEnVigueur(): float
    {
        try {
            $taux = static::where('statut', self::APPLIQUE)
                // Le taux GÉNÉRAL : une dérogation ne vaut que pour son produit.
                ->whereNull('produit_id')
                ->whereNotNull('user_valide2_id')
                ->orderByDesc('date_validation_2')
                ->orderByDesc('id')
                ->value('taux');

            return (float) ($taux ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /** Applique un taux à un prix d'achat. */
    public static function appliquerA(float $prixAchat, ?float $taux = null): float
    {
        $taux = $taux ?? static::tauxEnVigueur();

        return $prixAchat * (1 + $taux / 100);
    }
}
