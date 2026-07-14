@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title', 'Liste des fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des fournisseurs </h2>
        <div>
            <a href="{{ route('show.registerSeller') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter
                Nouveau</a>
        </div>
    </div>
    <div class="card mb-4">
        {{-- <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    <input type="text" placeholder="Recherche..." class="form-control" />
                </div>
            </div>
        </header> --}}
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="liste-fournisseurs" title="Liste des fournisseurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Code Fourn.</th>
                            <th class="text-center">Raison Sociale</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Produit principal</th>
                            <th class="text-center">Contact</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Localisation</th>
                            <th class="text-center">Délai paiement (j)</th>
                            <th class="text-center">Notes</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fournisseurs as $fournisseur)
                            @php
                                $codeFrn = $fournisseur->code ?: 'FRN-' . str_pad($fournisseur->id, 3, '0', STR_PAD_LEFT);
                            @endphp
                            <tr>
                                <td class="text-center"><strong>{{ $codeFrn }}</strong></td>
                                <td>{{ $fournisseur->nom_prenoms }}</td>
                                <td class="text-center">
                                    @if ($fournisseur->type_fournisseur)
                                        <span class="badge bg-light text-dark">{{ $fournisseur->type_fournisseur }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $fournisseur->produit_principal ?? '-' }}</td>
                                <td class="text-center">{{ $fournisseur->nom_prenoms }}</td>
                                <td class="text-center">
                                    @if ($fournisseur->contact1)<div>{{ $fournisseur->contact1 }}</div>@endif
                                    @if ($fournisseur->contact2)<div class="text-muted small">{{ $fournisseur->contact2 }}</div>@endif
                                </td>
                                <td>{{ $fournisseur->adresse_geo }}</td>
                                <td class="text-center">{{ $fournisseur->delai_paiement ?? '-' }}</td>
                                <td>{{ $fournisseur->notes ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($fournisseur->user && $fournisseur->user->statut == 1)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-danger">Bloqué</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions</a>
                                        <div class="dropdown-menu">
                                            <button type="button" class="dropdown-item" data-toggle="modal"
                                                data-target="#exampleModalCenter{{ $fournisseur->id }}">
                                                Voir les produits
                                            </button>
                                             <a href="{{ route('show.editSellers', $fournisseur->id) }}"
                                                class="dropdown-item">Modifier les infos
                                            </a>
                                            @if($fournisseur->dfe)
                                                <a href="{{ route('show.fournisseurDocument', [$fournisseur->id, 'dfe']) }}" target="_blank"
                                                    class="dropdown-item"><i class="material-icons md-description align-middle"></i> Voir la DFE</a>
                                            @else
                                                <span class="dropdown-item text-muted">DFE non fournie</span>
                                            @endif
                                            @if($fournisseur->registre_commerce)
                                                <a href="{{ route('show.fournisseurDocument', [$fournisseur->id, 'rc']) }}" target="_blank"
                                                    class="dropdown-item"><i class="material-icons md-description align-middle"></i> Voir le registre de commerce</a>
                                            @else
                                                <span class="dropdown-item text-muted">Registre de commerce non fourni</span>
                                            @endif
                                            <button
                                                class="dropdown-item"
                                                data-id="{{ $fournisseur->id }}" data-nom="{{ $fournisseur->nom }}"
                                                data-bs-toggle="modal" data-bs-target="#blockModal-{{ $fournisseur->user_id }}">
                                                {{ $fournisseur->user->statut == 2 ? 'Débloquer' : 'Bloquer' }}
                                            </button>
                                            <button class="dropdown-item text-danger"
                                                data-nom="{{ $fournisseur->nom_prenoms }}" data-bs-toggle="modal"
                                                data-bs-target="#deleteModal-{{ $fournisseur->id }}">
                                                Supprimer
                                            </button>
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

    @foreach ($fournisseurs as $fournisseur)
        {{-- Modal de bloquage --}}
        <div class="modal fade" id="exampleModalCenter{{ $fournisseur->id }}" tabindex="-1" role="dialog"
            aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLongTitle">Les produits de <span
                                class="fw-bold">{{ $fournisseur->user->nom_prenoms }}</span> </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3"
                            style="max-height: 300px; overflow-y: auto; overflow-x: hidden; border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                            <table class="table">
                                @foreach ($fournisseur->produits as $produit)
                                    <tr>
                                        <td>{{ $produit->nom }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                        <a href="{{ route('show.editSellers', $fournisseur->id) }}" class="btn btn-primary">Modifier la liste</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Supprimer -->
        <div class="modal fade" id="deleteModal-{{ $fournisseur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de suppression</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment supprimer le fournisseur : <span class="fw-bold">
                                {{ $fournisseur->nom_prenoms }} </span>
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


                            <a href="{{ route('show.bloquerCompte', ['id' => $fournisseur->user_id, 'type' => 'sup']) }}"
                                class="btn btn-sm btn-danger rounded font-sm mt-15">Supprimer</a>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <!-- Modal Bloquer -->
        <div class="modal fade" id="blockModal-{{ $fournisseur->user_id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title text-white">Bloquer le fournisseur</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous bloquer le fournisseur : <span class="fw-bold"> {{ $fournisseur->nom_prenoms }}
                            </span>
                        </p>

                        <h5 class="fw-bold text-primary" id="blockNom"></h5>

                        <p class="text-muted">
                            Il ne pourra plus accéder à la plateforme.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <form method="POST" id="blockForm">
                            @csrf
                            @method('PATCH')

                            <button type="button" class="btn btn-sm btn-brand bg-secondary rounded font-sm mt-15" data-bs-dismiss="modal">
                                Annuler
                            </button>

                            <a href="{{ route('show.bloquerCompte', ['id' => $fournisseur->user_id, 'type' => 'blok']) }}"
                                class="btn btn-sm btn-brand bg-{{ $fournisseur->user->statut == 2 ? 'danger' : 'primary' }} rounded font-sm mt-15">{{ $fournisseur->user->statut == 2 ? 'Oui, débloquer' : 'Oui, Bloquer' }}</a>

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
        });
    </script>
@endsection
