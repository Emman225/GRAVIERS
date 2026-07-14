@extends('layout.main')
@section('title','Ajout de catégorie')
@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Categories</h2>
                        <p>Ajoutez, modifiez ou supprimez une categorie</p>
                    </div>
                    <div>
                        <a href="{{route('product.category')}}" class="btn btn-primary"> + Catégorie </a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <form method="post" action="" enctype="multipart/form-data">
                                    @csrf
                                    <div class="mb-4">
                                        @if(session('succes'))
                                            <div class="alert alert-success text-center">
                                                {{session('succes')}}
                                            </div>
                                        @endif
                                        <label for="product_name" class="form-label">Nom</label>
                                        <input type="text"  value="{{$categorie->nom}}" name="nom" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('nom')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        {{-- <label for="product_name" class="form-label">Parent</label> --}}
                                        <select name="parent" class="form-control" id="product_name">
                                            <option value="">Parent</option>
                                            @foreach ($lists as $cat )
                                                <option @selected($categorie->parent_id == $cat->id) value="{{$cat->id}}"> {{$cat->nom}} </option>
                                            @endforeach
                                        </select>
                                        <span class="text-danger">
                                            @error('parent')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Choisissez une image</label>
                                        <input type="file" {{$categorie->id > 0 ? '' : 'required'}}  class="form-control" name="image" id="product_name" />
                                        <span class="text-danger">
                                            @error('image')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    {{-- <div class="mb-4">
                                        <label class="form-label">Choisissez une icon <small class="text-mutted fw-bold">(Facultatif)</small> </label>
                                        <input type="file"  class="form-control" name="icon" id="product_name" />
                                        <span class="text-danger">
                                            @error('icon')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div> --}}

                                    <div class="mb-4">
                                        <label class="form-label">Description</label>
                                        <textarea name="description"  placeholder="Type here" class="form-control">{{$categorie->description}}</textarea>
                                        <span class="text-danger">
                                            @error('description')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="d-grid">
                                        <button class="btn btn-primary">Valider</button>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-9">
                                <div class="table-responsive">
                                    <table class="table table-striped">
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

                                                <td class="text-end">
                                                    <div class="dropdown">
                                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> </a>
                                                        <div class="dropdown-menu">
                                                            {{-- <a class="dropdown-item" href="#">Voir les details</a> --}}
                                                            <a class="dropdown-item" href="{{route('product.editCategory',$list)}}">Modifier les informations</a>
                                                            <a class="dropdown-item text-danger" href="{{route('product.deleteCategory',$list)}}">Supprimer</a>
                                                        </div>
                                                    </div>

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
