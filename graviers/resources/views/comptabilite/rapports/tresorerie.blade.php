@extends('layout.main')
@section('title', 'Trésorerie par mode de règlement')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['entrees', 'sorties', 'solde']);
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableTresorerie" filename="tresorerie-par-mode" title="Trésorerie par mode de règlement" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableTresorerie">
                    <thead><tr><th>Mois</th><th>Journal</th><th class="text-end">Entrées</th><th class="text-end">Sorties</th><th class="text-end">Solde du mouvement</th></tr></thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td data-order="{{ $ligne['mois'] }}">{{ $ligne['libelle_mois'] }}</td>
                                <td>{{ $ligne['journal'] }} — {{ $ligne['nom'] }}</td>
                                <td class="text-end">{{ $francs($ligne['entrees']) }}</td>
                                <td class="text-end">{{ $francs($ligne['sorties']) }}</td>
                                <td class="text-end"><strong>{{ $francs($ligne['solde']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Aucun mouvement de trésorerie sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totaux['entrees']) }}</th>
                                <th class="text-end">{{ $francs($totaux['sorties']) }}</th>
                                <th class="text-end">{{ $francs($totaux['solde']) }}</th>
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
            var $t = $('#tableTresorerie');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
