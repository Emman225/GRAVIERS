@extends('layout.main')
@section('title', 'Synthèse - Commandes comptant')

@php
    use Carbon\Carbon;
    $totalStatuts = max(1, $countEnAttente + $countEnRetard + $countPayees + $countPartielles + $countLivrees + $countAnnulees);
    $pctAttente   = ($countEnAttente   / $totalStatuts) * 100;
    $pctRetard    = ($countEnRetard    / $totalStatuts) * 100;
    $pctPayees    = ($countPayees      / $totalStatuts) * 100;
    $pctPartiel   = ($countPartielles  / $totalStatuts) * 100;
    $pctLivrees   = ($countLivrees     / $totalStatuts) * 100;
    $pctAnnulees  = ($countAnnulees    / $totalStatuts) * 100;

    $tauxRecouvrement = $totalCommande > 0 ? ($totalEncaisse / $totalCommande) * 100 : 0;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Synthèse <span class="dash-welcome-name">Comptant</span> 💰
                </h2>
                <p class="dash-welcome-subtitle">
                    Vue d'ensemble des commandes comptant — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <a href="{{ route('show.comptant.commandes') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-list_alt"></i>
                    <span>Voir commandes</span>
                </a>
                <a href="{{ route('show.comptant.encaissements') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-payments"></i>
                    <span>Encaissements</span>
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-receipt_long"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Commandes comptant</div>
                    <div class="kpi-card-value">{{ $nombreCommandes }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">total enregistré</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-attach_money"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total commandé</div>
                    <div class="kpi-card-value">{{ number_format($totalCommande, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">CA potentiel</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-account_balance_wallet"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total encaissé</div>
                    <div class="kpi-card-value">{{ number_format($totalEncaisse, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-up">
                            <i class="material-icons md-trending_up"></i>
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
                <div class="kpi-card-icon"><i class="material-icons md-warning"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Reste à recouvrer</div>
                    <div class="kpi-card-value">{{ number_format($creanceTotale, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">{{ $countEnAttente + $countEnRetard + $countPartielles }} commandes</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== STATUTS COMMANDES & TAUX DE CONVERSION ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition des statuts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-pending"></i></div>
                                <div>
                                    <div class="stat-mini-label">En attente paiement</div>
                                    <div class="stat-mini-value">{{ $countEnAttente }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-danger">
                                <div class="stat-mini-icon"><i class="material-icons md-error"></i></div>
                                <div>
                                    <div class="stat-mini-label">En retard</div>
                                    <div class="stat-mini-value">{{ $countEnRetard }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-success">
                                <div class="stat-mini-icon"><i class="material-icons md-check_circle"></i></div>
                                <div>
                                    <div class="stat-mini-label">Payées</div>
                                    <div class="stat-mini-value">{{ $countPayees }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-autorenew"></i></div>
                                <div>
                                    <div class="stat-mini-label">Partiellement payées</div>
                                    <div class="stat-mini-value">{{ $countPartielles }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-primary">
                                <div class="stat-mini-icon"><i class="material-icons md-local_shipping"></i></div>
                                <div>
                                    <div class="stat-mini-label">Livrées</div>
                                    <div class="stat-mini-value">{{ $countLivrees }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini" style="background:#f3f4f6;border-color:#9ca3af;">
                                <div class="stat-mini-icon" style="background:#9ca3af;color:#fff;"><i class="material-icons md-block"></i></div>
                                <div>
                                    <div class="stat-mini-label">Annulées</div>
                                    <div class="stat-mini-value">{{ $countAnnulees }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Stack bar de proportion --}}
                    <div class="dash-stack-bar mt-4">
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctAttente }}%" title="En attente : {{ number_format($pctAttente,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-danger"  style="width: {{ $pctRetard }}%"  title="En retard : {{ number_format($pctRetard,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-info"    style="width: {{ $pctPartiel }}%" title="Partielles : {{ number_format($pctPartiel,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-success" style="width: {{ $pctPayees }}%"  title="Payées : {{ number_format($pctPayees,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-primary" style="width: {{ $pctLivrees }}%" title="Livrées : {{ number_format($pctLivrees,1) }}%"></div>
                        <div class="dash-stack-bar-segment" style="width: {{ $pctAnnulees }}%; background:#9ca3af;" title="Annulées : {{ number_format($pctAnnulees,1) }}%"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>Attente</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-danger"></span>Retard</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>Partielles</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-success"></span>Payées</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-primary"></span>Livrées</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot" style="background:#9ca3af;"></span>Annulées</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-speed text-primary"></i>
                        Taux de conversion
                    </h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    @php
                        $tcRounded = round($tauxConversion);
                        $tcColor   = $tcRounded >= 70 ? '#10b981' : ($tcRounded >= 40 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <div class="conv-circle" style="
                        --tc: {{ $tcRounded }};
                        --tc-color: {{ $tcColor }};
                        background: conic-gradient({{ $tcColor }} calc(var(--tc) * 1%), #e5e7eb 0);">
                        <div class="conv-circle-inner">
                            <div class="conv-circle-value" style="color:{{ $tcColor }}">
                                {{ number_format($tauxConversion, 1, ',', ' ') }}%
                            </div>
                            <div class="conv-circle-label">payées / total</div>
                        </div>
                    </div>
                    <p class="text-muted small text-center mt-3 mb-0">
                        {{ $countPayees }} commande{{ $countPayees > 1 ? 's' : '' }} entièrement payée{{ $countPayees > 1 ? 's' : '' }}
                        sur {{ $nombreCommandes }} au total.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== RÉPARTITION PAR AGENCE ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-store text-primary"></i>
                        Répartition par agence
                    </h5>
                    <a href="{{ route('show.agences.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="material-icons md-settings" style="font-size:16px;vertical-align:middle;"></i>
                        Gérer les agences
                    </a>
                </div>
                <div class="card-body">
                    @if ($repartitionAgence->isEmpty())
                        <p class="text-center text-muted my-4">Aucune commande répartie par agence pour le moment.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Agence</th>
                                        <th class="text-center">Nb commandes</th>
                                        <th class="text-end">Encaissé</th>
                                        <th class="text-end">Reste dû</th>
                                        <th>Recouvrement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($repartitionAgence as $r)
                                        @php
                                            $totalAgence = $r->total_encaisse + $r->reste_du;
                                            $pctRecouvre = $totalAgence > 0 ? ($r->total_encaisse / $totalAgence) * 100 : 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div class="dash-counter-icon dash-counter-icon-primary" style="width:38px;height:38px;font-size:18px;">
                                                        <i class="material-icons md-store"></i>
                                                    </div>
                                                    <div>
                                                        <strong>{{ $r->agence->code }}</strong>
                                                        <div class="text-muted small">{{ $r->agence->nom }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark" style="font-size:0.85rem;">
                                                    {{ $r->nb_commandes }}
                                                </span>
                                            </td>
                                            <td class="text-end text-success"><strong>{{ Help::formatNombre($r->total_encaisse, true) }}</strong></td>
                                            <td class="text-end text-danger"><strong>{{ Help::formatNombre($r->reste_du, true) }}</strong></td>
                                            <td style="min-width:200px;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height:8px;border-radius:6px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                             style="width: {{ $pctRecouvre }}%;"
                                                             aria-valuenow="{{ $pctRecouvre }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted" style="min-width:42px;text-align:right;">{{ number_format($pctRecouvre, 0) }}%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== PARAMÈTRES ACTIFS ===== --}}
    @if ($config)
        <div class="alert alert-info" style="border-radius:14px;border:none;border-left:4px solid #3b82f6;background:#eff6ff;color:#1e3a8a;">
            <div class="d-flex align-items-start gap-3">
                <i class="material-icons md-info" style="font-size:24px;color:#3b82f6;"></i>
                <div class="flex-grow-1">
                    <strong>Paramètres actifs (modifiables dans <a href="{{ route('show.parametre') }}#tab-comptant" style="color:#1e3a8a;text-decoration:underline;">Paramètres → Comptant</a>) :</strong>
                    <ul class="mb-0 mt-1" style="font-size:0.9rem;">
                        <li>Délai max paiement agence : <strong>{{ $config->delai_max_paiement_agence ?? 3 }} jours</strong></li>
                        <li>Délai annulation auto : <strong>{{ $config->delai_annulation_auto ?? 7 }} jours</strong></li>
                        <li>Devise : <strong>{{ $config->devise ?? 'FCFA' }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <style>
        /* Cercle de conversion (donut CSS pur, sans dépendance JS) */
        .conv-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin-top: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .conv-circle-inner {
            width: 130px;
            height: 130px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .conv-circle-value {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1;
        }
        .conv-circle-label {
            color: #6b7280;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Variantes stat-mini complémentaires (au cas où elles ne sont pas dans premium-dashboard.css) */
        .stat-mini-danger {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        .stat-mini-danger .stat-mini-icon {
            background: #ef4444;
            color: #fff;
        }
        .stat-mini-primary {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
        }
        .stat-mini-primary .stat-mini-icon {
            background: #3b82f6;
            color: #fff;
        }
    </style>
@endsection
