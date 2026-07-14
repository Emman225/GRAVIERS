@extends('layout.main')
@section('title','Tableau de bord')

@php
    use Carbon\Carbon;
    $hour = (int) now()->format('H');
    $greeting = $hour < 6 ? 'Bonne nuit' : ($hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bon après-midi' : 'Bonsoir'));
    $userName  = Auth::user()->nom_prenoms ?? ($fournisseur->nom_prenoms ?? 'Fournisseur');
    $firstName = explode(' ', $userName)[0];
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    {{ $greeting }}, <span class="dash-welcome-name">{{ $firstName }}</span> 👋
                </h2>
                <p class="dash-welcome-subtitle">
                    Aperçu de votre activité fournisseur — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
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

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-monetization_on"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Solde disponible</div>
                    <div class="kpi-card-value">{{ number_format((float) $fournisseur->solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <a href="{{ route('sellers.demandeDepaieFournisseur') }}" class="kpi-pill kpi-pill-soft">
                            <i class="material-icons md-confirmation_number"></i> Demander un paiement
                        </a>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Bons validés</div>
                    <div class="kpi-card-value">{{ $bonsValides }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ $bonsCeMois }} ce mois</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-pending_actions"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Bons en attente</div>
                    <div class="kpi-card-value">{{ $bonsEnAttente }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">à traiter</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-qr_code"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Mon catalogue</div>
                    <div class="kpi-card-value">{{ $totalProduits }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ rtrim(rtrim(number_format($totalQuantites, 2, ',', ' '), '0'), ',') }} en stock</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== ACTIVITÉ + TOP PRODUITS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-emoji_events text-warning"></i>
                        Top 5 produits livrés
                    </h5>
                </div>
                <div class="card-body">
                    @forelse ($topProduits as $idx => $tp)
                        <div class="dash-top-item">
                            <div class="dash-top-rank dash-top-rank-{{ $idx + 1 }}">{{ $idx + 1 }}</div>
                            <div class="flex-grow-1">
                                <div class="dash-top-name">{{ $tp->produit?->nom ?? '—' }}</div>
                                <div class="dash-top-meta">{{ $tp->nb_bons }} bon{{ $tp->nb_bons > 1 ? 's' : '' }}</div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-primary">{{ rtrim(rtrim(number_format($tp->total_qte, 2, ',', ' '), '0'), ',') }}</div>
                                <small class="text-muted">qté servie</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted my-4">Aucun bon d'enlèvement pour le moment.</p>
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
                        <div class="dash-counter-icon dash-counter-icon-info"><i class="material-icons md-today"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Aujourd'hui</div>
                            <div class="dash-counter-value">{{ $bonsAujourdhui }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-primary"><i class="material-icons md-calendar_month"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Ce mois</div>
                            <div class="dash-counter-value">{{ $bonsCeMois }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-success"><i class="material-icons md-check_circle"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Validés</div>
                            <div class="dash-counter-value">{{ $bonsValides }}</div>
                        </div>
                    </div>
                    <div class="dash-counter">
                        <div class="dash-counter-icon dash-counter-icon-warning"><i class="material-icons md-receipt_long"></i></div>
                        <div class="flex-grow-1">
                            <div class="dash-counter-label">Bons cumulés</div>
                            <div class="dash-counter-value">{{ $totalBons }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== DERNIÈRES LIVRAISONS ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-receipt_long text-primary"></i>
                Dernières livraisons
            </h5>
            <a href="{{ route('sellers.accepte') }}" class="btn btn-sm btn-light">
                Voir tout <i class="material-icons md-arrow_forward"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Produit</th>
                            <th class="text-end">Quantité</th>
                            <th>Véhicule</th>
                            <th>Date</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dernieresLivraisons as $bon)
                            @php
                                $estValide = !is_null($bon->fournisseur_validation);
                                $vehicule  = optional($bon->livraison)->vehicule;
                            @endphp
                            <tr>
                                <td><strong class="text-primary">{{ $bon->code_enleve }}</strong></td>
                                <td>{{ $bon->produit?->nom ?? '—' }}</td>
                                <td class="text-end fw-bold">
                                    {{ rtrim(rtrim(number_format((float) ($bon->qte_servi ?? $bon->qte), 2, ',', ' '), '0'), ',') }}
                                </td>
                                <td>
                                    @if($vehicule?->immatriculation)
                                        <span class="badge bg-light text-dark">{{ $vehicule->immatriculation }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td><small>{{ Carbon::parse($bon->created_at)->format('d/m/Y H:i') }}</small></td>
                                <td class="text-center">
                                    @if($estValide)
                                        <span class="badge bg-success">Validé</span>
                                    @else
                                        <span class="badge bg-warning text-dark">En attente</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    Aucun bon récent.
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
