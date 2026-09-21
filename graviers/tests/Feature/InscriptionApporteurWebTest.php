<?php

namespace Tests\Feature;

use App\Models\Apporteur;
use App\Models\ModePaiement;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * L'INSCRIPTION D'UN APPORTEUR D'AFFAIRE DEPUIS LE SITE.
 *
 * Elle recueillait MOINS de renseignements que l'application mobile : ni pays,
 * ni ville, ni numero de piece, ni mode de paiement prefere. Un meme apporteur
 * n'avait donc pas le meme dossier selon l'endroit ou il s'inscrivait, et la
 * fiche restait a completer a la main.
 *
 * La page s'appuyait par ailleurs sur le gabarit du BACK-OFFICE — fond blanc,
 * carte nue — alors qu'elle s'adresse a un visiteur.
 */
class InscriptionApporteurWebTest extends TestCase
{
    use DatabaseTransactions;

    private function donnees(array $remplace = []): array
    {
        return array_merge([
            'nom_prenom'            => 'Awa Konan',
            // La validation exige « email:rfc,dns » : le domaine doit porter un
            // enregistrement MX. « example.com » n'en a pas, et le test aurait
            // echoue sur sa propre fixture plutot que sur le code.
            'email'                 => 'apporteur.' . uniqid() . '@gmail.com',
            'contact'               => (string) random_int(700000000, 799999999),
            'pays'                  => Pays::value('id'),
            'ville'                 => Ville::value('id'),
            'numero_piece'          => 'CI-' . uniqid(),
            // Un mode REELLEMENT propose : le premier de la table est « En Agence »,
            // que la liste de l'apporteur ecarte — la fixture aurait teste un cas
            // que le formulaire n'offre pas.
            'mode_paiement'         => ModePaiement::listePourApporteur()->first()?->id,
            'password'              => 'MotDePasse1!',
            'password_confirmation' => 'MotDePasse1!',
            'recto'                 => UploadedFile::fake()->image('recto.jpg'),
            'verso'                 => UploadedFile::fake()->image('verso.jpg'),
        ], $remplace);
    }

    public function test_la_page_reprend_le_dessin_du_site_public(): void
    {
        // Elle etendait le gabarit du back-office : fond blanc, carte nue.
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $this->assertStringContainsString('hero-register__card', $html,
            "La page doit reprendre le dessin de l'inscription client.");

        $this->assertStringContainsString('hero-register__hero-title', $html);
    }

    public function test_les_champs_suivent_ceux_de_l_application_mobile(): void
    {
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        foreach (['nom_prenom', 'pays', 'ville', 'contact', 'email',
                  'numero_piece', 'mode_paiement', 'recto', 'verso',
                  'password', 'password_confirmation'] as $champ) {
            $this->assertStringContainsString('name="' . $champ . '"', $html,
                "Le champ « {$champ} » manque au formulaire.");
        }
    }

    public function test_les_listes_deroulantes_sont_garnies(): void
    {
        // Un champ obligatoire dont la liste est vide rend le formulaire
        // impossible a envoyer.
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $pays = Pays::first();
        $ville = Ville::first();
        $mode = ModePaiement::listePourApporteur()->first();

        if ($pays)  $this->assertStringContainsString('value="' . $pays->id . '"', $html);
        if ($ville) $this->assertStringContainsString('value="' . $ville->id . '"', $html);
        if ($mode)  $this->assertStringContainsString($mode->libelle, $html);
    }

    public function test_le_lien_vers_la_connexion_est_present(): void
    {
        $this->get(route('apporteur.register'))
            ->assertOk()
            ->assertSee(route('apporteur.login'), false)
            ->assertSee('Se connecter');
    }

    public function test_deux_mots_de_passe_differents_sont_refuses(): void
    {
        Mail::fake();
        Storage::fake('public');

        $donnees = $this->donnees(['password_confirmation' => 'AutreChose1!']);

        $this->post(route('apporteur.store'), $donnees)
            ->assertSessionHasErrors('password');

        $this->assertNull(User::where('email', $donnees['email'])->first());
    }

    public function test_une_inscription_complete_enregistre_tout(): void
    {
        Mail::fake();
        Storage::fake('public');

        $donnees = $this->donnees();

        $this->post(route('apporteur.store'), $donnees)->assertSessionHasNoErrors();

        $user = User::where('email', $donnees['email'])->first();

        $this->assertNotNull($user, "Le compte doit etre cree.");
        $this->assertEquals($donnees['pays'], $user->pays_id,
            "Le pays etait perdu en silence : absent de la liste des champs assignables.");
        $this->assertEquals($donnees['ville'], $user->ville_id);

        $apporteur = Apporteur::where('user_id', $user->id)->first();

        $this->assertNotNull($apporteur);
        $this->assertEquals($donnees['numero_piece'], $apporteur->numero_piece);
        $this->assertEquals($donnees['mode_paiement'], $apporteur->mode_paiement_id);
    }

    public function test_les_nouveaux_champs_sont_obligatoires(): void
    {
        Mail::fake();
        Storage::fake('public');

        foreach (['pays', 'ville', 'numero_piece', 'mode_paiement'] as $champ) {
            $donnees = $this->donnees();
            unset($donnees[$champ]);

            $this->post(route('apporteur.store'), $donnees)
                ->assertSessionHasErrors($champ);
        }
    }

    public function test_les_pieces_se_deposent_depuis_une_vignette(): void
    {
        // Sur le TELEPHONE, l'application propose de photographier la piece. Sur
        // le WEB, on selectionne un fichier deja sur l'ordinateur : rien ne doit
        // forcer l'appareil photo.
        //
        // La vignette reste utile : un champ de fichier nu ne dit pas ce qui a
        // ete retenu, et l'apporteur devait rouvrir le selecteur pour verifier
        // qu'il n'avait pas envoye deux fois le meme cote.
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $this->assertStringContainsString("Cliquez sur l'image pour sélectionner le fichier", $html);
        $this->assertStringNotContainsString('capture=', $html,
            "L'attribut « capture » ouvre l'appareil photo : il n'a pas sa place sur le web.");
        $this->assertStringContainsString('Pièce recto', $html);
        $this->assertStringContainsString('Pièce verso', $html);

        // L'etiquette doit etre liee au champ, sinon la vignette ne declenche
        // rien du tout.
        $this->assertStringContainsString('for="piece-recto"', $html, false);
        $this->assertStringContainsString('id="piece-recto"', $html, false);
    }

    public function test_le_champ_de_fichier_reste_soumis_a_la_validation(): void
    {
        // Le masquer par « display:none » le retirerait de la validation du
        // navigateur, qui ne saurait plus signaler une piece manquante.
        $feuille = file_get_contents(resource_path('views/apporteur/register.blade.php'));

        $this->assertStringNotContainsString('.apporteur-piece__champ{display:none', $feuille);
        $this->assertStringContainsString('clip: rect(0 0 0 0)', $feuille);
    }

    public function test_le_bouton_et_la_mention_suivent_l_application(): void
    {
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $this->assertStringContainsString("Je m'inscris", $html);
        $this->assertStringContainsString('En continuant, vous confirmez avoir accepté nos', $html);

        // La mention renvoie vraiment aux conditions : une mention sans lien
        // n'engage personne.
        $this->assertStringContainsString(route('termesConditions'), $html);
    }

    public function test_le_formulaire_ne_demande_que_ce_que_demande_l_application(): void
    {
        // Le web reclamait deux renseignements que l'application ne demande pas :
        // l'adresse et la zone d'intervention. Un meme apporteur n'avait donc pas
        // le meme dossier selon l'endroit ou il s'inscrivait.
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="adresse"', $html);
        $this->assertStringNotContainsString('name="zone_intervention"', $html);
    }

    public function test_une_inscription_sans_adresse_aboutit(): void
    {
        // La colonne users.adresse accepte l'absence de valeur : ne plus la
        // demander ne doit rien casser.
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('public');

        $donnees = $this->donnees();

        $this->post(route('apporteur.store'), $donnees)->assertSessionHasNoErrors();

        $user = User::where('email', $donnees['email'])->first();

        $this->assertNotNull($user);
        $this->assertNull($user->adresse, "L'adresse n'est plus demandee : elle reste vide.");
    }

    public function test_les_modes_de_paiement_sont_ceux_de_l_application(): void
    {
        // C'est une preference de VERSEMENT : comment l'apporteur souhaite
        // toucher sa commission. « En agence » n'est pas un instrument mais un
        // lieu, et l'application ne le propose pas. Le site l'offrait : un
        // apporteur inscrit depuis le web pouvait choisir une preference
        // introuvable dans son application.
        $html = $this->get(route('apporteur.register'))->assertOk()->getContent();

        $attendus = ModePaiement::listePourApporteur();

        $this->assertGreaterThan(0, $attendus->count(), 'Aucun mode de paiement actif.');

        foreach ($attendus as $mode) {
            $this->assertStringContainsString($mode->libelle, $html,
                "Le mode « {$mode->libelle} » doit etre propose, comme dans l'application.");
        }

        // Et ceux que l'application ecarte ne doivent pas apparaitre.
        $ecartes = ModePaiement::where('statut', \Help::$STATUT_ACTIF)
            ->where('libelle', 'like', '%agence%')->get();

        foreach ($ecartes as $mode) {
            $this->assertStringNotContainsString('>' . $mode->libelle . '<', $html,
                "Le mode « {$mode->libelle} » n'a pas de sens pour un versement.");
        }
    }

    public function test_un_mode_de_paiement_non_propose_est_refuse(): void
    {
        // Le champ est un menu deroulant, mais un envoi direct du formulaire
        // peut porter n'importe quel identifiant. Le reglement en agence EXISTE
        // dans la table : « exists » seul l'aurait laisse passer.
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Storage::fake('public');

        $ecarte = ModePaiement::where('statut', \Help::$STATUT_ACTIF)
            ->where('libelle', 'like', '%agence%')->first();

        if (!$ecarte) {
            $this->markTestSkipped('Aucun mode « agence » en base.');
        }

        $this->post(route('apporteur.store'), $this->donnees(['mode_paiement' => $ecarte->id]))
            ->assertSessionHasErrors('mode_paiement');
    }
}
