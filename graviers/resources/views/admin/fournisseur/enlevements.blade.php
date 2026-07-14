@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Enlèvements - Achats fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Suivi des enlèvements / achats fournisseurs</h2>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalHt   = $lignes->sum('montant_ht');
                        $totalTva  = $lignes->sum('tva');
                        $totalTtc  = $lignes->sum('montant_ttc');
                        $totalPaye = $lignes->sum('montant_paye');
                        $totalReste= $lignes->sum('reste_a_payer');
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre d'enlèvements</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total achats TTC</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalTtc, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total payé</span><br>
                            <strong class="h5 text-success">{{ Help::formatNombre($totalPaye, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Reste à payer</span><br>
                            <strong class="h5 text-danger">{{ Help::formatNombre($totalReste, true) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="enlevements-fournisseurs" title="Enlèvements fournisseurs" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Bon Enlèvement</th>
                            <th class="text-center">Date Enlèvement</th>
                            <th class="text-center">Code Fournisseur</th>
                            <th class="text-center">Fournisseur</th>
                            <th class="text-center">N° Cmd Client</th>
                            <th class="text-center">Client final</th>
                            <th class="text-center">Produit</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">Prix unitaire achat</th>
                            <th class="text-end">Montant HT</th>
                            <th class="text-end">TVA {{ number_format($tauxTva, 0) }}%</th>
                            <th class="text-end">Montant TTC</th>
                            <th class="text-center">Date Échéance</th>
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
                                $statut = $l->statut_dette;
                                $badge  = \App\Models\StatutMetier::badgeFor($statut, 'dette_fournisseur');
                                $alerte = $l->jours_retard > 0;
                            @endphp
                            <tr class="{{ $alerte ? 'table-danger' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero_be }}</strong></td>
                                <td class="text-center">{{ $l->date_enlevement ? Carbon::parse($l->date_enlevement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->code_fournisseur }}</td>
                                <td>{{ $l->fournisseur_nom }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td>{{ $l->client_final }}</td>
                                <td>{{ $l->produit }}</td>
                                <td class="text-end">{{ $l->quantite ? rtrim(rtrim(number_format($l->quantite, 2, ',', ' '), '0'), ',') : '-' }}</td>
                                <td class="text-end">{{ $l->pu_achat ? Help::formatNombre($l->pu_achat, true) : '-' }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_ht, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->tva, true) }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->montant_ttc, true) }}</strong></td>
                                <td class="text-center">{{ $l->date_echeance ? Carbon::parse($l->date_echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->montant_paye, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center">
                                    @if ($l->jours_retard > 0)
                                        <span class="badge bg-danger">{{ $l->jours_retard }} j</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $statut }}</span></td>
                                <td>{{ $l->observations ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted">
                                    Aucun enlèvement enregistré.
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
                                <td></td>
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
