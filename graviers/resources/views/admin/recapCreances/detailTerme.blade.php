@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Recap global créances - Détail terme')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">📋 Détail des créances clients à terme</h2>
        <div>
            <a href="{{ route('show.recapCreances.dashboard') }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour tableau de bord
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalTtc   = $lignes->sum('montant_ttc');
                        $totalPaye  = $lignes->sum('paye');
                        $totalReste = $lignes->sum('reste_du');
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre de factures</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total TTC</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalTtc, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total payé</span><br>
                            <strong class="h5 text-success">{{ Help::formatNombre($totalPaye, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Restant dû</span><br>
                            <strong class="h5 text-danger">{{ Help::formatNombre($totalReste, true) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="recap-detail-terme" title="Détail créances clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Date Fact.</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Payé</th>
                            <th class="text-end">Restant Dû</th>
                            <th class="text-center">Échéance</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Jours Retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $badge = \App\Models\StatutMetier::badgeFor($l->statut, 'creance_terme');
                                // Mapping vers libellés Excel (conservé : c'est un libellé alternatif d'affichage)
                                $statutLabel = match($l->statut) {
                                    'Soldée'         => 'Payée',
                                    'Échue partielle'=> 'Payée Partielle',
                                    'Échue impayée'  => 'En retard',
                                    default          => $l->statut,
                                };
                                $alerte = $l->jours_retard >= 30;
                            @endphp
                            <tr class="{{ $alerte ? 'table-danger' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero }}</strong></td>
                                <td class="text-center">{{ $l->date_facture ? Carbon::parse($l->date_facture)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->telephone }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ttc, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->paye, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_du, true) }}</strong></td>
                                <td class="text-center">{{ $l->echeance ? Carbon::parse($l->echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $statutLabel }}</span></td>
                                <td class="text-center">
                                    @if ($l->jours_retard > 0)
                                        <span class="badge bg-danger">{{ $l->jours_retard }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">
                                    Aucune facture pour les clients à terme.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #fff3cd; font-weight: bold;">
                            <tr>
                                <td colspan="4" class="text-end">TOTAL</td>
                                <td class="text-end">{{ Help::formatNombre($totalTtc, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totalReste, true) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    @endif
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
                    order: [[1, 'desc']],
                });
            }
        });
    </script>
@endsection
