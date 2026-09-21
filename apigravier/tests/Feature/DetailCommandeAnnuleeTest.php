<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DetailCommande;
use App\Models\DetailLocation;
use App\Models\Location;
use App\Models\Produit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * UNE AFFAIRE ANNULÉE GARDE SES ARTICLES À L'ÉCRAN.
 *
 * Constaté le 01/09/2026 : sur l'application client, l'écran « Détails commande
 * à imprimer ou retourner » s'affichait entièrement BLANC.
 *
 * La cause : annuler une commande depuis l'application passe chacune de ses
 * lignes à `statut = 2`. L'écran de détail, lui, ne demandait que les lignes à
 * `statut = 1` — il n'en recevait donc plus AUCUNE. Le client ne pouvait plus
 * savoir ce qu'il avait commandé, et le bouton d'impression sortait un bon de
 * commande sans un seul article.
 *
 * La même faute frappait les locations : leur annulation passe elle aussi les
 * lignes en `statut = 2`, et depuis le back-office également.
 *
 * LA DISTINCTION QUI COMPTE : une ligne RETIRÉE d'une commande est effacée en
 * douceur (`deleted_at`) ; une ligne d'affaire ANNULÉE ne l'est pas. On montre
 * donc la seconde sans montrer la première.
 */
class DetailCommandeAnnuleeTest extends TestCase
{
    use DatabaseTransactions;

    private function unClient(): Client
    {
        $client = Client::whereNotNull('user_id')->whereHas('user')->first();

        if (!$client) {
            $this->markTestSkipped('Aucun client rattaché à un compte.');
        }

        return $client;
    }

    private function unProduit(string $typeAffaire): Produit
    {
        $produit = Produit::where('type_affaire', $typeAffaire)->first();

        if (!$produit) {
            $this->markTestSkipped("Aucun produit $typeAffaire.");
        }

        return $produit;
    }

    /** @return array{0:Commande,1:DetailCommande} */
    private function uneCommandeAnnulee(Client $client): array
    {
        $commande = Commande::create([
            'numero'        => 'TEST-' . uniqid(),
            'client_id'     => $client->id,
            'montant_total' => 8000,
            'etat_commande' => 'ANNULEE',
            'statut'        => \Help::$STATUT_INACTIF,
        ]);

        $ligne = DetailCommande::create([
            'commande_id'    => $commande->id,
            'produit_id'     => $this->unProduit(\Help::$VENTE)->id,
            'qte'            => 2,
            'prix'           => 4000,
            'etat_livraison' => \Help::$LIVRAISON_EN_ATTENTE,
            // Ce que fait l'annulation depuis l'application.
            'statut'         => \Help::$STATUT_INACTIF,
        ]);

        return [$commande, $ligne];
    }

    private function lignesVues(string $url, Client $client): array
    {
        $reponse = $this->postJson($url, [
            'access' => Crypt::encryptString((string) $client->user_id),
            'type'   => 'mobile',
        ]);

        $reponse->assertOk();
        $corps = $reponse->json();

        $this->assertSame(200, $corps['code'],
            'L’écran de détail répond en erreur : ' . ($corps['message'] ?? ''));

        return $corps['data']['lignes'] ?? [];
    }

    /** L'ÉCRAN D'UNE COMMANDE ANNULÉE N'EST PAS BLANC. */
    public function test_une_commande_annulee_montre_ses_articles(): void
    {
        $client = $this->unClient();
        [$commande, $ligne] = $this->uneCommandeAnnulee($client);

        $lignes = $this->lignesVues("/mon_gravier/details-commande/{$commande->id}", $client);

        $this->assertCount(1, $lignes,
            'L’écran de détail d’une commande annulée ne reçoit aucun article : '
            . 'il s’affiche entièrement blanc et son bon s’imprime vide.');

        $this->assertSame($ligne->id, $lignes[0]['id'],
            'La ligne rendue n’est pas celle de la commande.');
    }

    /** UNE LIGNE RETIRÉE DE LA COMMANDE, ELLE, RESTE INVISIBLE. */
    public function test_une_ligne_retiree_reste_invisible(): void
    {
        $client = $this->unClient();
        [$commande, $ligne] = $this->uneCommandeAnnulee($client);

        // `DetailCommande::supprimer()` : statut inactif ET effacement en douceur.
        $ligne->delete();

        $this->assertCount(0, $this->lignesVues("/mon_gravier/details-commande/{$commande->id}", $client),
            'Une ligne retirée de la commande réapparaît à l’écran : le client '
            . 'croit avoir commandé un article qui n’y est plus.');
    }

    /** L'ÉCRAN D'UNE LOCATION ANNULÉE NON PLUS. */
    public function test_une_location_annulee_montre_ses_articles(): void
    {
        $client = $this->unClient();

        $location = Location::create([
            'numero'        => 'TEST-' . uniqid(),
            'client_id'     => $client->id,
            'montant_total' => 50000,
            'etat_location' => 'ANNULEE',
            'statut'        => \Help::$STATUT_INACTIF,
        ]);

        DetailLocation::create([
            'location_id'   => $location->id,
            'produit_id'    => $this->unProduit(\Help::$LOCATION)->id,
            'qte'           => 1,
            'prix'          => 50000,
            'debut'         => now()->toDateString(),
            'fin'           => now()->addDay()->toDateString(),
            'etat_location' => \Help::$LOCATION_EN_ATTENTE,
            'statut'        => \Help::$STATUT_INACTIF,
        ]);

        $this->assertCount(1, $this->lignesVues("/mon_gravier/details-location/{$location->id}", $client),
            'L’écran de détail d’une location annulée ne reçoit aucun article.');
    }

    /**
     * CE QUI AGIT SUR LA COMMANDE NE VOIT QUE LES LIGNES VIVANTES.
     *
     * Le paiement et l'annulation parcourent la même liste. Leur ouvrir les
     * lignes inactives les ferait écrire sur des lignes déjà éteintes.
     */
    public function test_les_appelants_qui_agissent_gardent_le_filtre(): void
    {
        $client = $this->unClient();
        [$commande, ] = $this->uneCommandeAnnulee($client);

        $this->assertCount(0, DetailCommande::liste(null, $commande->id),
            'Sans le drapeau de lecture, la liste doit rester filtrée sur les '
            . 'lignes actives : le paiement et l’annulation s’en servent pour '
            . 'ÉCRIRE.');

        $this->assertCount(1, DetailCommande::liste(null, $commande->id, null, true),
            'Le drapeau de lecture doit, lui, tout rendre.');
    }
}
