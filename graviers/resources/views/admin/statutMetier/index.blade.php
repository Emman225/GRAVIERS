@extends('layout.main')
@section('title', 'Statuts métier')

@php
    use Carbon\Carbon;
    $totalStatuts  = $statuts->count();
    $totalActifs   = $statuts->where('statut', true)->count();
    $totalDomaines = $statuts->pluck('domaine')->unique()->count();
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Statuts <span class="dash-welcome-name">Métier</span> 🏷️
                </h2>
                <p class="dash-welcome-subtitle">
                    Référentiel des statuts utilisés dans toute l'application
                    (créances, dettes, livreurs, apporteurs) — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.statutMetier.create', ['domaine' => $domaineFiltre ?: 'creance_terme']) }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Nouveau statut
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
                <div class="kpi-card-icon"><i class="material-icons md-label"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total statuts</div>
                    <div class="kpi-card-value">{{ $totalStatuts }}</div>
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
                <div class="kpi-card-icon"><i class="material-icons md-category"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Domaines couverts</div>
                    <div class="kpi-card-value">{{ $totalDomaines }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== FILTRE & TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title">
                <i class="material-icons md-filter_list text-primary"></i>
                Filtre par domaine
            </h5>
            <form method="get" class="d-flex gap-2 align-items-center">
                <select name="domaine" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">— Tous les domaines —</option>
                    @foreach ($domaines as $key => $label)
                        <option value="{{ $key }}" {{ $domaineFiltre === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($domaineFiltre)
                    <a href="{{ route('show.statutMetier.index') }}" class="btn btn-sm btn-light">Réinitialiser</a>
                @endif
            </form>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="statuts-metier" title="Statuts métier" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="liste">
                    <thead>
                        <tr>
                            <th>Domaine</th>
                            <th>Libellé</th>
                            <th class="text-center">Aperçu</th>
                            <th>Description</th>
                            <th class="text-center">Ordre</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($statuts as $s)
                            <tr>
                                <td>
                                    <small class="text-muted">{{ $domaines[$s->domaine] ?? $s->domaine }}</small>
                                </td>
                                <td><strong>{{ $s->libelle }}</strong></td>
                                <td class="text-center">
                                    <span class="badge {{ $s->badge_class }}">{{ $s->libelle }}</span>
                                </td>
                                <td><small>{{ $s->description ?? '-' }}</small></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $s->ordre }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($s->statut)
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
                                            <a href="{{ route('show.statutMetier.edit', $s) }}" class="dropdown-item">
                                                <i class="material-icons md-edit"></i> Modifier
                                            </a>
                                            <a href="{{ route('show.statutMetier.toggle', $s) }}" class="dropdown-item">
                                                @if ($s->statut)
                                                    <i class="material-icons md-block"></i> Désactiver
                                                @else
                                                    <i class="material-icons md-check_circle"></i> Activer
                                                @endif
                                            </a>
                                            <form action="{{ route('show.statutMetier.destroy', $s) }}" method="POST"
                                                  class="js-delete-form"
                                                  data-item-name="{{ $s->libelle }} ({{ $domaines[$s->domaine] ?? $s->domaine }})"
                                                  data-confirm-text="Si ce statut est encore utilisé dans des factures ou commandes, les badges associés afficheront la couleur par défaut.">
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
                            <tr><td colspan="7" class="text-center text-muted">Aucun statut enregistré.</td></tr>
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
                    order: [[0, 'asc'], [4, 'asc']],
                });
            }
        });
    </script>
@endsection
