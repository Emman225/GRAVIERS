@extends('layout.main')
@section('title', 'Devis')
@section('contenu')
    {{-- @dd($devis) --}}

    <nav class="nav container-fluid">
        <p class="h3 d-flex"><span class="material-symbols-outlined text-success">
            check_circle
            </span>Commande validé</p>
    </nav>

    <div class="container">
        <div class="row container d-flex justify-content-around  mt-20">
            {{-- @dump($selection) --}}

                <div class="card shadow-sm" style="width: 18rem;">
                    <div class="card-body text-center">
                        <h5 class="card-title"> Commande n° : {{ $commande->numero }} </h5>

                        @foreach ($details as $detail)
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
                    <form action="{{route('client.devis')}}" class="text-center row">
                        <button class="btn btn-primary mt-2 d-block" type="submit">Revenir à la liste des dévis</button>
                    </form>
                </div>

    </div>
    </div>


@endsection
