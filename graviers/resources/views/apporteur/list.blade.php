{{-- @dd($frs) --}}
@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Liste des apporteurs')
@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des apporteurs d'affaire - </h2>

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
            <x-export-buttons table-id="liste" filename="liste-apporteurs" title="Liste des apporteurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Code Apporteur</th>
                            <th class="text-center">Nom &amp; Prénom</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Email</th>
                            <th class="text-center">CNI/Pièce</th>
                            <th class="text-end">Taux commission</th>
                            <th class="text-center">Mode paiement préféré</th>
                            <th class="text-center">Coordonnées paiement</th>
                            <th class="text-center">Statut</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($apporteurs as $apporteur)
                            @php
                                $codeApp = preg_match('/^APP-/', (string) $apporteur->code)
                                    ? $apporteur->code
                                    : 'APP-' . str_pad($apporteur->id, 3, '0', STR_PAD_LEFT);
                            @endphp
                            <tr>
                                <td class="text-center"><strong>{{ $codeApp }}</strong></td>
                                <td>{{ strtoupper($apporteur->user?->nom_prenoms ?? '-') }}</td>
                                <td class="text-center">{{ $apporteur->user?->contact ?? '-' }}</td>
                                <td class="text-center">{{ $apporteur->user?->email }}</td>
                                <td class="text-center">{{ $apporteur->numero_piece ?? '-' }}</td>
                                <td class="text-end">{{ number_format($apporteur->pourcentage, 1, ',', ' ') }} %</td>
                                <td class="text-center">{{ $apporteur->modePaiement?->libelle ?? '-' }}</td>
                                <td class="text-center">{{ $apporteur->user?->contact ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($apporteur->user?->statut == 2)
                                        <span class="badge bg-danger">Inactif</span>
                                    @else
                                        <span class="badge bg-success">Actif</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> Actions </a>
                                        <div class="dropdown-menu">
                                            <button class="dropdown-item" type="submit" data-bs-toggle="modal" data-bs-target="#pourcent-{{ $apporteur->id }}">Modifier le pourcentage</button>
                                            <a href="{{ route('show.profileApporteur', $apporteur) }}"
                                                class="dropdown-item">Profil</a>
                                            @if ($apporteur->user?->statut == 2)
                                                <button type="button" class="dropdown-item"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deblockModal-{{ $apporteur->id }}">
                                                    Débloquer
                                                </button>
                                            @else
                                                <button type="button" class="dropdown-item"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#blockModal-{{ $apporteur->id }}">
                                                    Bloquer
                                                </button>
                                            @endif
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

    @foreach ($apporteurs as $apporteur)
        <div class="modal fade" id="blockModal-{{ $apporteur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title text-white">Confirmation de blocage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <p>
                            Voulez-vous vraiment bloquer l'apporteur :
                            <span class="fw-bold">{{ strtoupper($apporteur->code) }}</span>
                        </p>
                        <p class="text-muted">
                            Il ne pourra plus acceder a la plateforme.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                            data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <a href="{{ route('show.bloquerCompte', ['id' => $apporteur->user_id, 'type' => 'blok']) }}"
                            class="btn btn-sm btn-danger rounded font-sm mt-15">Bloquer</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deblockModal-{{ $apporteur->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title text-white">Confirmation de déblocage</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                       <p>
                            Voulez-vous vraiment débloquer l'apporteur :
                            <span class="fw-bold">{{ strtoupper($apporteur->code) }}</span>
                        </p>
                        <p class="text-muted">
                            Il pourra de nouveau accéder à la plateforme.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                            data-bs-dismiss="modal">
                            Annuler
                        </button>
                        <a href="{{ route('show.bloquerCompte', ['id' => $apporteur->user_id, 'type' => 'blok']) }}"
                            class="btn btn-sm btn-info rounded font-sm mt-15">Débloquer</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="pourcent-{{ $apporteur->id }}" tabindex="-1">
            <form method="post" action="{{ route('show.pourcentage', $apporteur) }}">
                @csrf
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title text-white">Modification du pourcentage</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body text-center">
                            <p>
                                <input type="text" name="pourcentage" class="form-control" value="{{ $apporteur->pourcentage }}">
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-secondary rounded font-sm mt-15"
                                data-bs-dismiss="modal">
                                Annuler
                            </button>
                            <button  class="btn btn-success" >Valider</button>
                        </div>
                    </div>
                </div>
            </form>
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
            //Gestion du datatables
            $('#liste').DataTable();
        });
    </script>
@endsection
