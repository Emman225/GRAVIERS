@extends('layout.main')
@section('title', 'Rapprochement FNE / écritures')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    $badges = ['CONFORME' => 'bg-success', 'ECART' => 'bg-warning text-dark', 'ANOMALIE' => 'bg-warning text-dark', 'ABSENTE' => 'bg-danger'];
    $libelles = ['CONFORME' => 'Conforme', 'ECART' => 'Écart de montant', 'ANOMALIE' => 'En anomalie', 'ABSENTE' => 'Écriture absente'];
    $manquantes = collect($lignes)->where('etat', 'ABSENTE')->count();
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    @if ($manquantes > 0)
        <div class="alert alert-danger">
            <strong>{{ $manquantes }} facture(s) certifiée(s) sans écriture.</strong>
            Lancez la reprise depuis le <a href="{{ route('show.comptabilite.ecritures.anomalies') }}" class="alert-link">rapport d'anomalies</a>,
            ou la commande <code>php artisan comptabilite:produire-ecritures</code>.
        </div>
    @endif

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableRapprochement" filename="rapprochement-fne" title="Rapprochement factures FNE / écritures" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableRapprochement">
                    <thead>
                        <tr>
                            <th>Facture</th><th>Réf. FNE</th><th>Date certification</th><th>Type</th>
                            <th class="text-end">Montant facture</th><th>Écriture</th><th class="text-end">Écart</th><th class="text-center">État</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['facture'] }}</strong></td>
                                <td>{{ $ligne['reference'] ?: '-' }}</td>
                                <td data-order="{{ optional($ligne['date'])->format('YmdHis') }}">{{ \Help::dateHeure($ligne['date']) }}</td>
                                <td>{{ $ligne['type'] }}</td>
                                <td class="text-end">{{ $francs($ligne['montant']) }}</td>
                                <td>
                                    @if ($ligne['ecriture'])
                                        <a href="{{ route('show.comptabilite.ecritures.detail', $ligne['ecriture']) }}">{{ $ligne['ecriture']->identifiant }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end">{{ $ligne['ecart'] !== null ? $francs($ligne['ecart']) : '-' }}</td>
                                <td class="text-center"><span class="badge {{ $badges[$ligne['etat']] }}">{{ $libelles[$ligne['etat']] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Aucune facture certifiée sur cette période.</td></tr>
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
            var $t = $('#tableRapprochement');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[2, 'asc']] });
            }
        });
    </script>
@endsection
