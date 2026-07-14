@extends('client.main')
@section('title','Mes souhaits')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    <div class="alert alert-success text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Produit ajouté</span>
    </div>
    <div class="alert alert-warning text-center deja coller-en-haut" style="display: none" id="notify">
        Vous avez déjà selectionné ce produit
    </div>

    @include('client.navMobile')

    <main class="main wishlist-main">
        @php
            $favoris = $client->produits->filter(fn($p) => $p->pivot->deleted_at === null);
            $nbFavoris = $favoris->count();
        @endphp

        {{-- ===== HERO ===== --}}
        <section class="wishlist-hero">
            <div class="wishlist-hero__inner">
                <span class="wishlist-hero__chip"><i class="fi-rs-heart"></i> Liste de souhaits</span>
                <h1 class="wishlist-hero__title">Mes souhaits</h1>
                <p class="wishlist-hero__subtitle">
                    @if($nbFavoris > 0)
                        Vous avez <strong>{{ $nbFavoris }}</strong> article{{ $nbFavoris > 1 ? 's' : '' }} dans votre liste de favoris.
                    @else
                        Aucun article enregistré pour le moment.
                    @endif
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-40">

            @if($nbFavoris > 0)
                {{-- ===== GRILLE PRODUITS ===== --}}
                <div class="wishlist-grid">
                    @csrf
                    @foreach ($favoris as $produit)
                        @php
                            $prixUnit = isset($prixPerso[$produit->id]) ? (float) $prixPerso[$produit->id] : (float) $produit->prix_moyen;
                            $prixPersoActif = isset($prixPerso[$produit->id]);
                            $imageUrl = $produit->image && $produit->image->first()
                                ? '/storage/' . $produit->image->first()->image
                                : asset('frontend/assets/imgs/theme/loading.gif');
                            $noteEtoiles = round(($produit->meilleur_note * 5) / 100, 1);
                        @endphp

                        <article class="wishlist-card">
                            <a href="{{ route('client.likePlus', $produit->id) }}"
                               class="wishlist-card__remove"
                               onclick="return confirm('Voulez-vous vraiment retirer ce produit de votre liste de souhait ?')"
                               title="Retirer des favoris">
                                <i class="fi-rs-trash"></i>
                            </a>

                            <a href="{{ route('client.produit.info', $produit->id) }}" class="wishlist-card__media">
                                <img src="{{ $imageUrl }}" alt="{{ $produit->nom }}" loading="lazy" />
                            </a>

                            <div class="wishlist-card__body">
                                <a href="{{ route('client.produit.info', $produit->id) }}" class="wishlist-card__name">
                                    {{ $produit->nom }}
                                </a>

                                @if($produit->meilleur_note > 0)
                                    <div class="wishlist-card__rate">
                                        <div class="product-rate d-inline-block">
                                            <div class="product-rating" style="width: {{ $produit->meilleur_note }}%"></div>
                                        </div>
                                        <span class="wishlist-card__rate-num">{{ $noteEtoiles }}</span>
                                    </div>
                                @endif

                                <div class="wishlist-card__price">
                                    @if($prixPersoActif)
                                        <span class="wishlist-card__price-current">{{ number_format($prixUnit, 0, ',', ' ') }} <small>FCFA</small></span>
                                        <span class="wishlist-card__price-old">{{ number_format((float) $produit->prix_moyen, 0, ',', ' ') }} FCFA</span>
                                        <span class="wishlist-card__price-tag">Prix négocié</span>
                                    @else
                                        <span class="wishlist-card__price-current">{{ number_format($prixUnit, 0, ',', ' ') }} <small>FCFA</small></span>
                                    @endif
                                </div>

                                <button type="button" onclick="ajouter({{ $produit->id }})" class="wishlist-card__add">
                                    <i class="fi-rs-shopping-cart"></i>
                                    Ajouter au panier
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                {{-- ===== ÉTAT VIDE ===== --}}
                <div class="wishlist-empty">
                    <div class="wishlist-empty__icon">
                        <i class="fi-rs-heart"></i>
                    </div>
                    <h3 class="wishlist-empty__title">Votre liste est vide</h3>
                    <p class="wishlist-empty__text">
                        Parcourez le catalogue et cliquez sur le cœur des produits qui vous intéressent pour les retrouver ici plus tard.
                    </p>
                    <a href="{{ route('client.index') }}" class="wishlist-empty__btn">
                        <i class="fi-rs-arrow-right"></i> Découvrir les produits
                    </a>
                </div>
            @endif
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .wishlist-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 50px 20px 56px;
            overflow: hidden;
            isolation: isolate;
        }
        .wishlist-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .wishlist-hero__inner { max-width: 880px; margin: 0 auto; text-align: center; }
        .wishlist-hero__chip {
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
            margin-bottom: 14px;
        }
        .wishlist-hero__chip i { color: #ef4444; font-size: 16px; }
        .wishlist-hero__title,
        h1.wishlist-hero__title {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 8px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .wishlist-hero__subtitle {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 0.95rem;
            text-shadow: 0 1px 6px rgba(0,0,0,0.25);
        }
        .wishlist-hero__subtitle strong { color: #fbbf24; font-weight: 700; }

        /* ===== GRID ===== */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 22px;
        }

        /* ===== CARD ===== */
        .wishlist-card {
            position: relative;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }
        .wishlist-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.10);
            border-color: #cbd5e1;
        }

        .wishlist-card__remove {
            position: absolute;
            top: 10px; right: 10px;
            z-index: 2;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.10);
            transition: all 0.18s ease;
        }
        .wishlist-card__remove:hover {
            background: #ef4444;
            color: #ffffff;
            transform: scale(1.06);
            box-shadow: 0 8px 18px rgba(239, 68, 68, 0.35);
        }
        .wishlist-card__remove i { font-size: 16px; line-height: 1; }

        .wishlist-card__media {
            display: block;
            aspect-ratio: 4 / 3;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            overflow: hidden;
        }
        .wishlist-card__media img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .wishlist-card:hover .wishlist-card__media img { transform: scale(1.05); }

        .wishlist-card__body {
            padding: 18px 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
        .wishlist-card__name {
            color: #0a2540;
            font-weight: 700;
            font-size: 0.98rem;
            line-height: 1.35;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.7em;
        }
        .wishlist-card__name:hover { color: #1c57a3; }

        .wishlist-card__rate { display: inline-flex; align-items: center; gap: 6px; }
        .wishlist-card__rate-num { font-size: 0.78rem; color: #6b7280; }

        .wishlist-card__price {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 6px 10px;
            margin-top: auto;
        }
        .wishlist-card__price-current {
            color: #ea580c;
            font-weight: 800;
            font-size: 1.15rem;
            line-height: 1;
        }
        .wishlist-card__price-current small { font-size: 0.7em; font-weight: 600; color: #6b7280; }
        .wishlist-card__price-old {
            color: #9ca3af;
            text-decoration: line-through;
            font-size: 0.82rem;
        }
        .wishlist-card__price-tag {
            background: linear-gradient(135deg, #10b981, #047857);
            color: #fff;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .wishlist-card__add {
            margin-top: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 14px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.88rem;
            border: 0;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.30);
            transition: all 0.18s ease;
            letter-spacing: 0.01em;
        }
        .wishlist-card__add:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 12px 22px rgba(234, 88, 12, 0.42);
        }
        .wishlist-card__add:active { transform: translateY(0); }
        .wishlist-card__add i { font-size: 14px; }

        /* ===== ÉTAT VIDE ===== */
        .wishlist-empty {
            max-width: 520px;
            margin: 40px auto 0;
            padding: 50px 30px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .wishlist-empty__icon {
            width: 90px; height: 90px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fecaca, #fca5a5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ef4444;
            font-size: 36px;
        }
        .wishlist-empty__title {
            color: #0a2540;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0 0 8px;
        }
        .wishlist-empty__text {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 0 22px;
        }
        .wishlist-empty__btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.30);
            transition: all 0.18s ease;
        }
        .wishlist-empty__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(234, 88, 12, 0.42);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 575px) {
            .wishlist-hero { padding: 40px 16px 44px; }
            .wishlist-hero__title { font-size: 1.6rem; }
            .wishlist-grid { gap: 16px; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
            .wishlist-card__name { font-size: 0.88rem; }
            .wishlist-card__price-current { font-size: 1rem; }
            .wishlist-empty { padding: 36px 22px; }
        }
    </style>
@endsection
