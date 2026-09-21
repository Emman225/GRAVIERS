<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE NUMÉRO DE BON DE COMMANDE INTERNE, FIGÉ SUR LE DEVIS (09/09/2026).
 *
 * Une commande porte son bon (table bl_client) ; un devis n'avait aucune place
 * pour le numéro saisi par l'entreprise, qui ne pouvait donc pas figurer sur le
 * document. Le devis le garde désormais, et le reporte devant chaque
 * désignation, comme la proforma et la facture.
 *
 * Colonne facultative : poser cette migration ne change rien aux devis existants.
 * Le site et l'API partagent la base : chacun porte la même migration, gardée
 * par un contrôle d'existence, la seconde ne fait rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('devis', 'numero_bon_commande')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->string('numero_bon_commande', 255)->nullable()->after('tva_transport');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devis', 'numero_bon_commande')) {
            Schema::table('devis', function (Blueprint $table) {
                $table->dropColumn('numero_bon_commande');
            });
        }
    }
};
