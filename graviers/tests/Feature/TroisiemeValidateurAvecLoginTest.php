<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * LE 3e VALIDATEUR PORTE SON IDENTIFIANT (10/09/2026), comme les deux premiers :
 * « NOM PRÉNOMS (login) » sur les sept guichets du circuit de preuve.
 */
class TroisiemeValidateurAvecLoginTest extends TestCase
{
    public function test_le_libelle_est_le_meme_pour_les_trois_validateurs(): void
    {
        $user = new User(['nom_prenoms' => 'KONE Awa', 'login' => 'akone']);
        $this->assertSame('KONE Awa (akone)', \Help::compteAvecIdentifiant($user));
        $this->assertSame('KONE Awa (akone)', \App\Models\Paiement::libelleCompte($user));
        $this->assertSame('-', \Help::compteAvecIdentifiant(null));

        $sansLogin = new User(['nom_prenoms' => 'KONE Awa', 'login' => '']);
        $this->assertSame('KONE Awa', \Help::compteAvecIdentifiant($sansLogin));
    }

    public function test_les_sept_guichets_passent_par_la_regle(): void
    {
        foreach ([
            'CommandeComptantController', 'CreanceClientTermeController', 'DemandeLivraisonComptantController',
            'DetteApporteurController', 'DetteFournisseurController', 'DetteLivreurController', 'LocationComptantController',
        ] as $c) {
            $source = file_get_contents(app_path('Http/Controllers/' . $c . '.php'));
            $this->assertStringContainsString("'troisieme_par'", $source, $c);
            $this->assertStringContainsString('\\Help::compteAvecIdentifiant($p->agentEffectuee ?? $p->agentPreuve)', $source, $c);
            $this->assertStringNotContainsString('agentEffectuee?->nom_prenoms', $source, $c . ' affiche encore le nom seul.');
        }
    }
}
