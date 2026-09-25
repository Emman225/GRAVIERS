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
                            <th class="text-end">Moins de 30 j</th><th class="text-end">30 à 60 j</th><th class="text-end">60 à 90 j</th>
                            <th class="text-end">90 à 120 j</th><th class="text-end">Plus de 120 j</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['compte'] }}</strong></td>
                                <td>{{ $ligne['client'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['facture']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ligne['encaisse']) }}</td>
                                <td class="text-end text-nowrap"><strong>{{ $francs($ligne['solde']) }}</strong></td>
                                <td class="text-end text-nowrap">{{ $ligne['moins_30'] > 0 ? $francs($ligne['moins_30']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $ligne['de_30_60'] > 0 ? $francs($ligne['de_30_60']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $ligne['de_60_90'] > 0 ? $francs($ligne['de_60_90']) : '' }}</td>
                                <td class="text-nowrap text-end {{ $ligne['de_90_120'] > 0 ? 'text-warning fw-bold' : '' }}">{{ $ligne['de_90_120'] > 0 ? $francs($ligne['de_90_120']) : '' }}</td>
                                <td class="text-nowrap text-end {{ $ligne['plus_120'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $ligne['plus_120'] > 0 ? $francs($ligne['plus_120']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted">Aucun client facturé jusqu'à cette date.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-receipt_long text-primary"></i> Le détail, facture par facture</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Un règlement ne désigne pas une facture mais une AFFAIRE, qui peut en porter plusieurs.
                Il s'impute donc sur la facture qu'il nomme si elle est connue, sinon sur la plus ancienne
                facture encore due de la même affaire. Un avoir et une annulation s'imputent de la même façon.
            </p>
            <x-export-buttons table-id="tableFacturesClients" filename="detail-des-factures-clients" title="Situation des clients — le détail par facture" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableFacturesClients">
                    <thead>
                        <tr>
                            <th>Client</th><th>N° facture</th><th>Date</th>
                            <th class="text-end">Montant</th><th class="text-end">Réglé</th><th class="text-end">Reste dû</th>
                            <th class="text-end">Âge</th><th>Tranche</th><th>Moyen de paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($factures as $f)
                            <tr>
                                <td>{{ $f['client'] }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('show.comptabilite.ecritures.detail', $f['ecriture_id']) }}">{{ $f['numero'] }}</a>
                                </td>
                                <td data-order="{{ $f['date']->format('Ymd') }}" class="text-nowrap">{{ $f['date']->format('d/m/Y') }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['montant']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['regle']) }}</td>
                                <td class="text-end text-nowrap {{ $f['reste'] > 0 ? 'fw-bold' : 'text-muted' }}">{{ $francs($f['reste']) }}</td>
                                <td class="text-end text-nowrap">{{ $f['reste'] > 0 ? $f['jours'] . ' j' : '—' }}</td>
                                <td class="text-nowrap">{{ $f['tranche'] }}</td>
                                <td class="td-texte-long">{{ $f['moyen'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">Aucune facture jusqu'à cette date.</td></tr>
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
            var $d = $('#tableFacturesClients');
            if ($d.find('tbody tr').length && !$d.find('tbody tr td[colspan]').length) {
                $d.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[5, 'desc']] });
            }
        });
    </script>
@endsection
