<?php

namespace Tests\Feature;

use App\Models\DemandePaiement;
use App\Models\Fournisseur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le débit du solde et l'enregistrement de la demande vont ensemble.
 *
 * Le solde était débité d'abord, la demande créée ensuite, sans transaction.
 * Quand la création échouait — une colonne absente en base a suffi à le
 * provoquer en production —, le tiers repartait avec un solde amputé et
 * aucune demande en face : l'argent disparaissait de son tableau de bord
 * sans que personne ne puisse le lui verser, et la page restait blanche.
 */
class DemandePaiementAtomiqueTest extends TestCase
{
    public function test_le_solde_reste_intact_quand_l_enregistrement_echoue(): void
    {
        // Le test retire une colonne : il ne doit tourner que sur la base de
        // développement, et la remettre quoi qu'il arrive.
        if (DB::connection()->getDatabaseName() !== 'gravier') {
            $this->markTestSkipped('Réservé à la base de développement.');
        }

        $fournisseur = Fournisseur::whereNotNull('user_id')->whereHas('user')->first();

        if (!$fournisseur) {
            $this->markTestSkipped('Aucun fournisseur rattaché à un compte.');
        }

        $soldeMemo   = $fournisseur->solde;
        $demandesAvant = DemandePaiement::where('user_id', $fournisseur->user_id)->count();

        $fournisseur->update(['solde' => 50000]);

        // On rejoue la panne : la colonne manque, l'insertion est impossible.
        DB::statement('ALTER TABLE demande_paiement DROP COLUMN solde_debite_initiation');

        try {
            URL::forceRootUrl('');

            $reponse = $this->actingAs($fournisseur->user)->post('/demande-de-paiements', [
                'montant'  => 12000,
                'numero'   => '0102030405',
                'modePaie' => 6,
            ]);

            // Plus de page blanche : l'utilisateur est renvoyé avec un message.
            $this->assertSame(302, $reponse->getStatusCode());

            // Et surtout : son solde n'a pas bougé.
            $this->assertSame(50000.0, (float) $fournisseur->fresh()->solde);

            // Aucune demande fantôme non plus.
            $this->assertSame(
                $demandesAvant,
                DemandePaiement::where('user_id', $fournisseur->user_id)->count()
            );
        } finally {
            DB::statement('ALTER TABLE demande_paiement ADD COLUMN solde_debite_initiation TINYINT(1) NULL AFTER paye');
            $fournisseur->update(['solde' => $soldeMemo]);
        }
    }
}
