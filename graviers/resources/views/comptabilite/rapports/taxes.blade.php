@extends('layout.main')
@section('title', 'Taxes collectées')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['tva', 'airsi', 'total']);
    $totauxDetail = RapportsComptables::totaux($detail, ['ht', 'tva', 'airsi', 'ttc']);
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableTaxes" filename="taxes-collectees" title="État des taxes collectées" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableTaxes">
                    <thead><tr><th>Mois</th><th class="text-end">TVA facturée</th><th class="text-end">AIRSI collecté</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne['libelle'] }}</td>
                                <td class="text-end">{{ $francs($ligne['tva']) }}</td>
                                <td class="text-end">{{ $francs($ligne['airsi']) }}</td>
                                <td class="text-end"><strong>{{ $francs($ligne['total']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Aucune taxe collectée sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr>
                                <th class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totaux['tva']) }}</th>
                                <th class="text-end">{{ $francs($totaux['airsi']) }}</th>
                                <th class="text-end">{{ $francs($totaux['total']) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <small class="text-muted d-block mt-2">
                Pour préparer les déclarations mensuelles, rapprochez ces montants de ceux effectivement déclarés.
            </small>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-receipt text-primary"></i> Le détail, facture par facture</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Le HT est la somme des produits et du transport, remise déduite ; le TTC est ce que le client
                doit vraiment. Les totaux ci-dessous retombent sur ceux du récapitulatif mensuel : c'est aussi
                un contrôle. Seules les factures qui portent une taxe y figurent.
            </p>
            <x-export-buttons table-id="tableDetailTaxes" filename="detail-des-taxes" title="Taxes collectées — le détail par facture" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableDetailTaxes">
                    <thead>
                        <tr>
                            <th>Date</th><th>N° facture</th><th>Réf. FNE</th><th>Client</th>
                            <th class="text-end">Montant HT</th><th class="text-end">TVA facturée</th>
                            <th class="text-end">AIRSI collecté</th><th class="text-end">Montant TTC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($detail as $f)
                            <tr>
                                <td data-order="{{ $f['date']->format('Ymd') }}" class="text-nowrap">{{ $f['date']->format('d/m/Y') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('show.comptabilite.ecritures.detail', $f['ecriture_id']) }}">{{ $f['numero'] }}</a>
                                </td>
                                <td class="text-nowrap">{{ $f['reference'] ?: '-' }}</td>
                                <td class="td-texte-long">{{ $f['client'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['ht']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['tva']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['airsi']) }}</td>
                                <td class="text-end text-nowrap"><strong>{{ $francs($f['ttc']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Aucune facture taxée sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($detail))
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totauxDetail['ht']) }}</th>
                                <th class="text-end">{{ $francs($totauxDetail['tva']) }}</th>
                                <th class="text-end">{{ $francs($totauxDetail['airsi']) }}</th>
                                <th class="text-end">{{ $francs($totauxDetail['ttc']) }}</th>
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
            var $d = $('#tableDetailTaxes');
            if ($d.find('tbody tr').length && !$d.find('tbody tr td[colspan]').length) {
                $d.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
