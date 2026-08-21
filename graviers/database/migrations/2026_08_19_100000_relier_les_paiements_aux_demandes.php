<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une demande de paiement validée doit solder les bons du fournisseur.
 *
 * L'entreprise paie ses fournisseurs par deux chemins :
 *   - « Dettes fournisseurs » : un règlement portant sur un bon précis, qui
 *     crée une ligne `paiement_fournisseur` ;
 *   - « Demandes de paiement » : le fournisseur demande un montant, deux
 *     administrateurs valident, et il est payé.
 *
 * Le second n'écrivait RIEN dans `paiement_fournisseur`. Les bons restaient
 * donc entièrement dus après avoir été payés : le popup « Enregistrer un
 * paiement fournisseur » proposait encore la totalité, et un administrateur
 * pouvait payer une deuxième fois ce qui l'avait déjà été.
 *
 * Le paiement issu d'une demande crée désormais ses lignes, réparties sur les
 * bons non soldés du plus ancien au plus récent. Cette colonne les relie à leur
 * demande — c'est ce qui permet à Fournisseur::soldeCalcule() de ne pas
 * retrancher deux fois le même montant (une fois comme demande, une fois comme
 * règlement).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('paiement_fournisseur', 'demande_paiement_id')) {
            DB::statement(
                'ALTER TABLE `paiement_fournisseur`
                 ADD COLUMN `demande_paiement_id` BIGINT UNSIGNED NULL AFTER `enlevement_id`'
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paiement_fournisseur', 'demande_paiement_id')) {
            DB::statement('ALTER TABLE `paiement_fournisseur` DROP COLUMN `demande_paiement_id`');
        }
    }
};
