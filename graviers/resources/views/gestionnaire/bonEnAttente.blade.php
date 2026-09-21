@extends('layout.main')
@section('title','Liste des bons en attente')
@section('contenu')
    <div class="screen-overlay"></div>


    {{-- ===== HEADER WELCOME =====
         Le meme bandeau que les autres listes du back-office. --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Bons d'enlèvement <span class="dash-welcome-name">en attente</span> &#9203;
                </h2>
                <p class="dash-welcome-subtitle">
                    Bons émis, en attente de validation &mdash; {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-hourglass_empty"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total</div>
                    <div class="kpi-card-value">{{ $enlevements->count() }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card dash-card mb-4">
                    <div class="card-body">
                        <x-export-buttons table-id="tableBonsAttente" filename="bons-enlevement-en-attente" title="Bons d'enlèvement en attente" />
                        <div class="table-responsive">
                            <table id="tableBonsAttente" class="table dash-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>N° du bon</th>
                                        <th>Client</th>
                                        <th>Nom du Fournisseur</th>
                                        <th>livreur</th>
                                        <th>Produit</th>
                                        <th class="text-center">Quantité</th>
                                        <th class="text-center">Agent ayant créé l'enlèvement</th>
                                        <th>Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @forelse ($enlevements as $enlevement)
                                        <tr>
                                            {{-- N° public du bon ; le code d'enlèvement n'est montré qu'au client et au fournisseur (14/18). --}}
                                            <td> {{ $enlevement->id }} </td>
                                            {{-- Client : un @dd() de debug s'affichait ici (dump brut à la place
                                                 du tableau) dès qu'une livraison ou un client était supprimé. --}}
                                            <td>{{ ($enlevement->livraison?->client?->display_name ?? '') ?: '-' }}</td>
                                            <td><b> {{ $enlevement->fournisseur?->user?->nom_prenoms }} </b></td>
                                            {{-- Livreur : nullable (enlèvement créé sans livraison affectée par
                                                 « traitement sans livraison » -> livreur_id null). --}}
                                            <td>
                                                <b>
                                                    @if ($enlevement->livraison?->livre_par == 1)
                                                        {{ $enlevement->livraison?->livreur?->user?->nom_prenoms ?? '-' }}
                                                    @else
                                                        {{ $enlevement->livraison?->clientLivreur?->nom ?? '-' }}
                                                    @endif
                                                </b>
                                            </td>
                                            <td>{{ $enlevement->produit?->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->livraison?->gestionnaire?->nom_prenoms }}</td>
                                            <td>{{ $enlevement->created_at->format('d-m-Y')}} @if($enlevement->fournisseur_validation != null) <span class="text-success"> (Fournisseur) @elseif($enlevement->livreur_validation != null) <span class="text-success"> (Livreur) </span> @endif</span> </td>
                                            <td class="text-nowrap text-center">
                                                <a href="{{ route('show.bonApercu', $enlevement) }}"
                                                   class="btn btn-sm btn-primary" target="_blank"
                                                   title="Aperçu du bon">
                                                    <i class="material-icons md-visibility"></i>
                                                </a>
                                                <a href="{{ route('show.bonTelecharger', $enlevement) }}"
                                                   class="btn btn-sm btn-info"
                                                   title="Télécharger le PDF">
                                                    <i class="material-icons md-cloud_download"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        {{-- LE TABLEAU RESTE AFFICHÉ, MÊME VIDE.
                                             Il était enfermé dans un « @if » avec les boutons
                                             d'export : sur une liste vide, l'écran ne montrait
                                             qu'une phrase, sans tableau ni boutons — à la
                                             différence de toutes les autres listes. --}}
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Aucun bon pour l'instant.
                                            </td>
                                        </tr>
                                    @endforelse

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>
                    <!-- card-body end// -->
            </div>
            <!-- card end// -->
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
            // CIBLER LE TABLEAU PAR SON IDENTIFIANT, ET NON PAR SA CLASSE.
            //
            // « $('.table') » atteint TOUS les tableaux de la page — y compris
            // celui d'une fenêtre modale ou d'un reçu — et les transforme tous
            // en DataTable. Et sans garde-fou, une liste vide dont la seule
            // ligne porte un « colspan » fait lever « Requested unknown
            // parameter » : le tableau reste alors brut, sans recherche, sans
            // tri ni pagination, à la différence de tous les autres écrans.
            var $table = $('#tableBonsAttente');

            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [],
                });
            }
        });
    </script>
@endsection
