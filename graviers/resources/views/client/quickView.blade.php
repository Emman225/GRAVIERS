@php
    // Chargement groupé des trois relations lues plus bas (image, notes,
    // catégories). Sans cela, chaque produit déclenchait ses propres requêtes au
    // moment de l'affichage : 3 requêtes par produit, soit 240 pour un catalogue
    // de 80 articles, sur CHAQUE page rendant cette fenêtre.
    // loadMissing : ne recharge rien si le contrôleur a déjà préchargé.
    if (isset($produits) && $produits instanceof \Illuminate\Database\Eloquent\Collection) {
        $produits->loadMissing(['image', 'notes', 'categories']);
    }
@endphp

@foreach ($produits as $produit)

    <div class="modal fade custom-modal" id="quickView{{$produit->id}}" tabindex="{{$produit->id}}" aria-labelledby="quickViewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12 col-xs-12 mb-md-0 mb-sm-5">
                            <div class="detail-gallery">
                                <span class="zoom-icon"><i class="fi-rs-search"></i></span>
                                <!-- MAIN SLIDES -->
                                <div class="product-image-slider">
                                    {{-- <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-2.jpg') }}" alt="product image" />
                                    </figure>
                                    <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-1.jpg') }}" alt="product image" />
                                    </figure>
                                    <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-3.jpg') }}" alt="product image" />
                                    </figure>
                                    <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-4.jpg') }}" alt="product image" />
                                    </figure>
                                    <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-5.jpg') }}" alt="product image" />
                                    </figure>
                                    <figure class="border-radius-10">
                                        <img src="{{ asset('frontend/assets/imgs/shop/product-16-6.jpg') }}" alt="product image" />
                                    </figure> --}}
                                    @foreach($produit->image as $image)
                                        <figure class="border-radius-10">
                                            <img src="storage/{{$image->image}}" alt="product image" />
                                        </figure>
                                    @endforeach
                                </div>
                                <!-- THUMBNAILS -->
                                <div class="slider-nav-thumbnails">

                                    @foreach($produit->image as $image)
                                        <div><img src="storage/{{$image->image}}" alt="product image" /></div>
                                    @endforeach
                                </div>
                            </div>
                            <!-- End Gallery -->
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            @php
                                // Prix unitaire réellement appliqué à ce client : le prix
                                // personnalisé s'il existe, sinon le prix courant. Il est posé
                                // en data-price, jamais relu depuis le texte affiché — l'espace
                                // des milliers (« 12 000 fcfa ») tronquerait la valeur.
                                $prixUnitaire = $prixPerso[$produit->id] ?? $produit->prix_moyen;
                            @endphp
                            <div class="detail-info pr-30 pl-30" data-price="{{ $prixUnitaire }}">
                                {{-- <span class="stock-status out-stock"> Sale Off </span> --}}
                                <h3 class="title-detail"><a href="{{ route('client.produit.info', $produit) }}" class="text-heading">{{$produit->nom}}</a></h3>
                                <div class="product-detail-rating">
                                    <div class="product-rate-cover text-end">
                                        <div class="product-rate d-inline-block">
                                            <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                        </div>
                                        <span class="font-small ml-5 text-muted"> ({{$produit->notes->count()}} commentaires)</span>
                                    </div>
                                </div>
                                <div class="clearfix product-price-cover">
                                    <div class="product-price primary-color float-left">
                                        @if(isset($prixPerso) && isset($prixPerso[$produit->id]))
                                            <span class="current-price text-brand total-val"> {{number_format($prixUnitaire,0,'',' ')}} fcfa</span>
                                            <span>
                                                <span class="prix-unitaire font-md ml-15">soit {{number_format($prixUnitaire,0,'',' ')}} fcfa l’unité</span>
                                                <span class="save-price font-md color3 ml-15">Prix spécial</span>
                                                <span class="old-price font-md ml-15">{{number_format($produit->prix_moyen,0,'',' ')}} fcfa</span>
                                            </span>
                                        @else
                                            <span class="current-price text-brand total-val"> {{number_format($prixUnitaire,0,'',' ')}} fcfa</span>
                                            <span>
                                                <span class="prix-unitaire font-md ml-15">soit {{number_format($prixUnitaire,0,'',' ')}} fcfa l’unité</span>
                                                @if($produit->prix_reduction > 0)
                                                    <span class="save-price font-md color3 ml-15">-{{intval((($produit->prix_reduction-$produit->prix_moyen)/$produit->prix_reduction)*100)}}% de réduction</span>
                                                    <span class="old-price font-md ml-15">{{number_format($produit->prix_reduction,0,'',' ')}} fcfa</span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="detail-extralink mb-30">
                                    <div class="detail-qty border radius">
                                        <a href="#" class="qty-down" onclick="changeQtyQuickView(this,-1); return false;"><i class="fi-rs-angle-small-down"></i></a>
                                        <input type="number" min="1" step="1" value="1" class="qty-val" aria-label="Quantité" />
                                        <a href="#" class="qty-up" onclick="changeQtyQuickView(this,1); return false;"><i class="fi-rs-angle-small-up"></i></a>
                                    </div>

                                    {{-- Montant : saisissable dans les deux sens. Le client peut
                                         partir d'une quantité ou d'un budget. --}}
                                    <div class="detail-montant border radius">
                                        <input type="number" min="0" step="1" class="montant-val"
                                               value="{{ round($prixUnitaire) }}" aria-label="Montant en fcfa" />
                                        <span class="montant-devise">fcfa</span>
                                    </div>

                                        <div class="product-extra-link2">
                                            <button onclick="ajouter({{$produit->id}}, this.closest('.detail-extralink').querySelector('.qty-val').value)" class="button button-add-to-cart"><i class="fi-rs-shopping-cart"></i>Ajouter au panier</button>
                                        </div>

                                </div>
                                <div class="font-xs">
                                    <ul>
                                        @foreach($produit->categories as $categorie)
                                            <li class="mb-5">Catégorie: <span class="text-brand">{{$categorie->nom}}</span></li>
                                        @endforeach
                                        <li class="mb-5">Abréviation:<span class="text-brand"> {{$produit->nom}}</span></li>
                                    </ul>
                                </div>
                            </div>
                            <!-- Detail Info -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endforeach
