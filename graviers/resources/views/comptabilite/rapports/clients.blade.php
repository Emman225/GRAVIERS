@extends('layout.main')
@section('title', 'Situation des clients')

@php $francs = fn ($m) => number_format((float) $m, 0, ',', ' '); @endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <p class="text-muted">Situation à la date de fin de la période choisie. Une ligne réglée en entier (lettrée) ne compte plus dans l'ancienneté.</p>
            <x-export-buttons table-id="tableClients" filename="situation-des-clients" title="Situation des clients" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableClients">
                    <thead>
                        <tr>
                            <th>Compte tiers</th><th>Client</th>
                            <th class="text-end">Facturé</th><th class="text-end">Encaissé</th><th class="text-end">Solde dû</th>
                            <th class="text-end">Moins de 30 j</th><th class="text-end">30 à 60 j</th><th class="text-end">60 à 90 j</th><th class="text-end">Plus de 90 j</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['compte'] }}</strong></td>
                                <td>{{ $ligne['client'] }}</td>
                                <td class="text-end">{{ $francs($ligne['facture']) }}</td>
                                <td class="text-end">{{ $francs($ligne['encaisse']) }}</td>
                                <td class="text-end"><strong>{{ $francs($ligne['solde']) }}</strong></td>
                                <td class="text-end">{{ $ligne['moins_30'] > 0 ? $francs($ligne['moins_30']) : '' }}</td>
                                <td class="text-end">{{ $ligne['de_30_60'] > 0 ? $francs($ligne['de_30_60']) : '' }}</td>
                                <td class="text-end">{{ $ligne['de_60_90'] > 0 ? $francs($ligne['de_60_90']) : '' }}</td>
                                <td class="text-end {{ $ligne['plus_90'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $ligne['plus_90'] > 0 ? $francs($ligne['plus_90']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">Aucun client facturé jusqu'à cette date.</td></tr>
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
            var $t = $('#tableClients');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[4, 'desc']] });
            }
        });
    </script>
@endsection
