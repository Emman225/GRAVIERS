{{-- En-tête commun à tous les rapports : titre, explication, retour, choix de la période. --}}
<div class="dash-welcome mb-4">
    <div class="dash-welcome-content">
        <div>
            <h2 class="dash-welcome-title">{{ $titre }}</h2>
            <p class="dash-welcome-subtitle">{{ $explication }}</p>
        </div>
        <div class="dash-welcome-actions">
            <a href="{{ route('show.comptabilite.rapports.index', request()->only(['mode_periode', 'periode', 'du', 'au'])) }}" class="btn btn-primary">
                <i class="material-icons md-arrow_back"></i> Tous les rapports
            </a>
        </div>
    </div>
    <div class="dash-welcome-decoration"></div>
</div>

@if ($rubriquesSansCompte->isNotEmpty())
    <div class="alert alert-warning">
        Rubrique(s) sans compte dans le paramétrage : {{ $rubriquesSansCompte->pluck('libelle')->implode(', ') }}.
        Ce rapport peut être incomplet tant qu'elles ne sont pas réglées.
        <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'rubriques']) }}" class="alert-link">Ouvrir le paramétrage</a>
    </div>
@endif

<div class="card dash-card mb-4">
    <div class="card-body">
        @include('comptabilite.ecritures._periode', [
            'action' => request()->url(),
            'champsConserves' => ($champsConserves ?? []),
        ])
    </div>
</div>
