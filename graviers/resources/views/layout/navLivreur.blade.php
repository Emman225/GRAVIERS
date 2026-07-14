@php
    $isBonsActive = request()->routeIs('livreur.bon', 'livreur.bonValides', 'livreur.bon.detail', 'livreur.bonRecherche', 'livreur.afficheBon');
    $isLivraisonsActive = request()->routeIs(
        'livreur.livraison', 'livreur.livraisonValides',
        'livreur.ajoutVehiculePage', 'livreur.listeVehicule',
        'livreur.modificationVehicule', 'livreur.supressionVehicule',
        'livreur.vehiculeDispo', 'livreur.enRoute'
    );
    $isPaiementActive = request()->routeIs('livreur.listeDesDemandesDePaiement', 'show.demandeDepaiePage');
@endphp

{{-- TABLEAU DE BORD --}}
<li class="menu-item {{ request()->routeIs('livreur.home') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('livreur.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Tableau de bord</span>
    </a>
</li>

{{-- BON D'ENLEVEMENT --}}
<li class="menu-item has-submenu {{ $isBonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_bag"></i>
        <span class="text">Bons d'enlèvement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('livreur.bon', 'livreur.bon.detail') ? 'active' : '' }}" href="{{ route('livreur.bon') }}">Bons en attente</a>
        <a class="{{ request()->routeIs('livreur.bonValides') ? 'active' : '' }}" href="{{ route('livreur.bonValides') }}">Bons validés</a>
    </div>
</li>

{{-- LIVRAISON --}}
<li class="menu-item has-submenu {{ $isLivraisonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-local_shipping"></i>
        <span class="text">Livraisons</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('livreur.livraison', 'livreur.enRoute') ? 'active' : '' }}" href="{{ route('livreur.livraison') }}">Livraisons en attente</a>
        <a class="{{ request()->routeIs('livreur.livraisonValides') ? 'active' : '' }}" href="{{ route('livreur.livraisonValides') }}">Livraisons validés</a>
        <a class="{{ request()->routeIs('livreur.ajoutVehiculePage') ? 'active' : '' }}" href="{{ route('livreur.ajoutVehiculePage') }}">Ajouter un vehicule</a>
        <a class="{{ request()->routeIs('livreur.listeVehicule', 'livreur.modificationVehicule', 'livreur.vehiculeDispo') ? 'active' : '' }}" href="{{ route('livreur.listeVehicule') }}">Mes véhicule</a>
    </div>
</li>

{{-- PAIEMENT --}}
<li class="menu-item has-submenu {{ $isPaiementActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-monetization_on"></i>
        <span class="text">Paiement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('livreur.listeDesDemandesDePaiement') ? 'active' : '' }}" href="{{ route('livreur.listeDesDemandesDePaiement') }}">Liste des demandes</a>
        <a class="{{ request()->routeIs('show.demandeDepaiePage') ? 'active' : '' }}" href="{{ route('show.demandeDepaiePage') }}">Nouvelle demande</a>
    </div>
</li>
<hr>

<ul class="menu-aside">
    <li class="menu-item {{ request()->routeIs('livreur.parametreLivreur') ? 'active' : '' }}">
        <a class="menu-link" href="{{ route('livreur.parametreLivreur') }}">
            <i class="icon material-icons md-settings"></i>
            <span class="text">Paramètre</span>
        </a>
    </li>
</ul>
