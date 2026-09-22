<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables », phase 2 : le moteur d'écritures (lot 117, 21/09/2026).
 *
 * Une écriture par facture normalisée (ou par avoir), ses lignes, et les
 * anomalies qui l'empêchent d'être exportée. Les numéros de comptes sont
 * RECOPIÉS sur chaque ligne : une écriture doit rester lisible telle qu'elle a
 * été produite, même si le paramétrage change ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ecriture_comptable')) {
            Schema::create('ecriture_comptable', function (Blueprint $table) {
                $table->id();
                $table->string('identifiant', 40)->unique();      // stable : sert à l'API et évite les doublons
                $table->string('origine', 20);                     // FACTURE | AVOIR | ANNULATION (| ENCAISSEMENT | DECAISSEMENT, phase 2b)
                $table->string('source_type', 30)->nullable();     // facture (| paiement…, phase 2b)
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedSmallInteger('version')->default(1);
                $table->unsignedBigInteger('annulation_de_id')->nullable();   // l'écriture que celle-ci contre-passe
                $table->unsignedBigInteger('annulee_par_id')->nullable();     // l'écriture qui contre-passe celle-ci
                $table->unsignedBigInteger('journal_comptable_id')->nullable();
                $table->string('journal_code', 6)->nullable();
                $table->date('date_ecriture');
                $table->string('piece', 40);
                $table->string('reference_fne', 60)->nullable();
                $table->string('libelle', 190);
                $table->string('service', 20)->nullable();         // COMMANDE | LOCATION | LIVRAISON
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('numero_affaire', 40)->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_credit', 15, 2)->default(0);
                $table->string('etat', 15)->default('A_EXPORTER'); // A_EXPORTER | EXPORTEE | ANOMALIE
                $table->dateTime('exportee_le')->nullable();
                $table->unsignedBigInteger('deversement_id')->nullable();     // phase 3
                $table->timestamps();
                $table->index(['source_type', 'source_id']);
                $table->index(['date_ecriture', 'etat']);
                $table->index('journal_comptable_id');
            });
        }

        if (!Schema::hasTable('ligne_ecriture_comptable')) {
            Schema::create('ligne_ecriture_comptable', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ecriture_comptable_id');
                $table->unsignedSmallInteger('rang');
                $table->string('rubrique', 20);                    // CLIENT | PRODUIT | TRANSPORT | REMISE | TVA | AIRSI
                $table->unsignedBigInteger('compte_comptable_id')->nullable();
                $table->string('numero_compte', 20)->nullable();
                $table->string('compte_tiers', 20)->nullable();
                $table->unsignedBigInteger('compte_analytique_id')->nullable();
                $table->string('numero_analytique', 20)->nullable();
                $table->unsignedBigInteger('categorie_id')->nullable();       // grande famille, pour les rapports
                $table->unsignedBigInteger('produit_id')->nullable();
                $table->string('libelle', 190);
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->timestamps();
                $table->index('ecriture_comptable_id');
                $table->index('compte_comptable_id');
                $table->index('compte_analytique_id');
                $table->index('categorie_id');
                $table->index('compte_tiers');
            });
        }

        if (!Schema::hasTable('anomalie_comptable')) {
            Schema::create('anomalie_comptable', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ecriture_comptable_id');
                $table->unsignedBigInteger('facture_id')->nullable();
                $table->unsignedSmallInteger('rang_ligne')->nullable();
                $table->string('code', 40);
                $table->string('objet', 190);
                $table->string('colonne', 60);
                $table->string('cause', 255);
                $table->string('onglet', 20)->nullable();          // l'onglet du paramétrage où corriger
                $table->dateTime('resolue_le')->nullable();
                $table->timestamps();
                $table->index(['ecriture_comptable_id', 'resolue_le']);
                $table->index('facture_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalie_comptable');
        Schema::dropIfExists('ligne_ecriture_comptable');
        Schema::dropIfExists('ecriture_comptable');
    }
};
