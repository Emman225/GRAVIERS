@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Livraisons - Dettes livreurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Suivi des livraisons &amp; dettes livreurs</h2>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalForfait = $lignes->sum('forfait_base');
                        $totalKm      = $lignes->sum('frais_km');
                        $totalDu      = $lignes->sum('total_du');
                        // Total payé = demandes de paiement VALIDÉES (circuit mobile, source
                        // de vérité). L'ancienne somme par livraison (PaiementLivreur) est un
                        // circuit neutralisé : elle affichait toujours 0.
                        $totalPaye    = (float) ($totalPayeDemandes ?? $lignes->sum('montant_paye'));
                        $totalReste   = max(0, $totalDu - $totalPaye);
                    @endphp
                    <div class="row text-center">
                        <div class="col-md-3">
                            <span class="text-muted small">Nombre de livraisons</span><br>
                            <strong class="h5">{{ $lignes->count() }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted small">Total dû livreurs</span><br>
                            <strong class="h5 text-primary">{{ Help::formatNombre($totalDu, true) }}</strong>
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
            <x-export-buttons table-id="liste" filename="livraisons-livreurs" title="Livraisons livreurs" />
            <div class="table-responsive">
                <table class="table table-striped table-sm" id="liste" style="font-size: 0.85rem;">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">N° Livraison</th>
                            <th class="text-center">Date Livraison</th>
                            <th class="text-center">Code Livreur</th>
                            <th class="text-center">Nom Livreur</th>
                            <th class="text-center">N° Cmd liée</th>
                            <th class="text-center">Client final</th>
                            <th class="text-center">Type cmd (T/C)</th>
                            <th class="text-center">Adresse livraison</th>
                            <th class="text-end">Distance (km)</th>
                            <th class="text-center">Quantité livrée</th>
                            <th class="text-end">Forfait base</th>
                            <th class="text-end">Frais km</th>
                            <th class="text-end">Total dû livreur</th>
                            <th class="text-end">Montant Payé</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Date paiement</th>
                            <th class="text-center">Observations</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $statut = $l->statut;
                                $badge  = \App\Models\StatutMetier::badgeFor($statut, 'dette_livreur');
                                $alerte = $statut === 'En contestation';
                            @endphp
                            <tr class="{{ $alerte ? 'table-warning' : '' }}">
                                <td class="text-center"><strong>{{ $l->numero_liv }}</strong></td>
                                <td class="text-center">{{ $l->date_livraison ? Carbon::parse($l->date_livraison)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->code_livreur }}</td>
                                <td>{{ $l->nom_livreur }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td>{{ $l->client_final }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $l->type_commande === 'Terme' ? 'bg-info' : 'bg-success' }}">
                                        {{ $l->type_commande }}
                                    </span>
                                </td>
                                <td>{{ $l->adresse }}</td>
                                <td class="text-end">{{ $l->distance_km ? rtrim(rtrim(number_format($l->distance_km, 2, ',', ' '), '0'), ',') : '-' }}</td>
                                <td>{{ $l->quantite_livree }}</td>
                                <td class="text-end">{{ $l->forfait_base ? Help::formatNombre($l->forfait_base, true) : '-' }}</td>
                                <td class="text-end">{{ $l->frais_km ? Help::formatNombre($l->frais_km, true) : '-' }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->total_du, true) }}</strong></td>
                                <td class="text-end text-success">{{ Help::formatNombre($l->montant_paye, true) }}</td>
                                <td class="text-end text-danger"><strong>{{ Help::formatNombre($l->reste_a_payer, true) }}</strong></td>
                                <td class="text-center"><span class="badge {{ $badge }}">{{ $statut }}</span></td>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $l->observations ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted">
                                    Aucune livraison enregistrée.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="10" class="text-end">TOTAUX</td>
                                <td class="text-end">{{ Help::formatNombre($totalForfait, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalKm, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($totalDu, true) }}</td>
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
