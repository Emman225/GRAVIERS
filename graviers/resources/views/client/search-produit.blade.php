@extends('client.main')
@section('title','Resultat')
@section('content')
<div class="alert alert-success  text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
    <span>Produit ajouté</span>
</div>
<div class="alert alert-success  text-center like coller-en-haut mt-5" style="display: none;" id="notify">
    <span class="rep"></span>
</div>
{{-- @endif --}}

{{-- @if(session('deja')) --}}
<div class="alert alert-warning conatiner.fluid text-center deja coller-en-haut" style="display: none" id="notify">
    Vous avez déjà selectionné ce produit
</div>
<div class="container">
    <div class="tab-pane fade show active" id="tab-one" role="tabpanel" aria-labelledby="tab-one">
        @if(!$produits->isEmpty())
        <div class="row product-grid-4 mt-5">
            @foreach($produits as $produit)
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="product-cart-wrap mb-30">
                        <div class="product-img-action-wrap">
                            <div class="product-img product-img-zoom">
                                <a href="{{route('client.produit.info',$produit)}}">
                                    @foreach($produit->image as $image) 
                                    <img class="default-img" src="storage/{{$image->image}}" alt="" />
                                    @endforeach
                                    {{-- <img class="hover-img" src="assets/imgs/shop/product-1-2.jpg" alt="" /> --}}
                                </a>
                            </div>

                            <div class="product-action-1">
                                <a aria-label="J'aime" class="action-btn" onclick="jaime({{$produit->id}})"><i class="fi-rs-heart"></i></a>
                                {{-- <a aria-label="Compare" class="action-btn" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a> --}}
                                <a aria-label="Vue rapide" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickView{{$produit->id}}"><i class="fi-rs-eye"></i></a>
                            </div>
                        </div>
                        <div class="product-content-wrap">
                            <div class="product-category">
                                @foreach ($produit->categories as $categorie)
                                    <a href="">{{$categorie->nom}}</a>
                                @endforeach
                            </div>
                            <h2><a href="{{route('client.produit.info',$produit)}}"> {{$produit->nom}} </a></h2>
                            <div class="product-rate-cover">
                                <div class="product-rate d-inline-block">
                                    <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                </div>
                                <span class="font-small ml-5 text-muted"> ({{round(($produit->meilleur_note*5)/100,1)}})</span>
                            </div>
                            <div>
                                {{-- <span class="font-small text-muted">By <a href="vendor-details-1.html">NestFood</a></span> --}}
                            </div>
                            <div class="product-card-bottom">
                                <div class="product-price">
                                    @if(isset($prixPerso[$produit->id]))
                                        <span> {{number_format($prixPerso[$produit->id],0,'','.')}} fcfa </span>
                                        @if($produit->prix_moyen > $prixPerso[$produit->id])
                                            <span class="old-price">{{number_format($produit->prix_moyen,0,'','.')}} fcfa</span>
                                        @endif
                                    @else
                                        <span> {{number_format($produit->prix_moyen,0,'','.')}} fcfa </span>
                                        @if($produit->prix_reduction > $produit->prix_moyen)
                                            <span class="old-price">{{number_format($produit->prix_reduction,0,'','.')}} fcfa</span>
                                        @endif
                                    @endif
                                </div>

                                    {{-- <form action=""> --}}
                                        {{-- <livewire:add-cart/> --}}
                                        <div class="add-cart">
                                            <a class="add" onclick="ajouter({{$produit->id}})"><i class="fi-rs-shopping-cart mr-5"></i>Ajouter </a>
                                        </div>

                                        {{-- <a class="add" href="{{route('client.ajout.panier',$produit)}}"><i class="fi-rs-shopping-cart mr-5"></i>Ajouter </a> --}}
                                    {{-- </form> --}}

                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
            <!--end product card-->

        </div>
        @else
        <h4 class="text-center">Aucun article trouvé !!!</h4>
        @endif
        <!--End product-grid-4-->
    </div>
</div>
@endsection
