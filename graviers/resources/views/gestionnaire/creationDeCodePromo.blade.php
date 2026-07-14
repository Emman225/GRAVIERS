@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title','Création de code promo')
@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title">Créez un code promo</h2>
                        <p>Gerez facilement vos codes promo</p>
                    </div>
                    <div>
                        <input type="text" placeholder="Search Categories" class="form-control bg-white" />
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
                                            <div class="alert alert-success">
                                                {{session('succes')}}
                                            </div>
                                        @endif
                                        <label for="product_name" class="form-label">Libelle</label>
                                        <input type="text"  value="{{$leCode->libelle}} " name="libelle" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('libelle')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Date de debut du code promo</label>
                                        <input type="date"  value="" name="debut" value="{{Carbon::parse($leCode->debut)->format('d-m-Y')}}" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('parent')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label for="product_name" class="form-label">Date de fin du code promo</label>
                                        <input type="date"  value="" name="fin" value="{{$leCode->fin}}" class="form-control" id="product_name" />
                                        <span class="text-danger">
                                            @error('parent')
                                                {{$message}}
                                            @enderror
                                        </span>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Taux de réduction</label>
                                        <input type="number" required class="form-control" value="{{$leCode->taux_reduction}}" name="taux" id="product_name" />
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


                                    <div class="d-grid">
                                        @if ($leCode->id)
                                            <button type="submit" formaction="{{route('show.codeUpdated',$leCode)}}" class="btn btn-primary">Modifier le code promo</button>
                                            <a href="{{route('show.creationDeCodePromo')}}">Créer un nouveau code</a>
                                        @else
                                            <button type="submit" formaction="{{route('show.enregistrementDeCodePromo')}}" class="btn btn-primary">Générer un code promo</button>
                                        @endif
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-9">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Libelle</th>
                                                <th>Code</th>
                                                <th>Taux de réduction</th>
                                                <th>Début</th>
                                                <th>Fin</th>
                                                <th>Validité</th>
                                                <th>Créé par</th>
                                                <th class="text-end">Action</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reductions as $reduction)


                                            <tr>
                                                <td>{{$reduction->id}}</td>
                                                <td>{{$reduction->libelle}}</td>
                                                <td><b>{{$reduction->code}}</b></td>
                                                <td> {{$reduction->taux_reduction}}% </td>
                                                <td> {{Carbon::parse($reduction->debut)->format('d-m-Y')}} </td>
                                                <td> {{Carbon::parse($reduction->fin)->format('d-m-Y')}}</td>
                                                <td>
                                                    @if($reduction->est_utilise == 1)
                                                        <span class="badge bg-danger">Code invalide</span>
                                                    @elseif($reduction->est_expire)
                                                        <span class="badge bg-secondary">Expiré</span>
                                                    @else
                                                        <span class="badge bg-success">Code valide</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $reduction->user?->nom_prenoms }}
                                                </td>
                                                {{-- <td> {{$list->parent_id}} </td> --}}
                                                <td class="text-end">
                                                    <div class="dropdown">
                                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> </a>
                                                        <div class="dropdown-menu">

                                                            <a class="dropdown-item" href="{{route('show.updateDeCodePromo',$reduction)}}">Modifier les informations</a>
                                                            <a class="dropdown-item text-danger" href="{{route('show.suppressionDeCodePromo',$reduction)}}">Supprimer</a>
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
                order: [],
            });
        });
    </script>
@endsection
