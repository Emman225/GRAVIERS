<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES AVANCES DES CLIENTS (point 19 du cahier du 07/09/2026).
 *
 * Un client dépose au guichet une somme SANS commande ; ses commandes
 * suivantes réglées « en agence » s'en déduisent d'elles-mêmes. Réponses du
 * client aux six questions posées :
 *
 *   Q1  le reliquat non couvert reste dû au guichet, comme aujourd'hui ;
 *   Q2  pour un client à terme, le reliquat suit son crédit et son plafond ;
 *       un client ordinaire doit régler le reliquat avant tout traitement ;
 *   Q3  un client qui a des affaires non soldées ne dépose pas d'avance :
 *       il règle d'abord, et c'est le SURPLUS versé qui devient une avance ;
 *   Q4  une avance ne se rembourse pas, elle s'utilise — et le client en
 *       est informé (mention sur le reçu, courriel) ;
 *   Q5  seul le « Paiement en agence » la consomme ;
 *   Q6  le dépôt se fait au guichet, avec la double validation.
 *
 * Deux tables, aucune ligne dans `paiement` pour le dépôt lui-même : la
 * colonne `paiement.service` est une énumération fermée (COMMANDE, LOCATION,
 * LIVRAISON), et un dépôt qui y figurerait serait compté deux fois — au dépôt,
 * puis à l'imputation, qui crée le VRAI règlement de la commande.
 *
 *   avance_client     un dépôt : montant, part consommée, double validation,
 *                     numéro de reçu RA-AAAA-NNN, agence et caissier.
 *   mouvement_avance  l'historique demandé : DEPOT à la validation, DEDUCTION
 *                     à chaque imputation, avec la commande et le règlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('avance_client')) {
            Schema::create('avance_client', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->index();
                $table->double('montant')->default(0);
                $table->double('montant_consomme')->default(0);
                // 2 = en attente de la seconde validation, 1 = disponible,
                // 0 = annulée (jamais consommable).
                $table->smallInteger('statut')->default(2);
                $table->string('numero_recu', 50)->nullable()->index();
                $table->unsignedBigInteger('mode_paiement_id')->nullable();
                $table->string('moyen_paiement', 100)->nullable();
                $table->string('reference', 80)->nullable();
                $table->unsignedBigInteger('agence_id')->nullable();
                $table->unsignedBigInteger('caissier_id')->nullable();
                $table->string('libelle', 500)->nullable();
                // Origine : DEPOT (saisi tel quel) ou SURPLUS (excédent d'un
                // encaissement), avec le reçu de cet encaissement.
                $table->string('origine', 20)->default('DEPOT');
                $table->string('origine_recu', 50)->nullable();
                $table->dateTime('date_depot')->nullable();
                // Double validation, mêmes colonnes que `paiement`.
                $table->unsignedBigInteger('user_valide_id')->nullable();
                $table->unsignedBigInteger('user_valide2_id')->nullable();
                $table->dateTime('date_validation_1')->nullable();
                $table->dateTime('date_validation_2')->nullable();
                $table->dateTime('recu_envoye_le')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('mouvement_avance')) {
            Schema::create('mouvement_avance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('avance_client_id')->index();
                $table->unsignedBigInteger('client_id')->index();
                // DEPOT ou DEDUCTION.
                $table->string('type', 20);
                $table->double('montant')->default(0);
                $table->unsignedBigInteger('commande_id')->nullable()->index();
                $table->unsignedBigInteger('paiement_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('libelle', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvement_avance');
        Schema::dropIfExists('avance_client');
    }
};
