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
                        <span class="text-success h4">montant de la facture : {{ number_format($totalFacture, 0, '', ' ') }} fcfa</span>
                        <span class="text-success h4">Montant reglé : {{ number_format($totalRegle, 0, '', ' ') }} fcfa</span>
                        <span class="text-success h4">SOLDE : {{ number_format($totalSolde, 0, '', ' ') }} fcfa</span>
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
                            <th class="text-center">Compte Tier</th>
                            <th class="text-center">CLIENTS</th> {{--  --}}
                            <th class="text-center">N° FACTURE / Chèque / Reçu</th>
                            <th class="text-center">Montant de la fature</th> {{--  --}}
                            <th class="text-center">Montant reglé</th> {{--  --}}
                            <th class="text-center">SOLDE </th>
                            <th class="text-center">Date Ech </th>
                            <th class="text-center">Date EXO </th>
                            <th class="text-center">Échéance </th>
                            <th class="text-center">Age </th>
                            <th class="text-center">Ageing1 </th>
                            <th class="text-center">Ageing2 </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lignes as $l)
                            <tr>
                                <td class="text-center">{{ $l->date ? \Carbon\Carbon::parse($l->date)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->client_id ?? '-' }}</td>
                                <td class="text-center">{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->numero }}</td>
                                <td class="text-center">{{ number_format($l->total_a_payer, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->montant_paye, 0, '', ' ') }}</td>
                                <td class="text-center">{{ number_format($l->reste, 0, '', ' ') }}</td>
                                <td class="text-center">{{ $l->date_echeance ? \Carbon\Carbon::parse($l->date_echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">-</td>
                                <td class="text-center">{{ $l->facture->statutCreance() }}</td>
                                <td class="text-center">{{ $l->jours_retard }} j</td>
                                <td class="text-center">-</td>
                                <td class="text-center">-</td>
                            </tr>
                        @endforeach
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
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
