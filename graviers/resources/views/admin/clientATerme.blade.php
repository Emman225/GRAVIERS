@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Client à termes')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Client à termes - </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div style="width:100%" class="col-lg-4 col-md-6 me-auto">
                    <p class="d-flex justify-content-between" >
                        <span class="text-success h4">Total facturé : {{ number_format($totalFacture, 0, ',', ' ') }} fcfa</span>
                        <span class="text-success h4">Total réglé : {{ number_format($totalRegle, 0, ',', ' ') }} fcfa</span>
                        <span class="text-success h4">Solde restant dû : {{ number_format($totalSolde, 0, ',', ' ') }} fcfa</span>
                    </p>
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="etat-client-a-terme" title="Etat client à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center">Date mvt</th>
                            <th class="text-center">Compte tiers</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">N° facture</th>
                            <th class="text-center">Montant de la facture</th>
                            <th class="text-center">Montant réglé</th>
                            <th class="text-center">Solde</th>
                            <th class="text-center">Date d'échéance</th>
                            {{-- « Échéance » nommait en fait le statut de la créance, et trois
                                 colonnes — Date EXO, Ageing1, Ageing2 — n'affichaient qu'un
                                 tiret écrit en dur. La ventilation par ancienneté existe, et
                                 c'est la Balance âgée. --}}
                            <th class="text-center">Statut</th>
                            <th class="text-center">Retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr>
                                <td class="text-center">{{ $l->date ? \Carbon\Carbon::parse($l->date)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->client_id ?? '-' }}</td>
                                <td class="text-center">{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->numero }}</td>
                                <td class="text-end">{{ number_format($l->total_a_payer, 0, ',', ' ') }}</td>
                                <td class="text-end">{{ number_format($l->montant_paye, 0, ',', ' ') }}</td>
                                <td class="text-end fw-bold">{{ number_format($l->reste, 0, ',', ' ') }}</td>
                                <td class="text-center">{{ $l->date_echeance ? \Carbon\Carbon::parse($l->date_echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->facture?->statutCreance() }}</td>
                                <td class="text-center">{{ $l->jours_retard > 0 ? $l->jours_retard . ' j' : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    Aucune créance client à terme.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            // Sans ce garde-fou, une table vide — le cas dès qu'aucun client à
            // terme ne doit rien — fait échouer DataTables sur « Requested
            // unknown parameter » : la ligne « Aucune créance » n'a qu'une
            // cellule là où il en attend dix.
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: {
                        url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    },
                });
            }
        });
    </script>
@endsection
