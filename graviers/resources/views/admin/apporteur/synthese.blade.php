@extends('layout.main')
@section('title', 'Synthèse - Dettes apporteurs')

@php
    use Carbon\Carbon;

    // Répartition des dettes par statut (en montants)
    $totalDettesStatuts = max(1, $resteDue + $restePartielle + $resteAttente);
    $pctDue       = ($resteDue       / $totalDettesStatuts) * 100;
    $pctPartielle = ($restePartielle / $totalDettesStatuts) * 100;
    $pctAttente   = ($resteAttente   / $totalDettesStatuts) * 100;

    // Taux de paiement global
    $tauxPaiement = $totalCalcule > 0 ? ($totalPaye / $totalCalcule) * 100 : 0;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Synthèse <span class="dash-welcome-name">Apporteurs</span> 🤝
                </h2>
                <p class="dash-welcome-subtitle">
                    Suivi des commissions des apporteurs d'affaires — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <a href="{{ route('show.apporteurs.commissions') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-list_alt"></i>
                    <span>Commissions</span>
                </a>
                <a href="{{ route('show.apporteurs.paiements') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-payments"></i>
                    <span>Paiements</span>
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
                    <div class="kpi-card-label">Commissions</div>
                    <div class="kpi-card-value">{{ $nombreCommissions }}</div>
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
                    <div class="kpi-card-label">Total commissions calculées</div>
                    <div class="kpi-card-value">{{ number_format($totalCalcule, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">générées sur les commandes</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-account_balance_wallet"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total payé</div>
                    <div class="kpi-card-value">{{ number_format($totalPaye, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-up">
                            <i class="material-icons md-trending_up"></i>
                            {{ number_format($tauxPaiement, 1, ',', ' ') }}%
                        </span>
                        <span class="kpi-card-meta-text">de couverture</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-warning"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Dettes restantes</div>
                    <div class="kpi-card-value">{{ number_format($dettesTotales, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">à régler aux apporteurs</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== STATUTS DETTES & COMMISSIONS ANNULÉES ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition des dettes par statut
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-pending"></i></div>
                                <div>
                                    <div class="stat-mini-label">Dues</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($resteDue, true) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-autorenew"></i></div>
                                <div>
                                    <div class="stat-mini-label">Partiellement dues</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($restePartielle, true) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini" style="background:#f3f4f6;border-color:#9ca3af;">
                                <div class="stat-mini-icon" style="background:#9ca3af;color:#fff;"><i class="material-icons md-schedule"></i></div>
                                <div>
                                    <div class="stat-mini-label">En attente client</div>
                                    <div class="stat-mini-value">{{ Help::formatNombre($resteAttente, true) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Stack bar de proportion --}}
                    <div class="dash-stack-bar mt-4">
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctDue }}%"       title="Dues : {{ number_format($pctDue,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-info"    style="width: {{ $pctPartielle }}%" title="Partielles : {{ number_format($pctPartielle,1) }}%"></div>
                        <div class="dash-stack-bar-segment" style="width: {{ $pctAttente }}%; background:#9ca3af;" title="En attente client : {{ number_format($pctAttente,1) }}%"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>Dues</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>Partielles</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot" style="background:#9ca3af;"></span>En attente client</span>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        <em>Règle : la commission n'est due QUE si le client a effectivement payé la commande.</em>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-block text-primary"></i>
                        Commissions annulées
                    </h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    @php
                        $pctAnnulees = $nombreCommissions > 0 ? ($countAnnulees / $nombreCommissions) * 100 : 0;
                        $annColor = $countAnnulees == 0
                            ? '#10b981'
                            : ($pctAnnulees <= 10 ? '#f59e0b' : '#ef4444');
                    @endphp
                    <div class="conv-circle" style="
                        --tc: {{ $pctAnnulees }};
                        --tc-color: {{ $annColor }};
                        background: conic-gradient({{ $annColor }} calc(var(--tc) * 1%), #e5e7eb 0);">
                        <div class="conv-circle-inner">
                            <div class="conv-circle-value" style="color:{{ $annColor }}">
                                {{ $countAnnulees }}
                            </div>
                            <div class="conv-circle-label">annulées</div>
                        </div>
                    </div>
                    <p class="text-muted small text-center mt-3 mb-0">
                        @if ($countAnnulees == 0)
                            Aucune commission annulée 🎉
                        @else
                            {{ number_format($pctAnnulees, 1, ',', ' ') }}% des commissions ({{ $countAnnulees }} sur {{ $nombreCommissions }})
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DETTES PAR APPORTEUR ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-person text-primary"></i>
                        Dettes par apporteur
                    </h5>
                    <a href="{{ route('show.listApporteur') }}" class="btn btn-sm btn-outline-primary">
                        <i class="material-icons md-settings" style="font-size:16px;vertical-align:middle;"></i>
                        Gérer les apporteurs
                    </a>
                </div>
                <div class="card-body">
                    @if ($dettesParApporteur->isEmpty())
                        <p class="text-center text-muted my-4">Aucune dette par apporteur pour le moment.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Apporteur</th>
                                        <th class="text-center">Nb commissions</th>
                                        <th class="text-end">Calculé</th>
                                        <th class="text-end">Payé</th>
                                        <th class="text-end">Reste dû</th>
                                        <th>Couverture</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dettesParApporteur as $d)
                                        @php
                                            $pctPaye = $d->total_calcule > 0 ? ($d->total_paye / $d->total_calcule) * 100 : 0;
                                        @endphp
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:10px;">
                                                    <div class="dash-counter-icon dash-counter-icon-primary" style="width:38px;height:38px;font-size:18px;">
                                                        <i class="material-icons md-person"></i>
                                                    </div>
                                                    <div>
                                                        <strong>{{ $d->code }}</strong>
                                                        <div class="text-muted small">{{ $d->nom }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark" style="font-size:0.85rem;">
                                                    {{ $d->nb_commissions }}
                                                </span>
                                            </td>
                                            <td class="text-end">{{ Help::formatNombre($d->total_calcule, true) }}</td>
                                            <td class="text-end text-success"><strong>{{ Help::formatNombre($d->total_paye, true) }}</strong></td>
                                            <td class="text-end text-danger"><strong>{{ Help::formatNombre($d->reste_du, true) }}</strong></td>
                                            <td style="min-width:200px;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height:8px;border-radius:6px;">
                                                        <div class="progress-bar bg-success" role="progressbar"
                                                             style="width: {{ $pctPaye }}%;"
                                                             aria-valuenow="{{ $pctPaye }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted" style="min-width:42px;text-align:right;">{{ number_format($pctPaye, 0) }}%</small>
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
                    <strong>Paramètres actifs (modifiables dans <a href="{{ route('show.parametre') }}" style="color:#1e3a8a;text-decoration:underline;">Paramètres</a>) :</strong>
                    <ul class="mb-0 mt-1" style="font-size:0.9rem;">
                        <li>Taux commission standard : <strong>{{ number_format($config->taux_commission_standard ?? 3, 1, ',', ' ') }}%</strong></li>
                        <li>Délai paiement commission : <strong>{{ $config->delai_paiement_commission ?? 15 }} jours</strong></li>
                        <li>Devise : <strong>{{ $config->devise ?? 'FCFA' }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <style>
        /* Cercle d'annulation (donut CSS pur) */
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
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
        }
        .conv-circle-label {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
    </style>
@endsection
