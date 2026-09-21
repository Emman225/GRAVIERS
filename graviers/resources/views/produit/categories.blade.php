@extends('layout.main')
@section('title','Catégories')
@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Categories</h2>
                        <p>Ajoutez, modifiez ou supprimez une categorie</p>
                    </div>
                    <div>
                        {{-- La création se fait sur SA page : cet écran ne sert plus qu'à consulter. --}}
                        <a href="{{ route('product.nouvelleCategorie') }}" class="btn btn-sm btn-primary">
                            <i class="material-icons md-add align-middle"></i> Nouvelle catégorie
                        </a>
                    </div>
                </div>
                {{-- Le message de création s'affichait DANS le formulaire, donc
                     jamais : l'enregistrement renvoie sur cette liste. Et
                     « succes » n'est pas « success » : Flasher ne le capte pas,
                     il faut donc l'afficher soi-même. --}}
                @if (session('succes'))
                    <div class="alert alert-success">{{ session('succes') }}</div>
                @endif
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <x-export-buttons table-id="listeCategories"
                                                  filename="liste-des-categories"
                                                  title="Liste des catégories" />
                                <div class="table-responsive">
                                    <table class="table table-striped" id="listeCategories">
                                        <thead>
                                            <tr>
                                                <th class="text-center">

                                                </th>
                                                <th>ID</th>
                                                <th>Nom</th>
                                                <th>Description</th>
                                                {{-- <th>Parent</th> --}}

                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($lists as $list)


                                            <tr>
                                                <td class="text-center">

                                                </td>

                                                <td>{{$list->id}}</td>

                                                <td><b>{{$list->nom}}</b></td>

                                                <td> {{$list->description}} </td>

                                                <td class="text-nowrap text-end">
                                                    {{-- DES ICONES, COMME SUR LA LISTE DES GESTIONNAIRES.

                                                         Le menu déroulant demandait deux gestes pour une
                                                         action et cachait ce qu'on pouvait faire. --}}
                                                    <a href="{{route('product.editCategory',$list)}}"
                                                       class="btn btn-sm btn-primary" title="Modifier les informations">
                                                        <i class="material-icons md-edit"></i>
                                                    </a>
                                                    <a href="{{route('product.deleteCategory',$list)}}"
                                                       class="btn btn-sm btn-danger" title="Supprimer"
                                                       data-confirm-msg="Voulez-vous vraiment supprimer la catégorie {{ $list->nom }} ?">
                                                        <i class="material-icons md-delete"></i>
                                                    </a>
                                                </td>
                                            </tr>

                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- .col// -->
                        </div>
                        <!-- .row // -->
                    </div>
                    <!-- card body .// -->
                </div>
                <!-- card .// -->

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
                },
            });
        });
    </script>
@endsection
