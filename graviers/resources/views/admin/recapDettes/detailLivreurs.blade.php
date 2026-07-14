@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Détail dettes livreurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Détail dettes livreurs</h2>
        <small class="text-muted">Source : suivi des dettes livreurs</small>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalDu    = $lignes->sum('total_du');
                        $totalReste = $lignes->sum('reste_a_payer');
                    @endphp
                    <p class="d-flex justify-content-between mb-0">
                        <span class="h5">Nombre de livraisons : <strong>{{ $lignes->count() }}</strong></span>
                        <span class="h5">Total dû : <strong class="text-primary">{{ Help::formatNombre($totalDu, true) }}</strong></span>
                        <span class="h5">Reste à payer : <strong class="text-danger">{{ Help::formatNombre($totalReste, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="detail-dettes-livreurs" title="Détail dettes livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Livraison</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Livreur</th>
                            <th class="text-center">N° Cmd liée</th>
                            <th class="text-center">Client</th>
                            <th class="text-end">Total Dû</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $badge = \App\Models\StatutMetier::badgeFor($l->statut, 'dette_livreur');
                            @endphp
                            <tr>
                                <td class="text-center"><strong>{{ $l->numero_liv }}</strong></td>
                                <td class="text-center">{{ $l->date ? Carbon::parse($l->date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->livreur_nom }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td>{{ $l->client_nom }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->total_du, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $l->statut }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucune livraison enregistrée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL</td>
                                <td class="text-end">{{ Help::formatNombre($totalDu, true) }}</td>
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
