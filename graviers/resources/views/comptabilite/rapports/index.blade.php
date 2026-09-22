@extends('layout.main')
@section('title', 'Rapports comptables')

@php
    use App\Http\Controllers\RapportsComptablesController;
    $francs = fn ($montant) => number_format((float) $montant, 0, ',', ' ');
    // Les clés du tableau RAPPORTS ne portent pas toujours le nom exact de la route
    // (grand-livre / grandLivre, journal-ventes / journalDesVentes) : la table le dit une fois pour toutes.
    $routesDesRapports = [
        'deversements' => 'deversements', 'familles' => 'familles', 'soldes' => 'soldes',
        'grand-livre' => 'grandLivre', 'balance' => 'balance', 'consolidee' => 'consolidee',
        'rapprochement' => 'rapprochement', 'anomalies' => 'anomalies', 'ventes' => 'ventes',
        'taxes' => 'taxes', 'clients' => 'clients', 'tresorerie' => 'tresorerie', 'journal-ventes' => 'journalDesVentes',
    ];
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Rapports <span class="dash-welcome-name">comptables</span></h2>
                <p class="dash-welcome-subtitle">
                    Ce qui a été déversé et ce qui reste, les soldes des comptes, le grand livre, les balances,
                    et les rapports complémentaires — tous en lecture seule, aucun n'écrit dans les écritures.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.comptabilite.ecritures.index') }}" class="btn btn-primary">
                    <i class="material-icons md-menu_book"></i> Journal des écritures
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if ($rubriquesSansCompte->isNotEmpty())
        <div class="alert alert-warning">
            <strong>{{ $rubriquesSansCompte->count() }} rubrique(s) sans compte</strong> dans le paramétrage
            ({{ $rubriquesSansCompte->pluck('libelle')->implode(', ') }}) : les rapports qui en dépendent peuvent être incomplets tant qu'elles ne sont pas réglées.
            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'rubriques']) }}" class="alert-link">Ouvrir le paramétrage</a>
        </div>
    @endif

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-filter_list text-primary"></i> Période</h5>
        </div>
        <div class="card-body">
            @include('comptabilite.ecritures._periode', [
                'action' => route('show.comptabilite.rapports.index'),
            ])
            <small class="text-muted d-block mt-2">
                La période choisie ici est reprise par chaque rapport ; elle se change aussi depuis l'écran de chaque rapport.
            </small>
        </div>
    </div>

    @php $moitie = (int) ceil(count(RapportsComptablesController::RAPPORTS) / 2); $paquets = array_chunk(RapportsComptablesController::RAPPORTS, $moitie, true); @endphp
    <div class="row g-4">
        @foreach ($paquets as $paquet)
            <div class="col-lg-6">
                @foreach ($paquet as $cle => [$titre, $explication, $icone, $demande])
                    <div class="card dash-card mb-3">
                        <div class="card-body d-flex align-items-start gap-3">
                            <div class="kpi-card-icon flex-shrink-0" style="width:48px;height:48px;">
                                <i class="material-icons {{ $icone }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">
                                    {{ $titre }}
                                    @unless ($demande) <span class="badge bg-secondary ms-1">Complémentaire</span> @endunless
                                </h6>
                                <p class="text-muted small mb-2">{{ $explication }}</p>
                                <a href="{{ route('show.comptabilite.rapports.' . $routesDesRapports[$cle], request()->only(['mode_periode', 'periode', 'du', 'au'])) }}"
                                   class="btn btn-sm btn-primary">Ouvrir</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    @if ($deversements->isNotEmpty())
        <div class="card dash-card mt-2">
            <div class="card-header dash-card-header">
                <h5 class="dash-card-title"><i class="material-icons md-send text-primary"></i> Dernières transmissions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table dash-table align-middle mb-0">
                        <thead><tr><th>N°</th><th>Transmis le</th><th>Période</th><th class="text-end">Écritures</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach ($deversements as $deversement)
                                <tr>
                                    <td><strong>{{ $deversement->numero }}</strong></td>
                                    <td>{{ \Help::dateHeure($deversement->created_at) }}</td>
                                    <td>{{ $deversement->libelle_periode }}</td>
                                    <td class="text-end">{{ $deversement->nombre_ecritures }}</td>
                                    <td class="text-end">{{ $francs($deversement->total_debit) }} F</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
