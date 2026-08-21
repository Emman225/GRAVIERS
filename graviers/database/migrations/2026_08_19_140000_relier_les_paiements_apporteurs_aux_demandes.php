<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une demande de paiement d'apporteur validée doit solder ses commissions.
 *
 * Troisième et dernier des trois tiers. L'apporteur est payé par deux chemins,
 * comme le fournisseur et le livreur :
 *   - la demande qu'il initie lui-même, validée par deux administrateurs ;
 *   - le règlement qu'un administrateur saisit sur une de ses commissions.
 *
 * Le premier n'écrivait rien dans `paiement_apporteur` : ses commissions
 * restaient donc entièrement dues après avoir été payées. Le formulaire
 * « Enregistrer un paiement de commission » proposait encore la totalité, et un
 * administrateur pouvait régler une seconde fois ce qui l'était déjà.
 *
 * Cette colonne relie les règlements issus d'une demande à leur demande — c'est
 * ce qui permet à Apporteur::soldeCalcule() de ne pas retrancher deux fois le
 * même montant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paiement_apporteur', 'demande_paiement_id')) {
            DB::statement(
                'ALTER TABLE `paiement_apporteur`
                 ADD COLUMN `demande_paiement_id` BIGINT UNSIGNED NULL AFTER `commission_id`'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paiement_apporteur', 'demande_paiement_id')) {
            DB::statement('ALTER TABLE `paiement_apporteur` DROP COLUMN `demande_paiement_id`');
        }
    }
};
