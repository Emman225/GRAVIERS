<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Client;
use App\Models\CompteComptable;
use App\Models\Fournisseur;
use App\Models\HistoriqueParametrageComptable;
use App\Models\JournalComptable;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\RubriqueComptable;
use App\Models\User;
use App\Services\Comptabilite\ParametrageComptable;
use App\Support\MenusLateraux;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Module « Écritures comptables », phase 1 : le paramétrage (lot 116, 21/09/2026).
 *
 * Le compte général vient de la grande famille, le compte analytique du
 * produit ; chaque rubrique de facture, chaque tiers et chaque mode de
 * règlement a son compte. Un compte employé ne se supprime pas, et le
 * contrôle nomme ce qui manque avant qu'une écriture ne soit produite.
 */
class ParametrageComptableTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
        // Longueur connue, quel que soit le réglage de la base locale.
        DB::table('configuration')->update(['longueur_compte_comptable' => 6]);
    }

    private function unAdmin(): User
    {
        $admin = User::whereIn('type_user_id', [\Help::$USER_SA, \Help::$USER_ADMIN])
            ->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$admin) {
            $this->markTestSkipped('Aucun administrateur actif.');
        }

        return $admin;
    }

    private function enAdmin(): self
    {
        return $this->actingAs($this->unAdmin());
    }

    private function unCompte(string $nature, string $numero, string $libelle = 'Compte de recette'): CompteComptable
    {
        return CompteComptable::create(['nature' => $nature, 'numero' => $numero, 'libelle' => $libelle, 'statut' => 1]);
    }

    private function uneFamille(string $nom): Categorie
    {
        return Categorie::create(['nom' => $nom, 'description' => 'Recette', 'parent_id' => 0, 'statut' => \Help::$STATUT_ACTIF]);
    }

    private function unProduit(): Produit
    {
        $produit = Produit::where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$produit) {
            $this->markTestSkipped('Aucun produit actif.');
        }
        // Remis à nu : l'essai ne dépend pas du paramétrage déjà saisi en local.
        DB::table('produit')->where('id', $produit->id)->update(['categorie_comptable_id' => null, 'compte_analytique_id' => null]);

        return $produit->fresh();
    }

    // ------------------------------------------------------------------ accès

    public function test_l_ecran_est_reserve_aux_administrateurs(): void
    {
        $gestionnaire = User::where('type_user_id', \Help::$USER_GESTIONNAIRE)->where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$gestionnaire) {
            $this->markTestSkipped('Aucun gestionnaire actif.');
        }

        $this->actingAs($gestionnaire)->get('/comptabilite/parametrage')->assertStatus(403);
        $this->actingAs($gestionnaire)->post('/comptabilite/comptes', ['nature' => 'GENERAL', 'numero' => '701100', 'libelle' => 'X'])->assertStatus(403);
        $this->assertFalse(CompteComptable::where('numero', '701100')->where('libelle', 'X')->exists());
    }

    public function test_l_administrateur_ouvre_l_ecran_et_chaque_onglet(): void
    {
        foreach (\App\Http\Controllers\ParametrageComptableController::ONGLETS as $onglet) {
            $this->enAdmin()->get('/comptabilite/parametrage?onglet=' . $onglet)
                ->assertOk()
                ->assertSee('id="onglet-' . $onglet . '-bouton"', false)
                ->assertSee('Paramétrage');
        }
    }

    public function test_le_menu_est_inscrit_et_ne_s_allume_pas_sur_les_etats(): void
    {
        MenusLateraux::oublier();
        $this->assertContains('show.comptabilite.parametrage', MenusLateraux::nomsDeRoute());

        // « TVA collectée » porte le même préfixe de route : le menu « Écritures comptables » ne doit pas s'y ouvrir.
        $page = $this->enAdmin()->get('/comptabilite/tva-collectee')->assertOk()->getContent();
        $this->assertStringContainsString('Paramétrage comptable</a>', $page);
        $this->assertDoesNotMatchRegularExpression('/<li class="menu-item has-submenu active">\s*<a[^>]*>\s*<i class="icon material-icons md-account_balance">/', $page);
    }

    // ------------------------------------------------------------------ plan de comptes

    public function test_un_compte_general_respecte_la_longueur_du_plan(): void
    {
        $envoyer = fn (string $numero) => $this->enAdmin()->post('/comptabilite/comptes', [
            'nature' => 'GENERAL', 'numero' => $numero, 'libelle' => 'Ventes de granulats recette',
        ]);

        $envoyer('7011')->assertSessionHasErrors('numero');        // trop court
        $envoyer('70110000')->assertSessionHasErrors('numero');    // trop long pour six chiffres
        $envoyer('7011AB')->assertSessionHasErrors('numero');      // des lettres
        $this->assertFalse(CompteComptable::where('libelle', 'Ventes de granulats recette')->exists());

        $envoyer('701199')->assertSessionHasNoErrors()->assertRedirect();
        $compte = CompteComptable::where('numero', '701199')->first();
        $this->assertNotNull($compte);
        $this->assertSame(1, $compte->statut);

        $envoyer('701199')->assertSessionHasErrors('numero');      // doublon

        $trace = HistoriqueParametrageComptable::where('objet', 'compte')->where('objet_id', $compte->id)->first();
        $this->assertSame('CREATION', $trace->action);
        $this->assertSame($this->unAdmin()->id, (int) $trace->user_id);
    }

    public function test_la_longueur_suit_le_reglage_de_l_entreprise(): void
    {
        // Plan vierge : l'essai ne dépend pas des comptes déjà saisis en local.
        DB::table('compte_comptable')->delete();

        $this->enAdmin()->post('/comptabilite/parametrage/reglages', ['longueur_compte_comptable' => 8, 'format_libelle_ecriture' => 'Fact {numero}'])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, ParametrageComptable::longueurCompte());
        $this->assertSame('Fact {numero}', ParametrageComptable::formatLibelle());

        $this->enAdmin()->post('/comptabilite/comptes', ['nature' => 'GENERAL', 'numero' => '70110000', 'libelle' => 'Huit chiffres'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(CompteComptable::where('numero', '70110000')->exists());

        // La longueur ne change pas sous les comptes existants.
        $this->enAdmin()->post('/comptabilite/parametrage/reglages', ['longueur_compte_comptable' => 6])
            ->assertSessionHasErrors('longueur_compte_comptable');
        $this->assertSame(8, ParametrageComptable::longueurCompte());

        $this->enAdmin()->post('/comptabilite/parametrage/reglages', ['longueur_compte_comptable' => 9])
            ->assertSessionHasErrors('longueur_compte_comptable');
    }

    public function test_un_compte_analytique_accepte_lettres_et_tirets(): void
    {
        $this->enAdmin()->post('/comptabilite/comptes', ['nature' => 'ANALYTIQUE', 'numero' => 'gra-0515x', 'libelle' => 'Gravier 5/15'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(CompteComptable::where('nature', 'ANALYTIQUE')->where('numero', 'GRA-0515X')->exists());

        $this->enAdmin()->post('/comptabilite/comptes', ['nature' => 'ANALYTIQUE', 'numero' => 'GRA 0515', 'libelle' => 'Avec espace'])
            ->assertSessionHasErrors('numero');
    }

    public function test_un_compte_employe_ne_se_supprime_pas_il_se_desactive(): void
    {
        $compte  = $this->unCompte('GENERAL', '701198');
        $libre   = $this->unCompte('GENERAL', '701197');
        $famille = $this->uneFamille('Famille recette ' . uniqid());
        DB::table('categorie')->where('id', $famille->id)->update(['compte_comptable_id' => $compte->id]);

        $this->enAdmin()->delete('/comptabilite/comptes/' . $compte->id)->assertRedirect();
        $this->assertNotNull(CompteComptable::find($compte->id));

        $this->enAdmin()->post('/comptabilite/comptes/' . $compte->id . '/basculer')->assertRedirect();
        $this->assertSame(0, CompteComptable::find($compte->id)->statut);

        // Désactivé, il reste en face de la famille, et le contrôle le dit.
        $this->assertContains('Le compte général de cette famille est désactivé ou supprimé.',
            collect(ParametrageComptable::anomalies())->where('objet', $famille->nom)->pluck('cause')->all());

        $this->enAdmin()->delete('/comptabilite/comptes/' . $libre->id)->assertRedirect();
        $this->assertNull(CompteComptable::find($libre->id));
    }

    // ------------------------------------------------------------------ familles et produits

    public function test_une_famille_du_catalogue_apparait_aussitot_et_recoit_son_compte_general(): void
    {
        $famille = $this->uneFamille('Famille recette ' . uniqid());
        $general = $this->unCompte('GENERAL', '701196');
        $analytique = $this->unCompte('ANALYTIQUE', 'REC-' . substr(uniqid(), -6));

        $this->enAdmin()->get('/comptabilite/parametrage?onglet=familles')->assertSee($famille->nom);
        $this->assertContains('Aucun compte général en face de cette famille.',
            collect(ParametrageComptable::anomalies())->where('objet', $famille->nom)->pluck('cause')->all());

        // Un compte analytique ne peut pas tenir lieu de compte général.
        $this->enAdmin()->post('/comptabilite/parametrage/familles', ['compte' => [$famille->id => $analytique->id]]);
        $this->assertNull($famille->fresh()->compte_comptable_id);

        $this->enAdmin()->post('/comptabilite/parametrage/familles', ['compte' => [$famille->id => $general->id]])->assertRedirect();
        $this->assertSame($general->id, (int) $famille->fresh()->compte_comptable_id);
        $this->assertEmpty(collect(ParametrageComptable::anomalies())->where('objet', $famille->nom)->all());

        // Retirée du catalogue, elle quitte le paramétrage.
        Categorie::supprimer($famille->id);
        $this->assertFalse(ParametrageComptable::familles()->contains('id', $famille->id));
    }

    public function test_un_produit_recoit_une_seule_famille_et_son_compte_analytique(): void
    {
        $produit = $this->unProduit();
        $famille = $this->uneFamille('Famille recette ' . uniqid());
        $analytique = $this->unCompte('ANALYTIQUE', 'REC-' . substr(uniqid(), -6));
        $general = $this->unCompte('GENERAL', '701195');

        $this->enAdmin()->post('/comptabilite/parametrage/produits', [
            'famille'    => [$produit->id => $famille->id],
            'analytique' => [$produit->id => $general->id],   // un compte général n'est pas un analytique
        ]);
        $this->assertSame($famille->id, (int) $produit->fresh()->categorie_comptable_id);
        $this->assertNull($produit->fresh()->compte_analytique_id);

        $this->enAdmin()->post('/comptabilite/parametrage/produits', ['analytique' => [$produit->id => $analytique->id]]);
        $this->assertSame($analytique->id, (int) $produit->fresh()->compte_analytique_id);
        $this->assertSame($famille->id, (int) $produit->fresh()->categorie_comptable_id, 'Un champ non envoyé ne doit pas être vidé.');

        $causes = collect(ParametrageComptable::anomalies())->where('categorie', 'Produit')
            ->filter(fn ($a) => str_starts_with($a['objet'], $produit->nom))->pluck('cause')->all();
        $this->assertNotContains('Produit sans grande famille comptable.', $causes);
        $this->assertNotContains('Produit sans compte analytique.', $causes);
    }

    public function test_en_un_clic_la_premiere_famille_du_catalogue_et_un_analytique_d_apres_la_reference(): void
    {
        $produit = $this->unProduit();
        DB::table('categorie_produit')->where('produit_id', $produit->id)->update(['deleted_at' => now()]);
        $zebre = $this->uneFamille('Zèbre recette ' . uniqid());
        $abeille = $this->uneFamille('Abeille recette ' . uniqid());
        foreach ([$zebre, $abeille] as $famille) {
            DB::table('categorie_produit')->insert([
                'categorie_id' => $famille->id, 'produit_id' => $produit->id, 'statut' => \Help::$STATUT_ACTIF,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->enAdmin()->post('/comptabilite/parametrage/produits/en-un-clic', ['action' => 'familles'])->assertRedirect();
        $this->assertSame($abeille->id, (int) $produit->fresh()->categorie_comptable_id, 'Rangé dans deux familles, le produit prend la première par ordre alphabétique.');

        // « Par catégorie » ne remplace une famille déjà choisie que sur demande.
        $this->enAdmin()->post('/comptabilite/parametrage/produits/en-un-clic', ['action' => 'categorie', 'categorie_id' => $zebre->id]);
        $this->assertSame($abeille->id, (int) $produit->fresh()->categorie_comptable_id);
        $this->enAdmin()->post('/comptabilite/parametrage/produits/en-un-clic', ['action' => 'categorie', 'categorie_id' => $zebre->id, 'remplacer' => 1]);
        $this->assertSame($zebre->id, (int) $produit->fresh()->categorie_comptable_id);

        $this->enAdmin()->post('/comptabilite/parametrage/produits/en-un-clic', ['action' => 'analytiques'])->assertRedirect();
        $compte = CompteComptable::find($produit->fresh()->compte_analytique_id);
        $this->assertNotNull($compte);
        $this->assertSame('ANALYTIQUE', $compte->nature);
        $this->assertNull(ParametrageComptable::erreurDeNumero('ANALYTIQUE', $compte->numero));
        $this->assertSame(0, Produit::where('statut', \Help::$STATUT_ACTIF)->whereNull('compte_analytique_id')->count());

        // Rejoué, le bouton ne touche à rien.
        $avant = CompteComptable::count();
        $this->enAdmin()->post('/comptabilite/parametrage/produits/en-un-clic', ['action' => 'analytiques']);
        $this->assertSame($avant, CompteComptable::count());
    }

    // ------------------------------------------------------------------ rubriques, tiers, journaux

    public function test_chaque_rubrique_de_facture_a_son_compte(): void
    {
        $tva = $this->unCompte('GENERAL', '443199');
        $transport = $this->unCompte('GENERAL', '706199');
        $analytique = $this->unCompte('ANALYTIQUE', 'TRP-' . substr(uniqid(), -6));

        $this->enAdmin()->post('/comptabilite/parametrage/rubriques', [
            'compte'     => [RubriqueComptable::TVA_COLLECTEE => $tva->id, RubriqueComptable::TRANSPORT => $transport->id],
            'analytique' => [RubriqueComptable::TVA_COLLECTEE => $analytique->id, RubriqueComptable::TRANSPORT => $analytique->id],
        ])->assertRedirect();

        $this->assertSame($tva->id, (int) RubriqueComptable::pour(RubriqueComptable::TVA_COLLECTEE)->compte_comptable_id);
        $this->assertNull(RubriqueComptable::pour(RubriqueComptable::TVA_COLLECTEE)->compte_analytique_id, 'La TVA ne porte pas d\'analytique.');
        $this->assertSame($analytique->id, (int) RubriqueComptable::pour(RubriqueComptable::TRANSPORT)->compte_analytique_id);

        $this->assertSame(array_keys(RubriqueComptable::RUBRIQUES), RubriqueComptable::toutes()->pluck('code')->all());
    }

    public function test_un_compte_tiers_par_client_unique_et_attribuable_en_serie(): void
    {
        $clients = Client::orderBy('id')->limit(2)->get();
        if ($clients->count() < 2) {
            $this->markTestSkipped('Il faut deux clients.');
        }
        DB::table('client')->update(['compte_tiers' => null]);
        [$un, $deux] = [$clients[0], $clients[1]];

        $this->enAdmin()->post('/comptabilite/parametrage/tiers', ['client' => [$un->id => '411 dupont']])->assertSessionHasErrors('tiers');
        $this->assertNull($un->fresh()->compte_tiers);

        $this->enAdmin()->post('/comptabilite/parametrage/tiers', ['client' => [$un->id => '411dupont']])->assertSessionHasNoErrors();
        $this->assertSame('411DUPONT', $un->fresh()->compte_tiers);

        $this->enAdmin()->post('/comptabilite/parametrage/tiers', ['client' => [$deux->id => '411DUPONT']])->assertSessionHasErrors('tiers');
        $this->assertNull($deux->fresh()->compte_tiers);

        $this->enAdmin()->post('/comptabilite/parametrage/tiers/generer', ['cible' => 'client', 'prefixe' => '411'])->assertRedirect();
        $this->assertSame('411DUPONT', $un->fresh()->compte_tiers, 'Un compte déjà saisi ne change pas.');
        $this->assertSame('411' . str_pad((string) $deux->id, 3, '0', STR_PAD_LEFT), $deux->fresh()->compte_tiers,
            'Comptes à six chiffres, préfixe de trois : le rang en occupe trois.');
        $this->assertSame(0, Client::where(fn ($q) => $q->whereNull('compte_tiers')->orWhere('compte_tiers', ''))->count());

        $fournisseur = Fournisseur::first();
        if ($fournisseur) {
            DB::table('fournisseur')->update(['compte_tiers' => null]);
            $this->enAdmin()->post('/comptabilite/parametrage/tiers/generer', ['cible' => 'fournisseur', 'prefixe' => '401']);
            $this->assertSame('401' . str_pad((string) $fournisseur->id, 3, '0', STR_PAD_LEFT), $fournisseur->fresh()->compte_tiers);
        }
    }

    public function test_un_compte_tiers_attribue_atteint_la_longueur_des_comptes(): void
    {
        $client = Client::orderBy('id')->first();
        if (!$client) {
            $this->markTestSkipped('Aucun client.');
        }
        DB::table('client')->update(['compte_tiers' => null]);

        // La nomenclature du comptable : une racine, puis le rang sur les chiffres qui restent.
        // 4111 + quatre chiffres = 41110007, comme 46210007 dans sa balance Sage.
        DB::table('configuration')->update(['longueur_compte_comptable' => 8]);
        $this->enAdmin()->post('/comptabilite/parametrage/tiers/generer', ['cible' => 'client', 'prefixe' => '4111']);
        $compte = $client->fresh()->compte_tiers;
        $this->assertSame(8, mb_strlen($compte), 'Le compte tiers a la longueur des comptes du plan.');
        $this->assertSame('4111' . str_pad((string) $client->id, 4, '0', STR_PAD_LEFT), $compte);

        // À six chiffres, le rang tient sur deux : la règle suit le réglage.
        DB::table('client')->update(['compte_tiers' => null]);
        DB::table('configuration')->update(['longueur_compte_comptable' => 6]);
        $this->enAdmin()->post('/comptabilite/parametrage/tiers/generer', ['cible' => 'client', 'prefixe' => '4111']);
        $this->assertSame('4111' . str_pad((string) $client->id, 3, '0', STR_PAD_LEFT), $client->fresh()->compte_tiers,
            'Jamais moins de trois chiffres de rang, même si le préfixe est long.');
    }

    public function test_un_mode_de_reglement_se_rattache_a_un_journal_de_tresorerie(): void
    {
        $mode = ModePaiement::where('statut', \Help::$STATUT_ACTIF)->first();
        if (!$mode) {
            $this->markTestSkipped('Aucun mode de règlement actif.');
        }
        $banque = $this->unCompte('GENERAL', '521199', 'Banque recette');

        $this->enAdmin()->post('/comptabilite/journaux', ['code' => 'bqr', 'libelle' => 'Banque recette', 'type' => 'BANQUE', 'compte_comptable_id' => $banque->id])
            ->assertSessionHasNoErrors();
        $journal = JournalComptable::where('code', 'BQR')->first();
        $this->assertSame($banque->id, (int) $journal->compte_comptable_id);

        $this->enAdmin()->post('/comptabilite/journaux', ['code' => 'BQR', 'libelle' => 'Doublon', 'type' => 'BANQUE'])->assertSessionHasErrors('code');

        // Le journal des ventes ne reçoit pas de règlement, et ne porte pas de compte de trésorerie.
        $this->enAdmin()->post('/comptabilite/journaux', ['code' => 'VTR', 'libelle' => 'Ventes recette', 'type' => 'VENTES', 'compte_comptable_id' => $banque->id]);
        $ventes = JournalComptable::where('code', 'VTR')->first();
        $this->assertNull($ventes->compte_comptable_id);
        $this->enAdmin()->post('/comptabilite/parametrage/modes', ['journal' => [$mode->id => $ventes->id]]);
        $this->assertNotSame($ventes->id, (int) $mode->fresh()->journal_comptable_id);

        $this->enAdmin()->post('/comptabilite/parametrage/modes', ['journal' => [$mode->id => $journal->id]])->assertRedirect();
        $this->assertSame($journal->id, (int) $mode->fresh()->journal_comptable_id);

        // Employé, le journal ne se supprime plus.
        $this->enAdmin()->delete('/comptabilite/journaux/' . $journal->id)->assertRedirect();
        $this->assertNotNull(JournalComptable::find($journal->id));
    }

    public function test_les_journaux_de_depart_existent(): void
    {
        foreach (['VENTES', 'BANQUE', 'CAISSE', 'MOBILE_MONEY'] as $type) {
            $this->assertTrue(JournalComptable::withTrashed()->where('type', $type)->exists(), "Journal de type {$type} absent.");
        }
    }
}
