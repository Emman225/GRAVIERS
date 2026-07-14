@extends('layout.main')
@section('title', 'Devis')
@section('contenu')
    {{-- @dd($devis) --}}
@if(session('success'))
    <div class="alert alert-success d-flex" id="notify"><span class="material-symbols-outlined">check_circle</span> {{session('success')}}
    </div>
@endif
    <nav class="nav container-fluid">
        <p class="h3">Liste des dévis</p>
    </nav>

    <div class="container">
        <div class="row container d-flex justify-content-around  mt-20">
            {{-- @dump($selection) --}}
            @foreach ($devis as $devi)
                <div class="card shadow-sm" style="width: 18rem;">
                    <div class="card-body text-center">
                        <h5 class="card-title"> Dévis n° : {{ $devi->numero }} </h5>

                        @foreach ($devi->detaildevis as $detail)
                            <div class="row">
                                <div class="col-6">
                                    <p> {{ $detail->produit->nom }} </p>
                                </div>
                                <div class="col-2">
                                    <p> {{ $detail->qte }} </p>
                                </div>
                                <div class="col-4">
                                    <p> {{ $detail->prix }} </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="btn border text-center"> Montant Total: <span class="fw-bold">  {{ $devi->montant}} FCFA </span> </p>
                    <form action="{{route('client.devisAdresse',$devi)}}" class="text-center row">
                        <button class="btn btn-primary mt-2 d-block" type="submit">Passer la commande</button>
                    </form>
                </div>
        @endforeach

    </div>
    </div>


@endsection
