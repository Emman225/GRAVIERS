@php
    use Carbon\Carbon;
    $userName = Auth::user()->nom_prenoms ?? 'Utilisateur';
    $firstName = explode(' ', $userName)[0];
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
@endphp

@extends('layout.main')
@section('title', 'Récap Dettes - Tableau de bord')

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- HEADER WELCOME (rouge pour signaler les dettes) --}}
    <div class="dash-welcome dash-welcome-danger mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    🔴 Récapitulatif Dettes
                </h2>
                <p class="dash-welcome-subtitle">
                    {{ $greeting }} {{ $firstName }} — Vue consolidée des sommes à payer ({{ Carbon::now()->locale('fr')->isoFormat('D MMMM YYYY') }})
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex" style="gap:10px;">
                <button type="button" class="btn btn-light dash-guide-btn" data-bs-toggle="modal" data-bs-target="#modalGuideRecapDettes" title="Guide d'utilisation">
                    <i class="material-icons md-menu_book"></i>
                    <span>Guide d'utilisation</span>
                </button>
                <div class="dash-time-pill">
                    <i class="material-icons md-payments"></i>
                    <span>{{ number_format($totalReste, 0, ',', ' ') }} FCFA dus</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- KPI CARDS --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-account_balance"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total engagé</div>
                    <div class="kpi-card-value">{{ number_format($totalEngage, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ $totalNbOps }} opérations</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-check_circle"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">✅ Total payé</div>
                    <div class="kpi-card-value">{{ number_format($totalPaye, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">{{ number_format($ratioPayeEngage, 1, ',', ' ') }}% du total</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card">
                <div class="kpi-card-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                    <i class="material-icons md-warning"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">⚠️ Reste à payer</div>
                    <div class="kpi-card-value" style="color: #ef4444;">{{ number_format($totalReste, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-down">{{ number_format($ratioResteEngage, 1, ',', ' ') }}% à régler</span>
                    </div>
                </div>
                <div class="kpi-card-shape" style="background: #ef4444;"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-event_busy"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">🔥 Dettes immédiates</div>
                    <div class="kpi-card-value">{{ number_format($detteImmediate, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">Échus + validés à payer</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- INDICATEURS CLÉS --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-body text-center">
                    <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="material-icons md-savings"></i>
                    </div>
                    <div class="kpi-card-label">Trésorerie nécessaire à 30 j</div>
                    <div class="kpi-card-value mt-2" style="color: #f59e0b;">{{ Help::formatNombre($tresorerie30j, true) }}</div>
                    <small class="text-muted">Estimation des échéances dans les 30 prochains jours</small>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-body text-center">
                    <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #10b981, #047857);">
                        <i class="material-icons md-trending_up"></i>
                    </div>
                    <div class="kpi-card-label">Ratio Payé / Engagé</div>
                    <div class="kpi-card-value mt-2" style="color: #10b981;">{{ number_format($ratioPayeEngage, 1, ',', ' ') }} %</div>
                    <small class="text-muted">{{ number_format($ratioResteEngage, 1, ',', ' ') }}% restant à régler</small>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-body text-center">
                    <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                        <i class="material-icons md-calculate"></i>
                    </div>
                    <div class="kpi-card-label">Dette moyenne par opération</div>
                    <div class="kpi-card-value mt-2" style="color: #3b82f6;">{{ Help::formatNombre($detteMoyenne, true) }}</div>
                    <small class="text-muted">Sur l'ensemble des opérations en cours</small>
                </div>
            </div>
        </div>
    </div>

    {{-- SYNTHÈSE PAR CATÉGORIE --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title">
                <i class="material-icons md-pie_chart text-primary"></i>
                Synthèse par catégorie de dette
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Catégorie</th>
                            <th class="text-end">Nb opérations</th>
                            <th class="text-end">Total Engagé</th>
                            <th class="text-end">Total Payé</th>
                            <th class="text-end">Reste à Payer</th>
                            <th class="text-end">% Total Dettes</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kpi-card-icon" style="width: 36px; height: 36px; background: linear-gradient(135deg, #1c57a3, #134380); border-radius: 10px;">
                                        <i class="material-icons md-store" style="font-size: 18px;"></i>
                                    </div>
                                    <div>
                                        <strong>Fournisseurs</strong>
                                        <div class="small text-muted">Carrières / Usines</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">{{ $foNbOps }}</td>
                            <td class="text-end">{{ Help::formatNombre($foEngage, true) }}</td>
                            <td class="text-end text-success">{{ Help::formatNombre($foPaye, true) }}</td>
                            <td class="text-end fw-bold text-danger">{{ Help::formatNombre($foReste, true) }}</td>
                            <td class="text-end">
                                <span class="kpi-pill" style="background: #eef2ff; color: #1c57a3;">{{ number_format($foPct, 1, ',', ' ') }}%</span>
                            </td>
                            <td class="text-center"><span class="badge bg-warning text-dark">À surveiller</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kpi-card-icon" style="width: 36px; height: 36px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 10px;">
                                        <i class="material-icons md-local_shipping" style="font-size: 18px;"></i>
                                    </div>
                                    <div>
                                        <strong>Livreurs</strong>
                                        <div class="small text-muted">Frais livraison</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">{{ $lvNbOps }}</td>
                            <td class="text-end">{{ Help::formatNombre($lvEngage, true) }}</td>
                            <td class="text-end text-success">{{ Help::formatNombre($lvPaye, true) }}</td>
                            <td class="text-end fw-bold text-danger">{{ Help::formatNombre($lvReste, true) }}</td>
                            <td class="text-end">
                                <span class="kpi-pill" style="background: #eef2ff; color: #1c57a3;">{{ number_format($lvPct, 1, ',', ' ') }}%</span>
                            </td>
                            <td class="text-center"><span class="badge bg-info">Hebdomadaire</span></td>
                        </tr>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="kpi-card-icon" style="width: 36px; height: 36px; background: linear-gradient(135deg, #10b981, #047857); border-radius: 10px;">
                                        <i class="material-icons md-handshake" style="font-size: 18px;"></i>
                                    </div>
                                    <div>
                                        <strong>Apporteurs d'affaires</strong>
                                        <div class="small text-muted">Commissions</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end">{{ $apNbOps }}</td>
                            <td class="text-end">{{ Help::formatNombre($apEngage, true) }}</td>
                            <td class="text-end text-success">{{ Help::formatNombre($apPaye, true) }}</td>
                            <td class="text-end fw-bold text-danger">{{ Help::formatNombre($apReste, true) }}</td>
                            <td class="text-end">
                                <span class="kpi-pill" style="background: #eef2ff; color: #1c57a3;">{{ number_format($apPct, 1, ',', ' ') }}%</span>
                            </td>
                            <td class="text-center"><span class="badge bg-secondary">Sur encaissement</span></td>
                        </tr>
                    </tbody>
                    <tfoot style="background: linear-gradient(180deg, #fee2e2 0%, #fecaca 100%);">
                        <tr>
                            <td><strong>🔴 TOTAL DETTES</strong></td>
                            <td class="text-end fw-bold">{{ $totalNbOps }}</td>
                            <td class="text-end fw-bold">{{ Help::formatNombre($totalEngage, true) }}</td>
                            <td class="text-end fw-bold text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                            <td class="text-end fw-bold" style="color: #991b1b; font-size: 1.1rem;">{{ Help::formatNombre($totalReste, true) }}</td>
                            <td class="text-end fw-bold">100,0%</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- ACCÈS RAPIDES vers les détails --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="{{ route('show.recapDettes.detailFournisseurs') }}" class="text-decoration-none">
                <div class="card dash-card h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #1c57a3, #134380);">
                            <i class="material-icons md-store"></i>
                        </div>
                        <h6 class="mb-1" style="color: #111827;">Détail Fournisseurs</h6>
                        <small class="text-muted">{{ $foNbOps }} opérations</small>
                        <div class="mt-2"><span class="badge bg-light text-primary">{{ Help::formatNombre($foReste, true) }}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('show.recapDettes.detailLivreurs') }}" class="text-decoration-none">
                <div class="card dash-card h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                            <i class="material-icons md-local_shipping"></i>
                        </div>
                        <h6 class="mb-1" style="color: #111827;">Détail Livreurs</h6>
                        <small class="text-muted">{{ $lvNbOps }} livraisons</small>
                        <div class="mt-2"><span class="badge bg-light text-primary">{{ Help::formatNombre($lvReste, true) }}</span></div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('show.recapDettes.detailApporteurs') }}" class="text-decoration-none">
                <div class="card dash-card h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="kpi-card-icon mx-auto mb-3" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <i class="material-icons md-handshake"></i>
                        </div>
                        <h6 class="mb-1" style="color: #111827;">Détail Apporteurs</h6>
                        <small class="text-muted">{{ $apNbOps }} commissions</small>
                        <div class="mt-2"><span class="badge bg-light text-primary">{{ Help::formatNombre($apReste, true) }}</span></div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- BONNES PRATIQUES --}}
    <div class="card dash-card mb-4" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: none;">
        <div class="card-body">
            <h6 class="mb-3" style="color: #92400e;">📖 Bonnes pratiques</h6>
            <div class="row g-2">
                <div class="col-md-6"><span style="color: #92400e;">✓</span> Mise à jour <strong>hebdomadaire</strong> (chaque vendredi)</div>
                <div class="col-md-6"><span style="color: #92400e;">✓</span> Vérifier les <strong>dettes échues impayées</strong> en priorité</div>
                <div class="col-md-6"><span style="color: #92400e;">✓</span> Anticiper la <strong>trésorerie 30 jours</strong></div>
                <div class="col-md-6"><span style="color: #92400e;">✓</span> Conserver une <strong>archive mensuelle</strong></div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODALE — Guide d'utilisation du récap Dettes
         ============================================================ --}}
    <div class="modal fade" id="modalGuideRecapDettes" tabindex="-1" aria-labelledby="modalGuideRecapDettesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #991b1b 0%, #4c0519 100%); color: #fff; border-bottom: 0;">
                    <h5 class="modal-title" id="modalGuideRecapDettesLabel">
                        <i class="material-icons md-menu_book align-middle"></i>
                        Guide d'utilisation — Récap Dettes
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    @include('admin.recapDettes._guide')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
<style>
    .dash-welcome-danger {
        background: linear-gradient(135deg, #991b1b 0%, #7f1d1d 60%, #4c0519 100%) !important;
        box-shadow: 0 10px 30px rgba(153, 27, 27, 0.25) !important;
    }
    .hover-lift { transition: all 0.25s ease; cursor: pointer; }
    .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,0.1) !important; }
</style>
@include('admin.shared._recap-guide-styles')
@endsection
