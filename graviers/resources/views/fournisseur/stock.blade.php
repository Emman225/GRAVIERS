@extends('layout.main')
@section('title','Mon stock')

@php
    $produits = $fournisseur->produits;
    $totalProduits = $produits->count();
    $totalQuantite = (float) $produits->sum(fn($p) => (float) $p->pivot->qte);
    $totalValeur   = (float) $produits->sum(fn($p) => (float) $p->pivot->prix * (float) $p->pivot->qte);
    $produitsAlerte = $produits->filter(fn($p) => (float) $p->pivot->qte <= (float) $p->pivot->seuil_alert)->count();
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Mon stock</h2>
                <p class="dash-welcome-subtitle">Les produits que vous fournissez avec leur prix et leur quantité disponible.</p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-inventory"></i>
                    <span>{{ $totalProduits }} produit{{ $totalProduits > 1 ? 's' : '' }}</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-qr_code"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Produits</div>
                    <div class="kpi-card-value">{{ $totalProduits }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">référencés dans votre catalogue</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-inventory_2"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Quantité totale</div>
                    <div class="kpi-card-value">{{ rtrim(rtrim(number_format($totalQuantite, 2, ',', ' '), '0'), ',') }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">tous produits confondus</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-savings"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Valeur du stock</div>
                    <div class="kpi-card-value">{{ number_format($totalValeur, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">prix × quantité</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-warning"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Alerte stock</div>
                    <div class="kpi-card-value">{{ $produitsAlerte }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">produit{{ $produitsAlerte > 1 ? 's' : '' }} sous seuil</span></div>
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
                Liste des produits
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="stockTable" style="width:100%">
                    <thead class="table-light">
                        <tr>
                            <th>Produit</th>
                            <th class="text-end">Prix unitaire</th>
                            <th class="text-end">Quantité</th>
                            <th class="text-end">Seuil d'alerte</th>
                            <th class="text-end">Valeur</th>
                            <th class="text-center">État</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produits as $produit)
                            @php
                                $qte = (float) $produit->pivot->qte;
                                $seuil = (float) $produit->pivot->seuil_alert;
                                $prix = (float) $produit->pivot->prix;
                                $valeur = $prix * $qte;
                                $highlight = session('produit_id') == $produit->id;
                                $enAlerte = $qte <= $seuil;
                            @endphp
                            <tr @if($highlight) class="table-warning" @endif>
                                <td><strong>{{ $produit->nom }}</strong></td>
                                <td class="text-end fw-bold">{{ number_format($prix, 0, ',', ' ') }} <small class="text-muted">FCFA</small></td>
                                <td class="text-end">
                                    <span class="fw-bold {{ $enAlerte ? 'text-danger' : '' }}">{{ rtrim(rtrim(number_format($qte, 2, ',', ' '), '0'), ',') }}</span>
                                </td>
                                <td class="text-end text-muted">{{ rtrim(rtrim(number_format($seuil, 2, ',', ' '), '0'), ',') }}</td>
                                <td class="text-end fw-bold text-primary">{{ number_format($valeur, 0, ',', ' ') }} <small class="text-muted">FCFA</small></td>
                                <td class="text-center">
                                    @if($enAlerte)
                                        <span class="badge bg-danger"><i class="material-icons md-warning" style="font-size:14px;vertical-align:middle"></i> Stock bas</span>
                                    @else
                                        <span class="badge bg-success">Disponible</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('sellers.edit', $produit->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="material-icons md-edit"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="material-icons md-inventory_2" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucun produit dans votre catalogue.</p>
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
<script>
    $(function () {
        if ($('#stockTable tbody tr').length > 1 || $('#stockTable tbody tr td').length > 1) {
            $('#stockTable').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [[0, 'asc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'Tous']],
                columnDefs: [
                    { targets: '_all', defaultContent: '-' }, // évite l'erreur "unknown parameter" sur table vide
                    { orderable: false, targets: -1 } // colonne Action non triable
                ],
            });
        }
    });
</script>
@endsection
