@php
    // Détection des états actifs (Etat / Client / Gestionnaires / Agent)
    $isRecapCreancesActive = request()->routeIs(
        'show.recapCreances.dashboard', 'show.recapCreances.detailTerme', 'show.recapCreances.detailComptant'
    );
    $isRecapDettesActive = request()->routeIs(
        'show.recapDettes.tableauBord', 'show.recapDettes.detailFournisseurs',
        'show.recapDettes.detailLivreurs', 'show.recapDettes.detailApporteurs'
    );
    $isEtatActive   = request()->routeIs(
        'show.recapLivraison', 'show.CADetaille', 'show.CAParFamille',
        'show.clientATerme', 'show.balanceAgee', 'show.etatParrainage',
        'show.reapprovisionnement'
    ) || $isRecapCreancesActive || $isRecapDettesActive;

    $isClientOrdinaireActive = request()->routeIs(
        'show.listClient',
        'show.comptant.commandes', 'show.comptant.encaissements', 'show.comptant.synthese'
    );
    $isClientATermeActive    = request()->routeIs(
        'show.listClientATerme', 'show.listeDemandeClient',
        'show.creanceATermeListe', 'show.creancesTerme.factures',
        'show.creancesTerme.paiements', 'show.creancesTerme.relances',
        'show.creancesTerme.synthese'
    );
    $isClientActive = $isClientOrdinaireActive || $isClientATermeActive;

    $isAdministrateursActive = request()->routeIs('show.listeAdmin', 'show.registerAdmin');
    $isGestionnairesActive   = request()->routeIs('show.listeGestionnaire', 'show.registerGestionnaire');
    $isAgentActive           = request()->routeIs('show.listeAgent', 'show.AgentRegister');
@endphp

<li class="menu-item has-submenu {{ $isEtatActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <img class="me-2" style="width:30px" src="{{asset('frontend/assets/imgs/theme/icons/gestion.png')}}" alt="">
        <span class="text">Etat</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.recapLivraison') ? 'active' : '' }}" href="{{route('show.recapLivraison')}}">Recap. livraison</a>
        <a class="{{ request()->routeIs('show.CADetaille') ? 'active' : '' }}" href="{{route('show.CADetaille')}}">Etat chiffre d'affaire détaillé</a>
        <a class="{{ request()->routeIs('show.CAParFamille') ? 'active' : '' }}" href="{{route('show.CAParFamille')}}">Chiffre d'affaire par famille</a>
        <a class="{{ request()->routeIs('show.clientATerme') ? 'active' : '' }}" href="{{route('show.clientATerme')}}">Etat client à terme</a>
        <a class="{{ request()->routeIs('show.balanceAgee') ? 'active' : '' }}" href="{{route('show.balanceAgee')}}">Balance agée</a>
        <a class="{{ request()->routeIs('show.etatParrainage') ? 'active' : '' }}" href="{{route('show.etatParrainage')}}">Etat de paiement filleule</a>
        <a class="{{ request()->routeIs('show.reapprovisionnement') ? 'active' : '' }}" href="{{route('show.reapprovisionnement')}}">Livraison et réapprovisionnement</a>

        {{-- Sous-arborescence : Recap global Créance --}}
        <div class="menu-item has-submenu {{ $isRecapCreancesActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Recap global Créance</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.recapCreances.dashboard') ? 'active' : '' }}" href="{{route('show.recapCreances.dashboard')}}">Tableau de bord</a>
                <a class="{{ request()->routeIs('show.recapCreances.detailTerme') ? 'active' : '' }}" href="{{route('show.recapCreances.detailTerme')}}">Détail terme</a>
                <a class="{{ request()->routeIs('show.recapCreances.detailComptant') ? 'active' : '' }}" href="{{route('show.recapCreances.detailComptant')}}">Détail comptant</a>
            </div>
        </div>

        {{-- Sous-arborescence : Recap global Dette --}}
        <div class="menu-item has-submenu {{ $isRecapDettesActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Recap global Dette</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.recapDettes.tableauBord') ? 'active' : '' }}" href="{{route('show.recapDettes.tableauBord')}}">Tableau de bord</a>
                <a class="{{ request()->routeIs('show.recapDettes.detailFournisseurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailFournisseurs')}}">Détail Fournisseurs</a>
                <a class="{{ request()->routeIs('show.recapDettes.detailLivreurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailLivreurs')}}">Détail Livreurs</a>
                <a class="{{ request()->routeIs('show.recapDettes.detailApporteurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailApporteurs')}}">Détail Apporteurs</a>
            </div>
        </div>
    </div>
</li>

<li class="menu-item has-submenu {{ $isClientActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-person"></i>
        <span class="text">Client</span>
    </a>
    <div class="submenu">
        {{-- Sous-arborescence : Client ordinaire --}}
        <div class="menu-item has-submenu {{ $isClientOrdinaireActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Client ordinaire</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listClient') ? 'active' : '' }}" href="{{route('show.listClient')}}">Liste client ordinaire</a>
                <a class="{{ request()->routeIs('show.comptant.commandes') ? 'active' : '' }}" href="{{route('show.comptant.commandes')}}">Commandes comptant</a>
                <a class="{{ request()->routeIs('show.comptant.encaissements') ? 'active' : '' }}" href="{{route('show.comptant.encaissements')}}">Encaissements Agence</a>
                <a class="{{ request()->routeIs('show.comptant.synthese') ? 'active' : '' }}" href="{{route('show.comptant.synthese')}}">Synthèse comptant</a>
            </div>
        </div>

        {{-- Sous-arborescence : Client à terme --}}
        <div class="menu-item has-submenu {{ $isClientATermeActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Client à terme</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.listClientATerme') ? 'active' : '' }}" href="{{route('show.listClientATerme')}}">Liste client à terme</a>
                <a class="{{ request()->routeIs('show.listeDemandeClient') ? 'active' : '' }}" href="{{route('show.listeDemandeClient')}}">Demande client à terme</a>
                <a class="{{ request()->routeIs('show.creancesTerme.factures') ? 'active' : '' }}" href="{{route('show.creancesTerme.factures')}}">Créance Factures</a>
                <a class="{{ request()->routeIs('show.creancesTerme.paiements') ? 'active' : '' }}" href="{{route('show.creancesTerme.paiements')}}">Créance Paiements</a>
                <a class="{{ request()->routeIs('show.creancesTerme.relances') ? 'active' : '' }}" href="{{route('show.creancesTerme.relances')}}">Créance Relances</a>
                <a class="{{ request()->routeIs('show.creancesTerme.synthese') ? 'active' : '' }}" href="{{route('show.creancesTerme.synthese')}}">Créance Synthèse</a>
            </div>
        </div>
    </div>
</li>

<li class="menu-item has-submenu {{ $isAdministrateursActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-admin_panel_settings"></i>
        <span class="text">Administrateurs</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeAdmin') ? 'active' : '' }}" href="{{route('show.listeAdmin')}}">Liste</a>
        <a class="{{ request()->routeIs('show.registerAdmin') ? 'active' : '' }}" href="{{route('show.registerAdmin')}}">Ajouter un nouveau administrateur</a>
    </div>
</li>
<li class="menu-item has-submenu {{ $isGestionnairesActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-person"></i>
        <span class="text">Gestionnaires</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeGestionnaire') ? 'active' : '' }}" href="{{route('show.listeGestionnaire')}}">Liste</a>
        <a class="{{ request()->routeIs('show.registerGestionnaire') ? 'active' : '' }}" href="{{route('show.registerGestionnaire')}}">Création de compte</a>
    </div>
</li>
<li class="menu-item has-submenu {{ $isAgentActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-person"></i>
        <span class="text">Agent</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.listeAgent') ? 'active' : '' }}" href="{{route('show.listeAgent')}}">Liste </a>
        <a class="{{ request()->routeIs('show.AgentRegister') ? 'active' : '' }}" href="{{route('show.AgentRegister')}}">Création de compte</a>
    </div>
</li>
