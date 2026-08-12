<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnés à la lettre d'information.
 *
 * Le formulaire du pied de page n'était relié à rien : il n'avait ni action ni
 * nom de champ, et le visiteur qui s'inscrivait rechargeait simplement la page.
 * Aucune adresse n'était donc conservée.
 *
 * L'adresse est unique : une seconde inscription réactive la ligne existante
 * plutôt que d'en créer une deuxième.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->unique();
            // 1 = abonné, 0 = désabonné. Un désabonnement ne supprime pas la
            // ligne : on garde la trace pour ne pas réinscrire par mégarde.
            $table->tinyInteger('statut')->default(1);
            // D'où vient l'inscription (pied de page du site, plus tard une
            // autre origine) : utile pour mesurer ce qui recrute.
            $table->string('origine', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter');
    }
};
