@php
    use Illuminate\Support\Carbon;

    $total   = $clients->count();
    $recents = $clients->filter(fn ($c) => $c->created_at && Carbon::parse($c->created_at)->diffInDays(now()) <= 2)->count();
    $anciens = $total - $recents;
@endphp

@extends('layout.main')
@section('title', 'Inscriptions en attente')

@section('contenu')
    {{-- ===== HEADER WELCOME =====
         Le même bandeau que les autres listes du back-office : cet écran
         gardait un simple titre et détonnait au milieu des autres. --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Inscriptions <span class="dash-welcome-name">en attente</span> ⏳
                </h2>
                <p class="dash-welcome-subtitle">
                    Comptes commencés sans confirmation — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-hourglass_empty"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total en attente</div>
                    <div class="kpi-card-value">{{ $total }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-schedule"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Depuis 2 jours ou moins</div>
                    <div class="kpi-card-value">{{ $recents }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-event_busy"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Depuis plus de 2 jours</div>
                    <div class="kpi-card-value">{{ $anciens }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-body">
            <p class="text-muted mb-0">
                Ces personnes ont commencé leur inscription sans jamais saisir le code de
                confirmation reçu par e-mail. Leur compte n'est donc pas actif : elles ne
                peuvent ni se connecter, ni commander, et leur adresse e-mail reste
                réservée.
                <br>
                Pour débloquer l'une d'elles, invitez-la à refaire son inscription avec la
                même adresse : un nouveau code lui sera envoyé.
            </p>
        </div>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="liste"
                              filename="inscriptions-en-attente"
                              title="Inscriptions en attente de confirmation" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="liste">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Type de compte</th>
                            <th class="text-center">Inscription commencée le</th>
                            <th class="text-center">Depuis</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $c)
                            @php
                                $debut = $c->created_at ? Carbon::parse($c->created_at) : null;
                                $jours = $debut ? $debut->diffInDays(now()) : null;
                            @endphp
                            <tr>
                                <td>{{ $c->display_name }}</td>
                                <td>{{ $c->email ?: $c->user?->email }}</td>
                                <td class="text-center">{{ $c->contact1 }}</td>
                                <td class="text-center">{{ $c->type_client }}</td>
                                {{-- data-order : sans lui, DataTables trierait la date comme
                                     du texte et « 09-08-2026 » passerait avant « 10-07-2026 ». --}}
                                <td class="text-center" data-order="{{ $debut?->format('Y-m-d H:i:s') }}">
                                    {{ $debut ? $debut->format('d/m/Y à H:i:s') : '-' }}
                                </td>
                                <td class="text-center" data-order="{{ $jours }}">
                                    @if ($jours === null)
                                        -
                                    @elseif ($jours === 0)
                                        <span class="badge bg-info">aujourd'hui</span>
                                    @elseif ($jours <= 2)
                                        <span class="badge bg-warning text-dark">{{ $jours }} j</span>
                                    @else
                                        <span class="badge bg-danger">{{ $jours }} j</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucune inscription en attente. Toutes les inscriptions
                                    commencées ont été menées à leur terme.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

{{-- LES SECTIONS S'APPELLENT « cssParts » ET « jsParts ».
     Cet écran écrivait « jspart » — le nom retenu par le gabarit CLIENT, pas
     par celui du back-office. Le bloc n'était donc jamais rendu : ni la
     bibliothèque, ni son initialisation. Le tableau restait brut, sans
     recherche, sans tri et sans pagination — c'est précisément ce qui le
     distinguait des autres. --}}
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#liste');

            // Garde-fou : DataTables lève « Requested unknown parameter » quand
            // le tableau ne porte que la ligne « aucun résultat », dont le
            // colspan ne correspond à aucune colonne déclarée.
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[4, 'desc']],
                });
            }
        });
    </script>
@endsection
