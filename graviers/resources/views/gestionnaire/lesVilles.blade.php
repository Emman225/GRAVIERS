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
        {{-- ===== FORMULAIRE ===== --}}
        <div class="col-lg-5">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-add_location text-primary"></i>
                        {{ $ville->id ? 'Modifier la ville' : 'Nouvelle ville' }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom de la ville <span class="text-danger">*</span></label>
                            <input class="form-control" value="{{ $ville->nom }}" name="nom" type="text" />
                            @error('nom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Région <span class="text-danger">*</span></label>
                            <select class="form-control" name="region_id">
                                <option value="">— Sélectionnez une région —</option>
                                @foreach ($lesRegions as $region)
                                    <option @selected($ville->region_id == $region->id) value="{{ $region->id }}">{{ $region->nom }}</option>
                                @endforeach
                            </select>
                            @error('region_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i>
                                {{ $ville->id ? 'Modifier' : 'Enregistrer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== TABLEAU ===== --}}
        <div class="col-lg-7">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-list text-primary"></i>
                        Villes enregistrées
                    </h5>
                </div>
                <div class="card-body">
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
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm">
                                                    <i class="material-icons md-more_horiz"></i> Actions
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="{{ route('dest.modifierVille', $v) }}" class="dropdown-item">
                                                        <i class="material-icons md-edit"></i> Modifier
                                                    </a>
                                                    <a href="{{ route('dest.supprimerVille', $v) }}"
                                                       class="dropdown-item text-danger"
                                                       data-confirm-msg="Voulez-vous vraiment supprimer la ville {{ $v->nom }} ?">
                                                        <i class="material-icons md-delete"></i> Supprimer
                                                    </a>
                                                </div>
                                            </div>
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
