{{-- « CONFIGURATION » — administrateur seulement : les comptes qui
     administrent la maison et le référentiel des agences. Placé, selon
     l'ordre validé du point 1 (07/09/2026), après le catalogue et avant
     « Divers » : c'est la partie paramétrage de la barre. --}}
@php
    $isAdministrateursActive = request()->routeIs('show.listeAdmin', 'show.registerAdmin');
    $isGestionnairesActive   = request()->routeIs('show.listeGestionnaire', 'show.registerGestionnaire');
    $isAgencesActive         = request()->routeIs('show.agences.*');
    $isAgentActive           = request()->routeIs('show.listeAgent', 'show.AgentRegister');
    $isConfigurationActive   = $isAdministrateursActive || $isGestionnairesActive
        || $isAgencesActive || $isAgentActive;
@endphp

<li class="menu-item has-submenu {{ $isConfigurationActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-settings_applications"></i>
        <span class="text">Configuration</span>
    </a>
    <div class="submenu">
        <div class="menu-item has-submenu {{ $isAdministrateursActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Administrateurs</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listeAdmin') ? 'active' : '' }}" href="{{route('show.listeAdmin')}}">Liste</a>
                <a class="{{ request()->routeIs('show.registerAdmin') ? 'active' : '' }}" href="{{route('show.registerAdmin')}}">Ajouter un nouveau administrateur</a>
            </div>
        </div>

        <div class="menu-item has-submenu {{ $isGestionnairesActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Gestionnaires</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listeGestionnaire') ? 'active' : '' }}" href="{{route('show.listeGestionnaire')}}">Liste</a>
                <a class="{{ request()->routeIs('show.registerGestionnaire') ? 'active' : '' }}" href="{{route('show.registerGestionnaire')}}">Création de compte</a>
            </div>
        </div>

        <div class="menu-item has-submenu {{ $isAgentActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Agent</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listeAgent') ? 'active' : '' }}" href="{{route('show.listeAgent')}}">Liste </a>
                <a class="{{ request()->routeIs('show.AgentRegister') ? 'active' : '' }}" href="{{route('show.AgentRegister')}}">Création de compte</a>
            </div>
        </div>

        <a class="{{ $isAgencesActive ? 'active' : '' }}" href="{{ route('show.agences.index') }}">Agences</a>
    </div>
</li>
