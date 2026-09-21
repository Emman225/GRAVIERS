<?php

namespace Tests\Feature;

use App\Models\ModePaiement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * LE RÈGLEMENT AU GUICHET, SUR UNE DEMANDE DE LIVRAISON MOBILE.
 *
 * L'écran de commande a deux champs : le « mode » (en ligne, virement, agence),
 * tenu par l'application, et le « moyen » — l'opérateur — servi par le serveur.
 * La demande de livraison n'en a qu'UN, et il lisait la liste des opérateurs :
 * commander un transport obligeait donc à payer immédiatement par mobile money,
 * alors que le site offre le guichet depuis toujours.
 *
 * Ce que ce fichier tient :
 *   · la liste de la demande de livraison contient le règlement en agence ;
 *   · celle de l'écran de commande ne le contient PAS — l'y ajouter aurait fait
 *     apparaître le guichet parmi les opérateurs de mobile money, où choisir
 *     « En ligne » puis « Paiement en agence » aurait lancé la passerelle sur un
 *     mode hors ligne ;
 *   · les deux listes sont servies dans la configuration, sous des clés
 *     distinctes, pour qu'une application déjà installée garde son comportement.
 */
class ModePaiementAgenceLivraisonTest extends TestCase
{
    use DatabaseTransactions;

    private function libelles($liste): array
    {
        return collect($liste)->pluck('libelle')->map(fn ($l) => mb_strtolower($l))->all();
    }

    public function test_la_demande_de_livraison_propose_le_guichet(): void
    {
        $libelles = $this->libelles(ModePaiement::listePourDemandeLivraison());

        if (empty($libelles)) {
            $this->markTestSkipped('Aucun mode de paiement actif en base.');
        }

        $this->assertTrue(
            collect($libelles)->contains(fn ($l) => str_contains($l, 'agence')),
            'Le règlement en agence doit être proposé sur une demande de livraison.'
        );

        // Et les opérateurs en ligne restent proposés : le guichet s'ajoute, il
        // ne remplace rien.
        $this->assertGreaterThan(1, count($libelles));
    }

    public function test_l_ecran_de_commande_ne_propose_pas_le_guichet_comme_operateur(): void
    {
        $libelles = $this->libelles(ModePaiement::listePourClient());

        $this->assertFalse(
            collect($libelles)->contains(fn ($l) => str_contains($l, 'agence')),
            "Le guichet n'est pas un opérateur de paiement en ligne."
        );
    }

    public function test_les_instruments_reserves_a_l_agent_restent_ecartes(): void
    {
        // Un virement, un chèque, des espèces ou une carte se constatent à
        // l'encaissement : le client ne les choisit pas depuis son téléphone.
        $libelles = $this->libelles(ModePaiement::listePourDemandeLivraison());

        foreach (['virement', 'chèque', 'espèce', 'carte'] as $instrument) {
            $this->assertFalse(
                collect($libelles)->contains(
                    fn ($l) => str_contains($l, $instrument) && !str_contains($l, 'agence')
                ),
                "« {$instrument} » ne doit pas être proposé au client."
            );
        }
    }

    public function test_la_configuration_sert_les_deux_listes(): void
    {
        $reponse = $this->getJson('/mon_gravier/get-config');

        $reponse->assertOk();

        $reponse->assertJsonStructure(['mode_paiements', 'mode_paiements_livraison']);

        $guichet = collect($reponse->json('mode_paiements_livraison'))
            ->contains(fn ($m) => str_contains(mb_strtolower($m['libelle'] ?? ''), 'agence'));

        $this->assertTrue($guichet,
            "La configuration doit livrer le guichet à l'écran de demande de livraison.");
    }

    public function test_le_guichet_n_ouvre_pas_la_passerelle(): void
    {
        // La demande enregistrée avec un mode hors ligne ne doit déclencher aucun
        // paiement : le client vient régler au comptoir. C'est le drapeau
        // en_ligne qui commande, et lui seul.
        $guichet = ModePaiement::listePourDemandeLivraison()
            ->first(fn ($m) => str_contains(mb_strtolower((string) $m->libelle), 'agence'));

        if (!$guichet) {
            $this->markTestSkipped('Aucun mode « agence » en base.');
        }

        $this->assertEquals(0, (int) $guichet->en_ligne,
            'Le règlement en agence doit rester un mode HORS LIGNE.');
    }
}
