@extends('layout.main')
@section('title', 'Soldes des comptes')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['ouverture', 'debit', 'credit', 'cloture', 'deverse', 'attente']);
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableSoldes" filename="soldes-des-comptes" title="Suivi des soldes des comptes" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableSoldes">
                    <thead>
                        <tr>
                            <th>Compte</th>
                            <th>Libellé</th>
                            <th class="text-end">Ouverture débit</th>
                            <th class="text-end">Ouverture crédit</th>
                            <th class="text-end">Mouvement débit</th>
                            <th class="text-end">Mouvement crédit</th>
                            <th class="text-end">Solde final débit</th>
                            <th class="text-end">Solde final crédit</th>
                            <th class="text-end">Déversé</th>
                            <th class="text-end">En attente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['compte'] }}</strong></td>
                                <td class="td-texte-long">{{ $ligne['libelle'] }}</td>
                                <td class="text-end text-nowrap">{{ $ligne['ouverture_debit'] > 0 ? $francs($ligne['ouverture_debit']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $ligne['ouverture_credit'] > 0 ? $francs($ligne['ouverture_credit']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['debit']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['credit']) }}</td>
                                <td class="text-end text-nowrap"><strong>{{ $ligne['cloture_debit'] > 0 ? $francs($ligne['cloture_debit']) : '' }}</strong></td>
                                <td class="text-end text-nowrap"><strong>{{ $ligne['cloture_credit'] > 0 ? $francs($ligne['cloture_credit']) : '' }}</strong></td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['deverse']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['attente']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">Aucun mouvement sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totaux['ouverture']) }}</th>
                                <th class="text-end">{{ $francs($totaux['debit']) }}</th>
                                <th class="text-end">{{ $francs($totaux['credit']) }}</th>
                                <th class="text-end">{{ $francs($totaux['cloture']) }}</th>
                                <th class="text-end">{{ $francs($totaux['deverse']) }}</th>
                                <th class="text-end">{{ $francs($totaux['attente']) }}</th>
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
    <script>
        $(function () {
            var $t = $('#tableSoldes');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
