<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Lot 109 (17/09/2026) : pages publiques — menu actif, numéro, À propos, contact, livraison, blog. */
class PagesPubliquesSoigneesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_le_menu_met_en_avant_la_page_courante(): void
    {
        $accueil = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="active" href="[^"]*">Accueil</a>#', $accueil);
        $this->assertMatchesRegularExpression('#<a class="" href="[^"]*/a-propos">A propos</a>#', $accueil);

        $apropos = $this->get('/a-propos')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="active" href="[^"]*/a-propos">A propos</a>#', $apropos);
        $this->assertStringNotContainsString('a.active::after', $apropos, 'Couleur seule, pas de soulignement.');
        $this->assertMatchesRegularExpression('#<a class="" href="[^"]*">Accueil</a>#', $apropos, "Accueil n'est plus actif partout.");

        $contact = $this->get('/nous-contacter')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a class="active" href="[^"]*/nous-contacter">Contact</a>#', $contact);
    }

    public function test_le_pied_de_page_et_le_contact_portent_le_vrai_numero(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('(+225) - 07 00 13 07 98', $html);
        $this->assertStringNotContainsString('07 27 3333', $html);

        $contact = $this->get('/nous-contacter')->assertOk()->getContent();
        $this->assertStringContainsString('(+225) 07 00 13 07 98', $contact);
        $this->assertStringContainsString('href="https://wa.me/2250700130798"', $contact);
        $this->assertStringContainsString('Du lundi au samedi, de 08:00 à 18:00', $contact);
        $this->assertStringContainsString('DALAKOUN SARLU', $contact);
        $this->assertStringNotContainsString('8h00 à 22h00', $contact);
    }

    public function test_a_propos_dit_sarlu_et_presente_materiaux_services_et_etapes(): void
    {
        $html = $this->get('/a-propos')->assertOk()->getContent();
        foreach (['DALAKOUN SARLU', 'Qui sommes-nous', 'Nos services', 'Comment ça marche', 'Pourquoi nous choisir',
                  'Espace professionnels', 'Devenir livreur', 'Devenir fournisseur', 'Devenir affilié', 'Mon Gravier'] as $attendu) {
            $this->assertStringContainsString($attendu, $html, $attendu);
        }
        $this->assertStringNotContainsString('DALAKOUN SARL<', $html, 'Plus de « SARL » sans U.');
        $this->assertStringContainsString('<h1>Mon Gravier</h1>', $html, "La plateforme est Mon Gravier ; DALAKOUN SARLU en est l'entreprise.");
        $this->assertStringContainsString('Une plateforme de DALAKOUN SARLU', $html);
        $this->assertStringContainsString('.ap-hero h1{ color:#fff !important;', $html, 'Titre blanc sur le bandeau bleu.');
        $this->assertStringNotContainsString('Support 7j/7', $html);
    }

    public function test_la_demande_de_livraison_occupe_toute_la_largeur(): void
    {
        $html = $this->get('/demande-de-livraison')->assertOk()->getContent();
        $this->assertStringContainsString('<div class="col-lg-12">', $html);
        $this->assertStringContainsString('.dl-tableau th:nth-child(2), .dl-tableau td:nth-child(2) { width: 40%; }', $html);
        // Lot 109 bis : « Ville » (Select2, plafonnée à 155 px par le thème) et la recherche du géocodeur (246 px) en pleine largeur.
        $this->assertStringContainsString('.dl-carte .select2-container { width: 100% !important; max-width: none !important; }', $html);
        $this->assertStringContainsString('.dl-recherche .leaflet-control-geocoder { width: 100% !important;', $html);
        $this->assertStringContainsString('.dl-recherche .leaflet-control-geocoder-icon { display: none !important; }', $html, 'Plus de loupe orpheline au-dessus du champ.');
        // Les deux lieux sont obligatoires (coordonnées cachées requises) : le libellé le dit.
        $this->assertStringContainsString('Rechercher le lieu de prise en charge <span class="champ-obligatoire"', $html);
        $this->assertStringContainsString('Rechercher le lieu de destination <span class="champ-obligatoire"', $html);
    }

    public function test_le_blog_montre_trois_articles_par_page_en_cartes(): void
    {
        $colonnes = collect(DB::getSchemaBuilder()->getColumnListing('blogs'));
        for ($i = 1; $i <= 4; $i++) {
            $ligne = ['titre' => 'Article de recette ' . $i, 'description' => "## Titre\n**Texte** de l'article " . $i,
                      'publie' => 1, 'created_at' => now()->subMinutes($i), 'updated_at' => now()];
            foreach (['image' => '', 'vu' => 0, 'user_publie' => 1, 'user_publie_id' => 1] as $c => $v) {
                if ($colonnes->contains($c)) { $ligne[$c] = $v; }
            }
            DB::table('blogs')->insert($ligne);
        }

        $page1 = $this->get('/blog')->assertOk()->getContent();
        $this->assertSame(3, substr_count($page1, '<article class="blog-carte">'), 'Trois cartes sur la première page.');
        $this->assertStringContainsString('Article de recette 1', $page1);
        $this->assertStringNotContainsString('Article de recette 4', $page1);
        $this->assertStringContainsString('aria-label="Navigation des articles"', $page1, 'Le reste est en pagination.');
        $this->assertStringNotContainsString('## Titre', $page1, "L'extrait ne montre pas les marques de mise en forme.");
        $this->assertStringContainsString("Lire l'article", $page1);

        $page2 = $this->get('/blog?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('Article de recette 4', $page2);
    }
}
