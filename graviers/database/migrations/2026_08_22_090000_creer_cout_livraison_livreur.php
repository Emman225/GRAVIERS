<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA GRILLE DE FACTURATION DU LIVREUR.
 *
 * Le client paie le transport sur une grille de 96 tranches — unité, quantité,
 * distance. Le livreur, lui, était payé sur un réglage unique : un forfait, ou
 * un prix au kilomètre. Deux structures incompatibles, d'où des marges qui
 * partaient de 37 % à 79 % sans qu'aucune décision ne l'ait voulu, et un
 * livreur payé 2 500 F pour 80 km comme pour 10.
 *
 * Cette table donne au livreur la MÊME structure qu'au client. La marge devient
 * alors la différence entre deux grilles que l'entreprise fixe toutes les deux :
 * elle se décide au lieu de se subir.
 *
 * Le tarif d'une tranche couvre TOUT le chargement, sans multiplication par le
 * nombre de rotations : la tranche est déjà indexée sur la quantité — celle de
 * 20-60 t vaut trois fois celle de 0-20 t — et multiplier en plus compterait
 * deux fois le volume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cout_livraison_livreur', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('livreur_id');
            $table->unsignedBigInteger('unite_produit_id');

            // NULL = tarif valable partout, comme dans la grille client.
            $table->unsignedBigInteger('ville_id')->nullable();

            $table->double('unite_min')->default(0);
            $table->double('unite_max')->default(0);
            $table->double('distance_min_km')->default(0);
            $table->double('distance_max_km')->default(0);

            // Ce que DALAKOUN verse au livreur pour cette tranche.
            $table->double('prix')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['livreur_id', 'unite_produit_id']);
        });

        // La part revenant au livreur, propre à chacun : un transporteur qui
        // possède un gros camion accepte souvent moins par tonne. Elle sert à
        // pré-remplir sa grille depuis celle du client, et à afficher la marge
        // attendue tant qu'aucune tranche n'est saisie.
        if (!Schema::hasColumn('livreur', 'part_grille')) {
            Schema::table('livreur', function (Blueprint $table) {
                $table->double('part_grille')->nullable()->after('tarif_km');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cout_livraison_livreur');

        if (Schema::hasColumn('livreur', 'part_grille')) {
            Schema::table('livreur', function (Blueprint $table) {
                $table->dropColumn('part_grille');
            });
        }
    }
};
