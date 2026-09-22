<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module « Écritures comptables », phase 1 : le paramétrage (lot 116, 21/09/2026).
 *
 * Le compte général est porté par la grande famille (categorie), le compte
 * analytique par le produit ; les rubriques d'une facture hors produits
 * (clients, TVA, AIRSI, transport, remises…) ont chacune leur compte, les
 * modes de règlement leur journal de trésorerie, les clients et les
 * fournisseurs leur compte tiers. Aucune contrainte de clé étrangère : un
 * compte ne se supprime pas tant qu'il est employé, la règle est dans le code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compte_comptable')) {
            Schema::create('compte_comptable', function (Blueprint $table) {
                $table->id();
                $table->string('nature', 12);           // GENERAL | ANALYTIQUE
                $table->string('numero', 20);
                $table->string('libelle', 150);
                $table->unsignedTinyInteger('statut')->default(1);
                $table->softDeletes();
                $table->timestamps();
                $table->index(['nature', 'numero']);
            });
        }

        if (!Schema::hasTable('journal_comptable')) {
            Schema::create('journal_comptable', function (Blueprint $table) {
                $table->id();
                $table->string('code', 6);
                $table->string('libelle', 100);
                $table->string('type', 20);             // VENTES | BANQUE | CAISSE | MOBILE_MONEY | DIVERS
                $table->unsignedBigInteger('compte_comptable_id')->nullable();   // compte de trésorerie
                $table->unsignedTinyInteger('statut')->default(1);
                $table->softDeletes();
                $table->timestamps();
                $table->index('code');
            });

            $maintenant = now();
            foreach ([
                ['VT', 'Journal des ventes', 'VENTES'],
                ['BQ', 'Journal de banque', 'BANQUE'],
                ['CA', 'Journal de caisse', 'CAISSE'],
                ['MM', 'Journal Mobile Money', 'MOBILE_MONEY'],
            ] as [$code, $libelle, $type]) {
                DB::table('journal_comptable')->insert([
                    'code' => $code, 'libelle' => $libelle, 'type' => $type, 'statut' => 1,
                    'created_at' => $maintenant, 'updated_at' => $maintenant,
                ]);
            }
        }

        if (!Schema::hasTable('rubrique_comptable')) {
            Schema::create('rubrique_comptable', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->unsignedBigInteger('compte_comptable_id')->nullable();
                $table->unsignedBigInteger('compte_analytique_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('historique_parametrage_comptable')) {
            Schema::create('historique_parametrage_comptable', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('objet', 40);
                $table->unsignedBigInteger('objet_id')->nullable();
                $table->string('designation', 190);
                $table->string('action', 20);           // CREATION | MODIFICATION | SUPPRESSION | ACTIVATION | DESACTIVATION
                $table->text('avant')->nullable();
                $table->text('apres')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['objet', 'objet_id']);
            });
        }

        $colonnes = [
            'categorie'     => ['compte_comptable_id'],
            'produit'       => ['categorie_comptable_id', 'compte_analytique_id'],
            'mode_paiement' => ['journal_comptable_id'],
        ];
        foreach ($colonnes as $nomTable => $noms) {
            foreach ($noms as $nom) {
                if (Schema::hasTable($nomTable) && !Schema::hasColumn($nomTable, $nom)) {
                    Schema::table($nomTable, function (Blueprint $table) use ($nom) {
                        $table->unsignedBigInteger($nom)->nullable()->index();
                    });
                }
            }
        }

        foreach (['client', 'fournisseur'] as $nomTable) {
            if (Schema::hasTable($nomTable) && !Schema::hasColumn($nomTable, 'compte_tiers')) {
                Schema::table($nomTable, function (Blueprint $table) {
                    $table->string('compte_tiers', 20)->nullable()->index();
                });
            }
        }

        if (!Schema::hasColumn('configuration', 'longueur_compte_comptable')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->unsignedTinyInteger('longueur_compte_comptable')->default(6);
            });
        }
        if (!Schema::hasColumn('configuration', 'format_libelle_ecriture')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->string('format_libelle_ecriture', 190)->nullable();
            });
        }
        if (!Schema::hasColumn('configuration', 'periode_transmission_comptable')) {
            Schema::table('configuration', function (Blueprint $table) {
                $table->string('periode_transmission_comptable', 10)->nullable();   // DATES | MOIS, dernier choix
            });
        }
    }

    public function down(): void
    {
        foreach (['longueur_compte_comptable', 'format_libelle_ecriture', 'periode_transmission_comptable'] as $nom) {
            if (Schema::hasColumn('configuration', $nom)) {
                Schema::table('configuration', function (Blueprint $table) use ($nom) {
                    $table->dropColumn($nom);
                });
            }
        }

        $colonnes = [
            'client'        => ['compte_tiers'],
            'fournisseur'   => ['compte_tiers'],
            'mode_paiement' => ['journal_comptable_id'],
            'produit'       => ['categorie_comptable_id', 'compte_analytique_id'],
            'categorie'     => ['compte_comptable_id'],
        ];
        foreach ($colonnes as $nomTable => $noms) {
            foreach ($noms as $nom) {
                if (Schema::hasTable($nomTable) && Schema::hasColumn($nomTable, $nom)) {
                    Schema::table($nomTable, function (Blueprint $table) use ($nomTable, $nom) {
                        $table->dropIndex($nomTable . '_' . $nom . '_index');
                        $table->dropColumn($nom);
                    });
                }
            }
        }

        Schema::dropIfExists('historique_parametrage_comptable');
        Schema::dropIfExists('rubrique_comptable');
        Schema::dropIfExists('journal_comptable');
        Schema::dropIfExists('compte_comptable');
    }
};
