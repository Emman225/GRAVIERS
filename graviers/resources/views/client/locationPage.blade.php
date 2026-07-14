@extends('client.main')
@section('title','Location de matériel')
@section('content')

<div class="alert alert-success text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
    <span>Produit ajouté</span>
</div>

<div class="alert alert-success text-center like coller-en-haut mt-5" style="display: none;" id="notify">
    <span class="rep"></span>
</div>

<div class="alert alert-warning text-center deja coller-en-haut" style="display: none" id="notify">
    Vous avez déjà selectionné ce produit
</div>

@include('client.navMobile')

<main class="main location-page-main">
    {{-- ===== HERO ===== --}}
    <section class="location-page-hero">
        <div class="location-page-hero__inner">
            <span class="location-page-hero__chip"><i class="fi-rs-calendar"></i> Location</span>
            <h1 class="location-page-hero__title">Louez votre matériel de construction</h1>
            <p class="location-page-hero__subtitle">
                Bétonnières, échafaudages, vibreurs, compacteurs… Tout le matériel pro à louer pour vos chantiers.
            </p>
            <div class="location-page-hero__badges">
                <span><i class="fi-rs-shield-check"></i> Matériel vérifié</span>
                <span><i class="fi-rs-truck-side"></i> Livraison rapide</span>
                <span><i class="fi-rs-headset"></i> Support 7j/7</span>
            </div>
        </div>
    </section>

    <div class="container mt-30 mb-80">
        @if($produits->where('statut', 1)->count() > 0)
            <div class="tab-pane fade show active" id="tab-one" role="tabpanel" aria-labelledby="tab-one">
                <div class="row product-grid-4">
                    @foreach($produits as $produit)
                        @if ($produit->statut == 1)
                            <div class="col-lg-1-5 col-md-4 col-sm-2">
                                <div class="product-cart-wrap mb-30">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img product-img-zoom">
                                            <a href="{{ route('client.produit.info', $produit) }}">
                                                @foreach($produit->image as $image)
                                                    <img class="default-img" src="{{ asset('storage/'.$image->image) }}" alt="{{ $produit->nom }}" />
                                                @endforeach
                                            </a>
                                        </div>

                                        <div class="product-action-1">
                                            <a aria-label="J'aime" class="action-btn" onclick="jaime({{ $produit->id }})"><i class="fi-rs-heart"></i></a>
                                            <a aria-label="Vue rapide" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickView{{ $produit->id }}"><i class="fi-rs-eye"></i></a>
                                        </div>
                                        <div class="product-badges product-badges-position product-badges-mrg"></div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="product-category">
                                            @php $i = 1; @endphp
                                            @foreach($produit->categories as $categorie)
                                                {{ ($i > 1) ? '|' : '' }}
                                                <a href="">{{ $categorie->nom }}</a>
                                                @php $i++ @endphp
                                            @endforeach
                                        </div>
                                        <h2><a href="{{ route('client.produit.info', $produit) }}">{{ $produit->nom }}</a></h2>
                                        <div class="product-rate-cover">
                                            <div class="product-rate d-inline-block">
                                                <div class="product-rating" style="width: {{ $produit->meilleur_note }}%"></div>
                                            </div>
                                            <span class="font-small ml-5 text-muted">({{ round(($produit->meilleur_note * 5) / 100, 1) }})</span>
                                        </div>
                                        <div class="product-card-bottom">
                                            <div class="product-price">
                                                @if(false)
                                                    <span>{{ number_format($prixPerso[$produit->id], 0, '', ' ') }} fcfa</span>
                                                    <span class="old-price">{{ number_format($produit->prix_moyen, 0, '', ' ') }} fcfa</span>
                                                @else
                                                    <span>{{ number_format($produit->prix_moyen, 0, '', ' ') }} fcfa</span>
                                                    @if($produit->prix_reduction > 0)
                                                        <span class="old-price">{{ number_format($produit->prix_reduction, 0, '', ' ') }} fcfa</span>
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="add-cart">
                                                <a class="add" onclick="ajouter({{ $produit->id }})"><i class="fi-rs-shopping-cart mr-5"></i>Louer</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @else
            <div class="location-page-empty">
                <div class="location-page-empty__icon"><i class="fi-rs-calendar"></i></div>
                <h3 class="location-page-empty__title">Aucun matériel disponible</h3>
                <p class="location-page-empty__text">
                    Le catalogue de location est en cours de mise à jour. Revenez bientôt pour découvrir notre matériel professionnel.
                </p>
                <a href="{{ route('client.index') }}" class="location-page-empty__btn">
                    <i class="fi-rs-arrow-right"></i> Voir le catalogue vente
                </a>
            </div>
        @endif
    </div>
</main>

<style>
    .location-page-hero {
        position: relative;
        background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
        color: #fff;
        padding: 50px 20px 56px;
        overflow: hidden;
        isolation: isolate;
    }
    .location-page-hero::after {
        content: "";
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
            radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
        z-index: -1;
    }
    .location-page-hero__inner { max-width: 880px; margin: 0 auto; text-align: center; }
    .location-page-hero__chip {
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
    .location-page-hero__chip i { color: #fbbf24; font-size: 16px; }
    .location-page-hero__title,
    h1.location-page-hero__title {
        font-size: 2.2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin: 0 0 10px;
        color: #ffffff !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.35);
    }
    .location-page-hero__subtitle {
        margin: 0 0 22px;
        color: rgba(255,255,255,0.92);
        font-size: 1rem;
        text-shadow: 0 1px 6px rgba(0,0,0,0.25);
    }
    .location-page-hero__badges {
        display: inline-flex; flex-wrap: wrap; gap: 14px;
        justify-content: center;
    }
    .location-page-hero__badges span {
        display: inline-flex; align-items: center; gap: 6px;
        color: rgba(255,255,255,0.95);
        font-size: 0.85rem; font-weight: 600;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        padding: 6px 12px;
        border-radius: 999px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .location-page-hero__badges i { color: #10b981; }

    .location-page-empty {
        max-width: 520px;
        margin: 40px auto 0;
        padding: 50px 30px;
        text-align: center;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        box-shadow: 0 4px 14px rgba(15,23,42,0.05);
    }
    .location-page-empty__icon {
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
    .location-page-empty__title { color: #0a2540; font-weight: 700; margin: 0 0 8px; }
    .location-page-empty__text { color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 0 0 22px; }
    .location-page-empty__btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff !important;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(234,88,12,0.30);
        transition: all 0.18s ease;
    }
    .location-page-empty__btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(234,88,12,0.42);
    }

    @media (max-width: 575px) {
        .location-page-hero { padding: 36px 16px 40px; }
        .location-page-hero__title { font-size: 1.5rem; }
        .location-page-hero__subtitle { font-size: 0.9rem; }
    }
</style>
@endsection
