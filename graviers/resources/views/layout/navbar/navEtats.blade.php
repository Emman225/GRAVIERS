{{-- « COMPTABILITÉ » ET « ETAT » — administrateur seulement.
     Sortis de navAdmin pour se placer, comme le veut l'ordre validé du point 1
     (07/09/2026), en bas de la partie métier, juste après « Etat des paiements
     reçus » : ce sont les écrans de pilotage. Ils restent deux menus (et non
     deux sous-menus d'un menu « Caisse et états ») parce que « Etat » porte
     déjà ses propres sous-arborescences : un niveau de plus ne s'ouvrirait pas. --}}
@php
    $isRecapCreancesActive = request()->routeIs(
        'show.recapCreances.dashboard', 'show.recapCreances.detailTerme', 'show.recapCreances.detailComptant'
    );
    $isRecapDettesActive = request()->routeIs(
        'show.recapDettes.tableauBord', 'show.recapDettes.detailFournisseurs',
        'show.recapDettes.detailLivreurs', 'show.recapDettes.detailApporteurs'
    );
    $isDettesEtatActive = request()->routeIs(
        'show.dettesApporteurs', 'show.dettesFournisseurs', 'show.dettesLivreurs'
    );
    $isEtatActive = request()->routeIs(
        'show.recapLivraison', 'show.CADetaille', 'show.CAParFamille',
        'show.clientATerme', 'show.balanceAgee', 'show.etatParrainage',
        'show.reapprovisionnement'
    ) || $isRecapCreancesActive || $isRecapDettesActive || $isDettesEtatActive;

    $isComptabiliteActive = request()->routeIs(
        'show.comptabilite.tvaCollectee', 'show.comptabilite.airsiCollectee',
        'show.comptabilite.beneficesLivraisons',
        'show.comptabilite.recapVentes', 'show.comptabilite.recapLocations',
        'show.comptabilite.etatCautions'
    );
@endphp

<li class="menu-item has-submenu {{ $isComptabiliteActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-calculate"></i>
        <span class="text">Comptabilité</span>
    </a>
    <div class="submenu">
        <a class="{{ request()->routeIs('show.comptabilite.tvaCollectee') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.tvaCollectee') }}">État de TVA collectée</a>
        <a class="{{ request()->routeIs('show.comptabilite.airsiCollectee') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.airsiCollectee') }}">État d'AIRSI collecté</a>
        <a class="{{ request()->routeIs('show.comptabilite.beneficesLivraisons') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.beneficesLivraisons') }}">Bénéfices sur les livraisons</a>
        <a class="{{ request()->routeIs('show.comptabilite.recapVentes') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.recapVentes') }}">Récapitulatif des ventes</a>
        <a class="{{ request()->routeIs('show.comptabilite.recapLocations') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.recapLocations') }}">Récapitulatif des locations</a>
        <a class="{{ request()->routeIs('show.comptabilite.etatCautions') ? 'active' : '' }}"
           href="{{ route('show.comptabilite.etatCautions') }}">État caution location</a>
    </div>
</li>

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
                <a class="{{ request()->routeIs('show.recapDettes.detailFournisseurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailFournisseurs')}}">Détail fournisseurs</a>
                <a class="{{ request()->routeIs('show.recapDettes.detailLivreurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailLivreurs')}}">Détail livreurs</a>
                <a class="{{ request()->routeIs('show.recapDettes.detailApporteurs') ? 'active' : '' }}" href="{{route('show.recapDettes.detailApporteurs')}}">Détail apporteurs</a>
            </div>
        </div>

        {{-- Sous-arborescence : Dettes. Le gestionnaire, lui, la garde à la
             racine : « Etat » ne lui est pas affiché. --}}
        <div class="menu-item has-submenu {{ $isDettesEtatActive ? 'active' : '' }}">
            <a class="menu-link" href="javascript:void(0)">
                <span class="text">Dettes</span>
            </a>
            <div class="submenu">
                <a class="{{ request()->routeIs('show.dettesApporteurs') ? 'active' : '' }}" href="{{ route('show.dettesApporteurs') }}">Apporteurs d'affaires</a>
                <a class="{{ request()->routeIs('show.dettesFournisseurs') ? 'active' : '' }}" href="{{ route('show.dettesFournisseurs') }}">Fournisseurs</a>
                <a class="{{ request()->routeIs('show.dettesLivreurs') ? 'active' : '' }}" href="{{ route('show.dettesLivreurs') }}">Livreurs</a>
            </div>
        </div>
    </div>
</li>
