@extends('layout.main')
@section('title','Tableau de bord')

@php
    use Carbon\Carbon;
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
    $userName = Auth::user()->nom_prenoms ?? 'Livreur';
    $firstName = explode(' ', $userName)[0];
    $evolutionGain = $evolutionGain ?? 0;
    $isPositive = $evolutionGain >= 0;

    $nbEffectuees = $livraisonEffectuees->count();
    $nbAttente    = $livraisonAttente->count();
    $nbEnCours    = (int) $livraisonEnCours;
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
                    Voici un aperçu de vos livraisons aujourd'hui — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
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
                    <div class="kpi-card-value">{{ number_format($livreur->solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
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
                    <i class="material-icons md-local_shipping"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Livraisons effectuées</div>
                    <div class="kpi-card-value">{{ $nbEffectuees }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">{{ $livraisonsAujourdhui }} aujourd'hui</span>
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
                    <div class="kpi-card-label">Enlèvements en attente</div>
                    <div class="kpi-card-value">{{ $nbAttente }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">à accepter</span>
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

    {{-- ===== STATS LIVRAISONS & ACTIVITÉ ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition de vos livraisons
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-pending"></i></div>
                                <div>
                                    <div class="stat-mini-label">À accepter</div>
                                    <div class="stat-mini-value">{{ $nbAttente }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-local_shipping"></i></div>
                                <div>
                                    <div class="stat-mini-label">En cours</div>
                                    <div class="stat-mini-value">{{ $nbEnCours }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-success">
                                <div class="stat-mini-icon"><i class="material-icons md-check_circle"></i></div>
                                <div>
                                    <div class="stat-mini-label">Livrées</div>
                                    <div class="stat-mini-value">{{ $nbEffectuees }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $totalEtats   = max(1, $nbAttente + $nbEnCours + $nbEffectuees);
                        $pctAttente   = ($nbAttente / $totalEtats) * 100;
                        $pctEnCours   = ($nbEnCours / $totalEtats) * 100;
                        $pctEffectuees= ($nbEffectuees / $totalEtats) * 100;
                    @endphp
                    <div class="dash-stack-bar mt-4">
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctAttente }}%" title="À accepter"></div>
                        <div class="dash-stack-bar-segment bg-info"    style="width: {{ $pctEnCours }}%" title="En cours"></div>
                        <div class="dash-stack-bar-segment bg-success" style="width: {{ $pctEffectuees }}%" title="Livrées"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>{{ number_format($pctAttente, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>{{ number_format($pctEnCours, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-success"></span>{{ number_format($pctEffectuees, 1) }}%</span>
                    </div>
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
                        <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-today"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Aujourd'hui</div>
                            <div class="dash-counter-value">{{ $livraisonsAujourdhui }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-date_range"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Cette semaine</div>
                            <div class="dash-counter-value">{{ $livraisonsSemaine }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-primary"><i class="material-icons md-calendar_month"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Ce mois</div>
                            <div class="dash-counter-value">{{ $livraisonsMois }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-success"><i class="material-icons md-savings"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Gain total cumulé</div>
                            <div class="dash-counter-value">{{ number_format($gainTotal, 0, ',', ' ') }} <small class="text-muted" style="font-size:0.7em">FCFA</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TOP CLIENTS + DERNIÈRES LIVRAISONS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        Top 5 clients livrés
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topClients as $idx => $tc)
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ trim(($tc->client?->nom ?? '').' '.($tc->client?->prenom ?? '')) ?: 'Client #'.$tc->client_id }}</div>
                                <div class="dash-top-meta">{{ $tc->total_livraisons }} livraison{{ $tc->total_livraisons > 1 ? 's' : '' }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted my-4">Aucune livraison enregistrée pour le moment.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="dash-card-title mb-0">
                        <i class="material-icons md-receipt_long text-primary"></i>
                        Dernières livraisons
                    </h5>
                    <a href="{{ route('livreur.livraisonValides') }}" class="btn btn-sm btn-light">
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
                                    <th>Date</th>
                                    <th class="text-end">Quantité</th>
                                    <th class="text-center">État</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dernieresLivraisons as $liv)
                                    @php
                                        $etat = $liv->etat_livraison;
                                        if ($liv->accepte == 2 && $etat == 'EN ATTENTE') {
                                            $label = 'À ACCEPTER';
                                            $badge = 'bg-warning text-dark';
                                        } elseif ($liv->accepte == 1 && $etat == 'EN ATTENTE') {
                                            $label = 'EN COURS';
                                            $badge = 'bg-info';
                                        } elseif ($etat == 'LIVREE') {
                                            $label = 'LIVRÉE';
                                            $badge = 'bg-success';
                                        } else {
                                            $label = $etat ?: '—';
                                            $badge = 'bg-light text-dark';
                                        }
                                        $clientNom = trim(($liv->client?->nom ?? '').' '.($liv->client?->prenom ?? '')) ?: '—';
                                        $produitNom = $liv->produit?->nom ?? '';
                                        $unite = $liv->produit?->unite ?? '';
                                    @endphp
                                    <tr>
                                        <td><strong class="text-primary">{{ $liv->commande?->numero ?? '—' }}</strong></td>
                                        <td>{{ $clientNom }}</td>
                                        <td>{{ optional($liv->updated_at)->format('d/m/Y') }}</td>
                                        <td class="text-end fw-bold">
                                            {{ rtrim(rtrim(number_format((float) $liv->qte, 2, ',', ' '), '0'), ',') }}
                                            @if($unite) <small class="text-muted">{{ $unite }}</small> @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $badge }}">{{ $label }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            Aucune livraison récente.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('jsParts')
    <script>
        // Horloge live dans le bandeau de bienvenue
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
