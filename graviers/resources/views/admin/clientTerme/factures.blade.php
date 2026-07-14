@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Factures - Clients à terme')

@section('contenu')
    <div class="content-header d-flex justify-content-between align-items-center">
        <h2 class="content-title">{{ ($aEncaisser ?? false) ? 'Factures à encaisser - Clients à terme' : 'Suivi des factures & créances - Clients à terme' }}</h2>
        <div>
            @if($aEncaisser ?? false)
                <a href="{{ route('show.creancesTerme.factures') }}" class="btn btn-outline-secondary btn-sm">Toutes les factures</a>
            @else
                <a href="{{ route('show.creancesTerme.factures', ['a_encaisser' => 1]) }}" class="btn btn-primary btn-sm">Factures à encaisser uniquement</a>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalHt    = $lignes->sum('montant_ht');
                        $totalTva   = $lignes->sum('tva');
                        $totalTtc   = $lignes->sum('montant_ttc');
                        $totalLiv   = $lignes->sum('frais_livraison');
                        $totalAPay  = $lignes->sum('total_a_payer');
                        $totalPaye  = $lignes->sum('montant_paye');
                        $totalReste = $lignes->sum('reste_a_payer');
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre de factures</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total à payer</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalAPay, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total encaissé</span><br>
                            <strong class="h5 text-success">{{ Help::formatNombre($totalPaye, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Reste à encaisser</span><br>
                            <strong class="h5 text-danger">{{ Help::formatNombre($totalReste, true) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="factures-clients-a-terme" title="Factures clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Date Facture</th>
                            <th class="text-center">Code Client</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Produit principal</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">PU HT</th>
                            <th class="text-end">Montant HT</th>
                            <th class="text-end">TVA {{ number_format($tauxTva, 0) }}%</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Frais livraison</th>
                            <th class="text-end">Total à payer</th>
                            <th class="text-center">Date Échéance</th>
                            <th class="text-center">Délai (j)</th>
                            <th class="text-end">Montant Payé</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Jours retard</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $statut = $l->statut_creance;
                                $badge  = \App\Models\StatutMetier::badgeFor($statut, 'creance_terme');
                                $alerteRetard = $l->jours_retard >= $seuilAlerte;
                            @endphp
                            <tr class="{{ $alerteRetard ? 'table-danger' : '' }}">
                                <td class="text-center">{{ $l->facture->numero }}</td>
                                <td class="text-center">{{ $l->date_facture ? Carbon::parse($l->date_facture)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->code_client }}</td>
                                <td>{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td>{{ $l->produit_principal }}</td>
                                <td class="text-end">{{ $l->quantite ? rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') : '-' }}</td>
                                <td class="text-end">{{ $l->pu_ht ? Help::formatNombre($l->pu_ht, true) : '-' }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ht, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->tva, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ttc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->frais_livraison, true) }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->total_a_payer, true) }}</strong></td>
                                <td class="text-center">{{ $l->date_echeance ? Carbon::parse($l->date_echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->delai_jours ?? '-' }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->montant_paye, true) }}</td>
                                <td class="text-end text-danger">
                                    <strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong>
                                </td>
                                <td class="text-center">
                                    @if ($l->jours_retard > 0)
                                        <span class="badge bg-danger">{{ $l->jours_retard }} j</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $badge }}">{{ $statut }}</span>
                                </td>
                                <td>{{ $l->observations ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="20" class="text-center text-muted">
                                    Aucune facture pour les clients à terme.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="8" class="text-end">TOTAUX</td>
                                <td class="text-end">{{ Help::formatNombre($totalHt, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalTva, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalTtc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalLiv, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalAPay, true) }}</td>
                                <td colspan="2"></td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totalReste, true) }}</td>
                                <td colspan="3"></td>
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
    <script type="text/javascript">
        $(function () {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[1, 'desc']],
                });
            }
        });
    </script>
@endsection
