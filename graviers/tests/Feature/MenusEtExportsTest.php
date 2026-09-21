<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * RÉORGANISATION DU BACK-OFFICE DU 29/08/2026.
 *
 * - « Audit » quitte le menu principal pour un onglet de « Paramètre » ;
 * - « Administrateurs », « Gestionnaires » et « Agences » se regroupent sous
 *   un menu « Configuration » ;
 * - « Dettes » passe sous « Etat » ;
 * - quatre écrans reçoivent les boutons Excel / Word / PDF ;
 * - « Inscriptions en attente » retrouve la présentation des autres listes.
 */
class MenusEtExportsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('');
    }

    private function compte(array $types): User
    {
        $u = User::whereIn('type_user_id', $types)
            ->where('statut', \Help::$STATUT_ACTIF)->first();

        if (!$u) {
            $this->markTestSkipped('Aucun compte actif de type ' . implode('/', $types) . '.');
        }

        return $u;
    }

    private function unAdmin(): User
    {
        return $this->compte([\Help::$USER_SA, \Help::$USER_ADMIN]);
    }

    private function unGestionnaire(): User
    {
        return $this->compte([\Help::$USER_GESTIONNAIRE]);
    }

    /* ===================================================================
       LES QUATRE ÉCRANS PORTENT LEURS TROIS BOUTONS
       =================================================================== */

    public static function ecransExportables(): array
    {
        return [
            'CA par famille'          => ['/CA-par-famille'],
            'Réapprovisionnement'     => ['/reapprovisionnement'],
            'Inscriptions en attente' => ['/list-client-en-attente'],
            'Demandes client à terme' => ['/liste-demande-client-a-terme'],
        ];
    }

    /** @dataProvider ecransExportables */
    public function test_l_ecran_porte_les_trois_boutons_d_export(string $adresse): void
    {
        $reponse = $this->actingAs($this->unAdmin())->get($adresse);
        $reponse->assertOk();

        $html = $reponse->getContent();

        foreach (['GravierExport.toExcel', 'GravierExport.toWord', 'GravierExport.toPdf'] as $appel) {
            $this->assertStringContainsString($appel, $html,
                "L'ecran $adresse ne propose pas l'export « $appel » : les trois "
                . 'boutons doivent y etre comme sur les autres listes.');
        }
    }

    /** LE RÉAPPROVISIONNEMENT A DEUX TABLEAUX : LES DEUX S'EXPORTENT. */
    public function test_le_reapprovisionnement_exporte_ses_deux_tableaux(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/reapprovisionnement')->getContent();

        $this->assertStringContainsString('&quot;tableId&quot;:&quot;reappro&quot;', $html,
            'Le tableau des produits a reapprovisionner n est pas exportable.');
        $this->assertStringContainsString('&quot;tableId&quot;:&quot;liste&quot;', $html,
            'Le tableau de l etat de livraison n est pas exportable.');
    }

    /* ===================================================================
       LES INSCRIPTIONS EN ATTENTE RETROUVENT LA PRÉSENTATION COMMUNE

       Le bloc de script s'appelait « jspart » — le nom retenu par le gabarit
       CLIENT. Le gabarit du back-office attend « jsParts » : le bloc n'était
       donc JAMAIS rendu, ni la bibliothèque ni son initialisation. Le tableau
       restait brut, sans recherche, sans tri et sans pagination.
       =================================================================== */

    public function test_les_inscriptions_en_attente_ont_un_vrai_tableau(): void
    {
        $vue = file_get_contents(resource_path('views/admin/listClientEnAttente.blade.php'));

        $this->assertStringNotContainsString("@section('jspart')", $vue,
            'La section porte le nom du gabarit client : le script ne sera pas rendu.');

        $html = $this->actingAs($this->unAdmin())->get('/list-client-en-attente')->getContent();

        $this->assertStringContainsString('DataTables/datatables.min.js', $html,
            'La bibliotheque du tableau n est pas chargee : ni recherche, ni tri, '
            . 'ni pagination — c est ce qui distinguait cet ecran des autres.');

        $this->assertStringContainsString('dash-table', $html,
            'Le tableau ne porte pas la presentation commune aux autres listes.');
    }

    /* ===================================================================
       AUDIT : PLUS UN MENU, UN ONGLET DE PARAMÈTRE
       =================================================================== */

    public function test_l_audit_n_est_plus_un_menu_principal(): void
    {
        // Menu complet depuis le découpage du 07/09/2026 (point 1).
        $menu = file_get_contents(resource_path('views/layout/navGestionnnaire.blade.php'))
            . file_get_contents(resource_path('views/layout/navbar/navEtats.blade.php'))
            . file_get_contents(resource_path('views/layout/navbar/navConfiguration.blade.php'));

        $this->assertStringNotContainsString("route('show.audit.index')", $menu,
            'Le menu principal « Audit » subsiste : il devait devenir un onglet '
            . 'de « Parametre ».');
    }

    public function test_l_ancienne_adresse_de_l_audit_renvoie_sur_l_onglet(): void
    {
        $reponse = $this->actingAs($this->unAdmin())->get('/audit?recherche=plafond');

        $reponse->assertRedirect();

        $cible = $reponse->headers->get('Location');

        $this->assertStringContainsString('onglet=audit', $cible,
            'La redirection doit rouvrir l onglet Audit, sans quoi l utilisateur '
            . 'atterrit sur « Configuration generale ».');
        $this->assertStringContainsString('recherche=plafond', $cible,
            'Les filtres doivent etre reportes : les perdre annule la recherche '
            . 'en cours.');
    }

    public function test_l_onglet_audit_est_present_pour_l_administrateur(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/parametre')->getContent();

        $this->assertStringContainsString('id="tab-audit-tab"', $html,
            'L onglet Audit manque : le journal n est plus accessible nulle part.');
        $this->assertStringContainsString('id="journalAudit"', $html,
            'Le corps du journal n est pas rendu dans l onglet.');
    }

    /**
     * LE GESTIONNAIRE NE VOIT PAS LE JOURNAL.
     *
     * « Paramètre » lui est ouvert ; le journal ne l'a jamais été — l'ancienne
     * route portait `admin.seulement`. Le déplacer dans un onglet ne doit pas
     * le lui livrer au passage.
     */
    public function test_le_gestionnaire_ne_voit_pas_l_onglet_audit(): void
    {
        $html = $this->actingAs($this->unGestionnaire())->get('/parametre')->getContent();

        $this->assertStringNotContainsString('id="tab-audit-tab"', $html,
            'Le gestionnaire voit l onglet Audit : le deplacement lui a ouvert '
            . 'un ecran qui lui etait ferme.');
        $this->assertStringNotContainsString('id="journalAudit"', $html,
            'Le journal est rendu pour le gestionnaire.');
    }

    /* ===================================================================
       MENU « CONFIGURATION »
       =================================================================== */

    public function test_configuration_regroupe_administrateurs_gestionnaires_et_agences(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/parametre')->getContent();

        $this->assertStringContainsString('<span class="text">Configuration</span>', $html,
            'Le menu principal « Configuration » est absent.');

        // « Configuration » vit dans navConfiguration depuis le 07/09/2026 (point 1).
        $menu = file_get_contents(resource_path('views/layout/navbar/navConfiguration.blade.php'));
        $configuration = substr($menu, strpos($menu, 'Configuration</span>'));

        foreach (['show.listeAdmin', 'show.listeGestionnaire', 'show.agences.index'] as $route) {
            $this->assertStringContainsString($route, $configuration,
                "« $route » n est pas sous « Configuration ».");
        }
    }

    /* ===================================================================
       « DETTES » SOUS « ETAT »
       =================================================================== */

    public function test_les_dettes_sont_sous_etat_pour_l_administrateur(): void
    {
        // « Etat » vit dans navEtats depuis le 07/09/2026 (point 1).
        $menu = file_get_contents(resource_path('views/layout/navbar/navEtats.blade.php'));

        $debutEtat = strpos($menu, '<span class="text">Etat</span>');
        $this->assertNotFalse($debutEtat, 'Le menu « Etat » est introuvable.');

        $finEtat = strpos($menu, '</li>', $debutEtat);
        $etat    = substr($menu, $debutEtat, $finEtat - $debutEtat);

        foreach (['show.dettesApporteurs', 'show.dettesFournisseurs', 'show.dettesLivreurs'] as $route) {
            $this->assertStringContainsString($route, $etat,
                "« $route » n est pas dans le menu « Etat ».");
        }
    }

    /**
     * LE GESTIONNAIRE GARDE SES DETTES.
     *
     * « Etat » ne lui est pas affiché : y déplacer les dettes pour tout le
     * monde lui aurait retiré trois écrans que la route ne lui a jamais
     * fermés.
     */
    public function test_le_gestionnaire_garde_l_acces_aux_dettes(): void
    {
        $html = $this->actingAs($this->unGestionnaire())->get('/parametre')->getContent();

        $this->assertStringContainsString('/dettes/fournisseurs', $html,
            'Le gestionnaire n a plus aucune entree vers les dettes.');
    }

    /* ===================================================================
       LA FENÊTRE DE DÉTAIL DU JOURNAL NE TREMBLE PLUS

       Bootstrap 4 et 5 sont chargés ensemble sur cet écran. Ouverte par
       « data-bs-toggle » depuis l'intérieur du panneau d'onglet, la fenêtre
       recevait DEUX voiles et deux pièges à focus concurrents : elle
       tremblait. C'est le défaut déjà rencontré sur les fenêtres « Voir les
       produits », et la page portait déjà sa réponse — un mécanisme manuel.
       =================================================================== */

    public function test_la_fenetre_d_audit_n_est_pas_ouverte_par_bootstrap(): void
    {
        $journal = file_get_contents(
            resource_path('views/admin/audit/_journal.blade.php'));

        $this->assertStringNotContainsString('data-bs-toggle="modal"', $journal,
            'Le bouton « Details » rappelle Bootstrap : les deux versions '
            . 'chargees repondront, et la fenetre tremblera de nouveau.');

        $this->assertStringContainsString('data-modal-id="modalAudit"', $journal,
            'Le bouton doit designer sa fenetre pour le mecanisme manuel.');
    }

    public function test_la_fenetre_d_audit_est_hors_du_panneau_d_onglet(): void
    {
        $journal = file_get_contents(
            resource_path('views/admin/audit/_journal.blade.php'));

        // On cherche la FENÊTRE, pas l'identifiant : « data-modal-id="modalAudit" »
        // contient littéralement « id="modalAudit" », et l'essai se trompait de
        // cible en croyant la trouver.
        $this->assertStringNotContainsString('class="modal fade', $journal,
            'La fenetre est restee dans le corps du journal, donc dans le '
            . 'panneau d onglet : c est cette position qui la faisait trembler.');

        $page = file_get_contents(resource_path('views/layout/parametre.blade.php'));

        $positionPanneaux = strpos($page, '/tab-content');
        $positionFenetre  = strpos($page, "@include('admin.audit._fenetre-detail')");

        $this->assertNotFalse($positionFenetre, 'La fenetre n est incluse nulle part.');
        $this->assertGreaterThan($positionPanneaux, $positionFenetre,
            'La fenetre doit etre incluse APRES les panneaux d onglet, aupres '
            . 'des autres fenetres de l ecran.');
    }

    /** ELLE RÉPOND AU MÊME MÉCANISME QUE LES AUTRES FENÊTRES DE L'ÉCRAN. */
    public function test_la_fenetre_d_audit_partage_le_mecanisme_de_l_ecran(): void
    {
        $fenetre = file_get_contents(
            resource_path('views/admin/audit/_fenetre-detail.blade.php'));

        $this->assertStringContainsString('param-modal', $fenetre,
            'Sans la classe commune, ni le clic hors de la fenetre ni la '
            . 'touche Echap ne la fermeront.');

        $this->assertStringNotContainsString('data-bs-dismiss="modal"', $fenetre,
            'Les boutons de fermeture rappellent Bootstrap.');

        $this->assertStringContainsString('btn-close-modal', $fenetre,
            'Les boutons de fermeture doivent passer par le mecanisme manuel.');

        $page = file_get_contents(resource_path('views/layout/parametre.blade.php'));

        $this->assertStringContainsString(
            "openParamModal(\$b.data('modal-id'))", $page,
            'Le bouton « Details » n ouvre pas la fenetre.');

        $this->assertStringNotContainsString(
            "\$('.param-modal-produits').removeClass", $page,
            'La fermeture ne vise que les fenetres « produits » : celle de '
            . 'l audit resterait ouverte.');
    }

    /** LA PAGE RÉCUPÈRE LA LARGEUR QUE LES RETRAITS EMPILÉS LUI PRENAIENT. */
    public function test_la_page_parametre_recupere_de_la_largeur(): void
    {
        $page = file_get_contents(resource_path('views/layout/parametre.blade.php'));

        $this->assertStringContainsString(
            '.main-wrap > .content-main > .content-main > .content-main', $page,
            'Le troisieme « .content-main » garde son retrait de 3 % : la carte '
            . 'perd pres de 160 px, et les colonnes du journal se serrent.');
    }

    /* ===================================================================
       « AGENT » SOUS « CONFIGURATION »
       =================================================================== */

    public function test_agent_est_sous_configuration(): void
    {
        // « Configuration » vit dans navConfiguration depuis le 07/09/2026 (point 1).
        $menu = file_get_contents(resource_path('views/layout/navbar/navConfiguration.blade.php'));

        $configuration = substr($menu, strpos($menu, 'Configuration</span>'));
        $configuration = substr($configuration, 0, strpos($configuration, '</li>'));

        foreach (['show.listeAgent', 'show.AgentRegister'] as $route) {
            $this->assertStringContainsString($route, $configuration,
                "« $route » n est pas sous « Configuration ».");
        }

        $this->assertSame(1, substr_count($menu, "route('show.listeAgent')"),
            'Le menu principal « Agent » subsiste en plus du sous-menu.');
    }

    /* ===================================================================
       LES BOUTONS D'EXPORT SUR TOUTES LES LISTES DU BACK-OFFICE
       =================================================================== */

    public static function autresEcransExportables(): array
    {
        return [
            'Liste des agents'       => ['/liste-agent'],
            'Retours produits'       => ['/liste-de-retour-produit'],
            'Demandes d annulation'  => ['/demandes-annulation'],
            'Tickets SAV'            => ['/ticket-SAV'],
            'Livraisons en cours'    => ['/livraison-en-cours'],
            'Livraisons validees'    => ['/livraison-validees'],
            'Historique livraisons'  => ['/livraison-historique'],
            'Locations en attente'   => ['/liste-des-location-en-attente'],
            'Locations traitees'     => ['/locations-traitees'],
            'Demandes de livraison'  => ['/liste-demande-de-livraison'],
            'Demandes traitees'      => ['/liste-demande-de-livraison-traitee'],
            'Grille tarifaire'       => ['/grille-tarifaire'],
            'Bons en attente'        => ['/bonAttente'],
            'Factures non validees'  => ['/factures-non-validees'],
            'Factures validees'      => ['/factures-validees'],
            'Regions'                => ['/les-regions'],
            'Villes'                 => ['/les-villes'],
            'Categories produits'    => ['/products-category'],
            'Codes promo'            => ['/creation-de-code-promo'],
            'Moderation produits'    => ['/moderation-de-commentaire'],
            'Moderation blog'        => ['/moderation-commentaires-blog'],
            'Blogs'                  => ['/liste-des-Blogs'],
            'Bannieres'              => ['/liste-des-Bannieres'],
            'Diapositives'           => ['/liste-des-Slides'],
            'Messages de contact'    => ['/messages-de-contact'],
            'Pourcentages DALAKOUN'  => ['/pourcentage-dalakoun'],
            'Historique demandes'    => ['/historique-demande-de-paiemennt'],
            'Grand livre ordinaire'  => ['/grand-livre-client-ordinaire'],
            'Grand livre a terme'    => ['/grand-livre-client-client-a-terme'],
            'Grand livre livreur'    => ['/grand-livre-livreur'],
            'Grand livre fournisseur'=> ['/grand-livre-fournisseur'],
        ];
    }

    /** @dataProvider autresEcransExportables */
    public function test_la_liste_porte_les_trois_boutons(string $adresse): void
    {
        $reponse = $this->actingAs($this->unAdmin())->get($adresse);

        if (in_array($reponse->getStatusCode(), [404, 405], true)) {
            $this->markTestSkipped("Adresse absente de ce depot : $adresse");
        }

        $reponse->assertOk();

        $html = $reponse->getContent();

        // CERTAINS ECRANS MASQUENT LEUR TABLEAU QUAND LA LISTE EST VIDE
        // (« Aucune facture en attente… ») : il n y a alors rien a exporter,
        // et rien a controler ici. La presence du composant dans la vue est,
        // elle, verifiee par l essai suivant, qui ne depend d aucune donnee.
        if (!preg_match('/<table[^>]*\bid="/', $html)) {
            $this->markTestSkipped("Liste vide, aucun tableau rendu : $adresse");
        }

        foreach (['GravierExport.toExcel', 'GravierExport.toWord', 'GravierExport.toPdf'] as $appel) {
            $this->assertStringContainsString($appel, $html,
                "L ecran $adresse ne propose pas l export « $appel ».");
        }
    }

    /**
     * TOUTES LES LISTES DU BACK-OFFICE PORTENT LE COMPOSANT.
     *
     * Contrôle indépendant des données : une liste vide ne rend pas son
     * tableau, et l essai precedent ne peut alors rien affirmer.
     */
    public function test_aucune_liste_du_back_office_ne_reste_sans_export(): void
    {
        $racine = resource_path('views');
        $sans   = [];

        $fichiers = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($racine, \FilesystemIterator::SKIP_DOTS)
        );

        // Ecrans de DETAIL, de FORMULAIRE ou de TRAITEMENT : on y exporterait
        // une fiche, pas une liste. Et le tableau de bord porte deja ses
        // propres synthèses.
        $ecartes = [
            'gestionnaire/traiteLivraison', 'grand-livre/fournisseurBon',
            'layout/index', 'layout/parametre', 'livreur/profile',
            'livreur/dashboard', 'apporteur/dashboard',
        ];

        foreach ($fichiers as $f) {
            if (!str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }

            $court = str_replace([$racine . DIRECTORY_SEPARATOR, '\\', '.blade.php'], ['', '/', ''],
                $f->getPathname());

            if (in_array($court, $ecartes, true)) {
                continue;
            }

            $source = file_get_contents($f->getPathname());

            if (!str_contains($source, "@extends('layout.main')")) {
                continue;
            }
            // Un tableau IDENTIFIE signale une liste ; les tableaux de mise en
            // page ou de reçu n en portent pas.
            if (!preg_match('/<table[^>]*\bid="/', $source)) {
                continue;
            }
            if (str_contains($source, 'x-export-buttons')) {
                continue;
            }

            $sans[] = $court;
        }

        $this->assertSame([], $sans,
            "Ces listes du back-office n offrent pas les exports Excel / Word / "
            . 'PDF : ' . implode(', ', $sans));
    }

    /**
     * LE TABLEAU EST INITIALISÉ COMME LES AUTRES.
     *
     * « $('.table').DataTable() » atteint TOUS les tableaux de la page, et sans
     * garde-fou une liste vide dont la seule ligne porte un « colspan » fait
     * lever « Requested unknown parameter » : le tableau reste alors brut,
     * sans recherche, sans tri ni pagination.
     */
    public function test_les_bons_et_factures_ciblent_leur_tableau(): void
    {
        foreach ([
            'gestionnaire/bonEnAttente'  => 'tableBonsAttente',
            'gestionnaire/bonValides'    => 'tableBonsValides',
            'orders/facturesNonValidees' => 'tableFacturesNonValidees',
            'orders/facturesValidees'    => 'tableFacturesValidees',
        ] as $vue => $identifiant) {
            $source = file_get_contents(resource_path("views/$vue.blade.php"));

            $this->assertStringNotContainsString("\$('.table').DataTable(", $source,
                "$vue transforme TOUS les tableaux de la page en DataTable.");

            $this->assertStringContainsString("\$('#$identifiant')", $source,
                "$vue ne cible pas son tableau par son identifiant.");

            $this->assertStringContainsString('td[colspan]', $source,
                "$vue n a pas le garde-fou : une liste vide casserait le tableau.");
        }
    }

    /* ===================================================================
       LA LETTRE D'INFORMATION N'A PLUS DEUX JEUX DE BOUTONS

       Cette page portait déjà trois boutons en haut à droite, servis par des
       routes qui produisent le document CÔTÉ SERVEUR. Le balayage du
       29/08/2026 en a posé un second jeu au-dessus du tableau, comme sur
       toutes les autres listes : deux jeux pour la même chose.

       Le jeu du haut est retiré. Mais ce sont bien les documents du SERVEUR
       que l'autre jeu appelle désormais — et c'est ce qui compte : l'écran
       affiche aussi la corbeille (`withTrashed`), qu'un export reconstitué
       depuis les lignes affichées embarquerait.
       =================================================================== */

    public function test_les_abonnes_n_ont_qu_un_seul_jeu_de_boutons(): void
    {
        $html = $this->actingAs($this->unAdmin())
            ->get('/lettre-information/abonnes')->getContent();

        foreach (['Excel', 'Word', 'PDF'] as $libelle) {
            $this->assertSame(1, preg_match_all('/>\s*' . $libelle . '\s*</', $html),
                "L ecran propose « $libelle » plus d une fois : les deux jeux de "
                . 'boutons coexistent encore.');
        }
    }

    public function test_les_exports_des_abonnes_viennent_du_serveur(): void
    {
        $html = $this->actingAs($this->unAdmin())
            ->get('/lettre-information/abonnes')->getContent();

        foreach (['excel', 'word', 'pdf'] as $format) {
            $this->assertStringContainsString("/lettre-information/export/$format", $html,
                "L export « $format » ne passe plus par le serveur : reconstruit "
                . 'depuis les lignes affichees, il embarquerait la corbeille.');
        }

        // Aucun des trois ne doit être généré dans le navigateur ici.
        $this->assertStringNotContainsString('GravierExport.toExcel', $html,
            'L Excel est encore reconstitue depuis le tableau affiche.');
        $this->assertStringNotContainsString('GravierExport.toPdf', $html,
            'Le PDF est encore reconstitue depuis le tableau affiche.');
    }

    /**
     * LE COMPOSANT GARDE SON COMPORTEMENT PARTOUT AILLEURS.
     *
     * L'ajout de « excel-url » ne devait rien changer aux écrans qui ne le
     * fournissent pas : ils continuent de produire le classeur dans le
     * navigateur.
     */
    public function test_ailleurs_l_excel_reste_produit_par_le_navigateur(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/les-regions')->getContent();

        $this->assertStringContainsString('GravierExport.toExcel', $html,
            'Un ecran sans « excel-url » doit continuer a produire son classeur '
            . 'dans le navigateur.');
    }

    /* ===================================================================
       BONS ET FACTURES : LA PRÉSENTATION DES AUTRES LISTES

       Ces quatre écrans gardaient un simple titre, une carte nue et un
       en-tête de tableau peint à la main, là où toutes les autres listes
       portent un bandeau, une bande de compteurs et la carte commune.

       Surtout : leur tableau — donc AUSSI les boutons d'export — était
       enfermé dans un « @if (liste non vide) ». Sur une liste vide, l'écran
       n'affichait qu'une phrase : ni tableau, ni recherche, ni boutons. C'est
       ce qui les distinguait des autres, et c'est ce qu'on voyait en ligne.
       =================================================================== */

    public static function bonsEtFactures(): array
    {
        return [
            'Bons en attente'       => ['/bonAttente'],
            'Bons valides'          => ['/bonValides'],
            'Factures non validees' => ['/factures-non-validees'],
            'Factures validees'     => ['/factures-validees'],
        ];
    }

    /** @dataProvider bonsEtFactures */
    public function test_le_tableau_reste_affiche_meme_vide(string $adresse): void
    {
        $reponse = $this->actingAs($this->unAdmin())->get($adresse);
        $reponse->assertOk();

        $html = $reponse->getContent();

        $this->assertMatchesRegularExpression('/<table[^>]*\bid="/', $html,
            "L ecran $adresse ne rend aucun tableau : sur une liste vide il "
            . 'ne montrait qu une phrase, sans recherche ni pagination.');

        foreach (['GravierExport.toExcel', 'GravierExport.toWord', 'GravierExport.toPdf'] as $appel) {
            $this->assertStringContainsString($appel, $html,
                "L ecran $adresse perd l export « $appel » quand la liste est vide.");
        }
    }

    /** @dataProvider bonsEtFactures */
    public function test_l_ecran_porte_la_presentation_commune(string $adresse): void
    {
        $html = $this->actingAs($this->unAdmin())->get($adresse)->getContent();

        $this->assertStringContainsString('dash-welcome', $html,
            "L ecran $adresse n a pas le bandeau des autres listes.");

        $this->assertStringContainsString('kpi-card-value', $html,
            "L ecran $adresse n a pas sa bande de compteurs.");

        $this->assertStringContainsString('dash-table', $html,
            "Le tableau de $adresse ne porte pas la presentation commune.");
    }

    /** L'EN-TÊTE N'EST PLUS PEINT À LA MAIN. */
    public function test_les_factures_n_ont_plus_d_entete_peint_a_la_main(): void
    {
        foreach (['orders/facturesNonValidees', 'orders/facturesValidees'] as $vue) {
            $source = file_get_contents(resource_path("views/$vue.blade.php"));

            $this->assertStringNotContainsString(
                '<thead style="background-color: #1c57a3; color: white;">', $source,
                "$vue peint son en-tete a la main au lieu de suivre le theme.");
        }
    }

    /* ===================================================================
       LA LISTE PASSE DEVANT, LE FORMULAIRE PREND SA PROPRE PAGE

       Ces quatre écrans montraient le formulaire de saisie À CÔTÉ de la
       liste, en permanence. On vient pourtant y consulter dix fois pour
       saisir une fois : la liste était comprimée sur une moitié d'écran par
       un formulaire dont on n'avait pas besoin.
       =================================================================== */

    public static function ecransListeEtFormulaire(): array
    {
        return [
            // Les regions et les villes ne sont plus ici : leur formulaire a
            // quitte la liste pour sa propre page. C'est PageDeSaisieTest qui
            // en repond.
            'Codes promo' => ['/creation-de-code-promo', 'enctype="multipart/form-data"', 'Nouveau code promo'],
            // Les categories ne sont plus ici : leur formulaire a quitte la
            // liste pour sa propre page (/nouvelle-categorie). C'est
            // PageCategorieTest qui en repond.
        ];
    }

    /** @dataProvider ecransListeEtFormulaire */
    public function test_la_liste_s_affiche_seule_par_defaut(
        string $adresse, string $marqueurFormulaire, string $bouton): void
    {
        $html = $this->actingAs($this->unAdmin())->get($adresse)->getContent();

        $this->assertStringNotContainsString($marqueurFormulaire, $html,
            "Le formulaire de saisie occupe encore $adresse alors qu'on vient "
            . 'seulement consulter la liste.');

        $this->assertMatchesRegularExpression('/<table[^>]*\bid="/', $html,
            "La liste doit rester affichée sur $adresse.");

        $this->assertStringContainsString('nouveau=1', $html,
            "Aucun bouton n'ouvre le formulaire depuis $adresse : la saisie "
            . 'deviendrait inatteignable.');

        $this->assertStringContainsString($bouton, $html,
            "Le bouton d'ouverture du formulaire doit se nommer « $bouton ».");
    }

    /** @dataProvider ecransListeEtFormulaire */
    public function test_le_formulaire_s_ouvre_sur_demande(
        string $adresse, string $marqueurFormulaire, string $bouton): void
    {
        $html = $this->actingAs($this->unAdmin())->get($adresse . '?nouveau=1')->getContent();

        $this->assertStringContainsString($marqueurFormulaire, $html,
            "Le formulaire ne s'ouvre pas sur $adresse?nouveau=1 : la saisie "
            . 'est perdue.');

        $this->assertStringContainsString('Retour à la liste', $html,
            "Depuis le formulaire, il faut pouvoir revenir à la liste.");
    }

    /**
     * LA MODIFICATION CONTINUE D'AFFICHER LE FORMULAIRE.
     *
     * Elle passe par ses propres adresses, qui rendent la MÊME vue avec
     * l'enregistrement chargé. Si le formulaire ne s'affichait que sur
     * « ?nouveau=1 », plus aucune modification ne serait possible.
     */
    public function test_la_modification_affiche_toujours_le_formulaire(): void
    {
        $region = \App\Models\Region::first();
        if (!$region) {
            $this->markTestSkipped('Aucune région en base.');
        }

        $html = $this->actingAs($this->unAdmin())
            ->get('/modifier-region-' . $region->id)->getContent();

        $this->assertStringContainsString('name="nom"', $html,
            "L'écran de modification n'affiche plus son formulaire.");

        $this->assertStringContainsString('Modifier la région', $html,
            "L'écran doit annoncer qu'on modifie, et non qu'on crée.");
    }

    /**
     * LA CARTE NE S'INITIALISE QUE LÀ OÙ ELLE EXISTE.
     *
     * Depuis que la liste s'affiche seule, le script de la carte tournait sur
     * une page sans conteneur : Leaflet levait « Map container not found. » —
     * une erreur non rattrapée, qui aurait fait taire tout ce qu'on aurait
     * ajouté à sa suite dans ce même bloc.
     */
    public function test_la_carte_est_gardee_par_un_garde_fou(): void
    {
        // La carte a suivi le formulaire sur sa page le 03/09/2026.
        $source = file_get_contents(resource_path('views/gestionnaire/formRegion.blade.php'));

        $garde = strpos($source, "if (!document.getElementById('map')) return;");
        $init = strpos($source, "L.map('map')");

        $this->assertNotFalse($garde,
            "Le script de la carte n'a pas de garde-fou : il leve « Map container "
            . 'not found » sur la page de liste.');

        $this->assertLessThan($init, $garde,
            'Le garde-fou doit precéder l’initialisation, sinon il ne sert à rien.');
    }

    /* ===================================================================
       LE FOURNISSEUR SE MODIFIE D'UN BOUTON

       La modification existait, mais enfouie dans le menu « Actions » : il
       fallait déjà savoir qu'elle était là pour l'y chercher. C'est l'action
       la plus courante sur cette liste.
       =================================================================== */

    public function test_le_fournisseur_se_modifie_d_un_bouton(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/sellers-list')->getContent();

        // ON REGARDE DANS LE TABLEAU, PAS DANS LA PAGE ENTIÈRE.
        //
        // Les fenêtres « Voir les produits » portent depuis toujours un
        // « Modifier la liste » de classe « btn ». Chercher dans toute la page,
        // c'était le trouver LUI : l'essai passait au vert même après avoir
        // reconverti le bouton en simple entrée de menu. Constaté en
        // réintroduisant le défaut — il ne mordait pas.
        // L'identifiant n'est pas le premier attribut de la balise : on le
        // cherche tel qu'il est écrit, puis on remonte au début du tableau.
        $marque = strpos($html, 'id="liste"');
        $this->assertNotFalse($marque, 'Le tableau des fournisseurs est introuvable.');
        $debut = strrpos(substr($html, 0, $marque), '<table');
        $tableau = substr($html, $debut, strpos($html, '</table>', $debut) - $debut);

        $this->assertMatchesRegularExpression(
            '/<a[^>]+href="[^"]*\/modify"[^>]*class="[^"]*\bbtn\b[^"]*"/', $tableau,
            "La modification d'un fournisseur n'est pas proposée par un bouton "
            . 'visible dans la colonne des actions du tableau.');

        // L'INTITULÉ A QUITTÉ LE BOUTON POUR SON TITRE.
        //
        // Les listes portent désormais des icônes, et le mot se lit au survol
        // (demande du 02/09/2026). L'intention de cet essai ne change pas : le
        // bouton doit rester IDENTIFIABLE sans cliquer.
        $this->assertMatchesRegularExpression('/title="Modifier[^"]*"/', $tableau,
            'Le bouton de modification ne dit pas son nom : ni texte, ni titre au survol.');
    }

    /** ET L'ENTRÉE DU MENU NE FAIT PLUS DOUBLON. */
    public function test_la_modification_du_fournisseur_n_est_plus_en_double(): void
    {
        $source = file_get_contents(resource_path('views/fournisseur/sellers-list.blade.php'));

        $this->assertStringNotContainsString(
            'class="dropdown-item">Modifier les infos', $source,
            'La modification figure à la fois en bouton et dans le menu : deux '
            . 'chemins pour la même chose.');
    }

    /* ===================================================================
       LE BOUTON « RETOUR » NE S'AFFICHE PAS SUR UNE PAGE DU MENU

       On n'y « revient » de nulle part : on y est allé d'un clic. La liste
       des écrans concernés était tenue à la main et avait dérivé — chaque
       entrée ajoutée au menu depuis gardait son bouton. Elle se déduit
       désormais du menu lui-même.
       =================================================================== */

    public static function pagesDuMenu(): array
    {
        return [
            'Tableau de bord'  => ['/gestionnaire/home'],
            'Regions'          => ['/les-regions'],
            'Locations'        => ['/liste-des-location-en-attente'],
            'Bons en attente'  => ['/bonAttente'],
            'Cautions'         => ['/comptabilite/etat-cautions'],
            'Commissions'      => ['/apporteur/commissions'],
        ];
    }

    /** @dataProvider pagesDuMenu */
    public function test_pas_de_bouton_retour_sur_une_page_du_menu(string $adresse): void
    {
        $html = $this->actingAs($this->unAdmin())->get($adresse)->getContent();

        $this->assertStringNotContainsString('id="globalBackBtn"', $html,
            "L'écran $adresse s'ouvre d'un clic dans le menu : le bouton "
            . '« Retour » n’y a aucun sens.');
    }

    /** MAIS IL RESTE LÀ OÙ IL SERT. */
    public function test_le_bouton_retour_subsiste_hors_du_menu(): void
    {
        $region = \App\Models\Region::first();
        if (!$region) {
            $this->markTestSkipped('Aucune région en base.');
        }

        $html = $this->actingAs($this->unAdmin())
            ->get('/modifier-region-' . $region->id)->getContent();

        $this->assertStringContainsString('id="globalBackBtn"', $html,
            "Cet écran ne s'atteint pas depuis le menu : sans bouton « Retour », "
            . 'on y serait enfermé.');
    }

    /** LA LISTE SE DÉDUIT DU MENU, ELLE NE SE TIENT PLUS À LA MAIN. */
    public function test_les_routes_du_menu_sont_deduites_du_menu(): void
    {
        $noms = \App\Support\MenusLateraux::nomsDeRoute();

        $this->assertGreaterThan(50, count($noms),
            'Les gabarits du menu ne sont pas lus : la liste retombe sur celle '
            . 'tenue à la main, et le défaut revient.');

        foreach (['show.home', 'show.lesRegions', 'show.bonAttente'] as $attendu) {
            $this->assertContains($attendu, $noms,
                "La route « $attendu » figure au menu mais n'a pas été relevée.");
        }

        // Une route qui n'est PAS au menu ne doit pas y être comptée.
        $this->assertNotContains('show.modifierRegion', $noms,
            'Une route hors menu a été relevée : le bouton « Retour » '
            . 'disparaîtrait là où il sert.');
    }

    /* ===================================================================
       LES RÉGIONS INCOMPLÈTES SE VOIENT

       Onze des dix-neuf régions n'ont ni adresse ni coordonnées. Un simple
       tiret laissait croire à un détail d'affichage. C'en est un autre :
       une région sans coordonnées facture le coût de livraison MINIMUM,
       quelle que soit la distance réelle (Help::coutLivraison).
       =================================================================== */

    public function test_une_region_sans_adresse_est_signalee(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/les-regions')->getContent();

        $incompletes = \App\Models\Region::where(function ($q) {
            $q->whereNull('description')->orWhere('description', '');
        })->count();

        if (!$incompletes) {
            $this->markTestSkipped('Toutes les régions sont renseignées.');
        }

        $this->assertStringContainsString('Adresse à renseigner', $html,
            "Une région sans adresse n'affiche qu'un tiret : rien ne dit qu'il "
            . 'faut la compléter.');

        $this->assertStringContainsString('livraison facturée au minimum', $html,
            "La conséquence n'est pas dite : une région sans coordonnées fait "
            . 'facturer le minimum quelle que soit la distance.');

        $this->assertStringContainsString('région(s) à compléter', $html,
            'Le compteur doit annoncer combien de régions restent à compléter.');
    }

    /* ===================================================================
       LE TABLEAU DE BORD : EXPORTS, RECHERCHE ET PAGINATION
       =================================================================== */

    public function test_les_dernieres_commandes_sont_exportables_et_filtrables(): void
    {
        $html = $this->actingAs($this->unAdmin())->get('/gestionnaire/home')->getContent();

        foreach (['GravierExport.toExcel', 'GravierExport.toWord', 'GravierExport.toPdf'] as $appel) {
            $this->assertStringContainsString($appel, $html,
                "Le tableau des dernières commandes n'a pas l'export « $appel ».");
        }

        $this->assertStringContainsString('datatables.min.js', $html,
            'Sans DataTables, le tableau n’a ni recherche ni pagination.');

        $this->assertStringContainsString("$('#lastOrdersTable')", $html,
            'La recherche et la pagination doivent viser CE tableau, par son '
            . 'identifiant.');
    }

    /**
     * DIX LIGNES NE SE CHERCHENT PAS.
     *
     * Le tableau n'en chargeait que dix, toutes visibles : une recherche et
     * une pagination n'y auraient servi à rien.
     */
    public function test_le_tableau_de_bord_charge_de_quoi_chercher(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/UserController.php'));
        $debut = strpos($source, 'public function home ()');
        $corps = substr($source, $debut, 1200);

        $this->assertStringContainsString('->limit(50)', $corps,
            'Le tableau de bord ne charge pas assez de commandes pour que la '
            . 'recherche et la pagination aient un sens.');
    }

    /* ===================================================================
       UN EXPORT NE DOIT PAS S'ARRÊTER À LA PAGE AFFICHÉE

       DataTables RETIRE DU DOM les lignes des autres pages. Lire
       « tbody tr » ne rendait donc que la page courante : sur 31 commandes
       paginées par 10, l'export en emportait 10 et se taisait sur les 21
       autres. Découvert en équipant le tableau de bord — le défaut valait
       pour TOUTES les listes paginées du back-office.
       =================================================================== */

    public function test_l_export_prend_toutes_les_pages(): void
    {
        $composant = file_get_contents(
            resource_path('views/components/export-buttons.blade.php'));

        $this->assertStringContainsString(
            "rows({ search: 'applied' }).nodes()", $composant,
            "L'export lit le DOM : il n'emporte que la page affichée, et se tait "
            . 'sur les autres.');

        $this->assertStringContainsString('isDataTable($table)', $composant,
            'La lecture doit passer par DataTables quand il pilote le tableau, '
            . 'et retomber sur le DOM sinon.');

        // Le repli doit subsister : tous les tableaux ne sont pas des DataTables.
        // Guillemets SIMPLES : en guillemets doubles, PHP interpolait « $table »
        // et l'essai cherchait « .querySelectorAll » tout court.
        $this->assertStringContainsString(
            '$table.querySelectorAll(\'tbody tr\')', $composant,
            'Sans repli, un tableau simple ne s’exporterait plus du tout.');
    }

    /* ===================================================================
       LE TABLEAU DE BORD DU FOURNISSEUR AUSSI

       Même traitement que celui de l'administrateur : ses « Dernières
       livraisons » n'avaient ni export, ni recherche, ni pagination.
       =================================================================== */

    private function unFournisseur(): User
    {
        $frn = \App\Models\Fournisseur::whereNotNull('user_id')->first();

        if (!$frn || !$frn->user_id) {
            $this->markTestSkipped('Aucun fournisseur rattaché à un compte.');
        }

        $u = User::find($frn->user_id);

        if (!$u) {
            $this->markTestSkipped('Le compte du fournisseur est introuvable.');
        }

        return $u;
    }

    public function test_le_tableau_de_bord_fournisseur_est_exportable_et_filtrable(): void
    {
        $html = $this->actingAs($this->unFournisseur())->get('/sellers-home')->getContent();

        foreach (['GravierExport.toExcel', 'GravierExport.toWord', 'GravierExport.toPdf'] as $appel) {
            $this->assertStringContainsString($appel, $html,
                "Le tableau de bord du fournisseur n'a pas l'export « $appel ».");
        }

        $this->assertStringContainsString('datatables.min.js', $html,
            'Sans DataTables, le tableau n’a ni recherche ni pagination.');

        $this->assertStringContainsString("$('#dernieresLivraisonsFournisseur')", $html,
            'La recherche et la pagination doivent viser CE tableau, par son '
            . 'identifiant.');

        $this->assertStringContainsString('id="dernieresLivraisonsFournisseur"', $html,
            'Le tableau doit porter l’identifiant que l’export et DataTables '
            . 'attendent.');
    }

    /** DIX LIGNES NE SE CHERCHENT PAS. */
    public function test_le_tableau_de_bord_fournisseur_charge_de_quoi_chercher(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/SellerController.php'));

        $this->assertStringContainsString('$bons->take(50)', $source,
            'Le tableau de bord du fournisseur ne charge pas assez de bons pour '
            . 'que la recherche et la pagination aient un sens.');
    }
}
