
@php
    use Illuminate\Support\carbon;

    // dd($blog->clients);
@endphp
@extends('layout.main')
@section('title','Liste des gestionnaires')

@section('contenu')

<div class="content-header">
    <div>
        <h2 class="content-title card-title">Comment sur les produits</h2>
        {{-- <p>Vous </p> --}}
    </div>
    <div>
        @if (session('ok'))
            <div class="alert alert-success" id="notify">
                {{session('ok')}}
            </div>
        @endif
        @if (session('no'))
            <div class="alert alert-danger" id="notify">
                {{session('no')}}
            </div>
        @endif

    </div>
</div>
<div class="card mb-4">
    <header class="card-header">
        <div class="row gx-3">
            <div class="col-lg-4 col-md-6 me-auto">

            </div>
            <div class="col-lg-2 col-md-3 col-6">

            </div>
            <div class="col-lg-2 col-md-3 col-6">

            </div>
        </div>
    </header>
    <!-- card-header end// -->
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>

                        <th>#ID</th>
                        <th>Client</th>
                        <th>commentaire</th>
                        <th>Note</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($blog->clients as $client )
                        <tr>
                            <td>
                                identifiant
                            </td>

                            <td> {{$client->nom}} </td>

                            <td><b> {{$client->pivot->commentaire}} </b></td>

                            {{-- <td> {{$client->pivot->note}} </td> --}}
                            <td>
                                <ul class="rating-stars">
                                    <li style="width: {{$client->pivot->note*20}}%" class="stars-active">
                                        <img src="{{asset('backend/assets/imgs/icons/stars-active.svg')}}" alt="stars" />
                                    </li>
                                    <li>
                                        <img src="{{asset('backend/assets/imgs/icons/starts-disable.svg')}}" alt="stars" />
                                    </li>
                                </ul>
                            </td>

                            <td> {{$client->pivot->created_at->isoFormat('LL')}} </td>

                            <td>
                                @switch($client->pivot->statut)
                                    @case(1)
                                        <span class="badge bg-secondary">En attente</span>
                                        @break
                                    @case(2)
                                        <span class="badge bg-success">Publié</span>
                                        @break
                                    @case(3)
                                        <span class="badge bg-danger">Annulé</span>
                                        @break
                                    @default

                                @endswitch
                            </td>

                            <td class="text-end">
                                {{-- <a href="#" class="btn btn-md rounded font-sm">Detail</a> --}}
                                <div class="dropdown">
                                    <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm"> <i class="material-icons md-more_horiz"></i> </a>
                                    <div class="dropdown-menu">
                                        {{-- <a class="dropdown-item" href="#">View detail</a> --}}
                                        <a class="dropdown-item" href="{{route('show.publierCommentaireBlog',$client->pivot->id)}}">Publier</a>
                                        <a class="dropdown-item text-danger" href="{{route('show.annulerCommentaireBlog',$client->pivot->id)}}">Annuler</a>
                                    </div>
                                </div>
                                <!-- dropdown //end -->
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <!-- table-responsive//end -->
    </div>
    <!-- card-body end// -->
</div>


@endsection
