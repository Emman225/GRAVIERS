@extends('layout.main')
@section('title','Ajout de pays')
@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Pays</h2>
                        <p>Ajoutez, modifiez ou supprimez un pays</p>
                    </div>
                    <div>
                        {{-- <a href="{{route('product.category')}}" class="btn btn-primary"> + Catégorie </a> --}}
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <form method="post" action="" enctype="multipart/form-data">
                                    @csrf
                                    @if(session('succes'))
                                        <div class="alert alert-success text-center">
                                            {{session('succes')}}
                                        </div>
                                    @endif
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Nom</label>
                                        <input type="text"  value="" name="nom" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('nom')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Code</label>
                                        <input type="text"  value="" name="code" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('code')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Indicatif</label>
                                        <input type="text"  value="" name="indicatif" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('indicatif')
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
                                                <th>Code</th>
                                                <th>Indicatif</th>

                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($pays as $p)


                                            <tr>
                                                <td class="text-center">

                                                </td>

                                                <td>{{$p->id}}</td>

                                                <td><b>{{$p->nom}}</b></td>

                                                <td> {{$p->code}} </td>
                                                <td> {{$p->indicatif}} </td>

                                                <td class="text-end">
                                                    <div class="dropdown">
                                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> </a>
                                                        <div class="dropdown-menu">
                                                            {{-- <a class="dropdown-item" href="#">Voir les details</a> --}}
                                                            <a class="dropdown-item" href="">Modifier les informations</a>
                                                            <a class="dropdown-item text-danger" href="">Supprimer</a>
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
