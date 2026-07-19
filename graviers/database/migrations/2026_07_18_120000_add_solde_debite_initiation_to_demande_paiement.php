<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fin du DOUBLE DÉBIT du solde des livreurs à la validation d'une demande de paiement.
 *
 * Les points de création d'une DemandePaiement n'ont pas la même convention :
 *  - mobile livreur, web (livreur/apporteur/fournisseur), portail fournisseur :
 *    le solde est DÉBITÉ à l'initiation (réservation du montant demandé) ;
 *  - mobile apporteur et reglerDette (admin) : le solde N'EST PAS débité.
 *
 * Or valideDemande (2e validation) devait deviner ce passé avec une heuristique
 * (« initiateur admin => règlement de dette => débiter ») fausse : le 1er
 * validateur d'une demande mobile est TOUJOURS un admin, donc chaque demande
 * mobile acceptée était débitée UNE SECONDE FOIS. Cas réel : livreur crédité
 * 4000 (2 livraisons), demande de 2000 -> solde 2000 à l'initiation, puis
 * re-débit à la validation -> solde 0 au lieu de 2000.
 * Symétriquement, un REFUS restituait le montant sans condition : refuser un
 * règlement reglerDette (jamais débité) aurait CRÉDITÉ le tiers à tort.
 *
 * On remplace l'heuristique par un fait enregistré à la création :
 * solde_debite_initiation = 1 si le flux a débité le solde, 0 sinon.
 * NULL (demandes antérieures à cette migration) : repli sur numero IS NULL
 * (seul reglerDette génère un numero).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('demande_paiement', 'solde_debite_initiation')) {
            Schema::table('demande_paiement', function (Blueprint $table) {
                $table->boolean('solde_debite_initiation')->nullable()->after('paye');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('demande_paiement', 'solde_debite_initiation')) {
            Schema::table('demande_paiement', function (Blueprint $table) {
                $table->dropColumn('solde_debite_initiation');
            });
        }
    }
};
