<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une réduction de commande n'était rattachée qu'au DEVIS.
     *
     * L'écran d'initialisation est accessible pour n'importe quelle commande
     * (/reduction-{commande}), mais une commande née d'une vente comptant ou de
     * l'application mobile n'a pas de devis : la réduction était alors créée
     * avec devis_id = NULL, donc orpheline. L'écran de confirmation, qui la
     * cherche par $commande->devis->reduction, ne la retrouvait jamais et
     * annonçait « Pas de demande de réduction ». La réduction ne pouvait donc
     * jamais être appliquée sur ces commandes — la majorité d'entre elles.
     *
     * On rattache désormais la réduction directement à la commande. Le lien par
     * le devis reste en place pour les lignes déjà écrites.
     */
    public function up(): void
    {
        if (Schema::hasColumn('reduction', 'commande_id')) {
            return;
        }

        Schema::table('reduction', function (Blueprint $table) {
            $table->unsignedBigInteger('commande_id')->nullable()->after('devis_id')->index();
        });

        // Reprise des réductions existantes : celles qui passaient par un devis
        // pointent maintenant aussi sur la commande correspondante.
        $reprises = DB::table('reduction')
            ->join('devis', 'devis.id', '=', 'reduction.devis_id')
            ->join('commande', 'commande.devis_id', '=', 'devis.id')
            ->whereNull('reduction.commande_id')
            ->update(['reduction.commande_id' => DB::raw('commande.id')]);

        if ($reprises > 0) {
            echo "  {$reprises} réduction(s) rattachée(s) à leur commande." . PHP_EOL;
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('reduction', 'commande_id')) {
            return;
        }

        Schema::table('reduction', function (Blueprint $table) {
            $table->dropIndex(['commande_id']);
            $table->dropColumn('commande_id');
        });
    }
};
