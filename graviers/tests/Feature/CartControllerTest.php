<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\TypeUser;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Gloudemans\Shoppingcart\Facades\Cart;

class CartControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the type_user record for clients exists
        $typeUser = TypeUser::firstOrCreate(
            ['id' => \Help::$USER_CLIENT],
            ['nom' => 'Client', 'statut' => 1]
        );

        // Ensure a configuration row exists for TVA calculation
        Configuration::firstOrCreate(
            ['id' => 1],
            [
                'tva' => 18,
                'tonne_moyenne' => 25,
                'cout_liv_fixe' => 100,
                'cout_livraison_min' => 5000,
            ]
        );

        // Create a user of type Client
        $this->user = User::factory()->create([
            'type_user_id' => \Help::$USER_CLIENT,
            'statut' => \Help::$STATUT_ACTIF,
        ]);

        // Create the associated client record
        $this->client = Client::create([
            'user_id' => $this->user->id,
            'nom' => 'Test',
            'prenom' => 'Client',
            'email' => $this->user->email,
            'contact1' => '0101010101',
            'type_client' => \Help::$PARTICULIER,
            'statut' => \Help::$STATUT_ACTIF,
            'applique_tva' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Cart::destroy();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Adding items to cart
    // ---------------------------------------------------------------

    public function test_can_add_item_to_cart(): void
    {
        // Signature : add($id, $name, $qty, $price, array $options = [], $taxrate = null)
        Cart::add('prod-1', 'Gravier 6/10', 5, 15000, ['unite' => 'tonne']);

        $this->assertCount(1, Cart::content());
        $this->assertEquals(5, Cart::content()->first()->qty);
        $this->assertEquals('Gravier 6/10', Cart::content()->first()->name);
    }

    public function test_add_multiple_items_to_cart(): void
    {
        Cart::add('prod-1', 'Gravier 6/10', 5, 15000);
        Cart::add('prod-2', 'Sable fin', 3, 8000);

        $this->assertCount(2, Cart::content());
    }

    // ---------------------------------------------------------------
    // Updating cart quantity via the controller (the dd() fix)
    // ---------------------------------------------------------------

    public function test_update_quantite_returns_json_with_totals(): void
    {
        Cart::add('prod-1', 'Gravier 6/10', 5, 15000);
        $item = Cart::content()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.quantite'), [
                'rowId' => $item->rowId,
                'qty' => 10,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'subtotal', 'total', 'tva', 'ttc']);

        // Verify the cart was actually updated
        $updatedItem = Cart::get($item->rowId);
        $this->assertEquals(10, $updatedItem->qty);
    }

    public function test_update_quantite_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.quantite'), [
                // missing rowId and qty
            ]);

        $response->assertStatus(422);
    }

    public function test_update_quantite_rejects_zero_qty(): void
    {
        Cart::add('prod-1', 'Gravier', 5, 15000);
        $item = Cart::content()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.quantite'), [
                'rowId' => $item->rowId,
                'qty' => 0,
            ]);

        $response->assertStatus(422);
    }

    // ---------------------------------------------------------------
    // updateAll (quantity or montant-based update)
    // ---------------------------------------------------------------

    public function test_update_all_by_quantity_change(): void
    {
        Cart::add('prod-1', 'Gravier 6/10', 5, 15000);
        $item = Cart::content()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.all'), [
                'rowId' => $item->rowId,
                'qte' => 8,
                'montant' => '120 000',
                'prixUnitaire' => 15000,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'subtotal', 'total', 'tva', 'ttc', 'rowId', 'qte']);
    }

    public function test_update_all_by_montant_change(): void
    {
        Cart::add('prod-1', 'Gravier 6/10', 5, 15000);
        $item = Cart::content()->first();

        // Same qty but different montant => calculates qte from montant/prixUnitaire
        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.all'), [
                'rowId' => $item->rowId,
                'qte' => 5,           // same as current qty
                'montant' => '90 000', // 90000 / 15000 = 6
                'prixUnitaire' => 15000,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_update_all_enforces_minimum_qty(): void
    {
        // Le controller clampe la quantité à 0.1 minimum. Quand on envoie 0,
        // l'item ne doit PAS être supprimé : sa quantité doit être ramenée à 0.1.
        Cart::add('prod-1', 'Gravier', 5, 15000);
        $item = Cart::content()->first();

        $response = $this->actingAs($this->user)
            ->postJson(route('panier.update.all'), [
                'rowId' => $item->rowId,
                'qte' => 0,           // sous le minimum → clampé à 0.1
                'montant' => '0',
                'prixUnitaire' => 15000,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'qte' => 0.1]);

        // L'item est toujours dans le panier avec qte = 0.1
        $this->assertEquals(0.1, Cart::get($item->rowId)->qty);
    }

    // ---------------------------------------------------------------
    // Removing items from cart
    // ---------------------------------------------------------------

    public function test_remove_item_from_cart(): void
    {
        Cart::add('prod-1', 'Gravier', 5, 15000);
        $item = Cart::content()->first();

        Cart::remove($item->rowId);

        $this->assertCount(0, Cart::content());
    }

    // ---------------------------------------------------------------
    // Cart total calculation
    // ---------------------------------------------------------------

    public function test_cart_total_sums_correctly(): void
    {
        Cart::add('prod-1', 'Gravier', 2, 15000);
        Cart::add('prod-2', 'Sable', 3, 8000);

        // 2*15000 + 3*8000 = 30000 + 24000 = 54000
        $this->assertEquals(54000, Cart::total());
    }

    public function test_cart_subtotal_per_item(): void
    {
        Cart::add('prod-1', 'Gravier', 4, 10000);
        $item = Cart::content()->first();

        $this->assertEquals(40000, $item->subtotal());
    }

    // ---------------------------------------------------------------
    // Cart destroy
    // ---------------------------------------------------------------

    public function test_cart_destroy_empties_cart(): void
    {
        Cart::add('prod-1', 'Gravier', 5, 15000);
        Cart::add('prod-2', 'Sable', 3, 8000);

        Cart::destroy();

        $this->assertCount(0, Cart::content());
        $this->assertEquals(0, Cart::total());
    }

    // ---------------------------------------------------------------
    // Unauthenticated user updates
    // ---------------------------------------------------------------

    public function test_update_quantite_works_for_guest(): void
    {
        Cart::add('prod-1', 'Gravier', 5, 15000);
        $item = Cart::content()->first();

        // The route has no auth middleware, so guests can use it
        $response = $this->postJson(route('panier.update.quantite'), [
            'rowId' => $item->rowId,
            'qty' => 7,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }
}
