@extends('layout.main')
@section('title', 'État par grande famille')

@php $francs = fn ($m) => number_format((float) $m, 0, ',', ' '); @endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableFamilles" filename="etat-par-famille" title="État déversé / non déversé par grande famille" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableFamilles">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th>Grande famille</th>
                            <th class="text-end">Déversé</th>
                            <th class="text-end">Non déversé</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Écart</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td data-order="{{ $ligne['mois'] }}">{{ $ligne['libelle_mois'] }}</td>
                                <td><strong>{{ $ligne['famille'] }}</strong></td>
                                <td class="text-end">{{ $francs($ligne['deverse']) }} <small class="text-muted">({{ $ligne['nombre_deverse'] }})</small></td>
                                <td class="text-end">{{ $francs($ligne['non_deverse']) }} <small class="text-muted">({{ $ligne['nombre_non_deverse'] }})</small></td>
                                <td class="text-end">{{ $francs($ligne['total']) }}</td>
                                <td class="text-end">{{ $francs($ligne['non_deverse']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Aucune vente sur cette période.</td></tr>
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
            var $t = $('#tableFamilles');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'desc']] });
            }
        });
    </script>
@endsection
