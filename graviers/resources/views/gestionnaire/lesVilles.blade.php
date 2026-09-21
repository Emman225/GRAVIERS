@extends('layout.main')
@section('title', 'Gestion des villes')

@php
    use Carbon\Carbon;
    $totalVilles  = $lesVilles->count();
    $totalRegions = $lesRegions->count();
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Gestion des <span class="dash-welcome-name">Villes</span> 🌆
                </h2>
                <p class="dash-welcome-subtitle">
                    Référentiel des villes desservies — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('saved'))
        <div class="alert alert-success">{{ session('saved') }}</div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-location_city"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total villes</div>
                    <div class="kpi-card-value">{{ $totalVilles }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Régions couvertes</div>
                    <div class="kpi-card-value">{{ $totalRegions }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    <div class="row g-3">

        {{-- ===== TABLEAU ===== --}}
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                    <h5 class="dash-card-title mb-0">
                        <i class="material-icons md-list text-primary"></i>
                        Villes enregistrées
                    </h5>
                    {{-- La saisie se fait sur SA page : cet ecran ne sert plus qu a consulter. --}}
                    <a href="{{ route('dest.nouvelleVille') }}" class="btn btn-sm btn-primary"><i class="material-icons md-add align-middle"></i> Nouvelle ville</a>
                </div>
                <div class="card-body">
                    <x-export-buttons table-id="listeVilles"
                                      filename="liste-des-villes"
                                      title="Liste des villes" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0" id="listeVilles">
                            <thead>
                                <tr>
                                    <th>Ville</th>
                                    <th>Région</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lesVilles as $v)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div class="dash-counter-icon dash-counter-icon-primary" style="width:34px;height:34px;font-size:16px;">
                                                    <i class="material-icons md-location_city"></i>
                                                </div>
                                                <strong>{{ $v->nom }}</strong>
                                            </div>
                                        </td>
                                        <td>{{ $v->region?->nom ?? '-' }}</td>
                                        <td class="text-nowrap text-end">
                                            <a href="{{ route('dest.modifierVille', $v) }}"
                                               class="btn btn-sm btn-primary rounded" title="Modifier la ville">
                                                <i class="material-icons md-edit"></i>
                                            </a>
                                            <a href="{{ route('dest.supprimerVille', $v) }}"
                                               class="btn btn-sm btn-danger rounded" title="Supprimer la ville"
                                               data-confirm-msg="Voulez-vous vraiment supprimer la ville {{ $v->nom }} ?">
                                                <i class="material-icons md-delete"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Aucune ville enregistrée.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
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
            var $table = $('#listeVilles');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'asc']],
                });
            }
        });
    </script>
@endsection
