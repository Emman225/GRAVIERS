@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des clients')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des clients ordinaires- </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter nouveau</a>
        </div> --}}
    </div>


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
                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="liste-clients-ordinaires" title="Liste des clients ordinaires" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center">Numéro de compte</th>
                            <th class="text-center">Date d'ouverture</th> {{--  --}}
                            <th class="text-center">Nom</th>
                            <th class="text-center">type de compte</th> {{--  --}}
                            <th class="text-center">Adresse géographique</th> {{--  --}}
                            <th class="text-center">Contact</th>
                            <th class="text-center">Email</th>
                            <th>TVA</th>
                            <th class="text-end">Avance disponible</th>
                            <th>Bloqué</th>
                            <th class="text-center width-10%">Action</th>


                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $c)
                        <tr>
                            <td class="text-center">
                                <div class="info pl-3">
                                    <h6 class="mb-0 title">{{ $c->user_id }}</h6>
                                </div>
                            </td>

                            <td class="text-center">{{ Carbon::parse($c->created_at)->format('d-m-Y') }}</td>

                            <td class="text-center">{{ $c->display_name }}</td>

                            <td class="text-center">|
                                {{ $c->type_client }}
                            </td>
                            <td class="text-center">{{ $c->user?->adresse }}</td>
                            <td class="text-center">
                                <p> {{ $c->contact1 }} </p>
                                <p> {{ $c->contact2 }} </p>
                            </td>
                            <td class="text-center">{{ $c->user?->email }}</td>
                            <td class="small">
                                {{-- TVA marchandise et TVA transport, retirables séparément (10/09/2026). --}}
                                Marchandise : <strong>{{ $c->applique_tva == 1 ? 'appliquée' : 'non appliquée' }}</strong>{{ $c->applique_tva == 1 ? '' : ' — ' . $c->libelleExonerationFne() }}<br>
                                Transport : <strong>{{ (int) ($c->applique_tva_transport ?? 1) === 1 ? 'appliquée' : 'non appliquée' }}</strong>
                            </td>
                            <td class="text-end">{{ ($soldesAvance[$c->id] ?? 0) >= 1 ? Help::formatNombre($soldesAvance[$c->id], true) : '-' }}</td>
                            <td> {{ $c->user?->statut == 1 ? "NON" : "OUI" }} </td>

                            <td class="text-nowrap">
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions</a>
                                    <div class="dropdown-menu">

                                        {{-- Compte utilisateur supprimé : route() sans paramètre levait
                                             « Missing required parameter » et TOUTE la liste tombait en 500. --}}
                                        @if($c->user)
                                            <a class="dropdown-item" href="{{route('show.clientDetailCommande',$c->user)}}">Commandes</a>
                                        @endif
                                        {{-- Un seul écran d'encaissement pour les clients ordinaires. --}}
                                        <a class="dropdown-item" href="{{route('show.comptant.encaissements')}}">Faire un paiement </a>
                                        @if($c->type_client == 'ENTREPRISE')
                                            @php
                                                $dfeExists = !empty($c->dfe) && \App\Models\Client::resolveStoragePath($c->dfe) !== null;
                                                $rcExists = !empty($c->registre_commerce) && \App\Models\Client::resolveStoragePath($c->registre_commerce) !== null;
                                            @endphp
                                            @if(!empty($c->dfe))
                                                @if($dfeExists)
                                                    <a class="dropdown-item" href="{{ route('show.clientDocument', ['client' => $c->id, 'type' => 'dfe', 'mode' => 'inline']) }}" target="_blank" rel="noopener">
                                                        <i class="material-icons md-visibility" style="font-size:14px;"></i> Voir DFE
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('show.clientDocument', ['client' => $c->id, 'type' => 'dfe', 'mode' => 'download']) }}">
                                                        <i class="material-icons md-get_app" style="font-size:14px;"></i> Télécharger DFE
                                                    </a>
                                                @else
                                                    <span class="dropdown-item text-danger" style="cursor:default;" title="Fichier référencé en BD mais introuvable sur le disque">
                                                        <i class="material-icons md-warning" style="font-size:14px;"></i> DFE (manquant)
                                                    </span>
                                                @endif
                                            @endif
                                            @if(!empty($c->registre_commerce))
                                                @if($rcExists)
                                                    <a class="dropdown-item" href="{{ route('show.clientDocument', ['client' => $c->id, 'type' => 'rc', 'mode' => 'inline']) }}" target="_blank" rel="noopener">
                                                        <i class="material-icons md-visibility" style="font-size:14px;"></i> Voir registre de commerce
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('show.clientDocument', ['client' => $c->id, 'type' => 'rc', 'mode' => 'download']) }}">
                                                        <i class="material-icons md-get_app" style="font-size:14px;"></i> Télécharger registre de commerce
                                                    </a>
                                                @else
                                                    <span class="dropdown-item text-danger" style="cursor:default;" title="Fichier référencé en BD mais introuvable sur le disque">
                                                        <i class="material-icons md-warning" style="font-size:14px;"></i> Registre de commerce (manquant)
                                                    </span>
                                                @endif
                                            @endif
                                            @if(empty($c->dfe) && empty($c->registre_commerce))
                                                <span class="dropdown-item text-muted" style="cursor:default;">Aucun document joint</span>
                                            @endif
                                        @endif
                                        <button class="dropdown-item" data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#tvaModal-{{ $c->id }}">
                                            {{ $c->applique_tva == 1 ? 'Retirer la TVA marchandise' : 'Appliquer la TVA marchandise' }}
                                        </button>
                                        <button class="dropdown-item" data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#tvaTransportModal-{{ $c->id }}">
                                            {{ (int) ($c->applique_tva_transport ?? 1) === 1 ? 'Retirer la TVA transport' : 'Appliquer la TVA transport' }}
                                        </button>
                                        @switch($c->user?->statut)
                                            @case(1)
                                                <button data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#blockModal-{{ $c->id }}"
                                                    class="dropdown-item">Bloquer</a>
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
         <!-- Modal TVA transport (10/09/2026) -->
        @php $tvaTransportAppliquee = (int) ($c->applique_tva_transport ?? 1) === 1; @endphp
        <div class="modal fade" id="tvaTransportModal-{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-{{ $tvaTransportAppliquee ? 'danger' : 'warning' }} text-white">
                        <h5 class="modal-title text-white">Confirmation</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment {{ $tvaTransportAppliquee ? 'retirer la TVA sur le transport' : 'appliquer la TVA sur le transport' }} au client : <span class="fw-bold">{{ $c->display_name }}</span>
                        </p>
                        <p class="text-muted">
                            {{ $tvaTransportAppliquee ? 'Ses prochains transports (ventes, locations, livraisons) seront facturés hors taxe.' : 'Ses prochains transports porteront la TVA, si elle est activée dans Paramètres.' }}
                            La TVA sur la marchandise n'est pas concernée.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15" data-bs-dismiss="modal">Annuler</button>
                        <a href="{{ route('show.appliqueTvaTransport', $c) }}"
                           class="btn btn-sm btn-{{ $tvaTransportAppliquee ? 'danger' : 'warning' }} rounded font-sm mt-15">{{ $tvaTransportAppliquee ? 'Retirer' : 'Appliquer' }}</a>
                    </div>
                </div>
            </div>
        </div>
         <!-- Modal retirer tva -->
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
    @endforeach

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                }, order: [],
            });
        });
    </script>
@endsection
