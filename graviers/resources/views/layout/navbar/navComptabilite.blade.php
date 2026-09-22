{{-- « ÉCRITURES COMPTABLES » — administrateur seulement (module « Écritures
     comptables », lot 116, 21/09/2026). Les écritures sont produites par et
     pour DALAKOUN : ni le gestionnaire ni aucun autre profil n'y accède, et
     les routes le refusent aussi (intergiciel admin.seulement). Le menu
     « Comptabilité » des états (navEtats) existait déjà : celui-ci porte un autre nom. Les entrées
     Points d'entrée : Journal des écritures, Rapport d'anomalies, Rapports
     comptables, Paramétrage comptable. --}}
@php
    // Pas « show.comptabilite.* » : les états du menu « États » (TVA collectée,
    // récapitulatifs, cautions) portent déjà ce préfixe et ne sont pas d'ici.
    $isParametrageComptableActive = request()->routeIs('show.comptabilite.parametrage', 'show.comptabilite.comptes.*', 'show.comptabilite.journaux.*');
    $isRapportAnomaliesActive     = request()->routeIs('show.comptabilite.ecritures.anomalies');
    $isJournalDesEcrituresActive  = request()->routeIs('show.comptabilite.ecritures.*') && !$isRapportAnomaliesActive;
    $isRapportsComptablesActive   = request()->routeIs('show.comptabilite.rapports.*');
    $isJetonsApiActive            = request()->routeIs('show.comptabilite.jetonsApi.*');
    $isEcrituresComptablesActive  = $isParametrageComptableActive || $isJournalDesEcrituresActive
        || $isRapportAnomaliesActive || $isRapportsComptablesActive || $isJetonsApiActive;
@endphp

<li class="menu-item has-submenu {{ $isEcrituresComptablesActive ? 'active' : '' }}">
    <a class="menu-link" href="javascript:void(0)">
        <i class="icon material-icons md-account_balance"></i>
        <span class="text">Écritures comptables</span>
    </a>
    <div class="submenu">
        <a class="{{ $isJournalDesEcrituresActive ? 'active' : '' }}"
           href="{{ route('show.comptabilite.ecritures.index') }}">Journal des écritures</a>
        <a class="{{ $isRapportAnomaliesActive ? 'active' : '' }}"
           href="{{ route('show.comptabilite.ecritures.anomalies') }}">Rapport d'anomalies</a>
        <a class="{{ $isRapportsComptablesActive ? 'active' : '' }}"
           href="{{ route('show.comptabilite.rapports.index') }}">Rapports comptables</a>
        <a class="{{ $isParametrageComptableActive ? 'active' : '' }}"
           href="{{ route('show.comptabilite.parametrage') }}">Paramétrage comptable</a>
        <a class="{{ $isJetonsApiActive ? 'active' : '' }}"
           href="{{ route('show.comptabilite.jetonsApi.index') }}">Jetons API</a>
    </div>
</li>
