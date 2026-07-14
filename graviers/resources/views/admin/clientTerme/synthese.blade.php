@extends('layout.main')
@section('title', 'Synthèse - Créances clients à terme')

@php
    use Carbon\Carbon;
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir');
    $tauxRecouvrement = $tauxRecouvrement ?? 0;
    $isHealthy = $tauxRecouvrement >= 80;
@endphp

@section('contenu')

    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Synthèse <span class="dash-welcome-name">Créances à terme</span> 📊
                </h2>
                <p class="dash-welcome-subtitle">
                    Vue d'ensemble des créances clients à terme — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-receipt_long"></i>
                    {{ $nombreFactures }} factures suivies
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI CARDS PRINCIPAUX ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-receipt_long"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total facturé TTC</div>
                    <div class="kpi-card-value">{{ number_format($totalFacture, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">{{ $nombreFactures }} facture{{ $nombreFactures > 1 ? 's' : '' }}</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-payments"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total encaissé</div>
                    <div class="kpi-card-value">{{ number_format($totalEncaisse, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-{{ $isHealthy ? 'up' : 'down' }}">
                            <i class="material-icons md-{{ $isHealthy ? 'trending_up' : 'trending_down' }}"></i>
                            {{ number_format($tauxRecouvrement, 1, ',', ' ') }}%
                        </span>
                        <span class="kpi-card-meta-text">de recouvrement</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-account_balance_wallet"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Reste à encaisser</div>
                    <div class="kpi-card-value">{{ number_format($creanceTotale, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">créances totales en cours</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-schedule"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Retard moyen</div>
                    <div class="kpi-card-value">{{ $retardMoyen }}<span class="kpi-card-currency">jours</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">sur les factures en retard</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== RÉPARTITION CRÉANCES + TOP DÉBITEURS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition des créances
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-warning"></i></div>
                                <div>
                                    <div class="stat-mini-label">Échues partielles</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($creanceEcheuePartielle, true) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-event"></i></div>
                                <div>
                                    <div class="stat-mini-label">À échoir</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($creanceAEchoir, true) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-danger">
                                <div class="stat-mini-icon"><i class="material-icons md-error"></i></div>
                                <div>
                                    <div class="stat-mini-label">Échues impayées</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($creanceEcheueImpayee, true) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $totalCreances = max(1, $creanceAEchoir + $creanceEcheuePartielle + $creanceEcheueImpayee);
                        $pctAEchoir   = ($creanceAEchoir / $totalCreances) * 100;
                        $pctPartielle = ($creanceEcheuePartielle / $totalCreances) * 100;
                        $pctImpayee   = ($creanceEcheueImpayee / $totalCreances) * 100;
                    @endphp
                    <div class="dash-stack-bar mt-4">
                        <div class="dash-stack-bar-segment bg-info" style="width: {{ $pctAEchoir }}%" title="À échoir"></div>
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctPartielle }}%" title="Échues partielles"></div>
                        <div class="dash-stack-bar-segment bg-danger" style="width: {{ $pctImpayee }}%" title="Échues impayées"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>À échoir {{ number_format($pctAEchoir, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>Partielles {{ number_format($pctPartielle, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-danger"></span>Impayées {{ number_format($pctImpayee, 1) }}%</span>
                    </div>

                    {{-- Barre de progression du recouvrement --}}
                    <div class="mt-4 pt-3" style="border-top: 1px dashed #e5e7eb;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">
                                <i class="material-icons md-trending_up" style="vertical-align: middle; font-size: 16px;"></i>
                                Taux de recouvrement global
                            </span>
                            <strong class="text-{{ $isHealthy ? 'success' : 'warning' }}">{{ number_format($tauxRecouvrement, 1, ',', ' ') }}%</strong>
                        </div>
                        <div class="progress" style="height: 10px; border-radius: 6px;">
                            <div class="progress-bar bg-{{ $isHealthy ? 'success' : 'warning' }}"
                                 role="progressbar"
                                 style="width: {{ min(100, $tauxRecouvrement) }}%; border-radius: 6px;"
                                 aria-valuenow="{{ $tauxRecouvrement }}"
                                 aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted" style="font-size: 0.78rem;">
                            Objectif : 80 % minimum — {{ $isHealthy ? '✅ Bon niveau' : '⚠️ À améliorer' }}
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        🏆 Top clients débiteurs
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topDebiteurs->take(5) as $idx => $d)
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ $d->nom ?: 'Client #' . $d->code_client }}</div>
                                <div class="dash-top-meta">
                                    <i class="material-icons md-account_circle" style="font-size:14px;vertical-align:middle;"></i>
                                    Code : <strong>#{{ $d->code_client }}</strong>
                                    ·
                                    <strong class="text-danger">{{ Help::formatNombre($d->reste_du, true) }}</strong>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center my-4 py-3">
                            <i class="material-icons md-celebration text-success" style="font-size: 48px;"></i>
                            <p class="text-muted mt-2 mb-0">Aucun débiteur — bravo ! 🎉</p>
                        </div>
                    @endforelse

                    @if ($topDebiteurs->count() > 5)
                        <div class="text-center mt-3 pt-2" style="border-top: 1px dashed #e5e7eb;">
                            <small class="text-muted">
                                + {{ $topDebiteurs->count() - 5 }} autre{{ $topDebiteurs->count() - 5 > 1 ? 's' : '' }} client{{ $topDebiteurs->count() - 5 > 1 ? 's' : '' }} débiteur{{ $topDebiteurs->count() - 5 > 1 ? 's' : '' }}
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== PARAMÈTRES & ACTIONS RAPIDES ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-tune text-primary"></i>
                        Paramètres en vigueur
                    </h5>
                </div>
                <div class="card-body">
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-call"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Délai de relance standard</div>
                            <div class="dash-counter-value">{{ $config->delai_relance_standard ?? 7 }} <small class="text-muted">jours</small></div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-danger"><i class="material-icons md-error_outline"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Seuil alerte retard</div>
                            <div class="dash-counter-value">{{ $config->seuil_alerte_retard ?? 15 }} <small class="text-muted">jours</small></div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-payments"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Devise</div>
                            <div class="dash-counter-value">{{ $config->devise ?? 'FCFA' }}</div>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <a href="{{ route('show.parametre') }}#tab-creance" class="btn btn-sm btn-outline-primary">
                            <i class="material-icons md-edit" style="vertical-align:middle;font-size:16px;"></i>
                            Modifier les paramètres
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-bolt text-primary"></i>
                        Actions rapides
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <a href="{{ route('show.creancesTerme.factures') }}" class="dash-counter w-100" style="text-decoration:none;color:inherit;">
                                <div class="dash-counter-icon dash-counter-icon-primary"><i class="material-icons md-receipt_long"></i></div>
                                <div class="flex-grow-1">
                                    <div class="dash-counter-label">Voir les factures</div>
                                    <div class="dash-counter-value" style="font-size: 0.95rem;">Liste détaillée</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('show.creancesTerme.relances') }}" class="dash-counter w-100" style="text-decoration:none;color:inherit;">
                                <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-call"></i></div>
                                <div class="flex-grow-1">
                                    <div class="dash-counter-label">Gérer les relances</div>
                                    <div class="dash-counter-value" style="font-size: 0.95rem;">+ À relancer</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('show.creancesTerme.paiements') }}" class="dash-counter w-100" style="text-decoration:none;color:inherit;">
                                <div class="dash-counter-icon dash-counter-icon-success"><i class="material-icons md-payments"></i></div>
                                <div class="flex-grow-1">
                                    <div class="dash-counter-label">Encaissements</div>
                                    <div class="dash-counter-value" style="font-size: 0.95rem;">Journal paiements</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('show.listClientATerme') }}" class="dash-counter w-100" style="text-decoration:none;color:inherit;">
                                <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-group"></i></div>
                                <div class="flex-grow-1">
                                    <div class="dash-counter-label">Clients à terme</div>
                                    <div class="dash-counter-value" style="font-size: 0.95rem;">Liste & profils</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
