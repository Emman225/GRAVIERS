@extends('layout.main')
@section('title', 'Chiffre d\'affaires')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    $variation = function ($actuel, $avant) {
        if ($avant <= 0) { return null; }
        return round(($actuel - $avant) / $avant * 100, 1);
    };
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableVentes" filename="chiffre-daffaires" title="Chiffre d'affaires par famille et par produit" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableVentes">
                    <thead>
                        <tr>
                            <th>Grande famille</th><th>Compte analytique</th><th>Produit</th>
                            <th class="text-end">Période</th><th class="text-end">Mois précédent</th><th class="text-end">Variation</th>
                            <th class="text-end">Même mois, année précédente</th><th class="text-end">Variation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            @php
                                $vsMois = $variation($ligne['montant'], $ligne['mois_precedent']);
                                $vsAn = $variation($ligne['montant'], $ligne['annee_precedente']);
                            @endphp
                            <tr>
                                <td><strong>{{ $ligne['famille'] }}</strong></td>
                                <td>{{ $ligne['analytique'] }}</td>
                                <td>{{ $ligne['produit'] }}</td>
                                <td class="text-end">{{ $francs($ligne['montant']) }}</td>
                                <td class="text-end">{{ $francs($ligne['mois_precedent']) }}</td>
                                <td class="text-end">{{ $vsMois !== null ? ($vsMois >= 0 ? '+' : '') . $vsMois . ' %' : '-' }}</td>
                                <td class="text-end">{{ $francs($ligne['annee_precedente']) }}</td>
                                <td class="text-end">{{ $vsAn !== null ? ($vsAn >= 0 ? '+' : '') . $vsAn . ' %' : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Aucune vente sur cette période.</td></tr>
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
    <script>
        $(function () {
            var $t = $('#tableVentes');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[3, 'desc']] });
            }
        });
    </script>
@endsection
