@extends('layout.main')
@section('title', 'Types de véhicules livreurs')

@php
    use Carbon\Carbon;
    $totalTypes    = $types->count();
    $totalActifs   = $types->where('statut', true)->count();
    $totalCapacite = $types->sum(function ($t) {
        return ((float) ($t->capacite_tonnes ?? 0)) * $t->livreurs()->count();
    });
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Types de <span class="dash-welcome-name">Véhicules Livreurs</span> 🚛
                </h2>
                <p class="dash-welcome-subtitle">
                    Catalogue des types de véhicules utilisables par la flotte — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.typeVehiculeLivreur.create') }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Nouveau type
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-local_shipping"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total types</div>
                    <div class="kpi-card-value">{{ $totalTypes }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Actifs</div>
                    <div class="kpi-card-value">{{ $totalActifs }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-fitness_center"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Capacité flotte cumulée</div>
                    <div class="kpi-card-value">{{ rtrim(rtrim(number_format($totalCapacite, 1, ',', ' '), '0'), ',') }}<span class="kpi-card-currency">T</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <p class="text-muted small mb-3">
                <i class="material-icons md-info" style="vertical-align:middle;font-size:16px;"></i>
                Catalogue issu de la feuille « Paramètres » du fichier <em>04_Suivi_Dettes_Livreurs.xlsx</em>.
            </p>
            <x-export-buttons table-id="liste" filename="types-vehicules-livreurs" title="Types de véhicules livreurs" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="liste">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th class="text-end">Capacité (T)</th>
                            <th>Description</th>
                            <th class="text-center">Nb livreurs</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($types as $t)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="dash-counter-icon dash-counter-icon-primary" style="width:38px;height:38px;font-size:18px;">
                                            <i class="material-icons md-local_shipping"></i>
                                        </div>
                                        <strong>{{ $t->libelle }}</strong>
                                    </div>
                                </td>
                                <td class="text-end">
                                    @if ($t->capacite_tonnes)
                                        <strong>{{ rtrim(rtrim(number_format($t->capacite_tonnes, 2, ',', ' '), '0'), ',') }}</strong> T
                                    @else
                                        -
                                    @endif
                                </td>
                                <td><small>{{ $t->description ?? '-' }}</small></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark" style="font-size:0.85rem;">
                                        {{ $t->livreurs()->count() }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    @if ($t->statut)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-secondary">Désactivé</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm">
                                            <i class="material-icons md-more_horiz"></i> Actions
                                        </a>
                                        <div class="dropdown-menu">
                                            <a href="{{ route('show.typeVehiculeLivreur.edit', $t) }}" class="dropdown-item">
                                                <i class="material-icons md-edit"></i> Modifier
                                            </a>
                                            <a href="{{ route('show.typeVehiculeLivreur.toggle', $t) }}" class="dropdown-item">
                                                @if ($t->statut)
                                                    <i class="material-icons md-block"></i> Désactiver
                                                @else
                                                    <i class="material-icons md-check_circle"></i> Activer
                                                @endif
                                            </a>
                                            <form action="{{ route('show.typeVehiculeLivreur.destroy', $t) }}" method="POST"
                                                  class="js-delete-form"
                                                  data-item-name="{{ $t->libelle }}"
                                                  data-confirm-text="Cette action est irréversible. Impossible si des livreurs y sont rattachés.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="material-icons md-delete"></i> Supprimer
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucun type enregistré. <a href="{{ route('show.typeVehiculeLivreur.create') }}">Créer le premier</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[1, 'asc']],
                });
            }
        });
    </script>
@endsection
