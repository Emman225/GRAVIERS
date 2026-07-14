@extends('layout.main')
@section('title','Mes demandes de paiement')

@php
    use Illuminate\Support\Carbon;
    $solde = (float) ($livreur->solde ?? 0);
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Mes demandes de paiement</h2>
                <p class="dash-welcome-subtitle">Historique de vos demandes de retrait sur votre solde.</p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex gap-2">
                <a href="{{ route('show.demandeDepaiePage') }}" class="btn btn-primary btn-sm">
                    <i class="material-icons md-add"></i> Nouvelle demande
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-account_balance_wallet"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Solde disponible</div>
                    <div class="kpi-card-value">{{ number_format($solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">disponible au retrait</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-pending_actions"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En attente</div>
                    <div class="kpi-card-value">{{ $totalEnAttente }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">{{ number_format($montantEnAttente, 0, ',', ' ') }} FCFA</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-paid"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Payées</div>
                    <div class="kpi-card-value">{{ $totalPayees }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">{{ number_format($montantPaye, 0, ',', ' ') }} FCFA reçus</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-receipt_long"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total demandes</div>
                    <div class="kpi-card-value">{{ $totalDemandes }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">@if($totalRefusees) {{ $totalRefusees }} refusée{{ $totalRefusees > 1 ? 's' : '' }} @else historique cumulé @endif</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLE ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-list_alt text-primary"></i>
                Historique des demandes
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="demandesTable">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th class="text-end">Montant</th>
                            <th class="text-center">Statut</th>
                            <th>Mode de paiement</th>
                            <th>Payée le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($demandes as $demande)
                            <tr>
                                <td><strong class="text-primary">{{ $demande->numero }}</strong></td>
                                <td>{{ Carbon::parse($demande->created_at)->format('d/m/Y') }}</td>
                                <td class="text-end fw-bold">{{ number_format((float) $demande->montant, 0, ',', ' ') }} FCFA</td>
                                <td class="text-center">
                                    @switch((int) $demande->paye)
                                        @case(0) <span class="badge bg-warning text-dark">En attente</span> @break
                                        @case(1) <span class="badge bg-success">Payé</span> @break
                                        @case(2) <span class="badge bg-danger">Refusé</span> @break
                                        @default <span class="badge bg-light text-dark">—</span>
                                    @endswitch
                                </td>
                                <td>{{ (int) $demande->paye === 1 ? ($demande->modePaiement?->libelle ?? '—') : '—' }}</td>
                                <td>
                                    @if((int) $demande->paye === 1)
                                        <small>{{ Carbon::parse($demande->updated_at)->format('d/m/Y') }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="material-icons md-inbox" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucune demande de paiement.</p>
                                    <a href="{{ route('show.demandeDepaiePage') }}" class="btn btn-sm btn-primary mt-2">
                                        <i class="material-icons md-add"></i> Faire une demande
                                    </a>
                                </td>
                            </tr>
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
    <script type="text/javascript">
        $(function() {
            if ($('#demandesTable tbody tr').length > 1 || $('#demandesTable tbody tr td').length > 1) {
                $('#demandesTable').DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[1, 'desc']],
                });
            }
        });
    </script>
@endsection
