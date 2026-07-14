@php
    use Carbon\Carbon;
    $userName = Auth::user()->nom_prenoms ?? 'Utilisateur';
    $firstName = explode(' ', $userName)[0];
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
    $totalEngage = $totalCreances + ($encaisseCeMois ?? 0);
    $tauxRecouvrement = $totalEngage > 0 ? (($encaisseCeMois ?? 0) / $totalEngage) * 100 : 0;
@endphp

@extends('layout.main')
@section('title', 'Récap Créances - Tableau de bord')

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- HEADER WELCOME --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    💰 Récapitulatif Créances Clients
                </h2>
                <p class="dash-welcome-subtitle">
                    {{ $greeting }} {{ $firstName }} — Vue consolidée des sommes à encaisser ({{ Carbon::now()->locale('fr')->isoFormat('D MMMM YYYY') }})
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex" style="gap:10px;">
                <button type="button" class="btn btn-light dash-guide-btn" data-bs-toggle="modal" data-bs-target="#modalGuideRecapCreances" title="Guide d'utilisation">
                    <i class="material-icons md-menu_book"></i>
                    <span>Guide d'utilisation</span>
                </button>
                <div class="dash-time-pill">
                    <i class="material-icons md-payments"></i>
                    <span>{{ number_format($totalCreances, 0, ',', ' ') }} FCFA</span>
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
                    <i class="material-icons md-account_balance_wallet"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Créances totales</div>
                    <div class="kpi-card-value">{{ number_format($totalCreances, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ $totalNbDocs }} documents · {{ $totalNbClients }} clients</span>
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
                    <div class="kpi-card-label">⚠️ Échu impayé</div>
                    <div class="kpi-card-value">{{ number_format($echuTotal, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-down">À relancer</span>
                    </div>
                </div>
                <div class="kpi-card-shape" style="background: #ef4444;"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-event_available"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">📅 À échoir</div>
                    <div class="kpi-card-value">{{ number_format($aEchoirTotal, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">Échéances futures</span>
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
                    <div class="kpi-card-label">✅ Encaissé ce mois</div>
                    <div class="kpi-card-value">{{ number_format($encaisseCeMois, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">Taux : {{ number_format($tauxRecouvrement, 1, ',', ' ') }}%</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- RÉPARTITION PAR TYPE --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition par type de créance
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Type de Créance</th>
                                    <th class="text-end">Montant Total</th>
                                    <th class="text-center">Nb Clients</th>
                                    <th class="text-center">Nb Documents</th>
                                    <th class="text-end">Échu</th>
                                    <th class="text-end">À échoir</th>
                                    <th class="text-end">% du Total</th>
                                    <th class="text-center">Détail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($repartition as $r)
                                    <tr>
                                        <td><strong>{{ $r->type }}</strong></td>
                                        <td class="text-end fw-bold text-primary">{{ Help::formatNombre($r->montant_total, true) }}</td>
                                        <td class="text-center">{{ $r->nb_clients }}</td>
                                        <td class="text-center">{{ $r->nb_documents }}</td>
                                        <td class="text-end text-danger">{{ Help::formatNombre($r->echu, true) }}</td>
                                        <td class="text-end text-info">{{ Help::formatNombre($r->a_echoir, true) }}</td>
                                        <td class="text-end">
                                            <span class="kpi-pill" style="background: #eef2ff; color: #1c57a3;">{{ number_format($r->pct, 1, ',', ' ') }}%</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ $r->detail_route }}" class="btn btn-sm btn-light">
                                                <i class="material-icons md-visibility"></i> {{ $r->detail_label }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot style="background: linear-gradient(180deg, #f5f7fb 0%, #e8eef7 100%);">
                                <tr>
                                    <td><strong>TOTAL CRÉANCES</strong></td>
                                    <td class="text-end fw-bold text-primary">{{ Help::formatNombre($totalCreances, true) }}</td>
                                    <td class="text-center fw-bold">{{ $totalNbClients }}</td>
                                    <td class="text-center fw-bold">{{ $totalNbDocs }}</td>
                                    <td class="text-end fw-bold text-danger">{{ Help::formatNombre($echuTotal, true) }}</td>
                                    <td class="text-end fw-bold text-info">{{ Help::formatNombre($aEchoirTotal, true) }}</td>
                                    <td class="text-end fw-bold">100,0%</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CRÉANCES ÉCHUES + TOP DÉBITEURS --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-warning text-danger"></i>
                        🚨 Créances échues impayées — À relancer en priorité
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Réf. Doc</th>
                                    <th>Client</th>
                                    <th>Type</th>
                                    <th class="text-end">Restant</th>
                                    <th class="text-center">Retard</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($creancesEchues as $ce)
                                    <tr>
                                        <td><strong class="text-primary">{{ $ce->reference }}</strong></td>
                                        <td>{{ $ce->client }}</td>
                                        <td>
                                            <span class="badge {{ $ce->type === 'Terme' ? 'bg-info' : 'bg-warning text-dark' }}">{{ $ce->type }}</span>
                                        </td>
                                        <td class="text-end fw-bold text-danger">{{ Help::formatNombre($ce->montant_restant, true) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-danger">{{ $ce->jours_retard }} j</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            🎉 Aucune créance échue ! Excellent.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        🏆 Top 5 clients débiteurs
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topDebiteurs as $idx => $td)
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ $td->nom ?: '—' }}</div>
                                <div class="dash-top-meta">
                                    <i class="material-icons md-phone" style="font-size:14px;vertical-align:middle;"></i>
                                    {{ $td->tel ?? '-' }}
                                    ·
                                    <strong class="text-danger">{{ Help::formatNombre($td->total_du, true) }}</strong>
                                    @if (!empty($td->nb_docs))
                                        · {{ $td->nb_docs }} doc{{ $td->nb_docs > 1 ? 's' : '' }}
                                    @endif
                                    @if (!empty($td->type))
                                        · <span class="badge bg-light text-dark">{{ $td->type }}</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                @php $risque = $td->risque ?? 'FAIBLE'; @endphp
                                <span class="badge {{ $risque === 'ÉLEVÉ' ? 'bg-danger' : ($risque === 'MOYEN' ? 'bg-warning text-dark' : 'bg-success') }}">
                                    {{ $risque }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted my-4">Aucun débiteur.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODALE — Guide d'utilisation du récap Créances
         ============================================================ --}}
    <div class="modal fade" id="modalGuideRecapCreances" tabindex="-1" aria-labelledby="modalGuideRecapCreancesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1c57a3 0%, #0a2540 100%); color: #fff; border-bottom: 0;">
                    <h5 class="modal-title" id="modalGuideRecapCreancesLabel">
                        <i class="material-icons md-menu_book align-middle"></i>
                        Guide d'utilisation — Récap Créances
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    @include('admin.recapCreances._guide')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    @include('admin.shared._recap-guide-styles')
@endsection
