<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une demande de paiement livreur validée doit solder ses courses.
 *
 * Le livreur est payé par deux chemins, exactement comme le fournisseur :
 *   - la demande qu'il initie depuis l'application mobile, validée par deux
 *     administrateurs ;
 *   - le règlement qu'un administrateur saisit sur une de ses courses.
 *
 * Le second avait été DÉSACTIVÉ pour empêcher un double paiement : la demande
 * validée n'écrivant rien dans `paiement_livreur`, les courses restaient dues
 * après avoir été payées, et rien n'empêchait de les régler une seconde fois.
 *
 * On traite la cause plutôt que le symptôme : la demande validée crée
 * désormais ses lignes de règlement, réparties sur les courses non soldées.
 * Cette colonne les relie à leur demande — c'est ce qui permet à
 * Livreur::soldeCalcule() de ne pas retrancher deux fois le même montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paiement_livreur', 'demande_paiement_id')) {
            DB::statement(
                'ALTER TABLE `paiement_livreur`
                 ADD COLUMN `demande_paiement_id` BIGINT UNSIGNED NULL AFTER `livraison_id`'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paiement_livreur', 'demande_paiement_id')) {
            DB::statement('ALTER TABLE `paiement_livreur` DROP COLUMN `demande_paiement_id`');
        }
    }
};
