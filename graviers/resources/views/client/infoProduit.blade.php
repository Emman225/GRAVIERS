@php
    use Illuminate\Support\Carbon;

@endphp
@extends('client.main')
@section('title', 'detail ' . $produit->nom)
@section('content')


<div class="alert alert-success  text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
    <span>Produit ajouté</span>
</div>

<div class="alert alert-warning conatiner.fluid text-center deja coller-en-haut" style="display: none" id="notify">
    Vous avez déjà selectionné ce produit
</div>

    @include('client.navMobile')
    <main class="main">
        {{-- <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> <a href="shop-grid-right.html">
                        @php
                            $i = 1;
                        @endphp
                        @foreach ($produit->categories as $categorie)
                            {{ $i > 1 ? '|' : '' }}
                            <a href="">{{ $categorie->nom }}</a>
                            @php $i++ @endphp
                        @endforeach
                    </a> <span></span> {{ $produit->nom }}
                </div>
            </div>
        </div> --}}
        @if (session('rated'))
            <div class="alert alert-success container text-center" id="notify">
                {{ session('rated') }}
            </div>
        @endif
        <div class="container mb-30">
            <div class="row">
                <div class="col-xl-11 col-lg-12 m-auto">
                    <div class="row">
                        <div class="col-xl-9">
                            <div class="product-detail accordion-detail">
                                <div class="row mb-50 mt-30">

                                    {{-- Galerie --}}
                                    <div class="col-md-6 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
                                        <div class="detail-gallery">
                                            <span class="zoom-icon"><i class="fi-rs-search"></i></span>
                                            <!-- MAIN SLIDES -->
                                            <div class="product-image-slider">
                                                @foreach ($produit->image as $image)
                                                    <figure class="border-radius-10">
                                                        <img src="/storage/{{ $image->image }}" alt="product image" />
                                                    </figure>
                                                @endforeach
                                            </div>
                                            <!-- THUMBNAILS -->
                                            <div class="slider-nav-thumbnails">
                                                @foreach ($produit->image as $image)
                                                    <div><img src="/storage/{{ $image->image }}" alt="product image" />
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        <!-- End Gallery -->
                                    </div>

                                    {{-- info droit --}}
                                    <div class="col-md-6 col-sm-12 col-xs-12" style="position: relative; z-index: 2;">
                                        <div class="detail-info pr-30 pl-30">
                                            {{-- <span class="stock-status out-stock"> Sale Off </span> --}}
                                            <h2 class="title-detail"> {{ $produit->nom }} </h2>
                                            <div class="product-detail-rating">
                                                <div class="product-rate-cover text-end">
                                                    <div class="product-rate d-inline-block">
                                                        <div class="product-rating"
                                                            style="width: {{ $produit->meilleur_note }}%"></div>
                                                    </div>
                                                    {{-- <span class="font-small ml-5 text-muted"> (32 commentaire)</span> --}}
                                                </div>
                                            </div>
                                            <div class="clearfix product-price-cover">
                                                <div class="product-price primary-color float-left">
                                                    @if(isset($prixPerso) && isset($prixPerso[$produit->id]))
                                                        <span class="current-price text-brand">
                                                            {{ number_format($prixPerso[$produit->id], 0, '', ' ') }} fcfa </span>
                                                        @if ($produit->prix_moyen > $prixPerso[$produit->id])
                                                            <span>
                                                                <span class="old-price font-md ml-15">
                                                                    {{ number_format($produit->prix_moyen, 0, '', ' ') }} fcfa</span>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="current-price text-brand">
                                                            {{ number_format($produit->prix_moyen, 0, '', ' ') }} fcfa </span>
                                                        @if ($produit->prix_reduction > $produit->prix_moyen)
                                                            <span>
                                                                <span
                                                                    class="save-price font-md color3 ml-15">-{{ intval((($produit->prix_reduction - $produit->prix_moyen) / $produit->prix_reduction) * 100) }}%
                                                                    de réduction</span>
                                                                <span class="old-price font-md ml-15">
                                                                    {{ $produit->prix_reduction }} fcfa</span>
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="short-desc mb-30">
                                                <p class="font-lg">{{ $produit->description }}.</p>
                                            </div>

                                            {{-- Bandeau d'avantages premium --}}
                                            <div class="pd-trust-row">
                                                <div class="pd-trust-item">
                                                    <i class="material-icons md-local_shipping"></i>
                                                    <span>Livraison rapide</span>
                                                </div>
                                                <div class="pd-trust-item">
                                                    <i class="material-icons md-verified"></i>
                                                    <span>Qualité garantie</span>
                                                </div>
                                                <div class="pd-trust-item">
                                                    <i class="material-icons md-support_agent"></i>
                                                    <span>Support 7j/7</span>
                                                </div>
                                            </div>
                                            {{-- <div class="attr-detail attr-size mb-30">
                                                <strong class="mr-10">Size / Weight: </strong>
                                                <ul class="list-filter size-filter font-small">
                                                    <li><a href="#">50g</a></li>
                                                    <li class="active"><a href="#">60g</a></li>
                                                    <li><a href="#">80g</a></li>
                                                    <li><a href="#">100g</a></li>
                                                    <li><a href="#">150g</a></li>
                                                </ul>
                                            </div> --}}
                                            <div class="detail-extralink mb-50">
                                                <div class="detail-qty border radius">
                                                    <a href="#" class="qty-down" onclick="changerQuantite(event,-1)"><i
                                                            class="fi-rs-angle-small-down"></i></a>
                                                    <input type="number" name="quantity" id="qtyProduit" class="qty-val" value="1"
                                                        min="1" step="1">
                                                    <a href="#" class="qty-up" onclick="changerQuantite(event,1)"><i
                                                            class="fi-rs-angle-small-up"></i></a>
                                                </div>
                                                <div class="product-extra-link2">
                                                    {{-- <form action="{{ route('client.ajout.panier', $produit) }}"> --}}
                                                        <button type="button" onclick="ajouter({{$produit->id}}, document.getElementById('qtyProduit').value)" class="button button-add-to-cart"><i
                                                                class="fi-rs-shopping-cart"></i>Ajouter au panier</button>
                                                        <a aria-label="Ajouter aux favoris" title="Ajouter aux favoris" class="action-btn hover-up"
                                                            onclick="jaime({{$produit->id}})"><i class="fi-rs-heart"></i></a>
                                                    {{-- </form> --}}
                                                    {{-- <a aria-label="Compare" class="action-btn hover-up" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a> --}}
                                                </div>
                                            </div>
                                            <div class="font-xs">
                                                <ul class="mr-50 float-start">
                                                    <li class="mb-5">Catégorie:
                                                        <span class="text-brand">
                                                            @php
                                                                $i = 1;
                                                            @endphp
                                                            @foreach ($produit->categories as $categorie)
                                                                {{ $i > 1 ? '|' : '' }}
                                                                <a href="">{{ $categorie->nom }}</a>
                                                                @php $i++ @endphp
                                                            @endforeach
                                                        </span>
                                                    </li>

                                                </ul>
                                                <ul class="float-start">
                                                    <li class="mb-5">Abréviation: <a
                                                            href="#">{{ $produit->abreviation }}</a></li>

                                                </ul>
                                            </div>
                                        </div>
                                        <!-- Detail Info -->
                                    </div>
                                </div>

                                {{-- cadre des info --}}
                                <div class="product-info">
                                    <div class="tab-style3">
                                        {{-- Les boutons --}}
                                        <ul class="nav nav-tabs text-uppercase">
                                            {{-- <li class="nav-item">
                                            <a class="nav-link active" id="Description-tab" data-bs-toggle="tab" href="#Description">Description</a>
                                        </li> --}}
                                            <li class="nav-item">
                                                <a class="nav-link" id="Additional-info-tab" data-bs-toggle="tab"
                                                    href="#Additional-info">Plus d'information</a>
                                            </li>
                                            {{-- <li class="nav-item">
                                            <a class="nav-link" id="Vendor-info-tab" data-bs-toggle="tab" href="#Vendor-info">Vendor</a>
                                        </li> --}}
                                            <li class="nav-item">
                                                <a class="nav-link" id="Reviews-tab" data-bs-toggle="tab"
                                                    href="#Reviews">Commentaire ({{ $lesNotes->count() }})</a>
                                            </li>
                                        </ul>
                                        <div class="tab-content shop_info_tab entry-main-content">

                                            {{-- Description --}}
                                            {{-- <div class="tab-pane fade show active" id="Description">
                                            <div class="">
                                                <p>Uninhibited carnally hired played in whimpered dear gorilla koala depending and much yikes off far quetzal goodness and from for grimaced goodness unaccountably and meadowlark near unblushingly crucial scallop tightly neurotic hungrily some and dear furiously this apart.</p>
                                                <p>Spluttered narrowly yikes left moth in yikes bowed this that grizzly much hello on spoon-fed that alas rethought much decently richly and wow against the frequent fluidly at formidable acceptably flapped besides and much circa far over the bucolically hey precarious goldfinch mastodon goodness gnashed a jellyfish and one however because.</p>
                                                <ul class="product-more-infor mt-30">
                                                    <li><span>Type Of Packing</span> Bottle</li>
                                                    <li><span>Color</span> Green, Pink, Powder Blue, Purple</li>
                                                    <li><span>Quantity Per Case</span> 100ml</li>
                                                    <li><span>Ethyl Alcohol</span> 70%</li>
                                                    <li><span>Piece In One</span> Carton</li>
                                                </ul>
                                                <hr class="wp-block-separator is-style-dots" />

                                                <p>Laconic overheard dear woodchuck wow this outrageously taut beaver hey hello far meadowlark imitatively egregiously hugged that yikes minimally unanimous pouted flirtatiously as beaver beheld above forward energetic across this jeepers beneficently cockily less a the raucously that magic upheld far so the this where crud then below after jeez enchanting drunkenly more much wow callously irrespective limpet.</p>
                                                <h4 class="mt-30">Packaging & Delivery</h4>
                                                <hr class="wp-block-separator is-style-wide" />
                                                <p>Less lion goodness that euphemistically robin expeditiously bluebird smugly scratched far while thus cackled sheepishly rigid after due one assenting regarding censorious while occasional or this more crane went more as this less much amid overhung anathematic because much held one exuberantly sheep goodness so where rat wry well concomitantly.</p>
                                                <p>Scallop or far crud plain remarkably far by thus far iguana lewd precociously and and less rattlesnake contrary caustic wow this near alas and next and pled the yikes articulate about as less cackled dalmatian in much less well jeering for the thanks blindly sentimental whimpered less across objectively fanciful grimaced wildly some wow and rose jeepers outgrew lugubrious luridly irrationally attractively dachshund.</p>
                                                <h4 class="mt-30">Suggested Use</h4>
                                                <ul class="product-more-infor mt-30">
                                                    <li>Refrigeration not necessary.</li>
                                                    <li>Stir before serving</li>
                                                </ul>
                                                <h4 class="mt-30">Other Ingredients</h4>
                                                <ul class="product-more-infor mt-30">
                                                    <li>Organic raw pecans, organic raw cashews.</li>
                                                    <li>This butter was produced using a LTG (Low Temperature Grinding) process</li>
                                                    <li>Made in machinery that processes tree nuts but does not process peanuts, gluten, dairy or soy</li>
                                                </ul>
                                                <h4 class="mt-30">Warnings</h4>
                                                <ul class="product-more-infor mt-30">
                                                    <li>Oil separation occurs naturally. May contain pieces of shell.</li>
                                                </ul>
                                            </div>
                                        </div> --}}


                                            {{-- Informations additionnels --}}
                                            <div class="tab-pane fade" id="Additional-info">
                                                <table class="font-md">
                                                    <tbody>
                                                        <tr class="stand-up">
                                                            <th>Stand Up</th>
                                                            <td>
                                                                <p>35″L x 24″W x 37-45″H(front to back wheel)</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="folded-wo-wheels">
                                                            <th>Folded (w/o wheels)</th>
                                                            <td>
                                                                <p>32.5″L x 18.5″W x 16.5″H</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="folded-w-wheels">
                                                            <th>Folded (w/ wheels)</th>
                                                            <td>
                                                                <p>32.5″L x 24″W x 18.5″H</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="door-pass-through">
                                                            <th>Door Pass Through</th>
                                                            <td>
                                                                <p>24</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="frame">
                                                            <th>Frame</th>
                                                            <td>
                                                                <p>Aluminum</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="weight-wo-wheels">
                                                            <th>Weight (w/o wheels)</th>
                                                            <td>
                                                                <p>20 LBS</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="weight-capacity">
                                                            <th>Weight Capacity</th>
                                                            <td>
                                                                <p>60 LBS</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="width">
                                                            <th>Width</th>
                                                            <td>
                                                                <p>24″</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="handle-height-ground-to-handle">
                                                            <th>Handle height (ground to handle)</th>
                                                            <td>
                                                                <p>37-45″</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="wheels">
                                                            <th>Wheels</th>
                                                            <td>
                                                                <p>12″ air / wide track slick tread</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="seat-back-height">
                                                            <th>Seat back height</th>
                                                            <td>
                                                                <p>21.5″</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="head-room-inside-canopy">
                                                            <th>Head room (inside canopy)</th>
                                                            <td>
                                                                <p>25″</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="pa_color">
                                                            <th>Color</th>
                                                            <td>
                                                                <p>Black, Blue, Red, White</p>
                                                            </td>
                                                        </tr>
                                                        <tr class="pa_size">
                                                            <th>Size</th>
                                                            <td>
                                                                <p>M, S</p>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            {{-- Informations de Fournisseurs --}}
                                            {{-- <div class="tab-pane fade" id="Vendor-info">
                                            <div class="vendor-logo d-flex mb-30">
                                                <img src="assets/imgs/vendor/vendor-18.svg" alt="" />
                                                <div class="vendor-name ml-15">
                                                    <h6>
                                                        <a href="vendor-details-2.html">Noodles Co.</a>
                                                    </h6>
                                                    <div class="product-rate-cover text-end">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating" style="width: 90%"></div>
                                                        </div>
                                                        <span class="font-small ml-5 text-muted"> (32 reviews)</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <ul class="contact-infor mb-50">
                                                <li><img src="assets/imgs/theme/icons/icon-location.svg" alt="" /><strong>Address: </strong> <span>5171 W Campbell Ave undefined Kent, Utah 53127 United States</span></li>
                                                <li><img src="assets/imgs/theme/icons/icon-contact.svg" alt="" /><strong>Contact Seller:</strong><span>(+91) - 540-025-553</span></li>
                                            </ul>
                                            <div class="d-flex mb-55">
                                                <div class="mr-30">
                                                    <p class="text-brand font-xs">Rating</p>
                                                    <h4 class="mb-0">92%</h4>
                                                </div>
                                                <div class="mr-30">
                                                    <p class="text-brand font-xs">Ship on time</p>
                                                    <h4 class="mb-0">100%</h4>
                                                </div>
                                                <div>
                                                    <p class="text-brand font-xs">Chat response</p>
                                                    <h4 class="mb-0">89%</h4>
                                                </div>
                                            </div>
                                            <p>
                                                Noodles & Company is an American fast-casual restaurant that offers international and American noodle dishes and pasta in addition to soups and salads. Noodles & Company was founded in 1995 by Aaron Kennedy and is headquartered in Broomfield, Colorado. The company went public in 2013 and recorded a $457 million revenue in 2017.In late 2018, there were 460 Noodles & Company locations across 29 states and Washington, D.C.
                                            </p>
                                        </div> --}}

                                            {{-- Commentaire et ajout de commentaire --}}
                                            <div class="tab-pane fade" id="Reviews">
                                                <!--Comments-->
                                                <div class="comments-area">
                                                    <div class="row">

                                                        {{-- Questions et réponses de clients --}}
                                                        <div class="col-lg-9">
                                                            <h4 class="mb-30">Comentaires d'autres utilisateur</h4>
                                                            <div class="comment-list">

                                                                @if ($produit->notes->count() > 0)
                                                                    @foreach ($produit->notes as $client)
                                                                    {{-- @dd($client->pivot) --}}
                                                                        @if ($client->pivot->statut == 2)
                                                                            <div
                                                                                class="single-comment justify-content-between d-flex mb-30">
                                                                                <div
                                                                                    class="user justify-content-between d-flex">
                                                                                    <div class="thumb text-center">
                                                                                        <img src="assets/imgs/blog/author-2.png"
                                                                                            alt="" />
                                                                                        <a href="#"
                                                                                            class="font-heading text-brand">{{ $client->nom . ' ' . $client->prenom }}</a>
                                                                                    </div>
                                                                                    <div class="desc">
                                                                                        <div class="d-flex justify-content-between mb-10">
                                                                                            <div class="d-flex align-items-center">
                                                                                                <span class="font-xs text-muted">
                                                                                                    {{ Carbon::parse($client->pivot->created_at)->format('d-m-Y à H:i') }}
                                                                                                </span>
                                                                                            </div>
                                                                                            <div class="product-rate d-inline-block">
                                                                                                <div class="product-rating"
                                                                                                    style="width: {{ $client->pivot->note * 20 }}%">
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>

                                                                                        {{-- REPONSE A UN COMMENTAIRE  --}}

                                                                                        <p class="mb-10">
                                                                                            {{ $client->pivot->avis }}
                                                                                            {{-- in<a href="#" class="reply">Reply</a> --}}
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @endif
                                                                    @endforeach
                                                                @else
                                                                    <h2>Soyez le premier à partager votre avis sur ce
                                                                        produit</h2>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        {{-- Notes d'autre utilisateurs et statisque --}}
                                                        @if ($lesNotes->count() > 0)
                                                            <div class="col-lg-4">
                                                                <h4 class="mb-30">Statistique des notes</h4>
                                                                <div class="d-flex mb-30">
                                                                    <div class="product-rate d-inline-block mr-15">
                                                                        <div class="product-rating"
                                                                            style="width: {{ ($data['somme'] / $lesNotes->count()) * 10 }}%">
                                                                        </div>
                                                                    </div>
                                                                    <h6>{{ $data['somme'] / $lesNotes->count() }} sur 5
                                                                    </h6>
                                                                </div>
                                                                <div class="progress">
                                                                    <span>5.0</span>
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: {{ ($data['five'] / $lesNotes->count()) * 100 }}%"
                                                                        aria-valuenow="50" aria-valuemin="0"
                                                                        aria-valuemax="100">
                                                                        {{ number_format(($data['five'] / $lesNotes->count()) * 100, 0, '', ' ') }}%
                                                                    </div>
                                                                </div>
                                                                <div class="progress">
                                                                    <span>4.0</span>
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: {{ ($data['four'] / $lesNotes->count()) * 100 }}%"
                                                                        aria-valuenow="25" aria-valuemin="0"
                                                                        aria-valuemax="100">
                                                                        {{ number_format(($data['four'] / $lesNotes->count()) * 100, 0, '', ' ') }}%
                                                                    </div>
                                                                </div>
                                                                <div class="progress">
                                                                    <span>3.0</span>
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: {{ ($data['three'] / $lesNotes->count()) * 100 }}%"
                                                                        aria-valuenow="45" aria-valuemin="0"
                                                                        aria-valuemax="100">
                                                                        {{ number_format(($data['three'] / $lesNotes->count()) * 100, 0, '', ' ') }}%
                                                                    </div>
                                                                </div>
                                                                <div class="progress">
                                                                    <span>2.0</span>
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: {{ ($data['two'] / $lesNotes->count()) * 100 }}%"
                                                                        aria-valuenow="65" aria-valuemin="0"
                                                                        aria-valuemax="100">
                                                                        {{ number_format(($data['two'] / $lesNotes->count()) * 100, 0, '', ' ') }}%
                                                                    </div>
                                                                </div>
                                                                <div class="progress mb-30">
                                                                    <span>1.0</span>
                                                                    <div class="progress-bar" role="progressbar"
                                                                        style="width: {{ ($data['one'] / $lesNotes->count()) * 100 }}%"
                                                                        aria-valuenow="85" aria-valuemin="0"
                                                                        aria-valuemax="100">
                                                                        {{ number_format(($data['one'] / $lesNotes->count()) * 100, 0, '', ' ') }}%
                                                                    </div>
                                                                </div>
                                                                {{-- <a href="#" class="font-xs text-muted">How are ratings calculated?</a> --}}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>


                                                <!--Ajout de commentaire-->
                                                <div class="comment-form">
                                                    <h4 class="mb-15">Un avis à partager ?</h4>
                                                    <div class="product-rate d-inline-block mb-30"></div>
                                                    <div class="row">
                                                        <div class="col-lg-8 col-md-12">
                                                            <form id="form" class="form-contact comment_form"
                                                                method="post"
                                                                action="{{ route('client.avisNote', $produit) }}"
                                                                id="commentForm">
                                                                @csrf
                                                                <alert class="alert-warning" id="warning"></alert>
                                                                <div class="row">
                                                                    <div class="col-12">
                                                                        <div class="form-group">
                                                                            <textarea class="form-control w-100" name="avis" id="avis" cols="30" rows="9"
                                                                                placeholder="Donnez votre avis sur ce produit"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-sm-6">
                                                                        <div class="form-group">
                                                                            <input class="form-control" min="0"
                                                                                max="5" required name="note"
                                                                                id="note" type="number"
                                                                                placeholder="Note sur 5" />
                                                                            <span class="text-danger"
                                                                                id="errorNote"></span>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                                <div class="form-group">
                                                                    <button type="submit"
                                                                        class="button button-contactForm">Envoyer</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- produit relatif --}}
                                {{-- <div class="row mt-60">
                                    <div class="col-12">
                                        <h2 class="section-title style-1 mb-30">Related products</h2>
                                    </div>
                                    <div class="col-12">
                                        <div class="row related-products">
                                            <div class="col-lg-3 col-md-4 col-12 col-sm-6">
                                                <div class="product-cart-wrap hover-up">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="shop-product-right.html" tabindex="0">
                                                                <img class="default-img"
                                                                    src="assets/imgs/shop/product-2-1.jpg"
                                                                    alt="" />
                                                                <img class="hover-img"
                                                                    src="assets/imgs/shop/product-2-2.jpg"
                                                                    alt="" />
                                                            </a>
                                                        </div>
                                                        <div class="product-action-1">
                                                            <a aria-label="Quick view" class="action-btn small hover-up"
                                                                data-bs-toggle="modal" data-bs-target="#quickViewModal"><i
                                                                    class="fi-rs-search"></i></a>
                                                            <a aria-label="Add To Wishlist"
                                                                class="action-btn small hover-up"
                                                                href="shop-wishlist.html" tabindex="0"><i
                                                                    class="fi-rs-heart"></i></a>
                                                            <a aria-label="Compare" class="action-btn small hover-up"
                                                                href="shop-compare.html" tabindex="0"><i
                                                                    class="fi-rs-shuffle"></i></a>
                                                        </div>
                                                        <div
                                                            class="product-badges product-badges-position product-badges-mrg">
                                                            <span class="hot">Hot</span>
                                                        </div>
                                                    </div>
                                                    <div class="product-content-wrap">
                                                        <h2><a href="shop-product-right.html" tabindex="0">Ulstra Bass
                                                                Headphone</a></h2>
                                                        <div class="rating-result" title="90%">
                                                            <span> </span>
                                                        </div>
                                                        <div class="product-price">
                                                            <span>$238.85 </span>
                                                            <span class="old-price">$245.8</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-4 col-12 col-sm-6">
                                                <div class="product-cart-wrap hover-up">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="shop-product-right.html" tabindex="0">
                                                                <img class="default-img"
                                                                    src="assets/imgs/shop/product-3-1.jpg"
                                                                    alt="" />
                                                                <img class="hover-img"
                                                                    src="assets/imgs/shop/product-4-2.jpg"
                                                                    alt="" />
                                                            </a>
                                                        </div>
                                                        <div class="product-action-1">
                                                            <a aria-label="Quick view" class="action-btn small hover-up"
                                                                data-bs-toggle="modal" data-bs-target="#quickViewModal"><i
                                                                    class="fi-rs-search"></i></a>
                                                            <a aria-label="Add To Wishlist"
                                                                class="action-btn small hover-up"
                                                                href="shop-wishlist.html" tabindex="0"><i
                                                                    class="fi-rs-heart"></i></a>
                                                            <a aria-label="Compare" class="action-btn small hover-up"
                                                                href="shop-compare.html" tabindex="0"><i
                                                                    class="fi-rs-shuffle"></i></a>
                                                        </div>
                                                        <div
                                                            class="product-badges product-badges-position product-badges-mrg">
                                                            <span class="sale">-12%</span>
                                                        </div>
                                                    </div>
                                                    <div class="product-content-wrap">
                                                        <h2><a href="shop-product-right.html" tabindex="0">Smart
                                                                Bluetooth Speaker</a></h2>
                                                        <div class="rating-result" title="90%">
                                                            <span> </span>
                                                        </div>
                                                        <div class="product-price">
                                                            <span>$138.85 </span>
                                                            <span class="old-price">$145.8</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-4 col-12 col-sm-6">
                                                <div class="product-cart-wrap hover-up">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="shop-product-right.html" tabindex="0">
                                                                <img class="default-img"
                                                                    src="assets/imgs/shop/product-4-1.jpg"
                                                                    alt="" />
                                                                <img class="hover-img"
                                                                    src="assets/imgs/shop/product-4-2.jpg"
                                                                    alt="" />
                                                            </a>
                                                        </div>
                                                        <div class="product-action-1">
                                                            <a aria-label="Quick view" class="action-btn small hover-up"
                                                                data-bs-toggle="modal" data-bs-target="#quickViewModal"><i
                                                                    class="fi-rs-search"></i></a>
                                                            <a aria-label="Add To Wishlist"
                                                                class="action-btn small hover-up"
                                                                href="shop-wishlist.html" tabindex="0"><i
                                                                    class="fi-rs-heart"></i></a>
                                                            <a aria-label="Compare" class="action-btn small hover-up"
                                                                href="shop-compare.html" tabindex="0"><i
                                                                    class="fi-rs-shuffle"></i></a>
                                                        </div>
                                                        <div
                                                            class="product-badges product-badges-position product-badges-mrg">
                                                            <span class="new">New</span>
                                                        </div>
                                                    </div>
                                                    <div class="product-content-wrap">
                                                        <h2><a href="shop-product-right.html" tabindex="0">HomeSpeak
                                                                12UEA Goole</a></h2>
                                                        <div class="rating-result" title="90%">
                                                            <span> </span>
                                                        </div>
                                                        <div class="product-price">
                                                            <span>$738.85 </span>
                                                            <span class="old-price">$1245.8</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-3 col-md-4 col-12 col-sm-6 d-lg-block d-none">
                                                <div class="product-cart-wrap hover-up mb-0">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="shop-product-right.html" tabindex="0">
                                                                <img class="default-img"
                                                                    src="assets/imgs/shop/product-5-1.jpg"
                                                                    alt="" />
                                                                <img class="hover-img"
                                                                    src="assets/imgs/shop/product-3-2.jpg"
                                                                    alt="" />
                                                            </a>
                                                        </div>
                                                        <div class="product-action-1">
                                                            <a aria-label="Quick view" class="action-btn small hover-up"
                                                                data-bs-toggle="modal" data-bs-target="#quickViewModal"><i
                                                                    class="fi-rs-search"></i></a>
                                                            <a aria-label="Add To Wishlist"
                                                                class="action-btn small hover-up"
                                                                href="shop-wishlist.html" tabindex="0"><i
                                                                    class="fi-rs-heart"></i></a>
                                                            <a aria-label="Compare" class="action-btn small hover-up"
                                                                href="shop-compare.html" tabindex="0"><i
                                                                    class="fi-rs-shuffle"></i></a>
                                                        </div>
                                                        <div
                                                            class="product-badges product-badges-position product-badges-mrg">
                                                            <span class="hot">Hot</span>
                                                        </div>
                                                    </div>
                                                    <div class="product-content-wrap">
                                                        <h2><a href="shop-product-right.html" tabindex="0">Dadua Camera
                                                                4K 2024EF</a></h2>
                                                        <div class="rating-result" title="90%">
                                                            <span> </span>
                                                        </div>
                                                        <div class="product-price">
                                                            <span>$89.8 </span>
                                                            <span class="old-price">$98.8</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> --}}
                            </div>
                        </div>
                        @include('client.categorieEtFiltreur')
                    </div>
                </div>
            </div>
        </div>
    </main>

@endsection

@section('jspart')
    <script>
        function changerQuantite(e, delta) {
            e.preventDefault();
            const input = document.getElementById('qtyProduit');
            if (!input) return;
            let val = parseInt(input.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            val = Math.max(1, val + delta);
            input.value = val;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('qtyProduit');
            if (input) {
                input.addEventListener('input', function () {
                    let v = parseInt(this.value, 10);
                    if (isNaN(v) || v < 1) {
                        this.value = 1;
                    }
                });
            }
        });

        let form = document.getElementById('form');
        form.addEventListener('submit', function(e) {

            let note = document.getElementById('note').value
            let avis = document.getElementById('avis').value
            // alert(avis)
            // alert(note)
            let warning = document.getElementById('warning')
            let errorNote = document.getElementById('errorNote')


            if (note.length == 0 && avis.length == 0) {
                warning.innerHTML = "Veuillez remplir au moins un champs"
                e.preventDefault();
            }

            if (note != null) {
                if (note < 0 || note > 5) {

                    errorNote.innerHTML = "La note doit être comprise entre 0 et 5 inclus"

                    e.preventDefault();
                }
            }
        })
    </script>

@endsection
