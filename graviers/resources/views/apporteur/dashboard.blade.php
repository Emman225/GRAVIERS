@extends('layout.main')
@section('title','Tableau de bord')

@php
    use Carbon\Carbon;
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
    $userName  = Auth::user()->nom_prenoms ?? 'Apporteur';
    $firstName = explode(' ', $userName)[0];
    $evolutionGain = $evolutionGain ?? 0;
    $isPositive = $evolutionGain >= 0;
    $nbFilleules = $clients->count();
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    {{ $greeting }}, <span class="dash-welcome-name">{{ $firstName }}</span> 👋
                </h2>
                <p class="dash-welcome-subtitle">
                    Suivi de votre activité d'apporteur d'affaires — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex gap-2">
                <div class="dash-time-pill">
                    <i class="material-icons md-loyalty"></i>
                    <span>Code parrain :&nbsp;<strong>{{ $apporteur->code }}</strong></span>
                </div>
                <div class="dash-time-pill">
                    <i class="material-icons md-access_time"></i>
                    <span id="dashLiveClock">{{ now()->format('H:i') }}</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-monetization_on"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Solde disponible</div>
                    <div class="kpi-card-value">{{ number_format($apporteur->solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <a href="{{ route('show.demandeDepaiePage') }}" class="kpi-pill kpi-pill-soft">
                            <i class="material-icons md-confirmation_number"></i> Demander un paiement
                        </a>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-group"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Mes filleul(e)s</div>
                    <div class="kpi-card-value">{{ $nbFilleules }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">parrainés via votre code</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-receipt_long"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Commissions ce mois</div>
                    <div class="kpi-card-value">{{ $commissionsMois }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ $totalCommissions }} au total</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-account_balance_wallet"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Gain ce mois</div>
                    <div class="kpi-card-value">{{ number_format($gainMensuel, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-trend kpi-trend-{{ $isPositive ? 'up' : 'down' }}">
                            <i class="material-icons md-{{ $isPositive ? 'trending_up' : 'trending_down' }}"></i>
                            {{ number_format(abs($evolutionGain), 1, ',', ' ') }}%
                        </span>
                        <span class="kpi-card-meta-text">vs mois dernier</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== ACTIVITÉ + RÉSUMÉ ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        Top 5 filleul(e)s rentables
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topFilleules as $idx => $tf)
                        @php
                            $nomComplet = trim(($tf->nom ?? '').' '.($tf->prenom ?? '')) ?: 'Client #'.$tf->id;
                        @endphp
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ $nomComplet }}</div>
                                <div class="dash-top-meta">
                                    {{ $tf->nb_commandes }} commande{{ $tf->nb_commandes > 1 ? 's' : '' }}
                                    @if(!empty($tf->contact1)) · {{ $tf->contact1 }} @endif
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-success">{{ number_format((float) $tf->total_commission, 0, ',', ' ') }}</div>
                                <small class="text-muted">FCFA</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted my-4">Aucune commission enregistrée pour vos filleul(e)s.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-insights text-primary"></i>
                        Mon activité
                    </h5>
                </div>
                <div class="card-body">
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-success"><i class="material-icons md-savings"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Gain total cumulé</div>
                            <div class="dash-counter-value">
                                {{ number_format($gainTotal, 0, ',', ' ') }}
                                <small class="text-muted" style="font-size:0.7em">FCFA</small>
                            </div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-receipt_long"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Commissions totales</div>
                            <div class="dash-counter-value">{{ $totalCommissions }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-group"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Filleul(e)s parrainés</div>
                            <div class="dash-counter-value">{{ $nbFilleules }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-primary"><i class="material-icons md-loyalty"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Code parrain</div>
                            <div class="dash-counter-value" style="font-size:1.2rem;letter-spacing:1px">{{ $apporteur->code }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DERNIÈRES TRANSACTIONS ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-receipt_long text-primary"></i>
                Dernières transactions
            </h5>
            <a href="{{ route('apporteur.paiement') }}" class="btn btn-sm btn-light">
                Voir tout <i class="material-icons md-arrow_forward"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>N° commande</th>
                            <th>Client</th>
                            <th>Date commande</th>
                            <th class="text-end">Montant commande</th>
                            <th>Date paiement</th>
                            <th class="text-end">Commission reçue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paiements as $paiement)
                            <tr>
                                <td><strong class="text-primary">{{ $paiement->num_commande }}</strong></td>
                                <td>{{ $paiement->client }}</td>
                                <td>{{ \Carbon\Carbon::parse($paiement->date_commande)->format('d/m/Y') }}</td>
                                <td class="text-end fw-bold">{{ number_format((float) $paiement->montant_total, 0, ',', ' ') }} FCFA</td>
                                <td>{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</td>
                                <td class="text-end fw-bold text-success">{{ number_format((float) $paiement->montant_recu, 0, ',', ' ') }} FCFA</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Aucune transaction pour le moment.
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
    <script>
        (function () {
            var clock = document.getElementById('dashLiveClock');
            if (!clock) return;
            function tick() {
                var d = new Date();
                clock.textContent = String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
            }
            setInterval(tick, 30000);
        })();
    </script>
@endsection
