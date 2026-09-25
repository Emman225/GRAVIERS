@extends('layout.main')
@section('title', 'Trésorerie par mode de règlement')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['entrees', 'sorties', 'solde']);
    $totauxDetail = RapportsComptables::totaux($detail, ['entree', 'sortie']);
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

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-list text-primary"></i> Le détail des opérations</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Une ligne par mouvement, avec le client qui a payé ou le partenaire qui a été payé.
                Les totaux retombent sur ceux du récapitulatif ci-dessus.
            </p>
            <x-export-buttons table-id="tableDetailTresorerie" filename="detail-de-tresorerie" title="Trésorerie — le détail des opérations" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableDetailTresorerie">
                    <thead>
                        <tr>
                            <th>Date</th><th>Journal</th><th>Bénéficiaire ou client</th><th>Nature</th><th>Pièce</th>
                            <th class="text-end">Entrée</th><th class="text-end">Sortie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($detail as $o)
                            <tr>
                                <td data-order="{{ $o['date']->format('Ymd') }}" class="text-nowrap">{{ $o['date']->format('d/m/Y') }}</td>
                                <td class="text-nowrap">{{ $o['journal'] }} — {{ $o['nom_journal'] }}</td>
                                <td class="td-texte-long">{{ $o['beneficiaire'] }}</td>
                                <td class="text-nowrap">{{ $o['nature'] }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('show.comptabilite.ecritures.detail', $o['ecriture_id']) }}">{{ $o['piece'] }}</a>
                                </td>
                                <td class="text-end text-nowrap">{{ $o['entree'] > 0 ? $francs($o['entree']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $o['sortie'] > 0 ? $francs($o['sortie']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Aucune opération de trésorerie sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($detail))
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totauxDetail['entree']) }}</th>
                                <th class="text-end">{{ $francs($totauxDetail['sortie']) }}</th>
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
            var $d = $('#tableDetailTresorerie');
            if ($d.find('tbody tr').length && !$d.find('tbody tr td[colspan]').length) {
                $d.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
