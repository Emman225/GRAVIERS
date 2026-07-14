{{-- @dd($livreurs) --}}

@extends('layout.main')
@section('title','Liste des livreurs')
@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des livreurs - </h2>
        <div>
            <a href="{{ route('show.registerLivreur') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div>
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
                {{-- <div class="col-lg-2 col-md-3 col-6">
                    <select class="form-select">
                        <option>Statut</option>
                        <option>Activé</option>
                        <option>Desactivé</option>
                        <option>Montrer tout</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <select class="form-select">
                        <option>Show 20</option>
                        <option>Show 30</option>
                        <option>Show 40</option>
                    </select>
                </div> --}}
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="liste-livreurs" title="Liste des livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Code Livreur</th>
                            <th class="text-center">Nom &amp; Prénom</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">CNI/Pièce</th>
                            <th class="text-center">Type véhicule</th>
                            <th class="text-center">Immat. véhicule</th>
                            <th class="text-end">Capacité</th>
                            <th class="text-center">Zone d'intervention</th>
                            <th class="text-end">Tarif km</th>
                            <th class="text-end">Tarif forfait base</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($livreurs as $livreur )
                            @php
                                $codeLvr = $livreur->code ?: 'LVR-' . str_pad($livreur->id, 3, '0', STR_PAD_LEFT);
                                $vehicule = $livreur->vehicules?->first() ?? \App\Models\Vehicule::where('livreur_id', $livreur->id)->where('statut', 1)->first();
                            @endphp
                        <tr>
                            <td class="text-center"><strong>{{ $codeLvr }}</strong></td>
                            <td>
                                <a href="{{route('show.profile',$livreur->id)}}">
                                    {{ strtoupper($livreur->user?->nom_prenoms) }}
                                </a>
                            </td>
                            <td class="text-center">{{ $livreur->user?->contact ?? '-' }}</td>
                            <td class="text-center">{{ $livreur->num_piece_identite ?? '-' }}</td>
                            <td class="text-center">
                                @if ($vehicule)
                                    <span class="badge bg-light text-dark">{{ $vehicule->type?->libelle ?? $vehicule->nom ?? '-' }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">{{ $vehicule?->immatriculation ?? '-' }}</td>
                            <td class="text-end">
                                {{ $vehicule?->capacite ? rtrim(rtrim(number_format($vehicule->capacite, 2, ',', ' '), '0'), ',') . ' T' : '-' }}
                            </td>
                            <td>{{ $livreur->zone_intervention ?? '-' }}</td>
                            {{-- Tarif actif selon le mode de tarification choisi sur le profil.
                                 NB : le tarif de base est stocké dans cout_livraison (PAS dans
                                 tarif_forfait_base, colonne jamais alimentée -> affichait toujours '-'). --}}
                            <td class="text-end">
                                {{ $livreur->tarif_km ? number_format($livreur->tarif_km, 0, ',', ' ') . ' FCFA' : '-' }}
                                @if (($livreur->mode_tarification ?? 'base') == 'km')
                                    <span class="badge bg-success">actif</span>
                                @endif
                            </td>
                            <td class="text-end">
                                {{ $livreur->cout_livraison ? number_format($livreur->cout_livraison, 0, ',', ' ') . ' FCFA' : '-' }}
                                @if (($livreur->mode_tarification ?? 'base') == 'base')
                                    <span class="badge bg-success">actif</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($livreur->user?->statut == 2)
                                    <span class="badge bg-danger">Suspendu</span>
                                @else
                                    <span class="badge bg-success">Actif</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions </a>
                                    <div class="dropdown-menu">
                                        <a href="{{route('show.profile',$livreur->id)}}" class="dropdown-item">Voir les details</a>
                                        @if ($livreur->user?->statut == 2)
                                            <button type="button" class="dropdown-item"
                                                data-bs-toggle="modal" data-bs-target="#deblockModal-{{ $livreur->id }}">
                                                Débloquer
                                            </button>
                                        @else
                                            <button type="button" class="dropdown-item"
                                                data-bs-toggle="modal" data-bs-target="#blockModal-{{ $livreur->id }}">
                                                Bloquer
                                            </button>
                                        @endif
                                        <button type="button" class="dropdown-item"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal-{{ $livreur->id }}">
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

    @foreach ($livreurs as $livreur)
        <div class="modal fade" id="blockModal-{{ $livreur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de blocage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment bloquer le livreur :
                            <span class="fw-bold">{{ strtoupper($livreur->user?->nom_prenoms) }}</span>
                        </p>
                        <p class="text-muted">
                            Il ne pourra plus acceder a la plateforme.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <a href="{{ route('show.bloquerCompte', ['id' => $livreur->user_id, 'type' => 'blok']) }}"
                            class="btn btn-sm btn-danger rounded font-sm mt-15">Bloquer</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deblockModal-{{ $livreur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title text-white">Confirmation de déblocage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment débloquer le livreur :
                            <span class="fw-bold">{{ strtoupper($livreur->user?->nom_prenoms) }}</span>
                        </p>
                        <p class="text-muted">
                            Il pourra acceder a la plateforme.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <a href="{{ route('show.bloquerCompte', ['id' => $livreur->user_id, 'type' => 'blok']) }}"
                            class="btn btn-sm btn-info rounded font-sm mt-15">Débloquer</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteModal-{{ $livreur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de suppression</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment supprimer le livreur :
                            <span class="fw-bold">{{ strtoupper($livreur->user?->nom_prenoms) }}</span>
                        </p>
                        <p class="text-muted">
                            Cette action est irreversible.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15" data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <a href="{{ route('show.bloquerCompte', ['id' => $livreur->user_id, 'type' => 'sup']) }}"
                            class="btn btn-sm btn-danger rounded font-sm mt-15">Supprimer</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="pagination-area mt-15 mb-50">
        {{-- <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-start">
                <li class="page-item active"><a class="page-link" href="#">01</a></li>
                <li class="page-item"><a class="page-link" href="#">02</a></li>
                <li class="page-item"><a class="page-link" href="#">03</a></li>
                <li class="page-item"><a class="page-link dot" href="#">...</a></li>
                <li class="page-item"><a class="page-link" href="#">16</a></li>
                <li class="page-item">
                    <a class="page-link" href="#"><i class="material-icons md-chevron_right"></i></a>
                </li>
            </ul>
        </nav> --}}
    </div>
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
