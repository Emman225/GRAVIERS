{{-- @dd($livreurs) --}}

@extends('layout.main')
@section('title','Liste des livreurs')
@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Liste des livreurs - </h2>
    </div>
    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">


            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead>
                        <tr>
                            <th>Livreur</th>
                            <th>Email</th>
                            {{-- <th>prix livraison</th> --}}
                            <!-- <th>Statut</th> -->
                            <th>Enregistré le</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($livreurs as $livreur )


                        <tr>
                            <td >
                                <a href="{{route('show.profile',$livreur->id)}}" class="itemside">
                                    {{-- <div class="left">
                                        <img src="{{ asset('backend/assets/imgs/people/avatar-1.png') }}"
                                            class="img-sm img-avatar" alt="Userpic" />
                                    </div> --}}
                                    <div class="info pl-3">
                                        <h6 class="mb-0 title"> <a href="{{route('grandLivre.livreurDetail',$livreur)}}"> {{strtoupper($livreur->user->nom_prenoms)}}</a></h6>
                                        <!-- <small class="text-muted">Login: {{$livreur->user->login}}</small><br> -->
                                    </div>
                                </a>
                            </td>
                            <td>{{$livreur->user->email}}</td>
                            {{-- <td>{{number_format($livreur->prix_livraison,'0','',' ')}}fcfa</td> --}}
                            <!-- <td><span class="badge rounded-pill text-success">Activé</span></td> -->
                            <td> {{$livreur->created_at}} </td>
                            <td class="text-end">
                                <a href="{{route('show.profile',$livreur->id)}}" class="btn btn-sm btn-brand rounded font-sm mt-15">Voir les details</a>
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
