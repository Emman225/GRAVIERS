{{-- ACCUEIL --}}
<li class="menu-item {{ request()->routeIs('client.home', 'client.accueil', 'client.index') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('client.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Accueil</span>
    </a>
</li>

{{-- DEVIS --}}
<li class="menu-item {{ request()->routeIs('client.devis', 'client.devisValide') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('client.devis') }}">
        <i class="icon material-icons md-description"></i>
        <span class="text">Devis</span>
    </a>
</li>

{{-- COMMANDE EN COURS --}}
<li class="menu-item {{ request()->routeIs('client.commande', 'client.listePaiementCommande') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('client.commande') }}">
        <i class="icon material-icons md-confirmation_number"></i>
        <span class="text">Commande en cours</span>
    </a>
</li>
