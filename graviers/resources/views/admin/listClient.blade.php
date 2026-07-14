@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des clients')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des clients ordinaires- </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
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
                            <td class="text-center">{{ $c->user->email }}</td>
                            <td>{{ $c->applique_tva == 1 ? 'Appliquée' : 'Non appliquée' }}</td>
                            <td> {{ $c->user->statut == 1 ? "NON" : "OUI" }} </td>

                            <td>
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions</a>
                                    <div class="dropdown-menu">

                                        <a class="dropdown-item" href="{{route('show.clientDetailCommande',$c->user)}}">Commandes</a>
                                        <a class="dropdown-item" href="{{route('paye.effectuerPaiement',$c)}}">Faire un paiement </a>
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
                                                        <i class="material-icons md-download" style="font-size:14px;"></i> Télécharger DFE
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
                                                        <i class="material-icons md-visibility" style="font-size:14px;"></i> Voir Registre de commerce
                                                    </a>
                                                    <a class="dropdown-item" href="{{ route('show.clientDocument', ['client' => $c->id, 'type' => 'rc', 'mode' => 'download']) }}">
                                                        <i class="material-icons md-download" style="font-size:14px;"></i> Télécharger Registre de commerce
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
                                            {{ $c->applique_tva == 1 ? 'Retirer la TVA' : 'Appliquer la TVA' }}
                                        </button>
                                        @switch($c->user->statut)
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
                            {{ $c->applique_tva == 1 ? 'Il le paiera plus de TVA sur ses commandes' : 'Il devra payer une TVA sur ses commandes.' }}
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


                            <a href="{{route('show.appliqueTva',$c)}}"
                                class="btn btn-sm btn-{{ $c->applique_tva == 1 ? 'danger' : 'warning' }} rounded font-sm mt-15">{{ $c->applique_tva == 1 ? 'Retirer la TVA' : 'Appliquer la TVA' }}</a>
                        </form>
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
