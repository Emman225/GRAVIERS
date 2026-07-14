


@section('title','Mon panier')


    @extends('layout.main')
    @section('contenu')


@if (session('ok'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{ session('ok') }}
    </div>
@endif


<div class="container-fluid row">


    <div class="col-8">

        <div class="card">
            <div class="row">
                @if (Cart::count() > 0)

                    <div class="row mt-10">
                        <h3 class="text-center">Vous avez ajoutez ces produits à votre panier</h3>
                    </div>
                    <div class="row container d-flex justify-content-around  mt-20">
                        {{-- @dump($selection) --}}
                        @foreach (Cart::content() as $produit)
                            <div class="card shadow-sm" style="width: 18rem;">
                                <img src="" class="card-img-top" alt="...">
                                <div class="card-body text-center">
                                    <h5 class="card-title"> {{ $produit->model->nom }} </h5>
                                    <p> {{ $produit->model->unite }} FCFA</p>
                                    {{-- <p class="card-text">{{ $produit->model->description }}</p> --}}
                                    {{-- <form action="" method="post"> --}}
                                    @csrf
                                    @method('delete')
                                    <div class="row">
                                        <div class="col-2">
                                            <form action="{{ route('client.decremente', $produit->rowId) }}">
                                                <button class="btn border">-</button>
                                            </form>
                                        </div>
                                        <div class="col-8">
                                            <input type="text" name="qte" value="{{ $produit->qty }}"
                                                class="form-control text-center">
                                        </div>
                                        <div class="col-2">
                                            <form action="{{ route('client.incremente', $produit->rowId) }}">
                                                <button class="btn btn border ">+</button>
                                            </form>
                                        </div>
                                    </div>
                                    {{-- </form> --}}
                                    <form action="" class="text-center row">
                                        <button class="btn btn-primary mt-2 d-block" type="submit">Voir les
                                            details</button>
                                        <button class="btn border mt-2 d-block" type="submit">Supprimer</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach

                    </div>

            </div>
        </div>
    </div>
    {{-- @dd(Cart::total()) --}}
    <div class="col-4">
        <div class="card container">
            <h3 class="fw-bold text-center h1">Devis</h3>
            <table class="table table-striped">
                <thead>
                    <th></th>
                    <th></th>
                    <th></th>
                </thead>
                <tbody>
                    @php $total = 0 @endphp
                    @foreach (Cart::content() as $produit)
                        <tr>
                            <td> {{ $produit->model->nom }} </td>
                            <td> {{ $produit->qty }} </td>
                            <td> {{ $produit->model->unite }} </td>
                        </tr>
                        @php $total += ($produit->model->unite*$produit->qty)  @endphp
                    @endforeach
                    <tr>
                        <td class="btn  d-block h1" colspan="3">
                            <h3 class="">Total : {{ Cart::total() }} FCFA </h3 class="text->white">
                            <small class="text-danger">(Hors frais de livraison)</small>
                        </td>
                    </tr>
                    <tr>

                        <td class="" colspan="3">
                            <form action="{{ route('client.panierDevis') }}">
                                <button class="btn btn-primary d-block h3 text-white">Enregistrer le devis</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @elseif (session('converti'))
        {{session('converti')}}
    @else
        <h2>Aucun article selectionné</h2>
    @endif

</div>



    @endsection



