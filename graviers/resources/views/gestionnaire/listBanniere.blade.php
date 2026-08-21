@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title','Liste des bannières')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    {{-- Le menu « Divers > Bannière » ouvre cette liste : la création se fait
         depuis le bouton ci-dessous, comme sur les autres écrans du back-office. --}}
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des bannières</h2>
            <p class="text-muted mb-0">
                {{ $bannieres->count() }} bannière(s) — dont {{ $bannieres->where('statut', 1)->whereNull('deleted_at')->count() }} en ligne
            </p>
        </div>
        <div>
            <a href="{{ route('show.creationDeBanniere') }}" class="btn btn-primary">
                <i class="material-icons md-plus"></i> Ajout bannière
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center" id="notify">{{session('success')}}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger text-center">{{session('error')}}</div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="listeBannieres">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N° d'ordre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Image</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Titre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Sous-titre</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Fin du décompte</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Statut</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bannieres as $banniere)
                                    <tr>
                                        <td class="text-center">{{ $banniere->num_ordre }}</td>
                                        <td class="text-center">
                                            @if ($banniere->image)
                                                <img src="{{ asset('storage/'.$banniere->image) }}" class="img-sm img-thumbnail" alt="Bannière" />
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $banniere->titre }}</td>
                                        <td>{{ $banniere->sous_titre }}</td>
                                        <td class="text-center">{{ $banniere->type_banniere }}</td>
                                        <td class="text-center">
                                            {{ $banniere->date_heure_decompte ? Carbon::parse($banniere->date_heure_decompte)->format('d/m/Y') : '—' }}
                                        </td>
                                        <td class="text-center">
                                            {{-- Une bannière à la corbeille n'est plus affichée nulle part,
                                                 quel que soit son statut de publication. --}}
                                            @if ($banniere->trashed())
                                                <span class="badge bg-dark">Corbeille</span>
                                            @elseif ($banniere->statut == 1)
                                                <span class="badge bg-success">En ligne</span>
                                            @else
                                                <span class="badge bg-secondary">Pas en ligne</span>
                                            @endif
                                        </td>

                                        {{-- Colonne Action unique : les commandes sont des icônes,
                                             chacune avec une bulle d'aide au survol. --}}
                                        <td class="text-center text-nowrap">
                                            <a href="{{ route('show.modificationDeBannierePage', $banniere->id) }}"
                                               class="btn btn-sm btn-primary" title="Modifier">
                                                <i class="material-icons md-edit"></i>
                                            </a>

                                            @if (!$banniere->trashed())
                                                @if ($banniere->statut == 1)
                                                    <a href="{{ route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'enLigne']) }}"
                                                       class="btn btn-sm btn-warning" title="Retirer de l'affichage">
                                                        <i class="material-icons md-visibility_off"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'enLigne']) }}"
                                                       class="btn btn-sm btn-success" title="Republier">
                                                        <i class="material-icons md-visibility"></i>
                                                    </a>
                                                @endif

                                                <a href="{{ route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'supprimer']) }}"
                                                   class="btn btn-sm btn-danger" title="Mettre à la corbeille">
                                                    <i class="material-icons md-delete"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('show.supprimerPublierBanniere',['id' => $banniere->id, 'action' => 'supprimer']) }}"
                                                   class="btn btn-sm btn-success" title="Restaurer">
                                                    <i class="material-icons md-restore_from_trash"></i>
                                                </a>

                                                {{-- Suppression définitive : en DELETE avec confirmation,
                                                     pour qu'un lien cliqué par erreur ne détruise rien.
                                                     js-delete-form : la confirmation passe par SweetAlert2
                                                     (public/backend/assets/js/delete-confirm.js, motif D),
                                                     comme sur le reste de l'administration. L'ancien
                                                     onsubmit="return confirm(...)" ouvrait la fenêtre grise
                                                     du navigateur, que ce script n'intercepte pas : il ne
                                                     reprend que les onclick. --}}
                                                <form action="{{ route('show.suppressionDefinitiveBanniere', $banniere->id) }}" method="POST" class="d-inline js-delete-form"
                                                      data-item-name="{{ $banniere->titre }}"
                                                      data-confirm-text="La bannière et son image seront supprimées définitivement. Cette action est irréversible.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-dark" title="Supprimer définitivement">
                                                        <i class="material-icons md-delete_forever"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#listeBannieres');

            // Garde-fou : initialiser DataTables sur un tableau VIDE provoque
            // « Requested unknown parameter ». On n'initialise que s'il y a des lignes.
            if ($table.find('tbody tr').length > 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    // Ni tri ni recherche sur l'image et sur la colonne Action.
                    columnDefs: [
                        { targets: [1, 7], orderable: false, searchable: false },
                        { targets: '_all', defaultContent: '-' }
                    ],
                    order: [[0, 'asc']],
                });
            }
        });
    </script>
    @notifyJs
@endsection
