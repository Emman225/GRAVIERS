@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Commandes comptant - Clients ordinaires')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Suivi des commandes comptant - Paiement en agence</h2>
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
                            <span class="text-muted small">Nombre de commandes</span><br>
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
            <x-export-buttons table-id="liste" filename="commandes-comptant" title="Commandes comptant" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Date Commande</th>
                            <th class="text-center">Nom Client</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Agence de retrait</th>
                            <th class="text-center">Produit principal</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">PU HT</th>
                            <th class="text-end">Montant HT</th>
                            <th class="text-end">TVA {{ number_format($tauxTva, 0) }}%</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-end">Frais livraison</th>
                            <th class="text-end">Total à payer</th>
                            <th class="text-center">Date limite paiement</th>
                            <th class="text-end">Montant Payé</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $statut = $l->statut;
                                $badge  = \App\Models\StatutMetier::badgeFor($statut, 'comptant');
                                $alerte = ($statut === 'En retard');
                            @endphp
                            <tr class="{{ $alerte ? 'table-danger' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero_commande }}</strong></td>
                                <td class="text-center">{{ $l->date_commande ? Carbon::parse($l->date_commande)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->nom_client }}</td>
                                <td class="text-center">{{ $l->telephone ?? '-' }}</td>
                                <td class="text-center">{{ $l->email ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($l->agence_code !== '-')
                                        <span class="badge bg-light text-dark" title="{{ $l->agence_nom }}">{{ $l->agence_code }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $l->produit_principal }}</td>
                                <td class="text-end">{{ $l->quantite ? rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') : '-' }}</td>
                                <td class="text-end">{{ $l->pu_ht ? Help::formatNombre($l->pu_ht, true) : '-' }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ht, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->tva, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ttc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->frais_livraison, true) }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->total_a_payer, true) }}</strong></td>
                                <td class="text-center">{{ $l->date_limite_paiement ? Carbon::parse($l->date_limite_paiement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->montant_paye, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $statut }}</span></td>
                                <td>{{ $l->observations ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="19" class="text-center text-muted">
                                    Aucune commande comptant.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="9" class="text-end">TOTAUX</td>
                                <td class="text-end">{{ Help::formatNombre($totalHt, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalTva, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalTtc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalLiv, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalAPay, true) }}</td>
                                <td></td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totalReste, true) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <strong>Paramètres :</strong> Délai max paiement agence : {{ $delaiAgence }} j • Délai annulation auto : {{ $delaiAnnul }} j
            </p>
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
