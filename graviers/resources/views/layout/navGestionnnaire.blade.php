@php
    $isCommandesActive = request()->routeIs('orders.list', 'orders.commandesTraitees', 'show.listeRetourProduit', 'show.demandesAnnulation', 'orders.listeDesDevis', 'show.ticketSAV');
    $isLivraisonsActive = request()->routeIs('show.livraisonEnCours', 'show.livraisonValidees', 'show.livraisonHistorique');
    $isLocationsActive = request()->routeIs('show.listeLocationEnAttente');
    $isDemandeLivraisonActive = request()->routeIs('show.demandeLivraisonlist', 'show.demandeLivraisonTraitee');
    $isFournisseursActive = request()->routeIs(
        'show.listSeller', 'show.registerSeller', 'show.listSellerPourBon',
        'show.fournisseurs.enlevements', 'show.fournisseurs.paiements', 'show.fournisseurs.synthese'
    );
    $isFacturesBonsActive = request()->routeIs('show.bonAttente', 'show.bonValides', 'orders.facturesNonValidees', 'orders.facturesValidees');
    $isApporteurActive = request()->routeIs(
        'show.listApporteur', 'show.commissions',
        'show.apporteurs.commissions', 'show.apporteurs.paiements', 'show.apporteurs.synthese'
    );
    $isLivreursActive = request()->routeIs(
        'show.list', 'show.registerLivreur',
        'show.livreurs.livraisons', 'show.livreurs.paiements', 'show.livreurs.synthese'
    );
    $isProduitsActive = request()->routeIs('product.list', 'product.category', 'product.add');
    $isPaiementsActive = request()->routeIs('paye.list');
    $isDemandesPaiementActive = request()->routeIs('show.listeDeDemandeLivreur', 'show.listeDeDemandeApporteur', 'show.listeDeDemandeFournisseur', 'show.historiqueDemande');
    $isDettesActive = request()->routeIs('show.dettesApporteurs', 'show.dettesFournisseurs', 'show.dettesLivreurs');
    $isDiversActive = request()->routeIs('show.creationDeBlog', 'show.creationDeBanniere', 'show.lesRegions', 'dest.lesVilles', 'show.agences.*', 'show.statutMetier.*', 'show.typeVehiculeLivreur.*');
    $isGrandLivreActive = request()->routeIs('grandLivre.*');
@endphp

<li class="menu-item {{ request()->routeIs('show.home') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('show.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Tableau de bord</span>
    </a>
</li>
@if (Auth::user()->type_user_id == Help::$USER_ADMIN)
    @include('layout.navbar.navAdmin')
@endrole
<li class="menu-item has-submenu {{ $isCommandesActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_cart"></i>
        <span class="text">Commandes</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('orders.list') ? 'active' : '' }}" href="{{ route('orders.list') }}">Commandes en attente</a>
        {{-- <a href="{{ route('orders.clientATerme') }}">Commandes client à terme</a> --}}
        <a class="{{ request()->routeIs('orders.commandesTraitees') ? 'active' : '' }}" href="{{ route('orders.commandesTraitees') }}">Commandes traitées</a>
        <a class="{{ request()->routeIs('show.listeRetourProduit') ? 'active' : '' }}" href="{{ route('show.listeRetourProduit') }}">Produits retourné</a>
        <a class="{{ request()->routeIs('show.demandesAnnulation') ? 'active' : '' }}" href="{{ route('show.demandesAnnulation') }}">Demandes d'annulation</a>
        <a class="{{ request()->routeIs('orders.listeDesDevis') ? 'active' : '' }}" href="{{ route('orders.listeDesDevis') }}">Les dévis</a>
        <a class="{{ request()->routeIs('show.ticketSAV') ? 'active' : '' }}" href="{{ route('show.ticketSAV') }}">Ticket SAV</a>
    </div>
</li>

<li class="menu-item has-submenu {{ $isLivraisonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_cart"></i>
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
    </div>
</li>
<li class="menu-item has-submenu {{ $isDemandeLivraisonActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_cart"></i>
        <span class="text">Demandes de livraison</span>
    </a>

    <div class="submenu">
        <a class="{{ request()->routeIs('show.demandeLivraisonlist') ? 'active' : '' }}" href="{{ route('show.demandeLivraisonlist') }}">Demande en attente</a>
        <a class="{{ request()->routeIs('show.demandeLivraisonTraitee') ? 'active' : '' }}" href="{{ route('show.demandeLivraisonTraitee') }}">Demande traitée</a>
    </div>
</li>

{{-- FOURNISSEUR --}}
<li class="menu-item has-submenu {{ $isFournisseursActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-store"></i>
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
</li>

<li class="menu-item has-submenu {{ $isFacturesBonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-store"></i>
        <span class="text">Factures & Bons d'enlèvement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.bonAttente') ? 'active' : '' }}" href="{{ route('show.bonAttente') }}">Bons en attente</a>
        <a class="{{ request()->routeIs('show.bonValides') ? 'active' : '' }}" href="{{ route('show.bonValides') }}">Bons validés</a>
        <a class="{{ request()->routeIs('orders.facturesNonValidees') ? 'active' : '' }}" href="{{ route('orders.facturesNonValidees') }}">Factures non validées</a>
        <a class="{{ request()->routeIs('orders.facturesValidees') ? 'active' : '' }}" href="{{ route('orders.facturesValidees') }}">Factures validées</a>
    </div>
</li>


{{-- <li class="menu-item has-submenu">
    <a class="menu-link" href="page-form-product-1.html">
        <i class="icon material-icons md-add_box"></i>
        <span class="text">Add product</span>
    </a>
    <div class="submenu">

    </div>
</li> --}}

{{-- COMPTE --}}
{{-- <li class="menu-item has-submenu">
    <a class="menu-link" href="#">
        <i class="icon material-icons md-person"></i>
        <span class="text">Création de compte</span>
    </a>
    <div class="submenu">
        <a href="{{route('sellers.register')}}">Fournisseur</a>
        <a href="{{route('livreur.register')}}">Livreur</a>
        <a href="{{route('apporteur.register')}}">Apporteur d'affaire</a>

    </div>
</li> --}}

{{-- APPORTEUR D'AFFAIRE --}}
<li class="menu-item has-submenu {{ $isApporteurActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-person"></i>
        <span class="text">Apporteur d'affaire</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listApporteur') ? 'active' : '' }}" href="{{ route('show.listApporteur') }}">Liste des apporteurs</a>
        {{-- <a href="{{ route('apporteur.register') }}">Création de compte</a> --}}
        <a class="{{ request()->routeIs('show.commissions') ? 'active' : '' }}" href="{{route('show.commissions')}}">Commission des apporteurs</a>
        <a class="{{ request()->routeIs('show.apporteurs.commissions') ? 'active' : '' }}" href="{{ route('show.apporteurs.commissions') }}">Dette &raquo; Commissions</a>
        <a class="{{ request()->routeIs('show.apporteurs.paiements') ? 'active' : '' }}" href="{{ route('show.apporteurs.paiements') }}">Dette &raquo; Paiements</a>
        <a class="{{ request()->routeIs('show.apporteurs.synthese') ? 'active' : '' }}" href="{{ route('show.apporteurs.synthese') }}">Dette &raquo; Synthèse</a>
    </div>
</li>
{{-- livreurs --}}
<li class="menu-item has-submenu {{ $isLivreursActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-person"></i>
        <span class="text">Livreurs</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.list') ? 'active' : '' }}" href="{{ route('show.list') }}">Liste des livreurs</a>
        <a class="{{ request()->routeIs('show.registerLivreur') ? 'active' : '' }}" href="{{ route('show.registerLivreur') }}">Création de compte</a>
        <a class="{{ request()->routeIs('show.livreurs.livraisons') ? 'active' : '' }}" href="{{ route('show.livreurs.livraisons') }}">Dette &raquo; Livraisons</a>
        <a class="{{ request()->routeIs('show.livreurs.paiements') ? 'active' : '' }}" href="{{ route('show.livreurs.paiements') }}">Dette &raquo; Paiements</a>
        <a class="{{ request()->routeIs('show.livreurs.synthese') ? 'active' : '' }}" href="{{ route('show.livreurs.synthese') }}">Dette &raquo; Synthèse</a>
    </div>
</li>
<hr />
{{-- PRODUIT --}}
<li class="menu-item has-submenu {{ $isProduitsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_bag"></i>
        <span class="text">Produits</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('product.list') ? 'active' : '' }}" href="{{ route('product.list') }}">Liste des produits</a>
        <a class="{{ request()->routeIs('product.category') ? 'active' : '' }}" href="{{ route('product.category') }}">Categories</a>
        <a class="{{ request()->routeIs('product.add') ? 'active' : '' }}" href="{{ route('product.add') }}">Ajout de produit</a>
    </div>
</li>
<li class="menu-item {{ request()->routeIs('show.creationDeCodePromo') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('show.creationDeCodePromo') }}">
        <i class="icon material-icons md-shopping_bag"></i>
        <span class="text">Code promo</span>
    </a>
</li>

{{-- TRANSACTION --}}
<li class="menu-item has-submenu {{ $isPaiementsActive ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('paye.list') }}">
        <i class="icon material-icons md-monetization_on"></i>
        <span class="text">Etat des paiements reçu</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('paye.list') ? 'active' : '' }}" href="{{ route('paye.list') }}">Liste des paiements</a>
    </div>
</li>

<li class="menu-item {{ request()->routeIs('show.moderationCommentaire') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('show.moderationCommentaire') }}">
        <i class="icon material-icons md-comment"></i>
        <span class="text">Moderation de commentaire</span>
    </a>
</li>


<li class="menu-item has-submenu {{ $isDemandesPaiementActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-comment"></i>
        <span class="text">Demandes de paiement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeDeDemandeLivreur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeLivreur') }}">Livreur</a>
        <a class="{{ request()->routeIs('show.listeDeDemandeApporteur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeApporteur') }}">Apporteur d'affaire</a>
        <a class="{{ request()->routeIs('show.listeDeDemandeFournisseur') ? 'active' : '' }}" href="{{ route('show.listeDeDemandeFournisseur') }}">Fournisseur</a>
        <a class="{{ request()->routeIs('show.historiqueDemande') ? 'active' : '' }}" href="{{ route('show.historiqueDemande') }}">Historique</a>
    </div>
</li>

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
<hr>

<ul class="menu-aside">
    <li class="menu-item has-submenu {{ $isDiversActive ? 'active' : '' }}">
        <a class="menu-link" href="javascript:void(0)">
            <i class="icon material-icons md-local_offer"></i>
            <span class="text">Divers</span>
        </a>
        <div class="submenu">
            <a class="{{ request()->routeIs('show.creationDeBlog') ? 'active' : '' }}" href="{{ route('show.creationDeBlog') }}">Blog</a>
            <a class="{{ request()->routeIs('show.creationDeBanniere') ? 'active' : '' }}" href="{{ route('show.creationDeBanniere') }}">Bannière</a>
            <a class="{{ request()->routeIs('show.lesRegions') ? 'active' : '' }}" href="{{ route('show.lesRegions') }}">Les régions</a>
            <a class="{{ request()->routeIs('dest.lesVilles') ? 'active' : '' }}" href="{{route('dest.lesVilles')}}">Villes</a>
            <a class="{{ request()->routeIs('show.agences.*') ? 'active' : '' }}" href="{{ route('show.agences.index') }}">Agences</a>
            <a class="{{ request()->routeIs('show.statutMetier.*') ? 'active' : '' }}" href="{{ route('show.statutMetier.index') }}">Statuts métier</a>
            <a class="{{ request()->routeIs('show.typeVehiculeLivreur.*') ? 'active' : '' }}" href="{{ route('show.typeVehiculeLivreur.index') }}">Types véhicules livreurs</a>
        </div>
    </li>
    <li class="menu-item has-submenu {{ $isGrandLivreActive ? 'active' : '' }}">
        <a class="menu-link" href="javascript:void(0)">
            <i class="icon material-icons md-comment"></i>
            <span class="text">Les grands livres</span>
        </a>
        <div class="submenu">
            <a class="{{ request()->routeIs('grandLivre.clientOrdinaire') ? 'active' : '' }}" href="{{ route('grandLivre.clientOrdinaire') }}">Clients ordinaire</a>
            <a class="{{ request()->routeIs('grandLivre.clientATerme') ? 'active' : '' }}" href="{{ route('grandLivre.clientATerme') }}">Clients à terme</a>
            <a class="{{ request()->routeIs('grandLivre.livreur') ? 'active' : '' }}" href="{{ route('grandLivre.livreur') }}">Livreurs</a>
            <a class="{{ request()->routeIs('grandLivre.fournisseur') ? 'active' : '' }}" href="{{ route('grandLivre.fournisseur') }}">fournisseur</a>
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

{{-- BRAND --}}
{{-- <li class="menu-item @if (request()->route()->getName() == 'show.brand') active @endif">
    <a class="menu-link" href="{{route('show.brand')}}"> <i class="icon material-icons md-b"></i> <span class="text">Brands</span> </a>
</li> --}}

{{-- STATISTIQUE --}}
{{-- <li class="menu-item @if (request()->route()->getName() == 'show.stat') active @endif">
    <a class="menu-link" disabled href="{{route('show.stat')}}">
        <i class="icon material-icons md-pie_chart"></i>
        <span class="text">Statistics</span>
    </a>
</li> --}}
