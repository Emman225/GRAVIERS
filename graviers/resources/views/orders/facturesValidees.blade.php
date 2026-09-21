@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Factures validées (FNE)')

@section('contenu')
    <section class="content-main">
    {{-- ===== HEADER WELCOME =====
             Le meme bandeau que les autres listes du back-office. --}}
        <div class="dash-welcome mb-4">
            <div class="dash-welcome-content">
                <div>
                    <h2 class="dash-welcome-title">
                        Factures <span class="dash-welcome-name">certifiées</span> &#9989;
                    </h2>
                    <p class="dash-welcome-subtitle">
                        Certifiées par la DGI (FNE) &mdash; {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </p>
                </div>
            </div>
            <div class="dash-welcome-decoration"></div>
        </div>

    <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="kpi-card kpi-card-success">
                    <div class="kpi-card-icon"><i class="material-icons md-verified"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">Total</div>
                        <div class="kpi-card-value">{{ $factures->count() }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </div>
        </div>

        <div class="card dash-card">
            <div class="card-body">

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                {{-- LE TABLEAU RESTE AFFICHÉ, MÊME VIDE.
                     Il était enfermé dans un « @if » avec les boutons d'export :
                     sur une liste vide, l'écran ne montrait qu'une phrase, sans
                     tableau ni boutons — à la différence des autres listes. --}}
                    <x-export-buttons table-id="tableFacturesValidees" filename="factures-validees" title="Factures validées (FNE)" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0" id="tableFacturesValidees">
                            <thead>
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Réf. FNE (DGI)</th>
                                    <th>Commande / Location</th>
                                    <th>Client</th>
                                    <th>Date certification</th>
                                    <th class="text-end">Montant</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($factures as $facture)
                                    <tr>
                                        <td>{{ Help::formatNumeroFacture($facture->numero) }}</td>
                                        <td>
                                            <span style="font-family: monospace; font-size: 0.9em;">
                                                {{ $facture->fne_reference }}
                                            </span>
                                            @if($facture->estUnAvoir())
                                                <span class="badge bg-danger ms-1">Avoir</span>
                                                @if($facture->origine)
                                                    <br><small class="text-muted">sur la facture {{ Help::formatNumeroFacture($facture->origine->numero) }}</small>
                                                @endif
                                            @else
                                                <span class="badge bg-success ms-1">Certifiée</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{-- Le transport en premier : sur une facture de
                                                 transport, $facture->commande renverrait la commande
                                                 qui porte par hasard le meme identifiant. --}}
                                            @if($facture->service === 'LIVRAISON')
                                                <span class="badge bg-secondary">Transport</span>
                                                {{ $facture->demandeLivraison?->numero ?? '—' }}
                                            @elseif($facture->service === 'LOCATION')
                                                @if($facture->location)
                                                    <span class="badge bg-info text-dark">Location</span> {{ $facture->location?->numero }}
                                                @else — @endif
                                            @elseif($facture->commande)
                                                <a href="{{ route('orders.BECommande', ['numero' => $facture->commande?->numero]) }}">
                                                    {{ $facture->commande?->numero }}
                                                </a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($facture->client)
                                                {{ $facture->client?->display_name }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @if($facture->fne_certified_at)
                                                {{ Carbon::parse($facture->fne_certified_at)->format('d/m/Y H:i:s') }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            {{ number_format($facture->montant, 0, ',', ' ') }} fcfa
                                        </td>
                                        <td class="text-center">
                                            @if($facture->fne_token)
                                                <a href="{{ $facture->fne_token }}" target="_blank"
                                                    class="btn btn-outline-success btn-sm" title="Vérifier sur la plateforme FNE"><i class="fas fa-qrcode"></i></a>
                                            @endif
                                            @if($facture->estUnAvoir())
                                                <a href="{{ route('orders.factureAvoir', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir l'avoir"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('orders.factureAvoir', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-info btn-sm" title="Télécharger l'avoir"><i class="fas fa-download"></i></a>
                                            @else
                                                {{-- Facture d'avoir sur cette facture certifiée (lot 92). --}}
                                                <a href="{{ route('orders.nouvelAvoir', $facture) }}"
                                                    data-confirm-msg="Établir une facture d'avoir sur la facture {{ Help::formatNumeroFacture($facture->numero) }} ? Vous choisirez ensuite les articles et les quantités à créditer ; l'avoir sera certifié par la DGI."
                                                    class="btn btn-outline-danger btn-sm" title="Établir une facture d'avoir"><i class="fas fa-undo"></i></a>
                                            @endif
                                            @if($facture->estUnAvoir())
                                            @elseif($facture->service === 'LIVRAISON')
                                                <a href="{{ route('orders.factureLivraison', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('orders.factureLivraison', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-info btn-sm" title="Télécharger la facture">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @elseif($facture->service === 'LOCATION' && $facture->location)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-info btn-sm" title="Télécharger la facture">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @elseif($facture->service !== 'LOCATION' && $facture->commande)
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => 1]) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir"><i class="fas fa-eye"></i></a>
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => 1]) }}"
                                                    class="btn btn-info btn-sm" title="Télécharger la facture">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Aucune facture certifiée par la DGI pour le moment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
            </div>
        </div>
    </section>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            // Garde-fou : une liste vide dont la seule ligne porte un
            // « colspan » fait lever « Requested unknown parameter », et le
            // tableau reste alors brut, sans recherche ni pagination.
            var $table = $('#tableFacturesValidees');

            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[4, 'desc']],
                });
            }
        });
    </script>
@endsection
