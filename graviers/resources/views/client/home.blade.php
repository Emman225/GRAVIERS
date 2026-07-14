
@extends('layout.main')

@section('title','Tableau de bord')
@section('contenu')
@if(session('ok'))

<div class="alert alert-success conatiner.fluid text-center" id="notify">

    {{session('ok')}}
</div>
@endif
@if(session('deja'))
<div class="alert alert-warning conatiner.fluid text-center" id="notify">
    {{session('deja')}}
</div>
@endif
<div class="card">
    <div class="row">
        <div class="col-lg-2 col-sm-12">
            Gauche
        </div>

        {{-- slide --}}
        <div class="col-lg-8 col-sm-12">
            <div id="carouselExampleDark" class="carousel carousel-dark slide">
                <div class="carousel-indicators">
                <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="1" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                <div class="carousel-item active" data-bs-interval="10000">
                    <img src="{{asset('backend/assets/imgs/theme/logoAvecFond1.jpg')}}" class="d-block w-100" alt="...">
                    <div class="carousel-caption d-none d-md-block">
                    <h5>Immobilier - Location - Distribution</h5>
                    <p>Nous proposons des produits de qualité et un bref delai de livraison</p>
                    </div>
                </div>
                <div class="carousel-item" data-bs-interval="2000">
                    <img src="{{asset('backend/assets/imgs/theme/gravier.png')}}" class="d-block w-100" alt="...">
                    <div class="carousel-caption d-none d-md-block">
                    <h5>Gravier</h5>
                    <p>Des gravier de qualité 0/5, 5/15 et plus encore</p>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="{{asset('backend/assets/imgs/theme/sable.jpg')}}" class="d-block w-100" alt="...">
                    <div class="carousel-caption d-none d-md-block">
                    <h5>Sable de construction</h5>
                    <p>Du sable pour la construction de vos édifices</p>
                    </div>
                </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleDark" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleDark" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
        <div class="col-lg-2 col-sm-12">
            Droite
        </div>

    </div>

    <div class="row mt-10">
        <h3 class="text-center">Nos produits</h3>
    </div>
    <div class="row container d-flex justify-content-around  mt-20" x-data = "{selection : []}">
        {{-- @dump($selection) --}}
        @foreach ($produits as $produit )

            <div class="card col-3"  style="width: 18rem;">
                <img src="" class="card-img-top" alt="...">
                <div class="card-body">
                <h5 class="card-title"> {{$produit->produit->nom}} </h5>
                <p> {{$produit->produit->unite}} FCFA</p>
                <p class="card-text">{{$produit->produit->description}}</p>
                <form action="{{route('client.ajout.panier',$produit->produit)}}">
                    @csrf
                    <button class="btn btn-primary d-block d-flex" type="submit">
                        <span class="material-symbols-outlined">shopping_cart</span>
                        Ajouter au panier
                    </button>
                </form>
                <form action="">
                    <button class="btn border mt-2 d-block" >Voir les détails</button>
                </form>
                </div>
            </div>
        @endforeach

    </div>
</div>
</div>
@endsection
