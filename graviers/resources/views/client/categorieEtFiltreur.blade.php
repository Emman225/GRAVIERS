<div class="col-lg-1-5 primary-sidebar sticky-sidebar pt-30">
    {{-- catégorie --}}
    <div class="sidebar-widget widget-category-2 mb-30">
        <h5 class="section-title style-1 mb-30">Categories</h5>

            <ul class="categorie-sidebar-list">
                @php
                    $iconFallback = asset('frontend/assets/imgs/theme/icons/category-1.svg');
                @endphp
                @foreach ($categories as $categorie )
                    @php
                        $iconPath = $categorie->icon;
                        $iconSrc = $iconFallback;
                        if (!empty($iconPath)) {
                            if (preg_match('#^https?://#i', $iconPath)) {
                                $iconSrc = $iconPath;
                            } else {
                                $cleanPath = preg_replace('#(categorieImage/)+#', 'categorieImage/', $iconPath);
                                if (str_contains($cleanPath, 'http')) {
                                    $iconSrc = $iconFallback;
                                } else {
                                    $candidate = public_path('storage/'.$cleanPath);
                                    $iconSrc = file_exists($candidate) ? asset('storage/'.$cleanPath) : $iconFallback;
                                }
                            }
                        }
                    @endphp
                    <li>
                        <a href="{{ route('product.categorie', $categorie->nom) }}"
                           class="categorie-sidebar-item"
                           title="Voir les produits de {{ $categorie->nom }}">
                            <img src="{{ $iconSrc }}"
                                 alt="{{ $categorie->nom }}"
                                 onerror="this.onerror=null;this.src='{{ $iconFallback }}';" />
                            <span class="categorie-sidebar-label">{{ $categorie->nom }}</span>
                            <span class="count">{{ $categorie->produits->count() }}</span>
                        </a>
                    </li>
                @endforeach

            </ul>

    </div>

    {{-- Hover & focus visuel pour rendre clairement les catégories cliquables --}}
    <style>
        .categorie-sidebar-list { list-style: none; padding: 0; margin: 0; }
        .categorie-sidebar-list li { margin-bottom: 4px; }
        .categorie-sidebar-list .categorie-sidebar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #2e2e2e;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease, transform 0.15s ease;
            cursor: pointer;
        }
        .categorie-sidebar-list .categorie-sidebar-item img {
            width: 22px;
            height: 22px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .categorie-sidebar-list .categorie-sidebar-label {
            flex-grow: 1;
            font-size: 14px;
        }
        .categorie-sidebar-list .categorie-sidebar-item .count {
            background: #eef2f7;
            color: #6b7280;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
            min-width: 24px;
            text-align: center;
        }
        .categorie-sidebar-list .categorie-sidebar-item:hover {
            background-color: #f1f5f9;
            color: #3bb77e;
            transform: translateX(2px);
        }
        .categorie-sidebar-list .categorie-sidebar-item:hover .count {
            background: #3bb77e;
            color: #fff;
        }
    </style>
    <!-- Fillter By Price -->
    {{-- <div class="sidebar-widget price_range range mb-30">
        <h5 class="section-title style-1 mb-30">Filtrer par :</h5>
        <div class="price-filter">
            <div class="price-filter-inner">
                <div id="slider-range" class="mb-20"></div>
                <div class="d-flex justify-content-between">
                    <div class="caption">De: <strong id="slider-range-value1" class="text-brand"></strong> fcfa</div>
                    <div class="caption">à: <strong id="slider-range-value2" class="text-brand"></strong> fcfa</div>
                </div>
            </div>
        </div>
        <div class="list-group">
            <div class="list-group-item mb-10 mt-10">

                <label class="fw-900">Color</label>
                <div class="custome-checkbox">
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox1" value="" />
                    <label class="form-check-label" for="exampleCheckbox1"><span>Red (56)</span></label>
                    <br />
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox2" value="" />
                    <label class="form-check-label" for="exampleCheckbox2"><span>Green (78)</span></label>
                    <br />
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox3" value="" />
                    <label class="form-check-label" for="exampleCheckbox3"><span>Blue (54)</span></label>
                </div>
                <label class="fw-900 mt-15">Item Condition</label>
                <div class="custome-checkbox">
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox11" value="" />
                    <label class="form-check-label" for="exampleCheckbox11"><span>New (1506)</span></label>
                    <br />
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox21" value="" />
                    <label class="form-check-label" for="exampleCheckbox21"><span>Refurbished (27)</span></label>
                    <br />
                    <input class="form-check-input" type="checkbox" name="checkbox" id="exampleCheckbox31" value="" />
                    <label class="form-check-label" for="exampleCheckbox31"><span>Used (45)</span></label>
                </div>
            </div>
        </div>
        <a href="shop-grid-right.html" class="btn btn-sm btn-default"><i class="fi-rs-filter mr-5"></i> Filtrer</a>
    </div> --}}

    <!-- New Product -->
    <div class="sidebar-widget product-sidebar mb-30 p-30 bg-grey border-radius-10">
        <h5 class="section-title style-1 mb-30">Nouveaux produits</h5>
        @foreach ($produits as $produit )
            @if($loop->iteration <= 3)
                <div class="single-post clearfix">
                    <div class="image">
                        @foreach($produit->image as $image)
                            <img src="{{ asset('storage/'.$image->image) }}" alt="#" />
                        @endforeach
                    </div>
                    <div class="content pt-10">
                        <h5><a href="{{route('client.produit.info',$produit)}}">{{$produit->nom}}</a></h5>
                        <p class="price mb-0 mt-5">{{number_format($produit->prix_moyen,0,'',' ')}} fcfa</p>
                        <div class="product-rate">
                            <div class="product-rating" style="width:  {{$produit->meilleur_note}}%"></div>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach

    </div>

    {{-- image bas --}}
    <div class="banner-img wow fadeIn mb-lg-0 animated d-lg-block d-none">
        <img src="{{asset('frontend/assets/imgs/theme/produit/banner.png')}}" alt="" />
        <div class="banner-text">
            <span>Bâtiment</span>
            <h4>
                Nous avons<br />
                ce <span class="text-brand">qu'il</span><br />
                Vous faut
            </h4>
        </div>
    </div>

</div>
