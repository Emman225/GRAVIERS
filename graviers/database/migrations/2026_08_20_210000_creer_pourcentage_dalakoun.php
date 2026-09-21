<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE POURCENTAGE DALAKOUN — la marge que l'entreprise ajoute au prix d'achat.
 *
 * Jusqu'ici, le prix montré au client était le prix du fournisseur lui-même :
 * l'écran d'ajout de produit recopie « Prix fournisseur » dans la ligne de
 * stock, et c'est cette ligne que le catalogue lit comme prix de vente. Tout
 * produit créé par cet écran était donc vendu à son prix d'achat.
 *
 * Ce taux est la pièce manquante. Il n'entre en vigueur qu'après une DOUBLE
 * VALIDATION — la même règle que les règlements : celui qui saisit ne valide
 * pas, et le second doit être administrateur. Un tarif touche tout le
 * catalogue ; il ne se change pas seul dans son coin.
 *
 * Chaque taux est conservé, jamais écrasé : on doit pouvoir dire quel
 * pourcentage s'appliquait le jour d'une vente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pourcentage_dalakoun', function (Blueprint $table) {
            $table->id();

            // Le taux, en pourcentage : 20 signifie « +20 % sur le prix d'achat ».
            $table->double('taux');

            $table->string('motif', 255)->nullable();

            // Qui a saisi, qui a validé — mêmes colonnes que les tables de
            // règlement, pour que l'affichage des validations soit identique.
            $table->unsignedBigInteger('user_valide_id')->nullable();
            $table->unsignedBigInteger('user_valide2_id')->nullable();
            $table->timestamp('date_validation_1')->nullable();
            $table->timestamp('date_validation_2')->nullable();

            // 2 = saisi, en attente de la seconde validation
            // 1 = validé, c'est le taux qui s'applique
            // 0 = refusé
            $table->smallInteger('statut')->default(2);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['statut', 'date_validation_2']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pourcentage_dalakoun');
    }
};
