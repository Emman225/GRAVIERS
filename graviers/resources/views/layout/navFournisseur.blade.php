@php
    $isBonsActive = request()->routeIs('sellers.bons', 'sellers.accepte', 'sellers.refuse', 'sellers.bon.detail', 'sellers.imprime', 'sellers.validate');
    $isPaiementActive = request()->routeIs('sellers.listePaiements', 'sellers.demandeDepaie', 'sellers.demandeDepaieFournisseur', 'show.demandeDepaiePage');
@endphp

{{-- TABLEAU DE BORD --}}
<li class="menu-item {{ request()->routeIs('sellers.home') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('sellers.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Tableau de bord</span>
    </a>
</li>

{{-- STOCK --}}
<li class="menu-item {{ request()->routeIs('sellers.stock', 'sellers.finDeStock') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('sellers.stock') }}">
        <i class="icon material-icons md-pie_chart"></i>
        <span class="text">Stock</span>
    </a>
</li>

{{-- BON D'ENLEVEMENT --}}
<li class="menu-item has-submenu {{ $isBonsActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-shopping_bag"></i>
        <span class="text">Bon de d'enlèvement</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('sellers.bons', 'sellers.bon.detail') ? 'active' : '' }}" href="{{ route('sellers.bons') }}">Bons en attente</a>
        <a class="{{ request()->routeIs('sellers.accepte') ? 'active' : '' }}" href="{{ route('sellers.accepte') }}">Bons traités</a>
        {{-- <a class="{{ request()->routeIs('sellers.refuse') ? 'active' : '' }}" href="{{ route('sellers.refuse') }}">Bons réfusés</a> --}}
    </div>
</li>

{{-- PAIEMENTS --}}
<li class="menu-item {{ $isPaiementActive ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('sellers.listePaiements') }}">
        <i class="icon material-icons md-monetization_on"></i>
        <span class="text">Paiements</span>
    </a>
</li>

<ul class="menu-aside">
    <li class="menu-item {{ request()->routeIs('sellers.parametreFournisseur') ? 'active' : '' }}">
        <a class="menu-link" href="{{ route('sellers.parametreFournisseur') }}">
            <i class="icon material-icons md-settings"></i>
            <span class="text">Paramètre</span>
        </a>
    </li>
</ul>
