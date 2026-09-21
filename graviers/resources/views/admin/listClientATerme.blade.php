@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des clients')

@section('contenu')

    @include('client._decisionsEnAttente', ['decisions' => $decisions ?? collect()])

    <div class="content-header">
        <h2 class="content-title">Liste des Clients à terme - </h2>

    </div>

    {{-- Retours des actions de cette page.

         Les clés « success », « error », « warning » et « info » ne sont PAS
         lisibles ici : le projet utilise Flasher, configuré avec flash_bag
         activé (config/flasher.php), qui capte ces quatre clés avant la vue et
         les rejoue en notification flottante. Les tester dans un @if ne produit
         donc jamais rien — c'est le cas dans tout le back-office.

         L'avertissement sur le plafond emprunte pour cette raison une clé qui
         lui est propre : trop long et trop lourd de conséquence pour une bulle
         qui s'efface au bout de quelques secondes, il doit rester sous les yeux
         du gestionnaire jusqu'à ce qu'il change de page. --}}
    @if (session('avertissement_plafond'))
        <div class="alert alert-warning">
            <i class="material-icons md-warning align-middle"></i>
            {{ session('avertissement_plafond') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    @if (session('locked'))
                        <div class="alert alert-success" id="notify">
                            {{ session('locked') }}
                        </div>
                    @endif
                    @if (session('unlocked'))
                        <div class="alert alert-success" id="notify">
                            {{ session('unlocked') }}
                        </div>
                    @endif
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="liste-clients-a-terme" title="Liste des clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Code client</th>
                            <th class="text-center">Raison sociale / Nom</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Adresse / Chantier</th>
                            <th class="text-end">Plafond crédit</th>
                            <th class="text-end">Avance disponible</th>
                            <th class="text-center">Délai paiement (j)</th>
                            <th class="text-center">Notes</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center width-10%">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $c)
                        @php
                            $codeClient = 'CLI-' . str_pad($c->id, 3, '0', STR_PAD_LEFT);
                            $typeBadge  = match(strtoupper((string) $c->type_client)) {
                                'ENTREPRISE' => 'Pro',
                                'PARTICULIER' => 'Particulier',
                                default       => $c->type_client,
                            };
                        @endphp
                        <tr>
                            <td class="text-center">
                                <strong>{{ $codeClient }}</strong>
                            </td>
                            <td>{{ trim($c->display_name) }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark">{{ $typeBadge }}</span>
                            </td>
                            <td class="text-center">{{ $c->nom }}</td>
                            <td class="text-center">
                                @if ($c->contact1)<div>{{ $c->contact1 }}</div>@endif
                                @if ($c->contact2)<div class="text-muted small">{{ $c->contact2 }}</div>@endif
                            </td>
                            <td class="text-center">{{ $c->user?->email ?? $c->email }}</td>
                            <td>{{ $c->user?->adresse ?? '-' }}</td>
                            <td class="text-end">
                                {{ $c->plafond_credit ? Help::formatNombre($c->plafond_credit, true) : '-' }}
                            </td>
                            <td class="text-end">{{ ($soldesAvance[$c->id] ?? 0) >= 1 ? Help::formatNombre($soldesAvance[$c->id], true) : '-' }}</td>
                            <td class="text-center">{{ $c->delai_paiement ?? '-' }}</td>
                            <td>{{ $c->notes ?? '-' }}</td>
                            <td class="text-center">
                                @if ($c->user && $c->user?->statut == 1)
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-danger">Bloqué</span>
                                @endif

                                {{-- Un client dont le statut à terme a été retiré reste
                                     dans cette liste : sans cela, le bouton qui le lui
                                     rend deviendrait introuvable. --}}
                                @if ((int) $c->client_a_terme !== 1)
                                    <br><span class="badge bg-secondary mt-1">Statut à terme retiré</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions</a>
                                    <div class="dropdown-menu">

                                        {{-- Même protection que la liste des clients ordinaires : sans
                                             compte utilisateur, route() faisait tomber toute la page. --}}
                                        @if($c->user)
                                            <a class="dropdown-item" href="{{route('show.clientDetailCommande',$c->user)}}">Commandes</a>
                                        @endif
                                        {{-- Clients à terme : leur encaissement se fait sur « Créance Paiements »
                                             (l'écran Encaissements Agence ne liste que les clients ordinaires). --}}
                                        <a class="dropdown-item" href="{{route('show.creancesTerme.paiements')}}">Faire un paiement </a>
                                        {{-- Le plafond n'était inscrit qu'une fois, à l'approbation de la
                                             demande : ni relèvement, ni baisse, ni correction d'une erreur
                                             de saisie n'étaient possibles ensuite. --}}
                                        @if ((int) $c->client_a_terme === 1)
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#plafondModal-{{ $c->id }}">
                                                Modifier le plafond
                                            </button>
                                            <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#retraitModal-{{ $c->id }}">
                                                Retirer le statut à terme
                                            </button>
                                        @else
                                            <button class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#reactivationModal-{{ $c->id }}">
                                                Rendre le statut à terme
                                            </button>
                                        @endif
                                        <button class="dropdown-item" data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#tvaModal-{{ $c->id }}">
                                            {{ $c->applique_tva == 1 ? 'Retirer la TVA' : 'Appliquer la TVA' }}
                                        </button>
                                        @switch($c->user?->statut)
                                            @case(1)
                                                <button data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#blockModal-{{ $c->id }}"
                                                    class="dropdown-item">Bloquer</button>
                                                @break
                                            @case(2)
                                                <button data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#deblockModal-{{ $c->id }}"
                                                    class="dropdown-item">Débloquer</button>
                                                @break
                                            @default
                                        @endswitch

                                        <button class="dropdown-item text-danger" data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $c->id }}">Supprimer</button>
                                    </div>
                                </div>
                            </td>

                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

    {{-- les modal --}}

    @foreach ( $clients as $c )
         <!-- Modal Supprimer -->
        <div class="modal fade" id="deleteModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de suppression</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment supprimer le client : <span class="fw-bold">
                                {{ $c->display_name }} </span>
                        </p>

                        <h5 class="fw-bold text-danger" id="deleteNom"></h5>

                        <p class="text-muted">
                            Cette action est irréversible.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <form method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')

                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                                data-bs-dismiss="modal">
                                Annuler
                            </button>


                            <a href="{{route('show.bloquerCompte',['id' => $c->user_id, 'type' => 'sup'])}}"
                                class="btn btn-sm btn-danger rounded font-sm mt-15">Supprimer</a>
                        </form>
                    </div>

                </div>
            </div>
        </div>

         <!-- Modal bloquer -->
        <div class="modal fade" id="blockModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de bloquage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment Bloquer le client : <span class="fw-bold">
                                {{ $c->display_name }} </span>
                        </p>

                        <h5 class="fw-bold text-danger" id="deleteNom"></h5>

                        <p class="text-muted">
                            Il ne pourra plus acceder à la plateforme.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <form method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')

                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                                data-bs-dismiss="modal">
                                Annuler
                            </button>


                            <a href="{{ route('show.bloquerCompte', ['id' => $c->user_id, 'type' => 'blok']) }}"
                                class="btn btn-sm btn-danger rounded font-sm mt-15">Bloquer</a>
                        </form>
                    </div>

                </div>
            </div>
        </div>

         <!-- Modal débloquer -->
        <div class="modal fade" id="deblockModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title text-white">Confirmation de débloquage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment débloquer le client : <span class="fw-bold">
                                {{ $c->display_name }} </span>
                        </p>

                        <h5 class="fw-bold text-danger" id="deleteNom"></h5>

                        <p class="text-muted">
                            Il pourra acceder à la plateforme.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <form method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')

                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                                data-bs-dismiss="modal">
                                Annuler
                            </button>


                            <a href="{{ route('show.bloquerCompte', ['id' => $c->user_id, 'type' => 'blok']) }}"
                                class="btn btn-sm btn-info rounded font-sm mt-15">Débloquer</a>
                        </form>
                    </div>

                </div>
            </div>
        </div>

         <!-- Modal applique tva -->
        {{-- RETRAIT DU STATUT À TERME.
             Le geste attendu face à un mauvais payeur : on ferme la ligne de
             crédit sans effacer ce qui est dû. --}}
        <div class="modal fade" id="retraitModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('show.decisionCredit.retrait', $c) }}" class="modal-content">
                    @csrf
                    <div class="modal-header bg-danger">
                        <h5 class="modal-title text-white">Retirer le statut à terme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            <strong>{{ trim($c->display_name) }}</strong> repasserait au comptant :
                            il ne pourrait plus commander à crédit.
                        </p>
                        <p class="text-muted small">
                            Ses créances en cours <strong>restent dues</strong> et continuent d'apparaître
                            dans les relances. Seul le droit de commander à crédit s'arrête. Le plafond
                            actuel ({{ Help::formatNombre($c->plafond_credit ?? 0, true) }}) est conservé
                            et vous sera proposé si vous lui rendez le statut plus tard.
                        </p>
                        <div class="mb-2">
                            <label class="form-label">Motif <span class="text-muted">(facultatif)</span></label>
                            <input type="text" name="motif" class="form-control" maxlength="255"
                                   placeholder="Retards de paiement répétés...">
                        </div>
                        <div class="alert alert-warning mb-0 small">
                            Rien ne change tant qu'un <strong>second administrateur</strong> n'a pas validé.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger btn-sm">Enregistrer le retrait</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- RÉACTIVATION. Le plafond d'avant le retrait est proposé. --}}
        <div class="modal fade" id="reactivationModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('show.decisionCredit.reactivation', $c) }}" class="modal-content">
                    @csrf
                    <div class="modal-header bg-success">
                        <h5 class="modal-title text-white">Rendre le statut à terme</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>{{ trim($c->display_name) }}</strong> pourrait de nouveau commander à crédit.</p>
                        <div class="mb-2">
                            <label class="form-label">Plafond de crédit</label>
                            <input type="number" name="plafond_credit" class="form-control" min="0" step="1"
                                   value="{{ (int) ($c->plafond_credit ?? 0) }}">
                            <small class="text-muted">Laissez tel quel pour reprendre le plafond d'avant le retrait.</small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Délai de paiement (jours)</label>
                            <input type="number" name="delai_paiement" class="form-control" min="1" max="365"
                                   value="{{ (int) ($c->delai_paiement ?? 30) }}">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Motif <span class="text-muted">(facultatif)</span></label>
                            <input type="text" name="motif" class="form-control" maxlength="255"
                                   placeholder="Situation régularisée...">
                        </div>
                        <div class="alert alert-warning mb-0 small">
                            Rien ne change tant qu'un <strong>second administrateur</strong> n'a pas validé.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success btn-sm">Enregistrer la réactivation</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Révision du plafond de crédit. En POST, avec jeton : ce montant est
             opposable — il bloque les commandes au-delà — et ne doit pas pouvoir
             changer sur un simple lien visité. --}}
        <div class="modal fade" id="plafondModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('show.modifierPlafondCredit', $c) }}">
                    @csrf
                    <div class="modal-content">

                        <div class="modal-header" style="background-color:#1c57a3;">
                            <h5 class="modal-title text-white">Plafond de crédit</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <p class="mb-3">
                                Client : <span class="fw-bold">{{ trim($c->display_name) }}</span>
                            </p>

                            <div class="mb-3">
                                <label class="form-label">Plafond de crédit (FCFA)</label>
                                <input type="number" name="plafond_credit" class="form-control" min="0" step="1"
                                       value="{{ old('plafond_credit', (int) ($c->plafond_credit ?? 0)) }}" required>
                                <small class="text-muted">
                                    Actuel : {{ $c->plafond_credit ? Help::formatNombre($c->plafond_credit, true) : '0' }}
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Délai de paiement (jours)</label>
                                <input type="number" name="delai_paiement" class="form-control" min="1" max="365" step="1"
                                       value="{{ old('delai_paiement', (int) ($c->delai_paiement ?? 30)) }}" required>
                                <small class="text-muted">Actuel : {{ (int) ($c->delai_paiement ?? 0) }} jour(s)</small>
                            </div>

                            <div class="mb-1">
                                <label class="form-label">Motif de la révision <span class="text-muted">(facultatif)</span></label>
                                <input type="text" name="motif" class="form-control" maxlength="255"
                                       placeholder="Ex. : activité en hausse, retards de paiement…">
                                <small class="text-muted">
                                    La révision est enregistrée avec l'ancienne et la nouvelle valeur, votre nom et la date.
                                </small>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-sm btn-primary rounded font-sm">Enregistrer</button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="tvaModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-{{ $c->applique_tva == 1 ? 'danger' : 'warning' }} text-white">
                        <h5 class="modal-title text-white">Confirmation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment {{ $c->applique_tva == 1 ? 'retirer la TVA' : 'appliquer la TVA' }} au client : <span class="fw-bold">
                                {{ $c->display_name }} </span>
                        </p>

                        <h5 class="fw-bold text-danger" id="deleteNom"></h5>

                        <p class="text-muted">
                            {{ $c->applique_tva == 1 ? 'Il ne paiera plus de TVA sur ses commandes.' : 'Il devra payer une TVA sur ses commandes.' }}
                        </p>
                        @if ($c->applique_tva == 1)
                            <p class="text-muted small">
                                Précisez le motif : <strong>légale</strong> (prévue par la loi, code DGI TVAD) ou
                                <strong>conventionnelle</strong> (accordée par convention ou agrément, code DGI TVAC).
                                Ce code sera porté par ses factures normalisées.
                            </p>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <form method="POST" id="deleteForm">
                            @csrf
                            @method('DELETE')

                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                                data-bs-dismiss="modal">
                                Annuler
                            </button>


                            @if ($c->applique_tva == 1)
                                {{-- Le retrait dit à la DGI POURQUOI ce client n'a pas de TVA (lot 82, 15/09/2026) :
                                     exonération légale (TVAD) ou conventionnelle (TVAC), code de ses lignes FNE. --}}
                                <a href="{{ route('show.appliqueTva', $c) }}?code=TVAD"
                                    class="btn btn-sm btn-danger rounded font-sm mt-15">Retirer : exonération légale (TVAD)</a>
                                <a href="{{ route('show.appliqueTva', $c) }}?code=TVAC"
                                    class="btn btn-sm btn-outline-danger rounded font-sm mt-15">Retirer : exonération conventionnelle (TVAC)</a>
                            @else
                                <a href="{{ route('show.appliqueTva', $c) }}"
                                    class="btn btn-sm btn-warning rounded font-sm mt-15">Appliquer la TVA</a>
                            @endif
                        </form>
                    </div>

                </div>
            </div>
        </div>
         <!-- Modal retirer tva -->

        {{-- Le modal « Débloquer » figurait ici une seconde fois, à l'identique.
             Deux éléments ne peuvent pas porter le même identifiant : le navigateur
             n'ouvrait jamais celui-ci. Doublon retiré. --}}

    @endforeach

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });p
    </script>
@endsection
