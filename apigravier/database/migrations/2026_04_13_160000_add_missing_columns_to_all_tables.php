<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cette migration ajoute les colonnes qui existaient en base de donnees
 * mais qui n'avaient jamais eu de fichier de migration correspondant.
 *
 * Colonnes concernees :
 * - apporteur: piece_recto, piece_verso
 * - commande: type_livraison, cout_livraison_client, cout_reduction, fichier_bl, numero_bl
 * - fournisseur: contact, adresse
 * - livreur: cout_livraison
 * - demande_paiement: user_valide2_id
 */
return new class extends Migration
{
    public function up(): void
    {
        // Apporteur: colonnes pieces d'identite
        Schema::table('apporteur', function (Blueprint $table) {
            if (!Schema::hasColumn('apporteur', 'piece_recto')) {
                $table->string('piece_recto', 255)->nullable()->after('pourcentage');
            }
            if (!Schema::hasColumn('apporteur', 'piece_verso')) {
                $table->string('piece_verso', 255)->nullable()->after('piece_recto');
            }
        });

        // Commande: colonnes livraison et reduction
        Schema::table('commande', function (Blueprint $table) {
            if (!Schema::hasColumn('commande', 'type_livraison')) {
                $table->string('type_livraison', 50)->nullable()->after('type_livraison_id');
            }
            if (!Schema::hasColumn('commande', 'cout_livraison_client')) {
                $table->double('cout_livraison_client')->default(0)->after('remise');
            }
            if (!Schema::hasColumn('commande', 'cout_reduction')) {
                $table->double('cout_reduction')->default(0)->after('cout_livraison_client');
            }
            if (!Schema::hasColumn('commande', 'fichier_bl')) {
                $table->string('fichier_bl', 255)->nullable()->after('cout_reduction');
            }
            if (!Schema::hasColumn('commande', 'numero_bl')) {
                $table->string('numero_bl', 50)->nullable()->after('fichier_bl');
            }
        });

        // Fournisseur: colonnes contact et adresse supplementaires
        Schema::table('fournisseur', function (Blueprint $table) {
            if (!Schema::hasColumn('fournisseur', 'nom')) {
                $table->string('nom', 100)->nullable()->after('nom_prenoms');
            }
            if (!Schema::hasColumn('fournisseur', 'prenom')) {
                $table->string('prenom', 100)->nullable()->after('nom');
            }
            if (!Schema::hasColumn('fournisseur', 'contact')) {
                $table->string('contact', 20)->nullable()->after('contact2');
            }
            if (!Schema::hasColumn('fournisseur', 'adresse')) {
                $table->string('adresse', 200)->nullable()->after('adresse_postale');
            }
        });

        // Livreur: colonne cout_livraison
        Schema::table('livreur', function (Blueprint $table) {
            if (!Schema::hasColumn('livreur', 'cout_livraison')) {
                $table->double('cout_livraison')->nullable()->after('solde');
            }
        });

        // Demande paiement: numero_compte
        Schema::table('demande_paiement', function (Blueprint $table) {
            if (!Schema::hasColumn('demande_paiement', 'numero_compte')) {
                $table->string('numero_compte', 50)->nullable()->after('user_valide2_id');
            }
        });

        // Detail commande: prix_fournisseur, reference
        Schema::table('detail_commande', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_commande', 'prix_fournisseur')) {
                $table->double('prix_fournisseur')->default(0)->after('prix');
            }
            if (!Schema::hasColumn('detail_commande', 'reference')) {
                $table->string('reference', 50)->nullable()->after('qte_livree');
            }
        });

        // Detail devis: prix_fournisseur
        Schema::table('detail_devis', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_devis', 'prix_fournisseur')) {
                $table->double('prix_fournisseur')->default(0)->after('prix');
            }
        });

        // Detail livraison: poids_vehicule_souhaite, nombre_voyage
        Schema::table('detail_livraison', function (Blueprint $table) {
            if (!Schema::hasColumn('detail_livraison', 'poids_vehicule_souhaite')) {
                $table->double('poids_vehicule_souhaite')->default(0)->after('unite_produit_id');
            }
            if (!Schema::hasColumn('detail_livraison', 'nombre_voyage')) {
                $table->integer('nombre_voyage')->default(1)->after('poids_vehicule_souhaite');
            }
        });

        // Livraison: code_livraison
        Schema::table('livraison', function (Blueprint $table) {
            if (!Schema::hasColumn('livraison', 'code_livraison')) {
                $table->string('code_livraison', 20)->nullable()->after('date_affectation_livreur');
            }
        });

        // Enlevement: code_enlevement
        Schema::table('enlevement', function (Blueprint $table) {
            if (!Schema::hasColumn('enlevement', 'code_enlevement')) {
                $table->string('code_enlevement', 20)->nullable()->after('gestionnaire_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('apporteur', function (Blueprint $table) {
            $table->dropColumn(['piece_recto', 'piece_verso']);
        });
        Schema::table('commande', function (Blueprint $table) {
            $table->dropColumn(['type_livraison', 'cout_livraison_client', 'cout_reduction', 'fichier_bl', 'numero_bl']);
        });
        Schema::table('fournisseur', function (Blueprint $table) {
            $table->dropColumn(['nom', 'prenom', 'contact', 'adresse']);
        });
        Schema::table('livreur', function (Blueprint $table) {
            $table->dropColumn(['cout_livraison']);
        });
        Schema::table('demande_paiement', function (Blueprint $table) {
            $table->dropColumn(['numero_compte']);
        });
        Schema::table('detail_commande', function (Blueprint $table) {
            $table->dropColumn(['prix_fournisseur', 'reference']);
        });
        Schema::table('detail_devis', function (Blueprint $table) {
            $table->dropColumn(['prix_fournisseur']);
        });
        Schema::table('detail_livraison', function (Blueprint $table) {
            $table->dropColumn(['poids_vehicule_souhaite', 'nombre_voyage']);
        });
        Schema::table('livraison', function (Blueprint $table) {
            $table->dropColumn(['code_livraison']);
        });
        Schema::table('enlevement', function (Blueprint $table) {
            $table->dropColumn(['code_enlevement']);
        });
    }
};
