<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Le journal d'audit se lit sans être développeur.
 *
 * Le détail d'une opération montrait une méthode HTTP, une route et un bloc
 * JSON. Or ce journal existe pour être relu par quelqu'un qui NE code pas : un
 * gérant qui cherche qui a relevé un plafond, un comptable qui veut savoir qui
 * a annulé une facture. Le technique reste disponible, mais dessous.
 */
class AuditLisibleTest extends TestCase
{
    use DatabaseTransactions;

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function uneTrace(string $action, array $donnees): Audit
    {
        $admin = $this->unAdmin();

        return Audit::create([
            'user_id'         => $admin->id,
            'nom_utilisateur' => 'Awa Konan',
            'type_user_id'    => $admin->type_user_id,
            'action'          => $action,
            'methode'         => 'POST',
            'url'             => 'http://exemple/test',
            'route_name'      => 'test.recette',
            'donnees'         => $donnees,
            'adresse_ip'      => '127.0.0.1',
            'user_agent'      => 'Recette',
        ]);
    }

    // ------------------------------------------------------------- LE RÉCIT

    public function test_le_recit_dit_qui_a_fait_quoi(): void
    {
        $trace = $this->uneTrace('Validation — commande', ['parametres' => ['commande' => 468662]]);

        $recit = $trace->recit();

        $this->assertStringContainsString('Awa Konan', $recit);
        $this->assertStringContainsString('valid', $recit);
        $this->assertStringContainsString('commande', $recit);
    }

    public function test_chaque_verbe_se_conjugue(): void
    {
        $attendus = [
            'Création — produit'      => 'a créé',
            'Modification — client'   => 'a modifié',
            'Suppression — produit'   => 'a supprimé',
            'Validation — facture'    => 'a validé',
            'Annulation — commande'   => 'a annulé',
            'Encaissement — paiement' => 'a encaissé',
            'Blocage — client'        => 'a bloqué',
            'Déblocage — client'      => 'a débloqué',
        ];

        foreach ($attendus as $action => $conjugue) {
            $this->assertStringContainsString(
                $conjugue,
                $this->uneTrace($action, [])->recit(),
                'Le verbe de « ' . $action . ' » doit se conjuguer.'
            );
        }
    }

    public function test_un_libelle_sans_tiret_ne_casse_pas_le_recit(): void
    {
        // Les traces ecrites a la main par un controleur ne suivent pas
        // forcement la forme « Verbe — objet ».
        $recit = $this->uneTrace('connexion au back-office', [])->recit();

        $this->assertStringContainsString('Awa Konan', $recit);
        $this->assertNotSame('', trim($recit));
    }

    // ---------------------------------------------------------- LES DÉTAILS

    public function test_les_champs_deviennent_des_mots(): void
    {
        $trace = $this->uneTrace('Modification — client', [
            'saisie' => ['plafond_credit' => '5000000', 'delai_paiement' => '45'],
        ]);

        $libelles = collect($trace->elementsLisibles())->pluck('libelle')->all();

        $this->assertContains('Plafond de crédit', $libelles);
        $this->assertContains('Délai de paiement', $libelles);
        $this->assertNotContains('plafond_credit', $libelles);
    }

    public function test_les_montants_prennent_leurs_espaces(): void
    {
        $trace = $this->uneTrace('Encaissement — paiement', ['saisie' => ['montant' => '250000']]);

        $valeurs = collect($trace->elementsLisibles())->pluck('valeur')->all();

        $this->assertContains('250 000 FCFA', $valeurs);
    }

    public function test_un_identifiant_se_lit_comme_un_numero(): void
    {
        $trace = $this->uneTrace('Validation — commande', ['parametres' => ['commande' => 468662]]);

        $this->assertContains('n° 468662', collect($trace->elementsLisibles())->pluck('valeur')->all());
    }

    public function test_un_taux_porte_son_signe_pourcent(): void
    {
        $trace = $this->uneTrace('Création — pourcentage', ['saisie' => ['taux' => '10.00']]);

        $this->assertContains('10 %', collect($trace->elementsLisibles())->pluck('valeur')->all());
    }

    public function test_un_champ_inconnu_reste_affiche(): void
    {
        // L ecarter tairait justement ce qu on est venu chercher.
        $trace = $this->uneTrace('Modification — client', [
            'saisie' => ['champ_maison_inedit' => 'valeur importante'],
        ]);

        $elements = collect($trace->elementsLisibles());

        $this->assertContains('valeur importante', $elements->pluck('valeur')->all());
        $this->assertContains('Champ maison inedit', $elements->pluck('libelle')->all());
    }

    public function test_les_champs_vides_ne_polluent_pas(): void
    {
        $trace = $this->uneTrace('Modification — client', [
            'saisie' => ['motif' => '', 'commentaire_admin' => null, 'plafond_credit' => '1000'],
        ]);

        $this->assertCount(1, $trace->elementsLisibles());
    }

    // ------------------------------------------------------------- L'ÉCRAN

    public function test_l_ecran_montre_les_deux_sections(): void
    {
        $this->uneTrace('Modification — client', [
            'parametres' => ['client' => 12],
            'saisie'     => ['plafond_credit' => '5000000'],
        ]);

        URL::forceRootUrl('');

        // « /audit » renvoie désormais sur l'onglet « Audit » de « Paramètre ».
        // L'écran a changé d'adresse, pas de nature : on suit la redirection
        // plutôt que d'exiger un code 200 sur l'ancienne page.
        $reponse = $this->actingAs($this->unAdmin())->followingRedirects()->get('/audit');

        $reponse->assertOk();
        $reponse->assertSee("Ce qui s'est passé", false);
        $reponse->assertSee('pour les développeurs', false);

        // Le récit voyage jusqu au bouton, sinon la fenetre resterait vide.
        $reponse->assertSee('data-recit=', false);
        $reponse->assertSee('data-elements=', false);
    }

    public function test_la_partie_technique_reste_disponible(): void
    {
        $this->uneTrace('Modification — client', ['saisie' => ['plafond_credit' => '1000']]);

        URL::forceRootUrl('');

        $reponse = $this->actingAs($this->unAdmin())->followingRedirects()->get('/audit');

        $reponse->assertSee('auditDonnees', false);
        $reponse->assertSee('auditRoute', false);
        $reponse->assertSee('Données brutes', false);
    }
}
