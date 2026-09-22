@php
    // ORDRE DU MENU VALIDÉ LE 07/09/2026 (point 1 du cahier d'évolutions) :
    // le quotidien en haut (commandes, livraisons, locations, demandes,
    // clients), un seul parent « Partenaires » pour les trois tiers
    // (fournisseurs, livreurs, apporteurs), puis les factures et bons, les
    // demandes de paiement, le pilotage (paiements reçus, comptabilité, états)
    // en bas de la partie métier ; enfin le catalogue, la configuration et le
    // reste. Aucune entrée n'a disparu : chaque page reste accessible.
    $estAdmin = (int) Auth::user()->type_user_id === (int) Help::$USER_ADMIN;

    $isCommandesActive = request()->routeIs('orders.list', 'orders.commandesTraitees', 'show.listeRetourProduit', 'show.demandesAnnulation', 'orders.listeDesDevis', 'show.ticketSAV');
    $isLivraisonsActive = request()->routeIs('show.livraisonEnCours', 'show.livraisonValidees', 'show.livraisonHistorique');
    $isLocationsActive = request()->routeIs(
        'show.listeLocationEnAttente',
        'show.locationsTraitees',
        'show.encaissements.locations'
    );
    $isDemandeLivraisonActive = request()->routeIs(
        'show.demandeLivraisonlist',
        'show.demandeLivraisonTraitee',
        'show.comptant.livraisons.encaissements'
    );
    $isFournisseursActive = request()->routeIs(
        'show.listSeller', 'show.registerSeller', 'show.listSellerPourBon',
        'show.fournisseurs.enlevements', 'show.fournisseurs.paiements', 'show.fournisseurs.synthese'
    );
    $isFacturesBonsActive = request()->routeIs('show.bonAttente', 'show.bonValides', 'orders.facturesNonValidees', 'orders.facturesValidees');
    $isApporteurActive = request()->routeIs(
        'show.listApporteur', 'show.commissions',
        'show.apporteurs.commissions', 'show.apporteurs.paiements', 'show.apporteurs.synthese'
    );
    // La grille tarifaire des livraisons vit sous « Livreurs » depuis le
    // 07/09/2026 : c'est le barème qui rémunère les courses.
    $isLivreursActive = request()->routeIs(
        'show.list', 'show.registerLivreur',
        'show.livreurs.livraisons', 'show.livreurs.paiements', 'show.livreurs.synthese',
        'show.grilleTarifaire'
    );
    // LE PARENT RESTE OUVERT SUR TOUTES LES PAGES DE SES ENFANTS.
    $isPartenairesActive = $isFournisseursActive || $isLivreursActive || $isApporteurActive;
    $isProduitsActive = request()->routeIs('product.list', 'product.category', 'product.nouvelleCategorie',
        'product.editCategory', 'product.add', 'product.pourcentage');
    $isCodePromoActive = request()->routeIs('show.creationDeCodePromo');
    $isModerationActive = request()->routeIs('show.moderationCommentaire', 'show.moderationCommentairesBlog');
    $isCatalogueActive = $isProduitsActive || $isCodePromoActive || $isModerationActive;
    $isPaiementsActive = request()->routeIs('paye.list');
    $isDemandesPaiementActive = request()->routeIs('show.listeDeDemandeLivreur', 'show.listeDeDemandeApporteur', 'show.listeDeDemandeFournisseur', 'show.historiqueDemande');
    $isDettesActive = request()->routeIs('show.dettesApporteurs', 'show.dettesFournisseurs', 'show.dettesLivreurs');
    // « Divers » reprend EXACTEMENT les routes que surlignent ses entrées
    // filles, liste, création et modification comprises.
    $isDiversActive = request()->routeIs(
        'show.listeDesBlogs', 'show.creationDeBlog', 'show.modificationDeBlogPage',
        'show.commentaireBlogs',
        'show.listeDesBannieres', 'show.creationDeBanniere', 'show.modificationDeBannierePage',
        'show.listeDesSlides', 'show.creationDeSlide', 'show.modificationDeSlidePage',
        'show.lesRegions', 'show.nouvelleRegion', 'show.modifierRegion',
        'dest.lesVilles', 'dest.nouvelleVille', 'dest.modifierVille',
        'show.agences.*', 'show.statutMetier.*',
        'show.typeVehiculeLivreur.*', 'newsletter.*', 'messagesContact.*');
    $isGrandLivreActive = request()->routeIs('grandLivre.*');
@endphp

<li class="menu-item {{ request()->routeIs('show.home') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('show.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Tableau de bord</span>
    </a>
</li>

{{-- ===================== LE QUOTIDIEN ===================== --}}
<li class="menu-item has-submenu {{ $isCommandesActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_cart"></i>
        <span class="text">Commandes</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('orders.list') ? 'active' : '' }}" href="{{ route('orders.list') }}">Commandes en attente</a>
        <a class="{{ request()->routeIs('orders.commandesTraitees') ? 'active' : '' }}" href="{{ route('orders.commandesTraitees') }}">Commandes traitées</a>
        <a class="{{ request()->routeIs('show.demandesAnnulation') ? 'active' : '' }}" href="{{ route('show.demandesAnnulation') }}">Demandes d'annulation</a>
        <a class="{{ request()->routeIs('show.listeRetourProduit') ? 'active' : '' }}" href="{{ route('show.listeRetourProduit') }}">Produits retournés</a>
        {{-- « Les devis » avant le SAV : on les traite plus souvent. --}}
        <a class="{{ request()->routeIs('orders.listeDesDevis') ? 'active' : '' }}" href="{{ route('orders.listeDesDevis') }}">Les devis</a>
        <a class="{{ request()->routeIs('show.ticketSAV') ? 'active' : '' }}" href="{{ route('show.ticketSAV') }}">Ticket SAV</a>
    </div>
</li>

<li class="menu-item has-submenu {{ $isLivraisonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-local_shipping"></i>
        <span class="text">Livraisons</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.livraisonEnCours') ? 'active' : '' }}" href="{{ route('show.livraisonEnCours') }}">Livraisons en cours</a>
        <a class="{{ request()->routeIs('show.livraisonValidees') ? 'active' : '' }}" href="{{ route('show.livraisonValidees') }}">Livraisons validées</a>
        <a class="{{ request()->routeIs('show.livraisonHistorique') ? 'active' : '' }}" href="{{ route('show.livraisonHistorique') }}">Historique des livraisons</a>
    </div>
</li>

<li class="menu-item has-submenu {{ $isLocationsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_cart"></i>
        <span class="text">Locations</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeLocationEnAttente') ? 'active' : '' }}" href="{{ route('show.listeLocationEnAttente') }}">Location en attente</a>
        <a class="{{ request()->routeIs('show.locationsTraitees') ? 'active' : '' }}" href="{{ route('show.locationsTraitees') }}">Locations traitées</a>
        {{-- La caisse des locations vit avec les locations : elle sert AUSSI
             les clients à terme, faute d'écran de créance pour ce service. --}}
        <a class="{{ request()->routeIs('show.encaissements.locations') ? 'active' : '' }}" href="{{ route('show.encaissements.locations') }}">Encaissements locations</a>
    </div>
</li>

<li class="menu-item has-submenu {{ $isDemandeLivraisonActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-local_shipping"></i>
        <span class="text">Demandes de livraison</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.demandeLivraisonlist') ? 'active' : '' }}" href="{{ route('show.demandeLivraisonlist') }}">Demande en attente</a>
        <a class="{{ request()->routeIs('show.demandeLivraisonTraitee') ? 'active' : '' }}" href="{{ route('show.demandeLivraisonTraitee') }}">Demande traitée</a>
        {{-- La grille chiffre le transport de tous les clients, et le guichet
             les encaisse tous — une demande de livraison ne génère aucune
             facture, elle n'entre donc pas dans les créances à terme. --}}
        <a class="{{ request()->routeIs('show.comptant.livraisons.encaissements') ? 'active' : '' }}" href="{{ route('show.comptant.livraisons.encaissements') }}">Encaissements demandes de livraison</a>
    </div>
</li>

{{-- CLIENT — administrateur seulement, comme avant. --}}
@if ($estAdmin)
    @include('layout.navbar.navClient')
@endif

{{-- ===================== LES PARTENAIRES ===================== --}}
{{-- Fournisseurs, livreurs et apporteurs ont exactement le même dessin de
     sous-menu (liste, création, dettes) : un seul parent dit ce qu'ils ont en
     commun et allège la barre de deux menus. --}}
<li class="menu-item has-submenu {{ $isPartenairesActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-groups"></i>
        <span class="text">Partenaires</span>
    </a>
    <div class="submenu">
        <div class="menu-item has-submenu {{ $isFournisseursActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Fournisseurs</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listSeller') ? 'active' : '' }}" href="{{ route('show.listSeller') }}">Liste des fournisseurs</a>
                <a class="{{ request()->routeIs('show.registerSeller') ? 'active' : '' }}" href="{{ route('show.registerSeller') }}">Création de compte</a>
                <a class="{{ request()->routeIs('show.listSellerPourBon') ? 'active' : '' }}" href="{{ route('show.listSellerPourBon') }}">Les bons d'enlèvement</a>
                <a class="{{ request()->routeIs('show.fournisseurs.enlevements') ? 'active' : '' }}" href="{{ route('show.fournisseurs.enlevements') }}">Dette &raquo; Enlèvements</a>
                <a class="{{ request()->routeIs('show.fournisseurs.paiements') ? 'active' : '' }}" href="{{ route('show.fournisseurs.paiements') }}">Dette &raquo; Paiements</a>
                <a class="{{ request()->routeIs('show.fournisseurs.synthese') ? 'active' : '' }}" href="{{ route('show.fournisseurs.synthese') }}">Dette &raquo; Synthèse</a>
            </div>
        </div>

        <div class="menu-item has-submenu {{ $isLivreursActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Livreurs</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.list') ? 'active' : '' }}" href="{{ route('show.list') }}">Liste des livreurs</a>
                <a class="{{ request()->routeIs('show.registerLivreur') ? 'active' : '' }}" href="{{ route('show.registerLivreur') }}">Création de compte</a>
                <a class="{{ request()->routeIs('show.livreurs.livraisons') ? 'active' : '' }}" href="{{ route('show.livreurs.livraisons') }}">Dette &raquo; Livraisons</a>
                <a class="{{ request()->routeIs('show.livreurs.paiements') ? 'active' : '' }}" href="{{ route('show.livreurs.paiements') }}">Dette &raquo; Paiements</a>
                <a class="{{ request()->routeIs('show.livreurs.synthese') ? 'active' : '' }}" href="{{ route('show.livreurs.synthese') }}">Dette &raquo; Synthèse</a>
                <a class="{{ request()->routeIs('show.grilleTarifaire') ? 'active' : '' }}" href="{{ route('show.grilleTarifaire') }}">Grille tarifaire livraison</a>
            </div>
        </div>

        <div class="menu-item has-submenu {{ $isApporteurActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Apporteurs d'affaires</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listApporteur') ? 'active' : '' }}" href="{{ route('show.listApporteur') }}">Liste des apporteurs</a>
                <a class="{{ request()->routeIs('show.commissions') ? 'active' : '' }}" href="{{route('show.commissions')}}">Commission des apporteurs</a>
                <a class="{{ request()->routeIs('show.apporteurs.commissions') ? 'active' : '' }}" href="{{ route('show.apporteurs.commissions') }}">Dette &raquo; Commissions</a>
                <a class="{{ request()->routeIs('show.apporteurs.paiements') ? 'active' : '' }}" href="{{ route('show.apporteurs.paiements') }}">Dette &raquo; Paiements</a>
                <a class="{{ request()->routeIs('show.apporteurs.synthese') ? 'active' : '' }}" href="{{ route('show.apporteurs.synthese') }}">Dette &raquo; Synthèse</a>
            </div>
        </div>
    </div>
</li>

<li class="menu-item has-submenu {{ $isFacturesBonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-receipt_long"></i>
        <span class="text">Factures & Bons d'enlèvement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.bonAttente') ? 'active' : '' }}" href="{{ route('show.bonAttente') }}">Bons en attente</a>
        <a class="{{ request()->routeIs('show.bonValides') ? 'active' : '' }}" href="{{ route('show.bonValides') }}">Bons validés</a>
        <a class="{{ request()->routeIs('orders.facturesNonValidees') ? 'active' : '' }}" href="{{ route('orders.facturesNonValidees') }}">Factures non validées</a>
        <a class="{{ request()->routeIs('orders.facturesValidees') ? 'active' : '' }}" href="{{ route('orders.facturesValidees') }}">Factures validées</a>
    </div>
</li>

{{-- Juste sous « Partenaires » et les factures : c'est leur circuit. --}}
<li class="menu-item has-submenu {{ $isDemandesPaiementActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-request_quote"></i>
        <span class="text">Demandes de paiement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeDeDemandeLivreur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeLivreur') }}">Livreur</a>
        <a class="{{ request()->routeIs('show.listeDeDemandeApporteur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeApporteur') }}">Apporteur d'affaire</a>
        <a class="{{ request()->routeIs('show.listeDeDemandeFournisseur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeFournisseur') }}">Fournisseur</a>
        <a class="{{ request()->routeIs('show.historiqueDemande') ? 'active' : '' }}" href="{{ route('show.historiqueDemande') }}">Historique</a>
    </div>
</li>

{{-- DETTES — MENU PRINCIPAL POUR LE SEUL GESTIONNAIRE. L'administrateur les
     trouve sous « Etat » ; le gestionnaire, à qui « Etat » n'est pas affiché,
     garde ici les trois écrans auxquels il a toujours eu accès. --}}
@if (!$estAdmin)
    <li class="menu-item has-submenu {{ $isDettesActive ? 'active' : '' }}">
        <a class="menu-link" href="javascript:void(0)">
            <i class="icon material-icons md-money_off"></i>
            <span class="text">Dettes</span>
        </a>
        <div class="submenu">
            <a class="{{ request()->routeIs('show.dettesApporteurs') ? 'active' : '' }}" href="{{ route('show.dettesApporteurs') }}">Apporteurs d'affaires</a>
            <a class="{{ request()->routeIs('show.dettesFournisseurs') ? 'active' : '' }}" href="{{ route('show.dettesFournisseurs') }}">Fournisseurs</a>
            <a class="{{ request()->routeIs('show.dettesLivreurs') ? 'active' : '' }}" href="{{ route('show.dettesLivreurs') }}">Livreurs</a>
        </div>
    </li>
@endif

{{-- ===================== LE PILOTAGE ===================== --}}
<li class="menu-item has-submenu {{ $isPaiementsActive ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('paye.list') }}">
        <i class="icon material-icons md-monetization_on"></i>
        <span class="text">Etat des paiements reçus</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('paye.list') ? 'active' : '' }}" href="{{ route('paye.list') }}">Liste des paiements</a>
    </div>
</li>

@if ($estAdmin)
    @include('layout.navbar.navEtats')
@endif
<hr />

{{-- ===================== LE CATALOGUE ===================== --}}
{{-- Ce qui décrit l'offre, ensemble : produits, code promo, avis. --}}
<li class="menu-item has-submenu {{ $isCatalogueActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_bag"></i>
        <span class="text">Catalogue</span>
    </a>
    <div class="submenu">
        <div class="menu-item has-submenu {{ $isProduitsActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Produits</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('product.list') ? 'active' : '' }}" href="{{ route('product.list') }}">Liste des produits</a>
                <a class="{{ request()->routeIs('product.category', 'product.nouvelleCategorie', 'product.editCategory') ? 'active' : '' }}" href="{{ route('product.category') }}">Categories</a>
                <a class="{{ request()->routeIs('product.add') ? 'active' : '' }}" href="{{ route('product.add') }}">Ajout de produit</a>
                <a class="{{ request()->routeIs('product.pourcentage') ? 'active' : '' }}" href="{{ route('product.pourcentage') }}">Pourcentage DALAKOUN</a>
            </div>
        </div>
        <a class="{{ $isCodePromoActive ? 'active' : '' }}" href="{{ route('show.creationDeCodePromo') }}">Code promo</a>
        {{-- Deux sources de commentaires bien distinctes : les avis sur les
             produits et les commentaires d'articles de blog. --}}
        <div class="menu-item has-submenu {{ $isModerationActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Modération des commentaires</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.moderationCommentaire') ? 'active' : '' }}" href="{{ route('show.moderationCommentaire') }}">Avis produits</a>
                <a class="{{ request()->routeIs('show.moderationCommentairesBlog') ? 'active' : '' }}" href="{{ route('show.moderationCommentairesBlog') }}">Commentaires de blog</a>
            </div>
        </div>
    </div>
</li>

{{-- ===================== LE PARAMÉTRAGE ===================== --}}
@if ($estAdmin)
    @include('layout.navbar.navConfiguration')
    @include('layout.navbar.navComptabilite')
@endif
<hr>

<ul class="menu-aside">
    <li class="menu-item has-submenu {{ $isDiversActive ? 'active' : '' }}">
        <a class="menu-link" href="javascript:void(0)">
            <i class="icon material-icons md-local_offer"></i>
            <span class="text">Divers</span>
        </a>
        <div class="submenu">
            {{-- Le menu ouvre la LISTE ; la création se fait depuis le bouton
                 d'ajout de cette liste. L'entrée reste surlignée sur les trois
                 écrans (liste, création, modification). --}}
            <a class="{{ request()->routeIs('show.listeDesBlogs', 'show.creationDeBlog', 'show.modificationDeBlogPage', 'show.commentaireBlogs') ? 'active' : '' }}" href="{{ route('show.listeDesBlogs') }}">Blog</a>
            <a class="{{ request()->routeIs('show.listeDesBannieres', 'show.creationDeBanniere', 'show.modificationDeBannierePage') ? 'active' : '' }}" href="{{ route('show.listeDesBannieres') }}">Bannière</a>
            <a class="{{ request()->routeIs('show.listeDesSlides', 'show.creationDeSlide', 'show.modificationDeSlidePage') ? 'active' : '' }}" href="{{ route('show.listeDesSlides') }}">Carrousel d'accueil</a>
            {{-- Messages reçus par la page publique « Nous contacter », avec
                 le nombre de non-lus. --}}
            <a class="{{ request()->routeIs('messagesContact.*') ? 'active' : '' }}" href="{{ route('messagesContact.liste') }}">
                Messages de contact
                @php $contactsNonLus = \App\Models\Contact::where('lu', false)->count(); @endphp
                @if ($contactsNonLus > 0)
                    <span class="badge bg-danger">{{ $contactsNonLus }}</span>
                @endif
            </a>
            {{-- Abonnés recueillis par le formulaire du pied de page du site. --}}
            <a class="{{ request()->routeIs('newsletter.*') ? 'active' : '' }}" href="{{ route('newsletter.liste') }}">Lettre d'information</a>
            <a class="{{ request()->routeIs('show.lesRegions', 'show.nouvelleRegion', 'show.modifierRegion') ? 'active' : '' }}" href="{{ route('show.lesRegions') }}">Les régions</a>
            <a class="{{ request()->routeIs('dest.lesVilles', 'dest.nouvelleVille', 'dest.modifierVille') ? 'active' : '' }}" href="{{route('dest.lesVilles')}}">Villes</a>
            {{-- AGENCES : sous « Configuration » pour l'administrateur ; ici
                 pour le gestionnaire, à qui « Configuration » n'est pas affiché. --}}
            @if (!$estAdmin)
                <a class="{{ request()->routeIs('show.agences.*') ? 'active' : '' }}" href="{{ route('show.agences.index') }}">Agences</a>
            @endif
            <a class="{{ request()->routeIs('show.statutMetier.*') ? 'active' : '' }}" href="{{ route('show.statutMetier.index') }}">Statuts métier</a>
            <a class="{{ request()->routeIs('show.typeVehiculeLivreur.*') ? 'active' : '' }}" href="{{ route('show.typeVehiculeLivreur.index') }}">Types véhicules livreurs</a>
        </div>
    </li>
    <li class="menu-item has-submenu {{ $isGrandLivreActive ? 'active' : '' }}">
        <a class="menu-link" href="javascript:void(0)">
            <i class="icon material-icons md-menu_book"></i>
            <span class="text">Les grands livres</span>
        </a>
        <div class="submenu">
            <a class="{{ request()->routeIs('grandLivre.clientOrdinaire') ? 'active' : '' }}" href="{{ route('grandLivre.clientOrdinaire') }}">Clients ordinaire</a>
            <a class="{{ request()->routeIs('grandLivre.clientATerme') ? 'active' : '' }}" href="{{ route('grandLivre.clientATerme') }}">Clients à terme</a>
            <a class="{{ request()->routeIs('grandLivre.livreur') ? 'active' : '' }}" href="{{ route('grandLivre.livreur') }}">Livreurs</a>
            <a class="{{ request()->routeIs('grandLivre.fournisseur') ? 'active' : '' }}" href="{{ route('grandLivre.fournisseur') }}">Fournisseurs</a>
        </div>
    </li>
    <li class="menu-item {{ request()->routeIs('show.parametre') ? 'active' : '' }}">
        <a class="menu-link" href="{{ route('show.parametre') }}">
            <i class="icon material-icons md-settings"></i>
            <span class="text">Paramètre</span>
        </a>
    </li>
    {{-- Le menu "Configuration prix" a été fusionné dans "Paramètre"
         (onglet "Prix personnalisés"). --}}
</ul>
