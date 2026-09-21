<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeLivraison;
use App\Models\Devis;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\Produit;
use App\Models\User;
use App\Models\Ville;
use App\Support\PlafondPaiementEnLigne;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * AU-DESSUS DE DEUX MILLIONS, ON NE PAIE PAS EN LIGNE.
 *
 * La règle est annoncée au client sur le panier et sur la page de paiement.
 * Elle était pourtant écrite huit fois à la main, avec trois comparaisons
 * différentes — et le parcours des COMMANDES ne la vérifiait pas du tout : la
 * ligne était commentée. Constaté le 04/09/2026, une commande de 6 502 000 fcfa
 * partie vers la passerelle.
 *
 * Là où le test existait — location, demande de livraison — il se contentait de
 * SAUTER le paiement en silence : l'affaire s'enregistrait sans qu'un franc
 * soit encaissé et sans que personne soit prévenu.
 */
class PlafondPaiementEnLigneTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * UN CLIENT CRÉÉ POUR L'ESSAI.
     *
     * Emprunter un client de la base l'expose à des particularités qu'on n'a
     * pas choisies — client à terme, plafond de crédit, commande en cours — et
     * l'essai finit par mesurer autre chose que la règle.
     */
    private function clientConnecte(): Client
    {
        $user = User::create([
            'nom_prenoms'  => 'Plafond Recette',
            'email'        => 'plaf_' . uniqid() . '@example.test',
            'login'        => 'plaf' . substr((string) uniqid(), -8),
            'password'     => bcrypt(\Illuminate\Support\Str::random(16)),
            'type_user_id' => 4,
            'contact'      => '0700000000',
            'statut'       => \Help::$STATUT_ACTIF,
        ]);

        $client = Client::create([
            'user_id'        => $user->id,
            'nom'            => 'Plafond',
            'prenom'         => 'Recette',
            'email'          => $user->email,
            'contact1'       => '0700000000',
            'type_client'    => 'PARTICULIER',
            'client_a_terme' => 0,
            'statut'         => \Help::$STATUT_ACTIF,
        ]);

        $this->actingAs($user);
        URL::forceRootUrl('');

        // La passerelle ne doit JAMAIS être appelée dans ces essais : si l'un
        // d'eux la joint, c'est que le garde-fou n'a pas mordu.
        Http::fake(['*' => Http::response(['code' => 400, 'message' => 'refus'], 200)]);

        return $client;
    }

    /** Le panier et la session qu'un parcours laisse avant validation. */
    private function amorcerLeParcours(int $modeId, float $ht, float $ttc, float $tva): void
    {
        $produit = Produit::first();
        $ville   = Ville::first();

        if (!$produit || !$ville) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        Cart::destroy();
        Cart::add($produit->id, $produit->nom, 1, $ht)->associate(Produit::class);

        session()->put([
            '0' => [
                'ville'          => null,
                'long'           => 0,
                'lat'            => 0,
                'infoSup'        => 'Adresse de recette',
                'montantTTC'     => $ttc,
                'montantHT'      => $ht,
                'tva'            => $tva,
                'cout_livraison' => 12000,
                'estLivrable'    => 'non',
            ],
            'totalLocation'  => $ht,
            'remise'         => 0,
            'mode_paiement'  => $modeId,
            'type_livraison' => 1,
            'date_livraison' => now()->addDays(3)->toDateString(),
            'debuts'         => [now()->addDay()->toDateString()],
            'fins'           => [now()->addDays(3)->toDateString()],
            'nbre_jour'      => [2],
        ]);
    }

    /** Tout ce que le formulaire de demande de livraison dépose en session. */
    private function sessionDemandeDeLivraison(int $modeId, float $montant, float $tva): array
    {
        $ville = Ville::first();
        $unite = \App\Models\UniteProduit::first();
        $type  = \App\Models\TypeLivraison::first();

        if (!$ville || !$unite || !$type) {
            $this->markTestSkipped('Jeu de données insuffisant.');
        }

        return [
            'villePec'       => $ville->id,
            'villeDest'      => $ville->id,
            'affichagePec'   => 'Départ de recette',
            'affichageDest'  => 'Arrivée de recette',
            'longPec'        => 0,
            'latPec'         => 0,
            'longDest'       => 0,
            'latDest'        => 0,
            'paiement'       => $modeId,
            'type_livraison' => $type->libelle,
            'date'           => now()->addDays(3)->toDateString(),
            'libelle'        => 'Recette',
            'description'    => 'Recette',
            'montant_total'  => $montant,
            'montantTva'     => $tva,
            'unite'          => $unite->abreviation,
            'produits'       => [[
                'nom_produit' => 'Gravier de recette',
                'qte'         => 1,
                'unite'       => $unite->id,
                'desc'        => 'Recette',
            ]],
        ];
    }

    private function modeEnLigne(): ModePaiement
    {
        return ModePaiement::where('en_ligne', 1)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->firstOrFail();
    }

    private function modeEnAgence(): ModePaiement
    {
        return ModePaiement::where('en_ligne', '!=', 1)
            ->where('statut', \Help::$STATUT_ACTIF)
            ->where('libelle', 'like', '%agence%')
            ->firstOrFail();
    }

    // --------------------------------------------------------------- LA RÈGLE

    public function test_le_plafond_porte_sur_un_montant_strictement_superieur(): void
    {
        // « SUPÉRIEUR à », comme le dit le message montré au client. Les
        // contrôleurs testaient « < 2000000 », ce qui range le montant exact du
        // mauvais côté.
        $this->assertFalse(PlafondPaiementEnLigne::depasse(1999999));
        $this->assertFalse(PlafondPaiementEnLigne::depasse(2000000));
        $this->assertTrue(PlafondPaiementEnLigne::depasse(2000001));
    }

    public function test_un_mode_hors_ligne_convient_a_tout_montant(): void
    {
        $this->assertTrue(
            PlafondPaiementEnLigne::modeAutorise($this->modeEnAgence(), 999000000),
            'Le règlement en agence doit rester possible quel que soit le montant.');
    }

    // ------------------------------------------------------------- LE MENU

    public function test_au_dela_du_plafond_le_menu_ne_propose_plus_le_paiement_en_ligne(): void
    {
        $sous = ModePaiement::listePourClient(2000000);
        $au_dessus = ModePaiement::listePourClient(2000001);

        $this->assertTrue($sous->contains(fn ($m) => (int) $m->en_ligne === 1),
            'Sous le plafond, le paiement en ligne doit rester proposé.');

        $this->assertFalse($au_dessus->contains(fn ($m) => (int) $m->en_ligne === 1),
            'Au-dessus du plafond, le menu propose encore un règlement que la '
            . 'suite du parcours refusera.');

        $this->assertTrue($au_dessus->isNotEmpty(),
            'Il doit rester au moins le règlement en agence : un menu vide '
            . 'empêcherait toute commande.');
    }

    public function test_sans_montant_le_menu_ne_change_pas(): void
    {
        // Sept écrans appellent cette liste sans montant. Aucun ne doit voir
        // son menu se réduire.
        $this->assertSame(
            ModePaiement::listePourClient()->pluck('id')->all(),
            ModePaiement::listePourClient(1000)->pluck('id')->all());
    }

    // ------------------------------------------------- LES TROIS PARCOURS

    /**
     * LA COMMANDE — le parcours qui ne vérifiait rien.
     *
     * On compte les devis et les commandes AVANT et APRÈS : un refus ne doit
     * rien laisser derrière lui.
     */
    public function test_une_commande_au_dessus_du_plafond_est_refusee_sans_rien_creer(): void
    {
        $client = $this->clientConnecte();

        $devisAvant = Devis::count();
        $commandesAvant = Commande::count();

        $this->amorcerLeParcours($this->modeEnLigne()->id, 5500000, 6502000, 990000);

        $reponse = $this->get('/panier-en-commande');

        // FLASHER AVALE LES FLASHS : `assertSessionHas('error')` ne peut pas
        // marcher, la bibliotheque capte success/error/warning/info et les
        // rejoue en toast. On lit donc ce que le client voit a l'ecran.
        $reponse->assertRedirect(route('client.modeDePaiement'));

        $this->get(route('client.modeDePaiement'))
            ->assertSee('virement bancaire', false)
            ->assertSee('Paiement en agence', false);

        $this->assertSame($devisAvant, Devis::count(),
            'Un devis a été créé alors que la commande était refusée.');
        $this->assertSame($commandesAvant, Commande::count(),
            'Une commande a été créée alors qu\'elle était refusée.');
    }

    /** LA LOCATION — le test existait, mais escamotait le paiement. */
    public function test_une_location_au_dessus_du_plafond_est_refusee_sans_rien_creer(): void
    {
        $this->clientConnecte();

        $avant = Location::count();

        $this->amorcerLeParcours($this->modeEnLigne()->id, 5500000, 6502000, 990000);

        $reponse = $this->get('/enregistrement-location-client');

        $reponse->assertRedirect(route('client.choixDateProduitLocationTraitement'));

        $this->assertSame($avant, Location::count(),
            'Une location a été enregistrée alors que le paiement était refusé.');
    }

    /** LA DEMANDE DE LIVRAISON — même défaut, même correction. */
    public function test_une_demande_de_livraison_au_dessus_du_plafond_est_refusee_sans_rien_creer(): void
    {
        $this->clientConnecte();

        $avant = DemandeLivraison::count();

        // LA SESSION COMPLÈTE, SINON L'ESSAI NE PROUVE RIEN.
        //
        // Une première version ne posait que le mode et le montant. Le parcours
        // renvoyait alors au formulaire faute des clés obligatoires — AVANT
        // d'atteindre le plafond — et l'essai passait aussi bien avec le
        // garde-fou que sans lui. Il faut que, SANS la règle, la demande soit
        // réellement créée : c'est le seul montage qui met le plafond en défaut.
        $this->withSession($this->sessionDemandeDeLivraison(
            $this->modeEnLigne()->id, 6000000, 502000
        ));

        $reponse = $this->get('/client-Validation-de-la-demande-de-livraison');

        $this->assertSame($avant, DemandeLivraison::count(),
            'Une demande de livraison a été enregistrée alors que le paiement '
            . 'était refusé.');
    }

    /** SOUS LE PLAFOND, RIEN NE CHANGE — le garde-fou ne doit pas déborder. */
    public function test_sous_le_plafond_le_mode_en_ligne_reste_accepte(): void
    {
        $this->assertTrue(
            PlafondPaiementEnLigne::modeAutorise($this->modeEnLigne(), 1999999),
            'Le paiement en ligne doit rester possible sous le plafond.');
    }
}
