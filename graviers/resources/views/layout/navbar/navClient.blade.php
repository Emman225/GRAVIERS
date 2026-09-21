{{-- MENU « CLIENT » — administrateur seulement (comme avant le 07/09/2026).
     Sorti de navAdmin pour prendre sa place dans l'ordre validé du point 1 :
     après les demandes de livraison, avec le reste du quotidien. --}}
@php
    $isClientOrdinaireActive = request()->routeIs(
        'show.listClient', 'show.listClientEnAttente',
        'show.comptant.commandes', 'show.comptant.encaissements', 'show.comptant.synthese'
    );
    $isClientATermeActive    = request()->routeIs(
        'show.listClientATerme', 'show.listeDemandeClient',
        'show.creanceATermeListe', 'show.creancesTerme.factures',
        'show.creancesTerme.paiements', 'show.creancesTerme.relances',
        'show.creancesTerme.synthese'
    );
    // Avances clients (point 19) : dépôts sans commande, ordinaires et à terme.
    $isAvancesActive = request()->routeIs('show.avances.*');
    $isClientActive  = $isClientOrdinaireActive || $isClientATermeActive || $isAvancesActive;
@endphp

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
                <a class="{{ request()->routeIs('show.listClientEnAttente') ? 'active' : '' }}" href="{{route('show.listClientEnAttente')}}">Inscriptions en attente</a>
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

        {{-- Avances déposées par les clients (point 19, 07/09/2026). --}}
        <a class="{{ $isAvancesActive ? 'active' : '' }}" href="{{ route('show.avances.index') }}">Avances clients</a>
    </div>
</li>
