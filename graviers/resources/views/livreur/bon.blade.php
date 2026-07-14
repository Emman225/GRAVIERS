@extends('layout.main')
@section('title','Bons en attente')

@php
    use Illuminate\Support\Carbon;
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Bons d'enlèvement en attente</h2>
                <p class="dash-welcome-subtitle">Acceptez ou refusez les nouveaux bons qui vous sont affectés.</p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-receipt_long"></i>
                    <span>{{ $totalBons }} bon{{ $totalBons > 1 ? 's' : '' }}</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-receipt_long"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total bons</div>
                    <div class="kpi-card-value">{{ $totalBons }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">à traiter</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-pending_actions"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">À accepter</div>
                    <div class="kpi-card-value">{{ $aAccepter }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">en attente de réponse</span></div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Acceptés</div>
                    <div class="kpi-card-value">{{ $accepted }}</div>
                    <div class="kpi-card-meta"><span class="kpi-card-meta-text">en cours de traitement</span></div>
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
                Liste des bons en attente
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="bonsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Fournisseur</th>
                            <th>Produit</th>
                            <th class="text-end">Qté</th>
                            <th>Véhicule</th>
                            <th>Immatriculation</th>
                            <th class="text-end">Capacité</th>
                            <th>Reçu le</th>
                            <th>Livraison prévue</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enlevements as $enlevement)
                            @php
                                $accepte = optional($enlevement->livraison)->accepte;
                                $vehicule = optional($enlevement->livraison)->vehicule;
                            @endphp
                            <tr>
                                <td>
                                    @if($accepte == 1)
                                        <a href="{{ route('livreur.bon.detail', $enlevement) }}" class="fw-bold text-primary">{{ $enlevement->code_enleve }}</a>
                                    @else
                                        <span class="badge bg-secondary">Acceptez pour voir le code</span>
                                    @endif
                                </td>
                                <td><strong>{{ $enlevement->fournisseur?->nom_prenoms.' '.$enlevement->fournisseur?->prenom }}</strong></td>
                                <td>{{ $enlevement->produit?->nom }}</td>
                                <td class="text-end fw-bold">{{ rtrim(rtrim(number_format((float) $enlevement->qte, 2, ',', ' '), '0'), ',') }}</td>
                                <td>{{ $vehicule?->marque ?: '—' }}</td>
                                <td>@if($vehicule?->immatriculation) <span class="badge bg-light text-dark">{{ $vehicule->immatriculation }}</span> @else — @endif</td>
                                <td class="text-end">{{ $vehicule?->capacite ? $vehicule->capacite.'t' : '—' }}</td>
                                <td><small>{{ Carbon::parse($enlevement->created_at)->format('d/m/Y H:i') }}</small></td>
                                <td><small>{{ optional($enlevement->livraison)->date_livraison ? Carbon::parse($enlevement->livraison->date_livraison)->format('d/m/Y') : '—' }}</small></td>
                                <td class="text-center">
                                    @if($accepte == 2)
                                        <a href="{{ route('livreur.actionBonEnlevement', ['enlevement' => $enlevement, 'action' => 'accepter']) }}" class="btn btn-sm btn-success">
                                            <i class="material-icons md-check"></i> Accepter
                                        </a>
                                        <a href="{{ route('livreur.actionBonEnlevement', ['enlevement' => $enlevement, 'action' => 'refuser']) }}" class="btn btn-sm btn-danger">
                                            <i class="material-icons md-close"></i> Refuser
                                        </a>
                                    @elseif($accepte == 1)
                                        <span class="badge bg-success-light text-success">Accepté</span>
                                    @else
                                        <span class="badge bg-light text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-5">
                                    <i class="material-icons md-inbox" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucun bon en attente pour le moment.</p>
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
            if ($('#bonsTable tbody tr').length > 1 || ($('#bonsTable tbody tr td').length > 1)) {
                $('#bonsTable').DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [],
                });
            }
        });
    </script>
@endsection
