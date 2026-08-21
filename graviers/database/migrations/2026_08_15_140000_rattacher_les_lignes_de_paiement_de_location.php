<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les règlements de location saisis depuis la fiche de la location
     * (PaiementController::paiementLocationTraitement) créaient une ligne de
     * paiement SANS service ni service_id, alors que le paiement parent, lui,
     * les portait.
     *
     * Conséquence : Location::montantPayeComptant(), qui somme les lignes sur
     * service = LOCATION, ne voyait aucun de ces règlements. Le nouveau guichet
     * des encaissements aurait affiché ces locations comme intégralement dues et
     * permis de les encaisser une seconde fois.
     *
     * Le correctif renseigne désormais ces deux colonnes à l'écriture ; cette
     * migration répare les lignes déjà en base, en les rattachant au service de
     * leur paiement parent.
     */
    public function up(): void
    {
        $reprises = DB::table('ligne_paiement')
            ->join('paiement', 'paiement.id', '=', 'ligne_paiement.paiement_id')
            ->where('paiement.service', 'LOCATION')
            ->whereNotNull('paiement.service_id')
            ->whereNull('ligne_paiement.service')
            ->update([
                'ligne_paiement.service'    => 'LOCATION',
                'ligne_paiement.service_id' => DB::raw('paiement.service_id'),
            ]);

        if ($reprises > 0) {
            echo "  {$reprises} ligne(s) de paiement rattachée(s) à leur location." . PHP_EOL;
        }
    }

    public function down(): void
    {
        // Rien : remettre ces colonnes à NULL rendrait de nouveau les règlements
        // invisibles pour la location. La reprise est sans perte.
    }
};
