@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Commissions - Dettes apporteurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Suivi des commissions - Dettes apporteurs d'affaires</h2>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalCmdTtc  = $lignes->sum('montant_cmd_ttc');
                        $totalEnc     = $lignes->sum('montant_encaisse');
                        $totalCalc    = $lignes->sum('commission_calc');
                        $totalPaye    = $lignes->sum('commission_payee');
                        $totalReste   = $lignes->sum('reste_a_payer');
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre de commissions</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total commissions calculées</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalCalc, true) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total déjà payé</span><br>
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
            <x-export-buttons table-id="liste" filename="commissions-apporteurs" title="Commissions apporteurs" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Commission</th>
                            <th class="text-center">Date Commande</th>
                            <th class="text-center">Code Apporteur</th>
                            <th class="text-center">Nom Apporteur</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Client final</th>
                            <th class="text-end">Montant Cmd TTC</th>
                            <th class="text-end">Montant Encaissé</th>
                            <th class="text-end">Taux Commission</th>
                            <th class="text-end">Commission Calculée</th>
                            <th class="text-end">Commission Payée</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Date Échéance</th>
                            <th class="text-center">Date paiement effectif</th>
                            <th class="text-center">Mode paiement</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $statut = $l->statut;
                                $badge  = \App\Models\StatutMetier::badgeFor($statut, 'commission_apporteur');
                            @endphp
                            <tr class="{{ $statut === 'Annulée' ? 'table-secondary text-muted' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero_com }}</strong></td>
                                <td class="text-center">{{ $l->date_commande ? Carbon::parse($l->date_commande)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->code_apporteur }}</td>
                                <td>{{ $l->nom_apporteur }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $l->type_commande === 'Terme' ? 'bg-info' : 'bg-success' }}">
                                        {{ $l->type_commande }}
                                    </span>
                                </td>
                                <td>{{ $l->client_final }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_cmd_ttc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($l->montant_encaisse, true) }}</td>
                                <td class="text-end">{{ number_format($l->taux_commission, 1, ',', ' ') }} %</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->commission_calc, true) }}</strong></td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->commission_payee, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center">{{ $l->date_echeance ? Carbon::parse($l->date_echeance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->mode_paiement }}</td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $statut }}</span></td>
                                <td>{{ $l->observations ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted">
                                    Aucune commission enregistrée.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="7" class="text-end">TOTAUX</td>
                                <td class="text-end">{{ Help::formatNombre($totalCmdTtc, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalEnc, true) }}</td>
                                <td></td>
                                <td class="text-end">{{ Help::formatNombre($totalCalc, true) }}</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td class="text-end text-danger">{{ Help::formatNombre($totalReste, true) }}</td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <p class="text-muted small mt-2 mb-0">
                <strong>Paramètres :</strong>
                Taux commission standard : {{ number_format($tauxStandard, 1, ',', ' ') }}% •
                Délai paiement commission : {{ $delaiCom }} j •
                <em>Règle : la commission n'est due QUE si le client a effectivement payé la commande.</em>
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
