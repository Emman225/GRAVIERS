@extends('client.main')
@section('title', 'Mon panier')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('info') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info text-center modifie coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Panier mis a jour</span>
    </div>

    <main class="main cart-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="cart-hero">
            <div class="cart-hero__inner">
                <span class="cart-hero__chip"><i class="fi-rs-shopping-cart"></i> Panier</span>
                <h1 class="cart-hero__title">Mon panier</h1>
                @if (Cart::count() > 0)
                    <p class="cart-hero__subtitle">
                        Vous avez <strong>{{ Cart::count() }}</strong> article{{ Cart::count() > 1 ? 's' : '' }} dans votre panier.
                    </p>
                @else
                    <p class="cart-hero__subtitle">Votre panier est actuellement vide.</p>
                @endif
            </div>
        </section>

        <div class="container mb-80 mt-30">

            @if (Cart::count() > 0)
                {{-- Top actions bar --}}
                <div class="cart-toolbar mb-3">
                    <a href="{{ route('client.index') }}" class="cart-toolbar__link">
                        <i class="fi-rs-arrow-left"></i> Continuer mes achats
                    </a>
                    <a href="{{ route('client.nettoyer') }}" class="cart-toolbar__danger"
                       onclick="return confirm('Voulez-vous vraiment vider le panier??')">
                        <i class="fi-rs-trash"></i> Vider le panier
                    </a>
                </div>

                <div class="row g-4">
                    {{-- ===== COLONNE PRODUITS ===== --}}
                    <div class="col-lg-8 contenuPanier">
                        <div class="cart-card">
                            <div class="cart-card__header">
                                <h5 class="cart-card__title"><i class="fi-rs-shopping-bag"></i> Articles ({{ Cart::count() }})</h5>
                            </div>

                            <div class="table-responsive shopping-summery">
                                <table id="table" class="table table-wishlist cart-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th class="custome-checkbox start pl-30"></th>
                                            <th scope="col" colspan="2">Produits</th>
                                            <th scope="col">Prix unitaire</th>
                                            <th scope="col">Quantité</th>
                                            <th scope="col">Sous-total</th>
                                            <th scope="col" class="end">Action</th>
                                        </tr>
                                    </thead>
                                    @php
                                        $total = 0;
                                        $i = 0;
                                    @endphp
                                    <form method="POST" id="produitForm" action="{{ route('client.update.produit') }}">
                                        @csrf
                                        <tbody id="listProduit">
                                            @if(!Cart::content()->isEmpty())
                                                @foreach (Cart::content() as $produit)
                                                    @php $type_affaire = $produit->options?->type_affaire @endphp
                                                    <tr class="pt-30 cart-row" data-rowid="{{ $produit->rowId }}">
                                                        <td class="custome-checkbox pl-30"></td>

                                                        <td class="image product-thumbnail pt-40">
                                                            <a href="{{ route('client.produit.info', $produit->model) }}">
                                                                <img src="/storage/{{ $produit->options?->image }}" alt="{{ $produit->name }}" />
                                                            </a>
                                                        </td>

                                                        <td class="product-des product-name">
                                                            <h6 class="mb-5">
                                                                <a class="product-name mb-10 text-heading" href="{{ route('client.produit.info', $produit->model) }}">
                                                                    {{ $produit->name }}
                                                                </a>
                                                            </h6>
                                                            @if(($produit->model?->meilleur_note ?? 0) > 0)
                                                                <div class="product-rate-cover">
                                                                    <div class="product-rate d-inline-block">
                                                                        <div class="product-rating" style="width: {{ $produit->model?->meilleur_note }}%"></div>
                                                                    </div>
                                                                    <span class="font-small ml-5 text-muted">
                                                                        ({{ round(($produit->model?->meilleur_note * 5) / 100, 1) }})
                                                                    </span>
                                                                </div>
                                                            @endif
                                                        </td>

                                                        <td class="price" data-title="Prix">
                                                            <div>
                                                                <div class="detail-qty cart-price">
                                                                    {{ number_format($produit->price, 0, '', ' ') }} fcfa /
                                                                    {{ $produit->model?->UniteProduit?->abreviation }}
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td class="text-center detail-info" data-title="Quantité">
                                                            <div class="detail-extralink mr-15">
                                                                <div class="detail-qty border radius cart-qty">
                                                                    <a href="#" class="qty-down" aria-label="Diminuer"><i class="fi-rs-angle-small-down"></i></a>
                                                                    <input type="text" required name="qte[]" id="inputQte" class="qty-val" value="{{ $produit->qty }}" min="1">
                                                                    <a href="#" class="qty-up" aria-label="Augmenter"><i class="fi-rs-angle-small-up"></i></a>
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td class="price" data-title="Sous-total">
                                                            <div class="detail-extralink mr-15">
                                                                <div class="radius w-100 cart-subtotal-wrap">
                                                                    <span class="cart-subtotal-amount font-md text-brand">{{ number_format($produit->price * $produit->qty, 0, '', ' ') }} fcfa</span>
                                                                    <input type="hidden" id="montant" name="montant[]"
                                                                           value="{{ $produit->price * $produit->qty }}">
                                                                </div>
                                                            </div>
                                                        </td>
                                                        @php $total += $produit->price * $produit->qty @endphp

                                                        <td class="action text-center" data-title="Supprimer">
                                                            <a onclick="return confirm('Voulez vous supprimer ce produit?')"
                                                               href="{{ route('client.supprimer.produit', $produit->rowId) }}"
                                                               class="cart-remove-btn" title="Retirer du panier">
                                                                <i class="fi-rs-trash"></i>
                                                            </a>
                                                        </td>
                                                        <input type="hidden" name="rowId[]" value="{{ $produit->rowId }}">
                                                    </tr>
                                                    @php $i++; @endphp
                                                @endforeach
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ===== COLONNE TOTAUX ===== --}}
                    <div class="col-lg-4">
                        <div class="cart-card cart-summary">
                            <div class="cart-card__header">
                                <h5 class="cart-card__title"><i class="fi-rs-calculator"></i> Récapitulatif</h5>
                            </div>
                            <div class="cart-summary__body">
                                <div class="cart-summary__row">
                                    <span class="cart-summary__label">Montant HT</span>
                                    <span class="cart-summary__value">
                                        <span id="montant_total">{{ number_format($totalCommande, 0, '', ' ') }}</span> FCFA
                                    </span>
                                </div>
                                <div class="cart-summary__row">
                                    <span class="cart-summary__label">TVA</span>
                                    <span class="cart-summary__value">
                                        <span id="montant_tva">{{ number_format($totalCommande * $tva, 0, '', ' ') }}</span> FCFA
                                    </span>
                                </div>
                                <div class="cart-summary__divider"></div>
                                <div class="cart-summary__row cart-summary__row--total">
                                    <span class="cart-summary__label">Montant TTC</span>
                                    <span class="cart-summary__value cart-summary__value--big">
                                        <span id="montant_ttc">{{ number_format($totalCommande + ($totalCommande * $tva), 0, '', ' ') }}</span>
                                        <small>FCFA</small>
                                    </span>
                                </div>

                                <div class="cart-summary__alert">
                                    <span class="text-danger" id="messageAlert">
                                        @if($totalCommande + ($totalCommande * $tva) > 2000000)
                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                        @endif
                                    </span>
                                </div>

                                @php $type_affaire = 'VENTE'; @endphp

                                @if ($type_affaire == 'VENTE')
                                    <a href="{{ route('client.commandeAdresse') }}" class="cart-summary__cta">
                                        <i class="fi-rs-shipping-fast"></i>
                                        Choisir le mode de livraison
                                    </a>
                                    <p class="cart-summary__note">Hors frais de livraison</p>
                                @else
                                    <a href="{{ route('client.locationAdresse') }}" class="cart-summary__cta">
                                        Valider la location
                                    </a>
                                @endif

                                {{-- Garanties --}}
                                <ul class="cart-summary__trust">
                                    <li><i class="fi-rs-shield-check"></i> Paiement sécurisé</li>
                                    <li><i class="fi-rs-truck-side"></i> Livraison rapide</li>
                                    <li><i class="fi-rs-headset"></i> Support 7j/7</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- ===== ÉTAT VIDE ===== --}}
                <div class="cart-empty">
                    <div class="cart-empty__icon"><i class="fi-rs-shopping-cart"></i></div>
                    <h3 class="cart-empty__title">Votre panier est vide</h3>
                    <p class="cart-empty__text">
                        Ajoutez des produits depuis le catalogue pour les retrouver ici, puis passer votre commande.
                    </p>
                    <a href="{{ route('client.index') }}" class="cart-empty__btn">
                        <i class="fi-rs-arrow-right"></i> Parcourir le catalogue
                    </a>
                </div>
            @endif
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .cart-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 44px 20px 50px;
            overflow: hidden;
            isolation: isolate;
        }
        .cart-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .cart-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .cart-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }
        .cart-hero__chip i { color: #fbbf24; font-size: 16px; }
        .cart-hero__title,
        h1.cart-hero__title {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .cart-hero__subtitle {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 0.95rem;
            text-shadow: 0 1px 6px rgba(0,0,0,0.25);
        }
        .cart-hero__subtitle strong { color: #fbbf24; font-weight: 700; }

        /* ===== TOOLBAR ===== */
        .cart-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .cart-toolbar__link {
            display: inline-flex; align-items: center; gap: 8px;
            color: #1c57a3;
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .cart-toolbar__link:hover { color: #0a2540; transform: translateX(-2px); }
        .cart-toolbar__danger {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px;
            border-radius: 10px;
            background: #fef2f2;
            color: #b91c1c !important;
            font-weight: 600;
            font-size: 0.88rem;
            text-decoration: none;
            border: 1px solid #fecaca;
            transition: all 0.15s ease;
        }
        .cart-toolbar__danger:hover { background: #fee2e2; border-color: #fca5a5; }

        /* ===== CARDS ===== */
        .cart-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }
        .cart-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .cart-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .cart-card__title i { color: #1c57a3; font-size: 18px; }

        /* ===== TABLE PANIER ===== */
        .cart-table thead .main-heading { background: #f9fafb; }
        .cart-table thead th {
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.82rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 12px !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        .cart-table tbody td {
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 18px 12px !important;
        }
        .cart-table .product-thumbnail img {
            width: 86px; height: 86px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .cart-table .product-name a {
            color: #0a2540 !important;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .cart-table .product-name a:hover { color: #1c57a3 !important; }
        .cart-price {
            font-weight: 700;
            color: #ea580c;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        /* Quantité */
        .cart-qty {
            display: inline-flex !important;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            overflow: hidden;
            padding: 0 !important;
        }
        .cart-qty .qty-down,
        .cart-qty .qty-up {
            width: 32px; height: 38px;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            color: #6b7280 !important;
            background: #f9fafb;
            border: 0;
            transition: all 0.15s ease;
        }
        .cart-qty .qty-down:hover,
        .cart-qty .qty-up:hover {
            background: #1c57a3;
            color: #ffffff !important;
        }
        .cart-qty .qty-val {
            width: 50px !important;
            text-align: center;
            border: 0 !important;
            background: transparent !important;
            font-weight: 700;
            color: #0a2540;
            font-size: 0.95rem;
            outline: none !important;
            padding: 0 !important;
            margin: 0 !important;
            height: 38px;
        }

        /* Sous-total éditable */
        .cart-subtotal-wrap {
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 7px 10px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .cart-subtotal-wrap:focus-within {
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }
        .cart-subtotal-wrap input.qty-val {
            width: 100% !important;
            text-align: center;
            border: 0 !important;
            background: transparent !important;
            font-weight: 700;
            color: #0a2540;
            outline: none !important;
            padding: 0 !important;
            margin: 0 !important;
            font-size: 0.92rem;
        }

        /* Bouton supprimer */
        .cart-remove-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #fef2f2;
            color: #b91c1c !important;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .cart-remove-btn:hover {
            background: #ef4444;
            color: #ffffff !important;
            transform: scale(1.06);
            box-shadow: 0 6px 14px rgba(239, 68, 68, 0.30);
        }
        .cart-remove-btn i { font-size: 16px; }

        /* ===== SUMMARY ===== */
        .cart-summary { position: sticky; top: 20px; }
        .cart-summary__body { padding: 20px 22px 22px; }
        .cart-summary__row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 8px 0;
            font-size: 0.92rem;
        }
        .cart-summary__label { color: #6b7280; font-weight: 500; }
        .cart-summary__value { color: #0a2540; font-weight: 700; }
        .cart-summary__divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }
        .cart-summary__row--total .cart-summary__label {
            color: #0a2540;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .cart-summary__value--big {
            font-size: 1.4rem !important;
            color: #ea580c !important;
        }
        .cart-summary__value--big small { font-size: 0.65em; color: #6b7280; font-weight: 600; margin-left: 4px; }
        .cart-summary__alert { min-height: 0; }
        .cart-summary__alert .text-danger {
            display: block;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-top: 8px;
            padding: 10px 12px;
            background: #fef2f2;
            border-radius: 8px;
            border-left: 3px solid #ef4444;
        }
        .cart-summary__alert .text-danger:empty { display: none; }
        .cart-summary__cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 18px;
            margin-top: 18px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(234, 88, 12, 0.32);
            transition: all 0.18s ease;
            letter-spacing: 0.01em;
        }
        .cart-summary__cta:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 14px 28px rgba(234, 88, 12, 0.42);
            color: #ffffff !important;
        }
        .cart-summary__cta i { font-size: 16px; }
        .cart-summary__note {
            text-align: center;
            color: #6b7280;
            font-size: 0.8rem;
            margin: 8px 0 0;
        }
        .cart-summary__trust {
            list-style: none;
            padding: 16px 0 0;
            margin: 16px 0 0;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .cart-summary__trust li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4b5563;
            font-size: 0.84rem;
            font-weight: 500;
        }
        .cart-summary__trust i {
            color: #10b981;
            font-size: 16px;
            width: 26px; height: 26px;
            background: #ecfdf5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== ÉTAT VIDE ===== */
        .cart-empty {
            max-width: 520px;
            margin: 40px auto 0;
            padding: 50px 30px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .cart-empty__icon {
            width: 90px; height: 90px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1c57a3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .cart-empty__title { color: #0a2540; font-weight: 700; font-size: 1.3rem; margin: 0 0 8px; }
        .cart-empty__text { color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 0 0 22px; }
        .cart-empty__btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.30);
            transition: all 0.18s ease;
        }
        .cart-empty__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(234, 88, 12, 0.42);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .cart-summary { position: static; }
        }
        @media (max-width: 575px) {
            .cart-hero { padding: 34px 16px 38px; }
            .cart-hero__title { font-size: 1.5rem; }
            .cart-table thead { display: none; }
            .cart-table tbody td { display: block; border-bottom: 0 !important; padding: 8px 12px !important; }
            .cart-table tbody tr { border-bottom: 1px solid #f1f5f9; padding: 14px 0 !important; }
            .cart-table .product-thumbnail img { width: 70px; height: 70px; }
            .cart-summary__value--big { font-size: 1.2rem !important; }
        }
    </style>
@endsection


@section('jspart')

    <script>

        // $('#produitForm').submit(function(e){
        //     e.preventDefault();
        //     let form = document.forms[3];

        //     const array = Array.prototype.slice.call(form);
        //     array.forEach(input => {
        //         if(input.name == 'qte[]'){
        //             let value = parseInt(input.value);

        //             if(isNaN(value)){
        //                 console.log('La quantité doit etre un nombre');

        //                 return false;
        //             }

        //         }

        //     })

        // })

    </script>


    <script>
        if (document.getElementById('map')) {
            var map = L.map('map').setView([51.505, -0.09], 13);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            L.marker([51.5, -0.09]).addTo(map)
                .bindPopup('A pretty CSS popup.<br> Easily customizable.')
                .openPopup();
        }
    </script>



<script>
    $(document).ready(function () {

        let donnees = getColonnesAvecRowId("table", [4, 5]);

        console.log('first donnes:', donnees);

        function updateTotals(row, newQty) {
            let rowId = row.data('rowid');

            $.ajax({
                url: '{{ route('panier.update.quantite') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qty: newQty
                },
                success: function (response) {
                    if (response.success) {
                        row.find('input[id=montant]').val(response.subtotal);
                        row.find('.cart-subtotal-amount').text(response.subtotal + ' fcfa');
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);

                        if(response.ttc.replace(/\s/g, '') > 2000000) {
                            console.log("Montant TTC depasse 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                            $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                        } else {
                            console.log("Montant TTC inferieur ou egal à 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                        }
                    }
                },
                error: function () {
                    alert('Une erreur est survenue.');
                }
            });
        }

        function getColonnesAvecRowId(idTable, indexColonnes = []) {
            const resultat = [];
            const table = document.getElementById(idTable);
            const lignes = table.querySelectorAll("tbody tr");

            lignes.forEach(ligne => {
                const rowId = ligne.getAttribute("data-rowid") || ""; // récupère l'attribut data-rowid
                const cellules = ligne.querySelectorAll("td");
                const ligneDonnees = [rowId]; // commence par rowId

                indexColonnes.forEach(index => {
                const cellule = cellules[index];
                if (!cellule) {
                    ligneDonnees.push(""); // valeur vide si cellule manquante
                    return;
                }

                const input = cellule.querySelector("input");
                if (input) {
                    ligneDonnees.push(input.value.trim());
                } else {
                    ligneDonnees.push(cellule.textContent.trim());
                }
                });

                resultat.push(ligneDonnees);
            });

            return resultat;
        }

            // Exemple : colonnes 0 et 2 → résultat = [rowId, col0, col2]

            console.log("Resultats : ", donnees);

        $('.qty-up').click(function (e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');

            console.log('Quantité actuelle : ' + input.val());

            let newQty = parseInt(input.val()) + 1;

            input.val(newQty);
            console.log('Nouvelle quantité : ' + newQty);
            updateTotals(row, newQty);
        });

        $('.qty-down').click(function (e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');
            let currentQty = parseInt(input.val());
            if (currentQty > 1) {
                let newQty = currentQty - 1;
                input.val(newQty);
                updateTotals(row, newQty);
            }
        });


        $('input[name="qte[]"]').on('keyup', function () {
            let row = $(this).closest('tr');
            let newQty = parseInt($(this).val());

            if (newQty > 0) {
                updateTotals(row, newQty);
            }
            //else {
            //    $(this).val(0.1);
           //     updateTotals(row, 0.1);
           // }
        });


        function updateByTotal(rowId, qte, prixUnitaire, montant) {

            console.log("row: "+rowId)
            console.log("qte: "+qte)
            console.log("prixUnitaire: "+prixUnitaire)
            console.log("montant: "+montant)
             $.ajax({
                url: '{{ route('panier.update.all') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qte: qte,
                    prixUnitaire: prixUnitaire,
                    montant: montant
                },
                success: function (response) {
                    if (response.success) {
                        console.log("Response:", response.qte);
                        if (response.qte < 0.1) {
                            alert('La quantité doit être supérieure à 0.1 !');
                            return;
                        }
                        console.log("Response:", response);
                        const tr = $('tr[data-rowid="' + response.rowId + '"]');

                        // Mettre à jour les champs qte[] et montant[]
                        tr.find('input[name="qte[]"]').val(response.qte);
                        tr.find('input[name="montant[]"]').val(response.subtotal);
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);

                        if(response.ttc.replace(/\s/g, '') > 2000000) {
                            console.log("Montant TTC depasse 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                            $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                        } else {
                            console.log("Montant TTC inferieur ou egal à 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                        }

                    }
                },
                error: function () {
                    alert('Une erreur est survenue lors de la mise à jour du total.');
                }
            });


        }

        const debounceTimers = {}; // Dictionnaire de timers par champ

        $(document).on('input', 'input[name="montant[]"]', function () {
            const inputMontant = $(this);
            const inputId = inputMontant.closest('tr').data('rowid');

            clearTimeout(debounceTimers[inputId]);

            debounceTimers[inputId] = setTimeout(function () {
                const tr = inputMontant.closest('tr');
                const rowId = tr.data('rowid');

                const montant = parseFloat(inputMontant.val()) || 0;
                const qte = parseFloat(tr.find('input[name="qte[]"]').val()) || 0;

                const prixText = tr.find('td.price .detail-qty').text().trim();
                const matchPrix = prixText.match(/(\d+([.,]?\d+)?)/);
                const prixUnitaire = matchPrix ? parseFloat(matchPrix[1].replace(',', '.')) : 0;
                if(montant> 0){
                    console.log('montant là: ' + montant);

                    updateByTotal(rowId, qte, prixUnitaire, montant);
                }

            }, 500); // Délai de 500 ms
        });

        $('#maj').click(function (e) {

            donnees = getColonnesAvecRowId("table", [4, 5]);

            $.ajax({
                url: form.action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $('#notify').text('Panier mis à jour avec succès').show();
                        setTimeout(() => {
                            $('#notify').fadeOut();
                        }, 3000);
                    }
                },
                error: function () {
                    alert('Une erreur est survenue lors de la mise à jour du panier.');
                }
            });
        });

        //envoie les données du panier au serveur
        function envoyerTableauParFetch(tableau) {
            // Utiliser fetch pour envoyer le tableau au serveur
            fetch('{{ route('panier.update.all') }}', {
                method: "POST",
                headers: {
                "Content-Type": "application/json",
                // Inclure ceci si vous êtes sous Laravel avec middleware CSRF :
                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ data: tableau }) // envoyer le tableau sous la clé "data"
            })
            .then(response => response.json())
            .then(result => {
                console.log("Succès :", result);
            })
            .catch(error => {
                console.error("Erreur AJAX :", error);
            });
        }


    });
</script>
@endsection
