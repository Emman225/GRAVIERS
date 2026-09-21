<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA TVA SUR LE TRANSPORT, AU CHOIX.
 *
 * Le site ajoutait 18 % au coût du transport, l'application mobile non : la
 * même course coûtait 23 600 F depuis le site et 20 000 F depuis le téléphone.
 * L'arbitrage du 13/08/2026 avait tranché « pas de TVA sur le transport », et
 * le montant a été forcé à zéro — en gardant la mécanique intacte pour le jour
 * où le régime changerait.
 *
 * Ce jour est venu : la TVA redevient possible, mais sur DÉCISION. Par défaut
 * elle reste désactivée, c'est-à-dire exactement le comportement d'aujourd'hui —
 * poser cette migration ne change aucun prix.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('configuration', 'tva_transport')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->boolean('tva_transport')->default(0)->after('tva');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configuration', 'tva_transport')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->dropColumn('tva_transport');
            });
        }
    }
};
