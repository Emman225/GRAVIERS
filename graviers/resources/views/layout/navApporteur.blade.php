{{-- TABLEAU DE BORD --}}
<li class="menu-item {{ request()->routeIs('apporteur.home') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('apporteur.home') }}">
        <i class="icon material-icons md-home"></i>
        <span class="text">Tableau de bord</span>
    </a>
</li>

{{-- FILLEUL(E)S --}}
<li class="menu-item {{ request()->routeIs('apporteur.filleule') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('apporteur.filleule') }}">
        <i class="icon material-icons md-group"></i>
        <span class="text">Mes filleul(e)s</span>
    </a>
</li>

{{-- PAIEMENTS --}}
<li class="menu-item {{ request()->routeIs('apporteur.paiement', 'show.demandeDepaiePage') ? 'active' : '' }}">
    <a class="menu-link" href="{{ route('apporteur.paiement') }}">
        <i class="icon material-icons md-confirmation_number"></i>
        <span class="text">Paiements</span>
    </a>
</li>

<ul class="menu-aside">
    <li class="menu-item {{ request()->routeIs('apporteur.parametreApporteur') ? 'active' : '' }}">
        <a class="menu-link" href="{{ route('apporteur.parametreApporteur') }}">
            <i class="icon material-icons md-settings"></i>
            <span class="text">Paramètre</span>
        </a>
    </li>
</ul>
