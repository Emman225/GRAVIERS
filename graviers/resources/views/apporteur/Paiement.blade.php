@extends('layout.main')
@section('title','Mes paiements')

@php
    use Illuminate\Support\Carbon;
    $solde = (float) ($apporteur->solde ?? 0);
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Mes paiements
                </h2>
                <p class="dash-welcome-subtitle">
                    Tout ce que vous avez reçu : vos demandes de paiement, et les règlements que l'entreprise a effectués sur vos commissions de sa propre initiative
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex gap-2">
                <a href="{{ route('show.demandeDepaiePage') }}" class="btn btn-primary btn-sm">
                    <i class="material-icons md-add"></i> Nouvelle demande
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-account_balance_wallet"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Solde disponible</div>
                    <div class="kpi-card-value">{{ number_format($solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">disponible au retrait</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-pending_actions"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En attente</div>
                    <div class="kpi-card-value">{{ $totalEnAttente }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ number_format($montantEnAttente, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-paid"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Reçus</div>
                    <div class="kpi-card-value">{{ $totalPayees }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ number_format($montantPaye, 0, ',', ' ') }} FCFA reçus</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-receipt_long"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total paiements</div>
                    <div class="kpi-card-value">{{ $totalDemandes }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">@if($totalRefusees) {{ $totalRefusees }} refusée{{ $totalRefusees > 1 ? 's' : '' }} @else historique cumulé @endif</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== LISTE DES PAIEMENTS ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-receipt_long text-primary"></i>
                Historique de mes paiements
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="paiementsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Référence</th>
                            <th>Date</th>
                            <th>Origine</th>
                            <th class="text-end">Montant</th>
                            <th class="text-center">Statut</th>
                            <th>Mode de paiement</th>
                            <th>Date du paiement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mouvements as $m)
                            <tr>
                                <td>
                                    <strong class="text-primary">{{ $m->reference }}</strong>
                                    <br><small class="text-muted">{{ $m->detail }}</small>
                                </td>
                                <td>{{ $m->date ? Carbon::parse($m->date)->format('d/m/Y') : '—' }}</td>
                                <td>
                                    {{-- Qui a lancé le versement : vous, ou l'entreprise de sa
                                         propre initiative. Les seconds n'étaient visibles nulle
                                         part sur cet écran. --}}
                                    @if ($m->origine === 'Vous')
                                        <span class="badge bg-info">Votre demande</span>
                                    @else
                                        <span class="badge bg-secondary">L'entreprise</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">{{ number_format($m->montant, 0, ',', ' ') }} FCFA</td>
                                <td class="text-center">
                                    @switch($m->statut)
                                        @case(1)
                                            <span class="badge bg-success">Payé</span>
                                            @break
                                        @case(2)
                                            <span class="badge bg-danger">Refusé</span>
                                            @break
                                        @default
                                            <span class="badge bg-warning text-dark">En attente</span>
                                    @endswitch
                                </td>
                                <td>{{ $m->statut === 1 ? ($m->mode ?? '—') : '—' }}</td>
                                <td>
                                    @if ($m->date_paiement)
                                        {{ Carbon::parse($m->date_paiement)->format('d/m/Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Aucun paiement pour le moment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('jsParts')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
<script type="text/javascript">
    $(function () {
        if ($('#paiementsTable tbody tr').length > 1 || $('#paiementsTable tbody tr td').length > 1) {
            $('#paiementsTable').DataTable({
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
                order: [[1, 'desc']],
                language: {
                    search: 'Rechercher :',
                    lengthMenu: 'Afficher _MENU_ entrées',
                    info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
                    infoEmpty: 'Aucune entrée',
                    infoFiltered: '(filtré sur _MAX_ entrées)',
                    paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
                    emptyTable: 'Aucune donnée disponible',
                    zeroRecords: 'Aucun résultat correspondant'
                }
            });
        }
    });
</script>
@endsection
