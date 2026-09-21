@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Factures non validées')

@section('contenu')
    <section class="content-main">
    {{-- ===== HEADER WELCOME =====
             Le meme bandeau que les autres listes du back-office. --}}
        <div class="dash-welcome mb-4">
            <div class="dash-welcome-content">
                <div>
                    <h2 class="dash-welcome-title">
                        Factures <span class="dash-welcome-name">non validées</span> &#8987;
                    </h2>
                    <p class="dash-welcome-subtitle">
                        En attente de certification par la DGI &mdash; {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </p>
                </div>
            </div>
            <div class="dash-welcome-decoration"></div>
        </div>

    <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="kpi-card kpi-card-warning">
                    <div class="kpi-card-icon"><i class="material-icons md-pending"></i></div>
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
                @if(session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                {{-- LE TABLEAU RESTE AFFICHÉ, MÊME VIDE.
                     Il était enfermé dans un « @if » avec les boutons d'export :
                     sur une liste vide, l'écran ne montrait qu'une phrase, sans
                     tableau ni boutons — à la différence des autres listes. --}}
                    <x-export-buttons table-id="tableFacturesNonValidees" filename="factures-non-validees" title="Factures non validées" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0" id="tableFacturesNonValidees">
                            <thead>
                                <tr>
                                    <th>N° Facture</th>
                                    <th>Commande / Location</th>
                                    <th>Client</th>
                                    <th>Date</th>
                                    <th class="text-end">Montant</th>
                                    <th>Statut FNE</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($factures as $facture)
                                    <tr>
                                        <td>{{ Help::formatNumeroFacture($facture->numero) }}</td>
                                        <td>
                                            {{-- LE TRANSPORT PASSE EN PREMIER.
                                                 `service_id` designe l affaire, quelle qu elle soit :
                                                 sur une facture de transport, $facture->commande
                                                 renverrait la commande qui porte par hasard le meme
                                                 identifiant — et son numero, et son lien. --}}
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
                                        <td>{{ Carbon::parse($facture->created_at)->format('d/m/Y H:i:s') }}</td>
                                        <td class="text-end">
                                            {{ number_format($facture->montant, 0, ',', ' ') }} fcfa
                                        </td>
                                        <td>
                                            @switch($facture->fne_status)
                                                @case('failed')
                                                    <span class="badge bg-danger" title="{{ $facture->fne_error_message }}">
                                                        Échec FNE
                                                    </span>
                                                    @break
                                                @case('disabled')
                                                    <span class="badge bg-secondary" title="Module FNE désactivé">
                                                        Non certifiée
                                                    </span>
                                                    @break
                                                @default
                                                    <span class="badge bg-warning text-dark">En attente</span>
                                            @endswitch
                                        </td>
                                        <td class="text-nowrap text-center">
                                            <form class="d-inline" action="{{ route('orders.validerFactureFne', $facture) }}"
                                                method="post" style="display:inline-block;">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm"
                                                    onclick="return confirm('Confirmer la validation de cette facture auprès de la DGI (FNE) ?');" title="Valider"><i class="material-icons md-check align-middle"></i></button>
                                            </form>

                                            @if($facture->service === 'LIVRAISON')
                                                <a href="{{ route('orders.factureLivraison', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir la facture de transport">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('orders.factureLivraison', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-outline-secondary btn-sm" title="Télécharger">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @elseif($facture->service === 'LOCATION' && $facture->location)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'voir']) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir la facture de location">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                {{-- Le telechargement n existait que sur les factures DEJA
                                                     certifiees. Or une facture en attente se transmet aussi :
                                                     au comptable, au client qui la reclame, a la DGI en cas
                                                     de question. --}}
                                                <a href="{{ route('orders.factureLocation', ['facture' => $facture->id, 'action' => 'telecharger']) }}"
                                                    class="btn btn-outline-secondary btn-sm" title="Télécharger">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @elseif($facture->service !== 'LOCATION' && $facture->commande)
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => 1]) }}"
                                                    class="btn btn-primary btn-sm" target="_blank" title="Voir la facture de vente">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('show.actionFacture', ['commande' => $facture->commande, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => 1]) }}"
                                                    class="btn btn-outline-secondary btn-sm" title="Télécharger">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Aucune facture en attente de validation FNE pour le moment.
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
            var $table = $('#tableFacturesNonValidees');

            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[3, 'desc']],
                });
            }
        });
    </script>
@endsection
