@extends('layout.main')
@section('title', 'Synthèse - Dettes livreurs')

@php
    use Carbon\Carbon;

    $totalStatuts = max(1, $countLivEffectuees + $countValideesAPayer + $countContestation + $countPayees + $countAnnulees);
    $pctLivEff    = ($countLivEffectuees  / $totalStatuts) * 100;
    $pctValAPay   = ($countValideesAPayer / $totalStatuts) * 100;
    $pctContest   = ($countContestation   / $totalStatuts) * 100;
    $pctPayees    = ($countPayees         / $totalStatuts) * 100;
    $pctAnnulees  = ($countAnnulees       / $totalStatuts) * 100;

    // Taux de paiement global
    $tauxPaiement = $totalFrais > 0 ? ($totalPaye / $totalFrais) * 100 : 0;

    // Prochaine date de paie livreur (via configuration)
    $prochainePaie = $config?->prochainePaiementLivreur();
    $joursAvantPaie = $prochainePaie ? max(0, Carbon::today()->diffInDays($prochainePaie, false)) : null;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Synthèse <span class="dash-welcome-name">Livreurs</span> 🚚
                </h2>
                <p class="dash-welcome-subtitle">
                    Suivi des dettes des livreurs — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <a href="{{ route('show.livreurs.livraisons') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-list_alt"></i>
                    <span>Livraisons</span>
                </a>
                <a href="{{ route('show.livreurs.paiements') }}" class="dash-time-pill" style="text-decoration:none;">
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
                <div class="kpi-card-icon"><i class="material-icons md-local_shipping"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Livraisons</div>
                    <div class="kpi-card-value">{{ $nombreLivraisons }}</div>
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
                    <div class="kpi-card-label">Total frais livraison</div>
                    <div class="kpi-card-value">{{ number_format($totalFrais, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">facturés aux livreurs</span>
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
                        <span class="kpi-pill kpi-pill-soft">à régler aux livreurs</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== STATUTS LIVRAISONS & PROCHAINE PAIE ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition des livraisons par statut
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-hourglass_empty"></i></div>
                                <div>
                                    <div class="stat-mini-label">À valider</div>
                                    <div class="stat-mini-value">{{ $countLivEffectuees }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-pending"></i></div>
                                <div>
                                    <div class="stat-mini-label">Validées à payer</div>
                                    <div class="stat-mini-value">{{ $countValideesAPayer }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="stat-mini stat-mini-danger">
                                <div class="stat-mini-icon"><i class="material-icons md-error"></i></div>
                                <div>
                                    <div class="stat-mini-label">En contestation</div>
                                    <div class="stat-mini-value">{{ $countContestation }}</div>
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
                        <div class="dash-stack-bar-segment bg-info"    style="width: {{ $pctLivEff }}%"   title="À valider : {{ number_format($pctLivEff,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctValAPay }}%"  title="Validées à payer : {{ number_format($pctValAPay,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-danger"  style="width: {{ $pctContest }}%"  title="En contestation : {{ number_format($pctContest,1) }}%"></div>
                        <div class="dash-stack-bar-segment bg-success" style="width: {{ $pctPayees }}%"   title="Payées : {{ number_format($pctPayees,1) }}%"></div>
                        <div class="dash-stack-bar-segment" style="width: {{ $pctAnnulees }}%; background:#9ca3af;" title="Annulées : {{ number_format($pctAnnulees,1) }}%"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>À valider</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>Validées</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-danger"></span>Contestation</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-success"></span>Payées</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot" style="background:#9ca3af;"></span>Annulées</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-event text-primary"></i>
                        Prochaine paie livreur
                    </h5>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center">
                    @if ($prochainePaie)
                        @php
                            // Couleur en fonction du nombre de jours restants
                            $jpColor = $joursAvantPaie === 0
                                ? '#ef4444'
                                : ($joursAvantPaie <= 2 ? '#f59e0b' : '#10b981');
                            // Jauge à 100 % le jour J, dégressive jusqu'à 0 j (7 jours = 100% restant à attendre)
                            $jpGauge = min(100, max(0, (1 - ($joursAvantPaie / 7)) * 100));
                        @endphp
                        <div class="conv-circle" style="
                            --tc: {{ $jpGauge }};
                            --tc-color: {{ $jpColor }};
                            background: conic-gradient({{ $jpColor }} calc(var(--tc) * 1%), #e5e7eb 0);">
                            <div class="conv-circle-inner">
                                <div class="conv-circle-value" style="color:{{ $jpColor }}">
                                    @if ($joursAvantPaie === 0)
                                        J
                                    @else
                                        J-{{ $joursAvantPaie }}
                                    @endif
                                </div>
                                <div class="conv-circle-label">{{ $prochainePaie->locale('fr')->isoFormat('D MMM') }}</div>
                            </div>
                        </div>
                        <p class="text-muted small text-center mt-3 mb-0">
                            @if ($joursAvantPaie === 0)
                                <strong class="text-danger">Aujourd'hui !</strong>
                            @else
                                Prochaine paie le <strong>{{ $prochainePaie->locale('fr')->isoFormat('dddd D MMMM') }}</strong>
                            @endif
                        </p>
                    @else
                        <div class="text-center text-muted">
                            <i class="material-icons md-event_busy" style="font-size:48px;"></i>
                            <p class="mb-0 mt-2">Configuration de paie non définie.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DETTES PAR LIVREUR ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-person text-primary"></i>
                        Dettes par livreur
                    </h5>
                    <a href="{{ route('show.list') }}" class="btn btn-sm btn-outline-primary">
                        <i class="material-icons md-settings" style="font-size:16px;vertical-align:middle;"></i>
                        Gérer les livreurs
                    </a>
                </div>
                <div class="card-body">
                    @if ($dettesParLivreur->isEmpty())
                        <p class="text-center text-muted my-4">Aucune dette par livreur pour le moment.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Livreur</th>
                                        <th class="text-center">Nb livraisons</th>
                                        <th class="text-end">Total dû</th>
                                        <th class="text-end">Total payé</th>
                                        <th class="text-end">Reste dû</th>
                                        <th>Couverture</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($dettesParLivreur as $d)
                                        @php
                                            $pctPaye = $d->total_du > 0 ? ($d->total_paye / $d->total_du) * 100 : 0;
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
                                                    {{ $d->nb_livraisons }}
                                                </span>
                                            </td>
                                            <td class="text-end">{{ Help::formatNombre($d->total_du, true) }}</td>
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
                        <li>Fréquence de paiement : <strong>{{ $config->frequence_paiement_livreur ?? 'Hebdomadaire' }}</strong></li>
                        <li>Jour de paiement : <strong>{{ $config->jour_paiement_livreur ?? 'Vendredi' }}</strong></li>
                        <li>Devise : <strong>{{ $config->devise ?? 'FCFA' }}</strong></li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <style>
        /* Cercle prochaine paie (donut CSS pur) */
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
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
            font-weight: 600;
        }
    </style>
@endsection
