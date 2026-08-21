<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La colonne commande.date_commande portait une valeur par défaut FIGÉE :
     * l'horodatage « 2026-04-13 12:32:21 », inscrit une fois pour toutes dans
     * le schéma.
     *
     * Aucun des points de création du SITE ne renseignait ce champ. Toutes les
     * commandes passées depuis le web héritaient donc de cette date unique. Le
     * site ne s'en apercevait pas — ses écrans affichent created_at sous le nom
     * « date_commande » — mais l'application mobile, qui lit la vraie colonne,
     * datait toutes les commandes du 13 avril 2026.
     *
     * Deux corrections :
     *   1. la valeur par défaut devient l'instant courant, pour qu'un oubli
     *      d'écriture donne au pire la bonne date plutôt qu'une date fausse ;
     *   2. les lignes déjà écrites avec la date figée sont réalignées sur leur
     *      created_at, qui est l'instant réel de création.
     *
     * Le correctif applicatif (ClientController) renseigne désormais le champ
     * explicitement : cette valeur par défaut n'est plus qu'un filet.
     */
    public function up(): void
    {
        // 1. Réparation des données. On ne touche QUE les lignes portant très
        //    exactement l'horodatage figé : ce sont les seules dont on peut
        //    affirmer qu'elles n'ont jamais été renseignées.
        $reparees = DB::table('commande')
            ->where('date_commande', '2026-04-13 12:32:21')
            ->whereNotNull('created_at')
            ->update(['date_commande' => DB::raw('created_at')]);

        // 2. Valeur par défaut : l'instant courant.
        DB::statement(
            'ALTER TABLE `commande` MODIFY `date_commande` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
        );

        if ($reparees > 0) {
            echo "  {$reparees} commande(s) redatée(s) sur leur date de création réelle." . PHP_EOL;
        }
    }

    public function down(): void
    {
        // On ne restaure PAS l'horodatage figé : c'était le défaut lui-même.
        // La colonne redevient simplement obligatoire, sans valeur par défaut.
        DB::statement('ALTER TABLE `commande` MODIFY `date_commande` DATETIME NOT NULL');
    }
};
