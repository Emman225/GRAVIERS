@extends('client.main')

{{-- Cette page affiche des vignettes de produit avec un bouton « vue rapide » :
     elle a donc besoin des fenêtres générées par client.quickView. --}}
@section('quickView', 'oui')

@section('title','liste de produit')
@section('content')

{{-- @if(session('sucessLivraison')) --}}
<div class="alert alert-success  text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
    <span>Produit ajouté</span>
</div>
{{-- @endif --}}

{{-- @if(session('deja')) --}}
<div class="alert alert-warning conatiner.fluid text-center deja coller-en-haut" style="display: none" id="notify">
    Vous avez déjà selectionné ce produit
</div>
    <main class="main">
        <div class="page-header mt-30 mb-50">
            <div class="container">
                <div class="archive-header" style="background: url({{asset('frontend/assets/imgs/blog/header-bg-blue.png')}}) no-repeat center center;">
                    <div class="row align-items-center">
                        <div class="col-xl-3">
                            <h1 class="mb-15"> {{$nom}} </h1>
                            <div class="breadcrumb">
                                <a href="{{route('client.index')}}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                                <span></span class="text-black"> Achat <span></span> {{$nom}}
                            </div>
                        </div>
                        <div class="col-xl-9 text-end d-none d-xl-block">
                            <ul class="tags-list">
                                {{-- <li class="hover-up">
                                    <a href="blog-category-grid.html"><i class="fi-rs-cross mr-10"></i>Cabbage</a>
                                </li>
                                <li class="hover-up active">
                                    <a href="blog-category-grid.html"><i class="fi-rs-cross mr-10"></i>Broccoli</a>
                                </li>
                                <li class="hover-up">
                                    <a href="blog-category-grid.html"><i class="fi-rs-cross mr-10"></i>Artichoke</a>
                                </li>
                                <li class="hover-up">
                                    <a href="blog-category-grid.html"><i class="fi-rs-cross mr-10"></i>Celery</a>
                                </li>
                                <li class="hover-up mr-0">
                                    <a href="blog-category-grid.html"><i class="fi-rs-cross mr-10"></i>Spinach</a>
                                </li> --}}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container mb-30">
            <div class="row">
                <div class="col-lg-4-5">
                    <div class="shop-product-fillter">
                        <div class="totall-product">
                            {{-- total() et non count() : depuis la pagination, count()
                                 ne compte que la page affichee, et la phrase annoncerait
                                 20 articles sur une categorie qui en compte 60. --}}
                            <p>Nous Avons trouvé <strong class="text-brand"> {{$produits->total()}} </strong> Articles pour vous!</p>
                        </div>
                        <div class="sort-by-product-area">

                            <div class="sort-by-cover">
                                <div class="sort-by-product-wrap">
                                    <div class="sort-by">
                                        <span><i class="fi-rs-apps-sort"></i>Trier par :</span>
                                    </div>
                                    {{-- Le tri EN COURS, pas un libelle fige. L'entete
                                         affichait « Tendance » en dur : le visiteur ne
                                         pouvait pas savoir sur quoi la liste etait
                                         classee. --}}
                                    <div class="sort-by-dropdown-wrap">
                                        <span> {{ $tris[$tri] }} <i class="fi-rs-angle-small-down"></i></span>
                                    </div>
                                </div>
                                <div class="sort-by-dropdown">
                                    <ul>
                                        {{-- Les cinq entrees pointaient sur « # » : le menu
                                             s'ouvrait, se refermait, et ne triait rien.
                                             Chacune porte maintenant son critere, en
                                             conservant le filtre par prix deja pose. --}}
                                        @foreach ($tris as $cle => $libelle)
                                            <li>
                                                <a class="{{ $tri === $cle ? 'active' : '' }}"
                                                   href="{{ request()->fullUrlWithQuery(['tri' => $cle, 'page' => null]) }}">
                                                    {{ $libelle }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row product-grid">
                        @foreach($produits as $produit)
                            <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                                <div class="product-cart-wrap mb-30">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img product-img-zoom">
                                            <a href="{{route('client.produit.info',$produit)}}">
                                                @foreach($produit->image as $image)
                                                    <img class="default-img" src="{{ asset('storage/'.$image->image) }}" alt="{{ $produit->nom }}" />
                                                @endforeach
                                            </a>
                                        </div>
                                        <div class="product-action-1">
                                            <a aria-label="Ajouter aux favoris" title="Ajouter aux favoris" class="action-btn" href="{{route('client.like',$produit)}}"><i class="fi-rs-heart"></i></a>
                                            {{-- <a aria-label="Compare" class="action-btn" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a> --}}
                                            {{-- La fenêtre générée par client.quickView s'appelle « quickView{id} »,
                                                 sans « Modal ». Le bouton visait « quickViewModal{id} » : Bootstrap ne
                                                 trouvait aucune fenêtre de ce nom et le clic ne produisait rien, sur
                                                 cette page uniquement — les quatre autres listes visent la bonne. --}}
                                            <a aria-label="Vue rapide" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickView{{$produit->id}}"><i class="fi-rs-eye"></i></a>
                                        </div>
                                        <div class="product-badges product-badges-position product-badges-mrg">
                                            @if($produit->prix_reduction > 0)<span class="hot">Promo</span>@endif
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="product-category">
                                            @foreach ($produit->categories as $categorie )
                                                <a href="{{route('client.produit.info',$produit)}}"> {{$categorie->nom}} </a>
                                            @endforeach
                                        </div>
                                        <h2><a href="{{route('client.produit.info',$produit)}}">{{$produit->nom}}</a></h2>
                                        <div class="product-rate-cover">
                                            <div class="product-rate d-inline-block">
                                                <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                            </div>
                                            <span class="font-small ml-5 text-muted"> ({{round(($produit->meilleur_note*5)/100,1)}})</span>
                                        </div>
                                        <div>
                                            {{-- <span class="font-small text-muted">By <a href="vendor-details-1.html">NestFood</a></span> --}}
                                        </div>
                                        <div class="product-card-bottom d-flex flex-column">
                                            <div class="product-price">
                                                @if(isset($prixPerso[$produit->id]))
                                                    <span> {{number_format($prixPerso[$produit->id],0,'','.')}} fcfa</span>
                                                    <span class="old-price">{{number_format($produit->prix_moyen,0,'','.')}} fcfa</span>
                                                @else
                                                    <span> {{number_format($produit->prix_moyen,0,'','.')}} fcfa</span>
                                                    @if ($produit->prix_reduction > 0)
                                                        <span class="old-price">{{number_format($produit->prix_reduction,0,'',' ')}} fcfa</span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="add-cart">
                                                <a class="add" onclick="ajouter({{$produit->id}})"><i class="fi-rs-shopping-cart mr-5"></i>Ajouter </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <!--end product card-->

                    </div>
                    <!--product grid-->
                {{-- UNE PAGINATION QUI DIT LA VERITE.

                     La page annoncait « 1 2 3 ... 6 » en dur, quel que soit le nombre
                     d'articles : six pages pour quatre produits, et aucun lien ne menait
                     nulle part. Le bloc entier avait fini par etre mis en commentaire.

                     Attention en le modifiant : un commentaire Blade NE S'IMBRIQUE PAS.
                     Ouvrir un second commentaire a l'interieur d'un premier fait fermer
                     celui-ci trop tot ; tout ce qui suit, marqueur de fermeture compris,
                     s'affiche alors en clair dans la page. --}}
                <div class="pagination-area mt-20 mb-20">
                    <nav aria-label="Pagination des produits">
                        {{ $produits->onEachSide(1)->links() }}
                    </nav>
                </div>
                    {{-- Section "Promo du jour" de démonstration du thème (produits factices,
                         textes en anglais et prix en dollars) désactivée. --}}
                    @if(false)
                    <section class="section-padding pb-5">
                        <div class="section-title">
                            <h3 class="">Pomo du jour</h3>
                            <a class="show-all" href="shop-grid-right.html">
                                Toutes les promos
                                <i class="fi-rs-angle-right"></i>
                            </a>
                        </div>
                        <div class="row">
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/counter.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="2025/03/25 00:00:00"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Gravier</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 90%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (4.0)</span>
                                            </div>
                                            <div>
                                                {{-- <span class="font-small text-muted">By <a href="vendor-details-1.html">NestFood</a></span> --}}
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$32.85</span>
                                                    <span class="old-price">$33.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Ajouter </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/counter2.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="2026/04/25 00:00:00"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Perdue Simply Smart Organics Gluten</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 90%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (4.0)</span>
                                            </div>
                                            <div>
                                                <span class="font-small text-muted">By <a href="vendor-details-1.html">Old El Paso</a></span>
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$24.85</span>
                                                    <span class="old-price">$26.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-4 col-md-6 d-none d-lg-block">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/counter3.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="2027/03/25 00:00:00"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Signature Wood-Fired Mushroom</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 80%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (3.0)</span>
                                            </div>
                                            <div>
                                                <span class="font-small text-muted">By <a href="vendor-details-1.html">Progresso</a></span>
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$12.85</span>
                                                    <span class="old-price">$13.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-4 col-md-6 d-none d-xl-block">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/counter4.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="2025/02/25 00:00:00"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Simply Lemonade with Raspberry Juice</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 80%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (3.0)</span>
                                            </div>
                                            <div>
                                                <span class="font-small text-muted">By <a href="vendor-details-1.html">Yoplait</a></span>
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$15.85</span>
                                                    <span class="old-price">$16.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-4 col-md-6 d-none d-xl-block">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/counter5.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="2025/02/25 00:00:00"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Simply Lemonade with Raspberry Juice</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 80%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (3.0)</span>
                                            </div>
                                            <div>
                                                <span class="font-small text-muted">By <a href="vendor-details-1.html">Yoplait</a></span>
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$15.85</span>
                                                    <span class="old-price">$16.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    @endif
                    <!--End Deals-->
                </div>
                {{-- Le filtre par prix n'a de sens que sur cette page : la barre
                     laterale est partagee avec trois autres vues, ou il ne
                     filtrerait rien. On le demande explicitement. --}}
                @include('client.categorieEtFiltreur', ['filtrePrix' => true])
            </div>
        </div>
    </main>

@endsection
