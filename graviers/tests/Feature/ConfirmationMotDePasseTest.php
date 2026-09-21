<?php

namespace Tests\Feature;

use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * CONFIRMER LE MOT DE PASSE A L'INSCRIPTION.
 *
 * Le mot de passe est saisi masque : une faute de frappe ne se voit pas. Le
 * compte etait alors cree avec un mot de passe que le client ne connaissait
 * pas, et il fallait le reinitialiser avant meme d'avoir commande une premiere
 * fois.
 *
 * Le formulaire distingue DEUX profils — particulier et entreprise — et chacun
 * a sa propre validation. Ne corriger que la premiere aurait laisse la porte
 * ouverte aux entreprises.
 */
class ConfirmationMotDePasseTest extends TestCase
{
    use DatabaseTransactions;

    private function unParticulier(array $remplace = []): array
    {
        return array_merge([
            'type'      => 1,
            'prenom'    => 'Awa',
            'nom'       => 'Konan',
            'email'     => 'essai-' . uniqid() . '@example.com',
            'pays'      => Pays::value('id'),
            'ville'     => Ville::value('id'),
            'contact1'  => '0700000001',
            'adresse'   => 'Yopougon',
            'password'  => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'condition' => 'on',
        ], $remplace);
    }

    public function test_le_champ_de_confirmation_est_present(): void
    {
        $this->get(route('client.register'))
            ->assertOk()
            ->assertSee('name="password_confirmation"', false)
            ->assertSee('Confirmer le mot de passe');
    }

    public function test_deux_mots_de_passe_differents_sont_refuses(): void
    {
        Mail::fake();

        $donnees = $this->unParticulier(['password_confirmation' => 'AutreChose1!']);

        $this->post(route('client.registerClient'), $donnees)
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', $donnees['email'])->first(),
            "Aucun compte ne doit etre cree quand les deux saisies different.");
    }

    public function test_la_confirmation_manquante_est_refusee(): void
    {
        // Un envoi direct du formulaire, sans passer par l'ecran : la regle
        // serveur doit tenir seule.
        Mail::fake();

        $donnees = $this->unParticulier();
        unset($donnees['password_confirmation']);

        $this->post(route('client.registerClient'), $donnees)
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', $donnees['email'])->first());
    }

    public function test_deux_saisies_identiques_laissent_passer(): void
    {
        // Non-regression : l'inscription normale ne doit pas etre genee.
        Mail::fake();

        $donnees = $this->unParticulier();

        $reponse = $this->post(route('client.registerClient'), $donnees);

        $reponse->assertSessionHasNoErrors();

        $this->assertNotSame(500, $reponse->status(),
            "L'inscription d'un particulier partait en erreur 500 : le formulaire ne "
            . "fournit aucun « display_name », et users.nom_prenoms n'accepte pas de nul.");

        $this->assertNotNull(User::where('email', $donnees['email'])->first(),
            "Une inscription correcte doit aboutir.");
    }

    public function test_la_regle_vaut_aussi_pour_une_entreprise(): void
    {
        // Les deux profils ont leur propre bloc de validation : ne corriger que
        // le particulier aurait laisse la porte ouverte aux entreprises.
        $controleur = file_get_contents(app_path('Http/Controllers/ClientController.php'));

        $this->assertEquals(2, substr_count($controleur, '"password" => "required|confirmed"'),
            "Les deux profils — particulier et entreprise — doivent exiger la confirmation.");
    }

    public function test_le_message_d_erreur_est_en_francais(): void
    {
        Mail::fake();

        $this->post(route('client.registerClient'),
                $this->unParticulier(['password_confirmation' => 'AutreChose1!']))
            ->assertSessionHasErrors(['password' => 'Les deux mots de passe ne correspondent pas !']);
    }
}
