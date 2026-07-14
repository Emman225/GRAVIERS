@extends('layout.main')
@section('title','Livraisons effectuées')

@php
    use Illuminate\Support\Carbon;
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== FLASH ===== --}}
    @if (session('errorCode'))
        <div class="alert alert-danger d-flex align-items-center">
            <i class="material-icons md-error me-2"></i>
            <div>{{ session('errorCode') }}</div>
        </div>
    @endif
    @if (session('codeExiste'))
        <div class="alert alert-info d-flex align-items-center">
            <i class="material-icons md-info me-2"></i>
            <div>{{ session('codeExiste') }}</div>
        </div>
    @endif
    @if (session('successCode'))
        <div class="alert alert-success d-flex align-items-center" id="notify">
            <i class="material-icons md-check_circle me-2"></i>
            <div>{{ session('successCode') }}</div>
        </div>
    @endif

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Livraisons effectuées</h2>
                <p class="dash-welcome-subtitle">Historique de toutes les livraisons que vous avez menées à bien.</p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-local_shipping"></i>
                    <span>{{ $totalLivraisons }} livraison{{ $totalLivraisons > 1 ? 's' : '' }}</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total livré</div>
                    <div class="kpi-card-value">{{ $totalLivraisons }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">depuis le début</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-date_range"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Ce mois-ci</div>
                    <div class="kpi-card-value">{{ $livraisonsCeMois }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">{{ \Carbon\Carbon::now()->locale('fr')->isoFormat('MMMM YYYY') }}</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLE ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-list_alt text-primary"></i>
                Liste des livraisons
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="livraisonsTable">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Client</th>
                            <th>Produit</th>
                            <th class="text-end">Quantité</th>
                            <th>Véhicule</th>
                            <th>Immatriculation</th>
                            <th class="text-end">Capacité</th>
                            <th>Adresse</th>
                            <th>Validée le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($livraisons as $livraison)
                            @php
                                // Produit selon la provenance : enlèvement (COMMANDE), detail_location
                                // (LOCATION : detail_commande_id pointe un detail_location), ou demande.
                                if ($livraison->enlevement) {
                                    $produitNom = $livraison->enlevement->produit?->nom;
                                } elseif ($livraison->provenance == 'LOCATION') {
                                    $produitNom = \App\Models\DetailLocation::find($livraison->detail_commande_id)?->produit?->nom;
                                } else {
                                    $produitNom = $livraison->detailLivraison?->nom_produit;
                                }
                                $qte = $livraison->enlevement?->qte_servi ?? $livraison->qte;
                            @endphp
                            <tr>
                                <td><strong class="text-primary">{{ $livraison->numero }}</strong></td>
                                <td><strong>{{ $livraison->client->nom.' '.$livraison->client->prenom }}</strong></td>
                                <td>{{ $produitNom ?: '—' }}</td>
                                <td class="text-end fw-bold">{{ rtrim(rtrim(number_format((float) $qte, 2, ',', ' '), '0'), ',') }}</td>
                                <td>{{ $livraison->vehicule?->marque ?: '—' }}</td>
                                <td>@if($livraison->vehicule?->immatriculation) <span class="badge bg-light text-dark">{{ $livraison->vehicule->immatriculation }}</span> @else — @endif</td>
                                <td class="text-end">{{ $livraison->vehicule?->capacite ? $livraison->vehicule->capacite.'t' : '—' }}</td>
                                <td><small>{{ $livraison->AdresseLivraison->affichage ?? '—' }}</small></td>
                                <td>
                                    <small>
                                        {{ Carbon::parse($livraison->updated_at)->format('d/m/Y') }}
                                        <span class="text-muted">{{ Carbon::parse($livraison->updated_at)->format('H:i') }}</span>
                                    </small>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="material-icons md-local_shipping" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucune livraison effectuée pour l'instant.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            if ($('#livraisonsTable tbody tr').length > 1 || $('#livraisonsTable tbody tr td').length > 1) {
                $('#livraisonsTable').DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[8, 'desc']],
                });
            }
        });
    </script>
    @notifyJs
@endsection
