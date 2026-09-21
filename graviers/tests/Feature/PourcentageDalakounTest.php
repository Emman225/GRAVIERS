<?php

namespace Tests\Feature;

use App\Models\PourcentageDalakoun;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le pourcentage que DALAKOUN ajoute au prix d'achat.
 *
 * Jusqu'ici l'entreprise vendait au prix auquel elle achetait : l'écran d'ajout
 * de produit recopie le prix fournisseur dans la ligne de stock, et c'est cette
 * ligne que le catalogue lit comme prix de vente.
 *
 * Ce taux fait le prix du catalogue. Il engage donc tout le tarif, et ne
 * s'applique qu'après une SECONDE validation par un administrateur DIFFÉRENT de
 * celui qui l'a saisi — la règle déjà en vigueur sur les règlements.
 */
class PourcentageDalakounTest extends TestCase
{
    use DatabaseTransactions;

    private function deuxAdmins(): array
    {
        $admins = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->limit(2)->get();

        if ($admins->count() < 2) {
            $this->markTestSkipped('Deux administrateurs actifs sont nécessaires.');
        }

        return [$admins[0], $admins[1]];
    }

    private function unGestionnaire(): ?User
    {
        return User::where('type_user_id', \Help::$USER_GESTIONNAIRE)
            ->where('statut', \Help::$STATUT_ACTIF)->first();
    }

    private function saisir(User $auteur, float $taux, string $motif = "Recette"): ?PourcentageDalakoun
    {
        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/pourcentage-dalakoun', [
            'taux'  => $taux,
            'motif' => $motif,
        ]);

        return PourcentageDalakoun::where('motif', $motif)->latest('id')->first();
    }

    // ------------------------------------------------------------- SAISIE

    public function test_un_taux_saisi_n_est_pas_encore_en_vigueur(): void
    {
        [$auteur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20);

        $this->assertNotNull($taux);
        $this->assertTrue($taux->attendUneSecondeValidation());
        $this->assertFalse($taux->estApplique());

        // Rien ne doit changer aux prix tant que personne n'a contrôlé.
        $this->assertNull(PourcentageDalakoun::enVigueur()?->id === $taux->id ? $taux : null);
    }

    public function test_le_taux_doit_etre_un_nombre_positif(): void
    {
        [$auteur] = $this->deuxAdmins();

        URL::forceRootUrl('');

        $this->actingAs($auteur)->post('/pourcentage-dalakoun', ['taux' => -5])
            ->assertSessionHasErrors('taux');

        $this->actingAs($auteur)->post('/pourcentage-dalakoun', ['taux' => 'beaucoup'])
            ->assertSessionHasErrors('taux');
    }

    public function test_un_second_taux_ne_peut_pas_attendre_en_meme_temps(): void
    {
        [$auteur] = $this->deuxAdmins();

        $this->saisir($auteur, 20, 'Premier ' . uniqid());

        $avant = PourcentageDalakoun::count();

        // Deux décisions concurrentes sur le tarif : on tranche la première.
        $this->saisir($auteur, 30, 'Second ' . uniqid());

        $this->assertSame($avant, PourcentageDalakoun::count());
    }

    // -------------------------------------------------------- VALIDATION

    public function test_celui_qui_saisit_ne_peut_pas_valider(): void
    {
        [$auteur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20, 'Auto ' . uniqid());

        URL::forceRootUrl('');
        $this->actingAs($auteur)->post('/pourcentage-dalakoun-' . $taux->id . '/valider');

        $this->assertTrue($taux->fresh()->attendUneSecondeValidation(),
            'Un administrateur ne doit pas pouvoir valider son propre pourcentage.');
    }

    public function test_un_second_administrateur_le_met_en_vigueur(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20, 'Valide ' . uniqid());

        URL::forceRootUrl('');
        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $taux->id . '/valider');

        $taux = $taux->fresh();

        $this->assertTrue($taux->estApplique());
        $this->assertSame((int) $valideur->id, (int) $taux->user_valide2_id);
        $this->assertSame($taux->id, PourcentageDalakoun::enVigueur()?->id);
        $this->assertSame(20.0, PourcentageDalakoun::tauxEnVigueur());
    }

    public function test_un_gestionnaire_ne_peut_pas_valider(): void
    {
        $gestionnaire = $this->unGestionnaire();

        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }

        [$auteur] = $this->deuxAdmins();
        $taux = $this->saisir($auteur, 20, 'Gest ' . uniqid());

        URL::forceRootUrl('');
        $this->actingAs($gestionnaire)->post('/pourcentage-dalakoun-' . $taux->id . '/valider');

        $this->assertTrue($taux->fresh()->attendUneSecondeValidation(),
            'Seul un administrateur peut donner la seconde validation.');
    }

    public function test_un_taux_refuse_ne_s_applique_jamais(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 45, 'Refus ' . uniqid());

        URL::forceRootUrl('');
        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $taux->id . '/refuser');

        $taux = $taux->fresh();

        $this->assertFalse($taux->estApplique());
        $this->assertNotSame($taux->id, PourcentageDalakoun::enVigueur()?->id);
    }

    public function test_un_taux_deja_traite_ne_se_revalide_pas(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20, 'Double ' . uniqid());

        URL::forceRootUrl('');
        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $taux->id . '/valider');

        $premiereDate = $taux->fresh()->date_validation_2;

        $this->actingAs($auteur)->post('/pourcentage-dalakoun-' . $taux->id . '/valider');

        $this->assertEquals($premiereDate, $taux->fresh()->date_validation_2);
    }

    // ------------------------------------------------------------ CALCUL

    public function test_le_taux_le_plus_recemment_valide_l_emporte(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        URL::forceRootUrl('');

        $ancien = $this->saisir($auteur, 15, 'Ancien ' . uniqid());
        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $ancien->id . '/valider');

        $nouveau = $this->saisir($auteur, 25, 'Nouveau ' . uniqid());
        $this->actingAs($valideur)->post('/pourcentage-dalakoun-' . $nouveau->id . '/valider');

        $this->assertSame(25.0, PourcentageDalakoun::tauxEnVigueur());
        $this->assertSame(12500.0, round(PourcentageDalakoun::appliquerA(10000), 2));
    }

    public function test_sans_taux_valide_le_prix_reste_le_prix_d_achat(): void
    {
        // Aucune marge inventée : tant que rien n'est décidé, le prix ne bouge
        // pas. Mieux vaut une marge nulle visible qu'un prix fabriqué.
        PourcentageDalakoun::query()->delete();

        $this->assertNull(PourcentageDalakoun::enVigueur());
        $this->assertSame(0.0, PourcentageDalakoun::tauxEnVigueur());
        $this->assertSame(10000.0, round(PourcentageDalakoun::appliquerA(10000), 2));
    }

    // ------------------------------------------------------------ ÉCRAN

    public function test_l_ecran_repond_et_liste_les_taux(): void
    {
        [$auteur] = $this->deuxAdmins();

        $this->saisir($auteur, 20, 'Ecran ' . uniqid());

        URL::forceRootUrl('');
        $reponse = $this->actingAs($auteur)->get('/pourcentage-dalakoun');

        $reponse->assertOk();
        $reponse->assertSee('Pourcentage DALAKOUN', false);
        $reponse->assertSee('20 %', false);
    }

    // ------------------------------------------- MIGRATION PAS ENCORE LANCEE

    public function test_sans_la_table_l_ecran_le_dit_au_lieu_de_tomber(): void
    {
        [$auteur] = $this->deuxAdmins();

        // Les fichiers arrivent sur le serveur avant que la migration ne soit
        // lancee : c'est le mode de deploiement ici, et l'ecart a deja produit
        // des pages blanches que personne ne savait interpreter.
        \Illuminate\Support\Facades\Schema::rename('pourcentage_dalakoun', 'pourcentage_dalakoun_absente');

        try {
            URL::forceRootUrl('');
            $reponse = $this->actingAs($auteur)->get('/pourcentage-dalakoun');

            $reponse->assertOk();
            $reponse->assertSee('migration', false);

            // Et le calcul du prix ne doit surtout pas exploser : des l'etape
            // suivante, il sera appele par tout le catalogue.
            $this->assertSame(0.0, PourcentageDalakoun::tauxEnVigueur());
            $this->assertSame(10000.0, round(PourcentageDalakoun::appliquerA(10000), 2));
        } finally {
            \Illuminate\Support\Facades\Schema::rename('pourcentage_dalakoun_absente', 'pourcentage_dalakoun');
        }
    }

    // ------------------------------------------------ CE QUE L'ECRAN PROPOSE

    public function test_l_auteur_ne_voit_pas_le_bouton_valider(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20, 'Bouton ' . uniqid());

        URL::forceRootUrl('');

        // Le serveur refusait deja ce cas, mais l'ecran proposait le bouton :
        // une interdiction qu'on ne decouvre qu'en cliquant n'en est pas une.
        $vueAuteur = $this->actingAs($auteur)->get('/pourcentage-dalakoun');
        $vueAuteur->assertOk();
        $vueAuteur->assertDontSee('pourcentage-dalakoun-' . $taux->id . '/valider', false);
        $vueAuteur->assertSee("En attente d'un autre administrateur", false);

        // Le second administrateur, lui, le voit.
        $vueValideur = $this->actingAs($valideur)->get('/pourcentage-dalakoun');
        $vueValideur->assertOk();
        $vueValideur->assertSee('pourcentage-dalakoun-' . $taux->id . '/valider', false);
    }

    public function test_l_auteur_ne_peut_rien_faire_sur_son_propre_taux(): void
    {
        [$auteur, $valideur] = $this->deuxAdmins();

        $taux = $this->saisir($auteur, 20, 'Rien ' . uniqid());

        // La colonne « Action » annoncait « En attente d'un autre administrateur »
        // tout en proposant « Refuser » : deux messages contraires dans la meme
        // cellule. Sur son propre taux, l'auteur ne fait plus rien.
        $this->assertFalse($taux->peutEtreValidePar($auteur));
        $this->assertFalse($taux->peutEtreRefusePar($auteur));

        URL::forceRootUrl('');

        $vueAuteur = $this->actingAs($auteur)->get('/pourcentage-dalakoun');
        $vueAuteur->assertDontSee('pourcentage-dalakoun-' . $taux->id . '/valider', false);
        $vueAuteur->assertDontSee('pourcentage-dalakoun-' . $taux->id . '/refuser', false);

        // Et le serveur refuse aussi, pas seulement l'ecran.
        $this->actingAs($auteur)->post('/pourcentage-dalakoun-' . $taux->id . '/refuser');
        $this->assertTrue($taux->fresh()->attendUneSecondeValidation());

        // Le second administrateur, lui, dispose des deux actions.
        $this->assertTrue($taux->peutEtreValidePar($valideur));
        $this->assertTrue($taux->peutEtreRefusePar($valideur));

        $vueValideur = $this->actingAs($valideur)->get('/pourcentage-dalakoun');
        $vueValideur->assertSee('pourcentage-dalakoun-' . $taux->id . '/refuser', false);
    }
}
