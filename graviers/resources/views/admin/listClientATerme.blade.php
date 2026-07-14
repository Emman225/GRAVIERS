@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des clients')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des Clients à terme - </h2>

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
                            <th class="text-center">Code Client</th>
                            <th class="text-center">Raison Sociale / Nom</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">Adresse / Chantier</th>
                            <th class="text-end">Plafond Crédit</th>
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
                            <td>{{ trim($c->nom . ' ' . $c->prenom) }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark">{{ $typeBadge }}</span>
                            </td>
                            <td class="text-center">{{ $c->nom }}</td>
                            <td class="text-center">
                                @if ($c->contact1)<div>{{ $c->contact1 }}</div>@endif
                                @if ($c->contact2)<div class="text-muted small">{{ $c->contact2 }}</div>@endif
                            </td>
                            <td class="text-center">{{ $c->user->email ?? $c->email }}</td>
                            <td>{{ $c->user->adresse ?? '-' }}</td>
                            <td class="text-end">
                                {{ $c->plafond_credit ? Help::formatNombre($c->plafond_credit, true) : '-' }}
                            </td>
                            <td class="text-center">{{ $c->delai_paiement ?? '-' }}</td>
                            <td>{{ $c->notes ?? '-' }}</td>
                            <td class="text-center">
                                @if ($c->user && $c->user->statut == 1)
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-danger">Bloqué</span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions</a>
                                    <div class="dropdown-menu">

                                        <a class="dropdown-item" href="{{route('show.clientDetailCommande',$c->user)}}">Commandes</a>
                                        <a class="dropdown-item" href="{{route('paye.effectuerPaiement',$c)}}">Faire un paiement </a>
                                        <button class="dropdown-item" data-id="{{ $c->id }}" data-nom="{{ $c->nom }}" data-bs-toggle="modal" data-bs-target="#tvaModal-{{ $c->id }}">
                                            {{ $c->applique_tva == 1 ? 'Retirer la TVA' : 'Appliquer la TVA' }}
                                        </button>
                                        @switch($c->user->statut)
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
                                {{ $c->nom .' '.$c->prenom }} </span>
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
                                {{ $c->nom .' '.$c->prenom }} </span>
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
                                {{ $c->nom .' '.$c->prenom }} </span>
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
                                {{ $c->nom .' '.$c->prenom }} </span>
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
                                {{ $c->nom .' '.$c->prenom }} </span>
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
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });p
    </script>
@endsection
