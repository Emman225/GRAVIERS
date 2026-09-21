<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES DÉCISIONS DE CRÉDIT, SOUS DOUBLE VALIDATION.
 *
 * Accorder le statut de client à terme, le retirer ou relever un plafond, c'est
 * décider combien l'entreprise accepte de ne pas être payée tout de suite. Ces
 * trois décisions se prenaient seul, d'un clic, et s'appliquaient aussitôt.
 *
 * Elles passent désormais par la règle déjà en vigueur sur les règlements et
 * sur le pourcentage DALAKOUN : celui qui saisit ne valide pas, et le second
 * doit être administrateur.
 *
 * Une seule table pour les trois : ce sont les mêmes acteurs, le même contrôle
 * et le même écran de suivi. En faire trois obligerait à corriger trois fois la
 * moindre évolution de la règle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_client_terme', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('client_id');
            // Renseigné quand la décision naît d'une demande du client ;
            // vide pour une révision de plafond décidée en interne.
            $table->unsignedBigInteger('demande_id')->nullable();

            // activation | desactivation | plafond | refus_demande
            $table->string('type', 20);

            // Ce que la décision appliquera si elle est validée.
            $table->double('plafond_credit')->nullable();
            $table->integer('delai_paiement')->nullable();
            $table->text('commentaire')->nullable();

            // L'état du client AVANT, figé au moment de la saisie : c'est ce qui
            // permet de dire, six mois plus tard, ce que la décision a changé.
            $table->double('ancien_plafond')->nullable();
            $table->integer('ancien_delai')->nullable();

            // Mêmes colonnes que les autres tables à double validation, pour que
            // l'affichage des validations soit identique partout.
            $table->unsignedBigInteger('user_valide_id')->nullable();
            $table->unsignedBigInteger('user_valide2_id')->nullable();
            $table->timestamp('date_validation_1')->nullable();
            $table->timestamp('date_validation_2')->nullable();

            // 2 = en attente de la seconde validation, 1 = appliquée, 0 = refusée
            $table->smallInteger('statut')->default(2);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['client_id', 'statut']);
            $table->index(['statut', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_client_terme');
    }
};
