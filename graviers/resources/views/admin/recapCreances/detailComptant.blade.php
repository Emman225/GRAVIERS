@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Recap global créances - Détail comptant')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">🏬 Détail des créances clients comptant (paiement en agence)</h2>
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
                        $totalTtc      = $lignes->sum('montant_ttc');
                        $totalEncaisse = $lignes->sum('encaisse');
                        $totalReste    = $lignes->sum('reste_du');
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre de commandes</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total TTC</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalTtc, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total encaissé</span><br>
                            <strong class="h5 text-success">{{ Help::formatNombre($totalEncaisse, true) }}</strong>
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
            <x-export-buttons table-id="liste" filename="recap-detail-comptant" title="Détail créances clients comptant" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Agence</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Encaissé</th>
                            <th class="text-end">Restant Dû</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $badge = match($l->statut) {
                                    'Encaissée'  => 'bg-success',
                                    'Partielle'  => 'bg-warning text-dark',
                                    'Échu'       => 'bg-danger',
                                    'Livrée'     => 'bg-info',
                                    'Annulée'    => 'bg-dark',
                                    default      => 'bg-secondary',
                                };
                                $alerte = $l->statut === 'Échu';
                            @endphp
                            <tr class="{{ $alerte ? 'table-danger' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero }}</strong></td>
                                <td class="text-center">{{ $l->date ? Carbon::parse($l->date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->telephone }}</td>
                                <td class="text-center">{{ $l->agence }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ttc, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->encaisse, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_du, true) }}</strong></td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $l->statut }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    Aucune commande comptant.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #fff3cd; font-weight: bold;">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL</td>
                                <td class="text-end">{{ Help::formatNombre($totalTtc, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalEncaisse, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totalReste, true) }}</td>
                                <td></td>
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
