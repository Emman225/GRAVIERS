@extends('layout.main')
@section('title', 'État par grande famille')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    $totauxCycle = \App\Services\Comptabilite\RapportsComptables::totaux($cycle, [
        'lignes', 'commande', 'livre', 'commande_non_livre', 'bons',
        'facture', 'livre_non_facture', 'factures', 'deverse', 'facture_non_deverse',
    ]);
@endphp

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

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-local_shipping text-primary"></i>
                Le cycle : commandé → livré → facturé → déversé</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <strong>Ce tableau n'est pas comptable.</strong> Une écriture ne naît que d'une facture certifiée :
                « commandé non livré » et « livré non facturé » se lisent dans les commandes et les bons
                d'enlèvement, pas dans les écritures. Tout est en HT, ligne de commande par ligne de commande ;
                la période se lit sur la <em>date de commande</em> ; les commandes annulées sont hors du compte ;
                « livré » suit la règle du site — ce qui est <em>servi</em> sur le bon, pas ce qui était demandé.
            </div>
            <x-export-buttons table-id="tableCycle" filename="cycle-par-grande-famille" title="Cycle commandé, livré, facturé, déversé" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableCycle">
                    <thead>
                        <tr>
                            <th>Grande famille</th>
                            <th class="text-end">Lignes</th>
                            <th class="text-end">Commandé</th>
                            <th class="text-end">Livré</th>
                            <th class="text-end">Commandé non livré</th>
                            <th class="text-end">Bons</th>
                            <th class="text-end">Facturé</th>
                            <th class="text-end">Livré non facturé</th>
                            <th class="text-end">Factures</th>
                            <th class="text-end">Déversé</th>
                            <th class="text-end">Facturé non déversé</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cycle as $c)
                            <tr>
                                <td><strong>{{ $c['famille'] }}</strong></td>
                                <td class="text-end text-nowrap">{{ $c['lignes'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['commande']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['livre']) }}</td>
                                <td class="text-end text-nowrap {{ $c['commande_non_livre'] > 0 ? 'text-warning fw-bold' : 'text-muted' }}">{{ $francs($c['commande_non_livre']) }}</td>
                                <td class="text-end text-nowrap">{{ $c['bons'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['facture']) }}</td>
                                <td class="text-end text-nowrap {{ $c['livre_non_facture'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $francs($c['livre_non_facture']) }}</td>
                                <td class="text-end text-nowrap">{{ $c['factures'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['deverse']) }}</td>
                                <td class="text-end text-nowrap {{ $c['facture_non_deverse'] > 0 ? 'fw-bold' : 'text-muted' }}">{{ $francs($c['facture_non_deverse']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">Aucune commande sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($cycle))
                        <tfoot>
                            <tr>
                                <th class="text-end">Totaux</th>
                                <th class="text-end">{{ $totauxCycle['lignes'] }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['commande']) }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['livre']) }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['commande_non_livre']) }}</th>
                                <th class="text-end">{{ $totauxCycle['bons'] }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['facture']) }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['livre_non_facture']) }}</th>
                                <th class="text-end">{{ $totauxCycle['factures'] }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['deverse']) }}</th>
                                <th class="text-end">{{ $francs($totauxCycle['facture_non_deverse']) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <small class="text-muted d-block mt-2">
                « Livré non facturé » est le manque à facturer : de la marchandise est sortie du dépôt sans
                qu'une facture ne la porte encore.
            </small>
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
