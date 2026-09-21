@extends('layout.main')
@section('title', 'Gestion des régions')

@php
    use Carbon\Carbon;
    $totalRegions = $regions->count();
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Gestion des <span class="dash-welcome-name">Régions</span> 🗺️
                </h2>
                <p class="dash-welcome-subtitle">
                    Référentiel des régions desservies — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('saved'))
        <div class="alert alert-success">{{ session('saved') }}</div>
    @endif

    {{-- Une cle PROPRE, et non « error » : Flasher capte success/error/warning/info
         pour les rejouer en bulle, et un @if (session('error')) ne s'afficherait
         jamais. Ce message doit rester sous les yeux du gestionnaire : il lui dit
         quoi faire avant de pouvoir supprimer. --}}
    @if (session('erreurRegion'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('erreurRegion') }}
        </div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total régions</div>
                    <div class="kpi-card-value">{{ $totalRegions }}</div>
                    @php
                        $aCompleter = $regions->filter(fn ($r) => !$r->description || (!$r->long && !$r->lat))->count();
                    @endphp
                    @if ($aCompleter)
                        <div class="kpi-card-meta">
                            <span class="kpi-card-meta-text text-warning">
                                {{ $aCompleter }} région(s) à compléter
                            </span>
                        </div>
                    @endif
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
                        Régions enregistrées
                    </h5>
                    {{-- La saisie se fait sur SA page : cet ecran ne sert plus qu a consulter. --}}
                    <a href="{{ route('show.nouvelleRegion') }}" class="btn btn-sm btn-primary"><i class="material-icons md-add align-middle"></i> Nouvelle région</a>
                </div>
                <div class="card-body">
                    <x-export-buttons table-id="listeRegions"
                                      filename="liste-des-regions"
                                      title="Liste des régions" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0" id="listeRegions">
                            <thead>
                                <tr>
                                    <th>Région</th>
                                    <th>Adresse</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($regions as $r)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div class="dash-counter-icon dash-counter-icon-primary" style="width:34px;height:34px;font-size:16px;">
                                                    <i class="material-icons md-public"></i>
                                                </div>
                                                <strong>{{ $r->nom }}</strong>
                                            </div>
                                        </td>
                                        {{-- UNE ADRESSE MANQUANTE SE DIT.
                                             Un simple tiret laissait croire à un détail
                                             d'affichage. C'en est un tout autre : une
                                             région sans coordonnées facture le coût de
                                             livraison MINIMUM, quelle que soit la
                                             distance réelle (Help::coutLivraison). --}}
                                        <td>
                                            @if ($r->description)
                                                <small class="text-muted">{{ $r->description }}</small>
                                            @else
                                                <span class="badge bg-warning text-dark">Adresse à renseigner</span>
                                                @if (!$r->long && !$r->lat)
                                                    <div><small class="text-danger">Sans coordonnées : livraison facturée au minimum.</small></div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="text-nowrap text-end">
                                            <a href="{{ route('show.modifierRegion', $r) }}"
                                               class="btn btn-sm btn-primary rounded" title="Modifier la région">
                                                <i class="material-icons md-edit"></i>
                                            </a>
                                            <a href="{{ route('show.supprimerRegion', $r) }}"
                                               class="btn btn-sm btn-danger rounded" title="Supprimer la région"
                                               data-confirm-msg="Voulez-vous vraiment supprimer la région {{ $r->nom }} ?">
                                                <i class="material-icons md-delete"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Aucune région enregistrée.</td>
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
            var $table = $('#listeRegions');
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
