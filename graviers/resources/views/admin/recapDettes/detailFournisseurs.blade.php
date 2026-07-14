@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Détail dettes fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Détail dettes fournisseurs</h2>
        <small class="text-muted">Source : suivi des dettes fournisseurs</small>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalTtc   = $lignes->sum('montant_ttc');
                        $totalReste = $lignes->sum('reste_a_payer');
                    @endphp
                    <p class="d-flex justify-content-between mb-0">
                        <span class="h5">Nombre d'enlèvements : <strong>{{ $lignes->count() }}</strong></span>
                        <span class="h5">Total TTC : <strong class="text-primary">{{ Help::formatNombre($totalTtc, true) }}</strong></span>
                        <span class="h5">Reste à payer : <strong class="text-danger">{{ Help::formatNombre($totalReste, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="detail-dettes-fournisseurs" title="Détail dettes fournisseurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Bon</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Fournisseur</th>
                            <th class="text-center">N° Cmd Client liée</th>
                            <th class="text-center">Produit</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $badge = \App\Models\StatutMetier::badgeFor($l->statut, 'dette_fournisseur');
                            @endphp
                            <tr class="{{ $l->statut === 'Échue impayée' ? 'table-danger' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero_be }}</strong></td>
                                <td class="text-center">{{ $l->date ? Carbon::parse($l->date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->fournisseur_nom }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td>{{ $l->produit }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ttc, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $l->statut }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">Aucun enlèvement enregistré.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL</td>
                                <td class="text-end">{{ Help::formatNombre($totalTtc, true) }}</td>
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
