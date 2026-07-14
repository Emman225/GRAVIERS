@extends('layout.main')
@section('title', 'Liste des agences')

@php
    use Carbon\Carbon;
    $totalAgences   = $agences->count();
    $totalActives   = $agences->where('statut', 1)->count();
    $totalInactives = $totalAgences - $totalActives;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Liste des <span class="dash-welcome-name">Agences</span> 🏬
                </h2>
                <p class="dash-welcome-subtitle">
                    Référentiel des points de vente et de paiement — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.agences.create') }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Nouvelle agence
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
                <div class="kpi-card-icon"><i class="material-icons md-store"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total agences</div>
                    <div class="kpi-card-value">{{ $totalAgences }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Actives</div>
                    <div class="kpi-card-value">{{ $totalActives }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-block"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Inactives</div>
                    <div class="kpi-card-value">{{ $totalInactives }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="liste-agences" title="Liste des agences" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="liste">
                    <thead>
                        <tr>
                            <th>Agence</th>
                            <th>Adresse</th>
                            <th class="text-center">Téléphone</th>
                            <th>Responsable</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($agences as $a)
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="dash-counter-icon dash-counter-icon-primary" style="width:38px;height:38px;font-size:18px;">
                                            <i class="material-icons md-store"></i>
                                        </div>
                                        <div>
                                            <strong>{{ $a->code }}</strong>
                                            <div class="text-muted small">{{ $a->nom }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $a->adresse ?? '-' }}</td>
                                <td class="text-center">{{ $a->telephone ?? '-' }}</td>
                                <td>{{ $a->responsable ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($a->statut == 1)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm">
                                            <i class="material-icons md-more_horiz"></i> Actions
                                        </a>
                                        <div class="dropdown-menu">
                                            <a href="{{ route('show.agences.edit', $a) }}" class="dropdown-item">
                                                <i class="material-icons md-edit"></i> Modifier
                                            </a>
                                            <a href="{{ route('show.agences.toggle', $a) }}" class="dropdown-item">
                                                @if ($a->statut == 1)
                                                    <i class="material-icons md-block"></i> Désactiver
                                                @else
                                                    <i class="material-icons md-check_circle"></i> Activer
                                                @endif
                                            </a>
                                            <form action="{{ route('show.agences.destroy', $a) }}" method="POST"
                                                  class="js-delete-form"
                                                  data-item-name="{{ $a->code }} — {{ $a->nom }}"
                                                  data-confirm-text="Cette action est irréversible. Si l'agence est rattachée à des commandes ou paiements, elle ne pourra pas être supprimée.">
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
                                    Aucune agence enregistrée. <a href="{{ route('show.agences.create') }}">Créer la première</a>.
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
                });
            }
        });
    </script>
@endsection
