@extends('layout.main')
@section('title','Tableau de bord')

@php
    use Carbon\Carbon;
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
    $userName = Auth::user()->nom_prenoms ?? 'Utilisateur';
    $firstName = explode(' ', $userName)[0];
    $evolutionGain = $evolutionGain ?? 0;
    $isPositive = $evolutionGain >= 0;
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
                    Voici un aperçu de votre activité aujourd'hui — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
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
                    <div class="kpi-card-label">Revenu total</div>
                    <div class="kpi-card-value">{{ number_format($revenu, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
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

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-local_shipping"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Commandes actives</div>
                    <div class="kpi-card-value">{{ $totalCommandes }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-pill kpi-pill-soft">{{ $commandesJour }} aujourd'hui</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-qr_code"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Catalogue produits</div>
                    <div class="kpi-card-value">{{ $produit }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">en {{ $categorie }} catégories</span>
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
                        <span class="kpi-card-meta-text">{{ Carbon::now()->locale('fr')->isoFormat('MMMM YYYY') }}</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== STATS COMMANDES & COMPTES ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-pie_chart text-primary"></i>
                        Répartition des commandes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-warning">
                                <div class="stat-mini-icon"><i class="material-icons md-pending"></i></div>
                                <div>
                                    <div class="stat-mini-label">En attente</div>
                                    <div class="stat-mini-value">{{ $commandesEnAttente }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-info">
                                <div class="stat-mini-icon"><i class="material-icons md-autorenew"></i></div>
                                <div>
                                    <div class="stat-mini-label">En traitement</div>
                                    <div class="stat-mini-value">{{ $commandesEnTraitement }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-mini stat-mini-success">
                                <div class="stat-mini-icon"><i class="material-icons md-check_circle"></i></div>
                                <div>
                                    <div class="stat-mini-label">Terminées (mois)</div>
                                    <div class="stat-mini-value">{{ $commandesTerminees }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $totalEtats = max(1, $commandesEnAttente + $commandesEnTraitement + $commandesTerminees);
                        $pctAttente   = ($commandesEnAttente / $totalEtats) * 100;
                        $pctTraitement = ($commandesEnTraitement / $totalEtats) * 100;
                        $pctTerminees = ($commandesTerminees / $totalEtats) * 100;
                    @endphp
                    <div class="dash-stack-bar mt-4">
                        <div class="dash-stack-bar-segment bg-warning" style="width: {{ $pctAttente }}%" title="En attente"></div>
                        <div class="dash-stack-bar-segment bg-info" style="width: {{ $pctTraitement }}%" title="En traitement"></div>
                        <div class="dash-stack-bar-segment bg-success" style="width: {{ $pctTerminees }}%" title="Terminées"></div>
                    </div>
                    <div class="dash-stack-legend mt-2">
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-warning"></span>{{ number_format($pctAttente, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-info"></span>{{ number_format($pctTraitement, 1) }}%</span>
                        <span class="dash-legend-item"><span class="dash-legend-dot bg-success"></span>{{ number_format($pctTerminees, 1) }}%</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-people text-primary"></i>
                        Communauté
                    </h5>
                </div>
                <div class="card-body">
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-primary"><i class="material-icons md-group"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Clients actifs</div>
                            <div class="dash-counter-value">{{ $totalClients }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-local_shipping"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Livreurs</div>
                            <div class="dash-counter-value">{{ $totalLivreurs }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-store"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Fournisseurs</div>
                            <div class="dash-counter-value">{{ $totalFournisseurs }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-success"><i class="material-icons md-handshake"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Apporteurs d'affaires</div>
                            <div class="dash-counter-value">{{ $totalApporteurs }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TOP PRODUITS + DERNIÈRES COMMANDES ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        Top 5 produits
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topProduits as $idx => $tp)
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ $tp->produit?->nom ?? 'Produit '.$tp->produit_id }}</div>
                                <div class="dash-top-meta">{{ rtrim(rtrim(number_format($tp->total_qte, 2, ',', ' '), '0'), ',') }} unités</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted my-4">Aucune donnée de commandes pour le moment.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="dash-card-title mb-0">
                        <i class="material-icons md-receipt_long text-primary"></i>
                        Dernières commandes
                    </h5>
                    <a href="{{ route('orders.list') }}" class="btn btn-sm btn-light">
                        Voir tout <i class="material-icons md-arrow_forward"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0" id="lastOrdersTable">
                            <thead>
                                <tr>
                                    <th>N° commande</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Statut</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($commandes as $commande)
                                    @php
                                        $etatBadge = match($commande->etat_commande) {
                                            'EN ATTENTE'    => 'bg-warning text-dark',
                                            'EN TRAITEMENT' => 'bg-info',
                                            'TERMINEE'      => 'bg-success',
                                            default         => 'bg-light text-dark',
                                        };
                                    @endphp
                                    <tr>
                                        <td><strong class="text-primary">{{ $commande->numero }}</strong></td>
                                        <td>{{ $commande->client?->nom.' '.$commande->client?->prenom }}</td>
                                        <td>{{ $commande->created_at?->format('d/m/Y') }}</td>
                                        <td class="text-end fw-bold">{{ number_format($commande->montant_total, 0, ',', ' ') }} FCFA</td>
                                        <td class="text-center">
                                            <span class="badge {{ $etatBadge }}">{{ $commande->etat_commande }}</span>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('orders.details', $commande->numero) }}" class="btn btn-sm btn-light">
                                                <i class="material-icons md-visibility"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Aucune commande récente.
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
