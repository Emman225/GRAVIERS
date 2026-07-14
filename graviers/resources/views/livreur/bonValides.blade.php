@extends('layout.main')
@section('title','Bons validés')

@php
    use Illuminate\Support\Carbon;
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Bons d'enlèvement validés</h2>
                <p class="dash-welcome-subtitle">Historique de vos bons traités et confirmés par le fournisseur.</p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-check_circle"></i>
                    <span>{{ $totalBons }} validé{{ $totalBons > 1 ? 's' : '' }}</span>
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
                    <div class="kpi-card-label">Bons validés</div>
                    <div class="kpi-card-value">{{ $totalBons }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">historique cumulé</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-inventory"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Quantité totale servie</div>
                    <div class="kpi-card-value">{{ rtrim(rtrim(number_format($totalQte, 2, ',', ' '), '0'), ',') }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">tous bons confondus</span></div>
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
                Liste des bons validés
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="bonsValidesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Fournisseur</th>
                            <th>Produit</th>
                            <th class="text-end">Qté servie</th>
                            <th>Véhicule</th>
                            <th>Immatriculation</th>
                            <th class="text-end">Capacité</th>
                            <th>Date livraison</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enlevements as $enlevement)
                            @php $vehicule = optional($enlevement->livraison)->vehicule; @endphp
                            <tr>
                                <td><strong class="text-primary">{{ $enlevement->code_enleve }}</strong></td>
                                <td><strong>{{ $enlevement->fournisseur?->nom_prenoms }}</strong></td>
                                <td>{{ $enlevement->produit?->nom }}</td>
                                <td class="text-end fw-bold">
                                    {{ rtrim(rtrim(number_format((float) ($enlevement->qte_servi ?? $enlevement->qte), 2, ',', ' '), '0'), ',') }}
                                </td>
                                <td>{{ $vehicule?->marque ?: '—' }}</td>
                                <td>@if($vehicule?->immatriculation) <span class="badge bg-light text-dark">{{ $vehicule->immatriculation }}</span> @else — @endif</td>
                                <td class="text-end">{{ $vehicule?->capacite ? $vehicule->capacite.'t' : '—' }}</td>
                                <td>{{ optional($enlevement->livraison)->date_livraison ? Carbon::parse($enlevement->livraison->date_livraison)->format('d/m/Y') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="material-icons md-inventory_2" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucun bon validé pour l'instant.</p>
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
            if ($('#bonsValidesTable tbody tr').length > 1 || $('#bonsValidesTable tbody tr td').length > 1) {
                $('#bonsValidesTable').DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[7, 'desc']],
                });
            }
        });
    </script>
@endsection
