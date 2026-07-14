@php
    use illuminate\Support\Carbon;

    // dd(session())
@endphp
@extends('client.main')
@section('title','Accueil')
@section('content')
@if(session('annule'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{session('annule')}}
    </div>
@endif
@if(session('remove'))
    <div class="alert alert-info conatiner.fluid text-center" id="notify">
        {{session('remove')}}
    </div>
@endif

@if(session('ok'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{session('ok')}}
    </div>
@endif
@if(session('sucessLivraison'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{session('sucessLivraison')}}
    </div>
@endif
{{-- @if(session('sucessLivraison')) --}}
    <div class="alert alert-info  text-center authBar coller-en-haut mt-5" style="display: none;" id="notify">
        <span class="auth"></span>
    </div>
    <div class="alert alert-success  text-center like coller-en-haut mt-5" style="display: none;" id="notify">
        <span class="rep"></span>
    </div>
    <div class="alert alert-success  text-center ajoute coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Produit ajouté</span>
    </div>
{{-- @endif --}}

{{-- @if(session('deja')) --}}
    <div class="alert alert-warning conatiner.fluid text-center deja coller-en-haut" style="display: none" id="notify">
        Vous avez déjà selectionné ce produit
    </div>
{{-- @endif --}}
@if(session('devisSaved'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{session('devisSaved')}}
    </div>
@endif
@if(session('commande'))
    <div class="alert alert-success conatiner.fluid text-center" id="notify">
        {{session('commande')}}
    </div>
@endif
    <!-- Quick view -->

    {{-- affichage mobile --}}
    @include('client.navMobile')
    <!--End header-->

    <main class="main">
        <div class="container-fluid px-4 mb-30" style="margin-top: -1px;">
            <div class="row flex-row-reverse" style="margin-right: -15px;">
                <div class="col-lg-4-5">
                    <!-- Hero Slider -->
                    <style>
                        /* Slider compact pour la page d'accueil */
                        .home-slider-compact .hero-slider-1 .single-hero-slider {
                            height: 180px !important;
                        }
                        .home-slider-compact .hero-slider-1 img {
                            max-height: 180px !important;
                        }
                        .home-slider-compact .hero-slider-1 .slider-content h1 {
                            font-size: 1.4rem !important;
                            margin-bottom: 5px !important;
                        }
                        .home-slider-compact .hero-slider-1 .slider-content p {
                            margin-bottom: 10px !important;
                            font-size: 0.85rem !important;
                        }
                        .home-slider-compact .hero-slider-1 .slider-content .btn {
                            padding: 6px 16px !important;
                            font-size: 0.8rem !important;
                        }
                        /* Cartes produits compactes */
                        .product-grid-4 .product-cart-wrap .product-img-action-wrap {
                            padding: 8px !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-img-action-wrap .product-img a {
                            max-height: 120px;
                            overflow: hidden;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            border-radius: 16px;
                        }
                        .product-grid-4 .product-cart-wrap .product-img-action-wrap .product-img a img {
                            object-fit: cover;
                            height: 120px;
                            width: 100%;
                            border-radius: 16px;
                        }
                        .product-grid-4 .product-cart-wrap {
                            margin-bottom: 12px !important;
                            border-radius: 20px !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-content-wrap {
                            padding: 8px 12px 12px !important;
                            min-height: auto !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-content-wrap h2 {
                            font-size: 13px !important;
                            margin: 4px 0 !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-content-wrap h2 a {
                            font-size: 13px !important;
                            margin-bottom: 0 !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-rate-cover {
                            margin-bottom: 0 !important;
                            line-height: 1 !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-category {
                            margin-bottom: 0 !important;
                            font-size: 11px;
                        }
                        .product-grid-4 .product-cart-wrap .product-card-bottom {
                            margin-top: 4px !important;
                        }
                        .product-grid-4 .product-cart-wrap .product-price span {
                            font-size: 1rem !important;
                        }
                        .product-grid-4 .product-cart-wrap .add-cart .add {
                            padding: 8px 16px !important;
                            font-size: 12px !important;
                            margin-top: 6px !important;
                        }
                        .section-title.style-2 {
                            margin-bottom: 8px !important;
                        }
                        .section-title.style-2 h3 {
                            font-size: 1.2rem !important;
                            padding-bottom: 8px !important;
                        }
                        /* Slider loader */
                        .slider-loader {
                            position: absolute;
                            inset: 0;
                            z-index: 10;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            background: #f5f5f5;
                            border-radius: 30px;
                            transition: opacity 0.4s ease;
                        }
                        .slider-loader .spinner {
                            width: 36px;
                            height: 36px;
                            border: 4px solid #e2e8f0;
                            border-top-color: #3bb77e;
                            border-radius: 50%;
                            animation: spin 0.7s linear infinite;
                        }
                        @keyframes spin {
                            to { transform: rotate(360deg); }
                        }
                        .slider-loader.hidden {
                            opacity: 0;
                            pointer-events: none;
                        }
                    </style>
                    {{-- ===== STYLES PREMIUM DU SLIDER ===== --}}
                    <style>
                        .premium-hero-slider .single-hero-slider {
                            height: 420px !important;
                            position: relative;
                            background-size: cover !important;
                            background-position: center !important;
                            border-radius: 18px;
                            overflow: hidden;
                        }
                        .premium-hero-slider .single-hero-slider::before {
                            content: '';
                            position: absolute;
                            inset: 0;
                            background: linear-gradient(110deg,
                                rgba(0,20,40,0.85) 0%,
                                rgba(0,30,60,0.70) 35%,
                                rgba(0,40,80,0.30) 60%,
                                rgba(0,0,0,0.10) 100%);
                            z-index: 1;
                        }
                        .premium-hero-slider .slider-content {
                            position: relative;
                            z-index: 2;
                            max-width: 580px;
                            padding: 38px 44px;
                            color: #fff;
                            height: 100%;
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                        }
                        .slider-badge {
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            background: rgba(255,255,255,0.15);
                            backdrop-filter: blur(6px);
                            -webkit-backdrop-filter: blur(6px);
                            border: 1px solid rgba(255,255,255,0.25);
                            color: #fff;
                            font-size: 0.7rem;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 1.2px;
                            padding: 6px 12px;
                            border-radius: 999px;
                            width: fit-content;
                            margin-bottom: 14px;
                            animation: slideDown 0.6s ease-out;
                        }
                        .slider-badge.badge-promo {
                            background: linear-gradient(135deg, #f97316, #ef4444);
                            border-color: transparent;
                        }
                        .slider-badge.badge-new {
                            background: linear-gradient(135deg, #10b981, #059669);
                            border-color: transparent;
                        }
                        .slider-badge.badge-hot {
                            background: linear-gradient(135deg, #f59e0b, #d97706);
                            border-color: transparent;
                        }
                        .slider-badge i {
                            font-size: 14px;
                            line-height: 1;
                        }
                        .premium-hero-slider .slider-title {
                            font-size: 2.1rem !important;
                            font-weight: 800 !important;
                            line-height: 1.15 !important;
                            margin: 0 0 12px !important;
                            color: #fff !important;
                            letter-spacing: -0.5px;
                            animation: slideUp 0.7s ease-out 0.1s both;
                        }
                        .premium-hero-slider .slider-title .accent {
                            color: #fbbf24;
                            background: linear-gradient(135deg, #fbbf24, #f59e0b);
                            -webkit-background-clip: text;
                            -webkit-text-fill-color: transparent;
                            background-clip: text;
                        }
                        .premium-hero-slider .slider-desc {
                            font-size: 0.95rem !important;
                            line-height: 1.5 !important;
                            margin: 0 0 18px !important;
                            color: rgba(255,255,255,0.92) !important;
                            max-width: 460px;
                            animation: slideUp 0.7s ease-out 0.2s both;
                        }
                        .premium-hero-slider .slider-features {
                            display: flex;
                            gap: 18px;
                            margin-bottom: 22px;
                            flex-wrap: wrap;
                            animation: slideUp 0.7s ease-out 0.3s both;
                        }
                        .premium-hero-slider .slider-feature {
                            display: flex;
                            align-items: center;
                            gap: 7px;
                            font-size: 0.78rem;
                            font-weight: 600;
                            color: #fff;
                        }
                        .premium-hero-slider .slider-feature i {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 26px;
                            height: 26px;
                            border-radius: 50%;
                            background: rgba(255,255,255,0.18);
                            color: #34d399;
                            font-size: 12px;
                            border: 1px solid rgba(255,255,255,0.20);
                        }
                        .premium-hero-slider .slider-price {
                            display: flex;
                            align-items: baseline;
                            gap: 8px;
                            margin-bottom: 18px;
                            animation: slideUp 0.7s ease-out 0.35s both;
                        }
                        .premium-hero-slider .slider-price-label {
                            font-size: 0.75rem;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            color: rgba(255,255,255,0.75);
                        }
                        .premium-hero-slider .slider-price-value {
                            font-size: 1.5rem;
                            font-weight: 800;
                            color: #fbbf24;
                        }
                        .premium-hero-slider .slider-actions {
                            display: flex;
                            gap: 10px;
                            flex-wrap: wrap;
                            animation: slideUp 0.7s ease-out 0.45s both;
                        }
                        .premium-hero-slider .btn-slider-primary {
                            background: linear-gradient(135deg, #3bb77e, #1a8d5a);
                            color: #fff;
                            font-weight: 700;
                            padding: 11px 22px;
                            border-radius: 10px;
                            text-decoration: none;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            font-size: 0.9rem;
                            box-shadow: 0 8px 20px rgba(59,183,126,0.35);
                            transition: transform 0.2s, box-shadow 0.2s;
                            border: 0;
                        }
                        .premium-hero-slider .btn-slider-primary:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 12px 28px rgba(59,183,126,0.45);
                            color: #fff;
                        }
                        .premium-hero-slider .btn-slider-outline {
                            background: rgba(255,255,255,0.08);
                            backdrop-filter: blur(6px);
                            -webkit-backdrop-filter: blur(6px);
                            color: #fff;
                            font-weight: 600;
                            padding: 10px 20px;
                            border-radius: 10px;
                            text-decoration: none;
                            display: inline-flex;
                            align-items: center;
                            gap: 8px;
                            font-size: 0.9rem;
                            border: 1.5px solid rgba(255,255,255,0.5);
                            transition: background 0.2s, border-color 0.2s;
                        }
                        .premium-hero-slider .btn-slider-outline:hover {
                            background: rgba(255,255,255,0.18);
                            border-color: #fff;
                            color: #fff;
                        }
                        /* Décoration coin droit */
                        .premium-hero-slider .slider-decoration {
                            position: absolute;
                            top: 22px;
                            right: 22px;
                            z-index: 2;
                            background: rgba(255,255,255,0.10);
                            backdrop-filter: blur(8px);
                            -webkit-backdrop-filter: blur(8px);
                            border: 1px solid rgba(255,255,255,0.20);
                            color: #fff;
                            padding: 10px 16px;
                            border-radius: 12px;
                            text-align: right;
                            font-size: 0.75rem;
                            animation: slideLeft 0.7s ease-out 0.5s both;
                        }
                        .premium-hero-slider .slider-decoration .deco-value {
                            display: block;
                            font-size: 1.3rem;
                            font-weight: 800;
                            color: #fbbf24;
                            line-height: 1;
                        }
                        .premium-hero-slider .slider-decoration .deco-label {
                            display: block;
                            margin-top: 3px;
                            opacity: 0.85;
                        }
                        /* Animations */
                        @keyframes slideUp {
                            from { opacity: 0; transform: translateY(20px); }
                            to   { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes slideDown {
                            from { opacity: 0; transform: translateY(-15px); }
                            to   { opacity: 1; transform: translateY(0); }
                        }
                        @keyframes slideLeft {
                            from { opacity: 0; transform: translateX(20px); }
                            to   { opacity: 1; transform: translateX(0); }
                        }
                        /* Responsive */
                        @media (max-width: 991px) {
                            .premium-hero-slider .single-hero-slider { height: 360px !important; }
                            .premium-hero-slider .slider-content { padding: 28px 30px; max-width: 100%; }
                            .premium-hero-slider .slider-title { font-size: 1.6rem !important; }
                            .premium-hero-slider .slider-decoration { display: none; }
                        }
                        @media (max-width: 575px) {
                            .premium-hero-slider .single-hero-slider { height: 320px !important; }
                            .premium-hero-slider .slider-title { font-size: 1.3rem !important; }
                            .premium-hero-slider .slider-desc { font-size: 0.85rem !important; }
                            .premium-hero-slider .slider-features { gap: 10px; }
                        }
                    </style>

                    <section class="home-slider premium-hero-slider position-relative mb-0" style="margin-top: 0 !important;">
                        <div class="home-slide-cover" style="position: relative;">
                            <div class="slider-loader" id="sliderLoader">
                                <div class="spinner"></div>
                            </div>
                            <div class="hero-slider-1 style-4 dot-style-1 dot-style-1-position-1" style="visibility: hidden;">

                                {{-- Slide 1 - Camion benne : Présentation générale --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-camion-benne.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">15+</span>
                                        <span class="deco-label">années d'expérience</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge badge-new">
                                            <i class="fi-rs-star"></i> N°1 EN CÔTE D'IVOIRE
                                        </span>
                                        <h1 class="slider-title">Construisez vos rêves,<br><span class="accent">on fournit le reste</span></h1>
                                        <p class="slider-desc">Sable, gravier, ciment, fer, briques... toute la matière première de qualité pour réussir votre chantier au meilleur prix.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Qualité garantie</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Prix professionnels</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Catalogue complet</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="#popular-categories" class="btn-slider-primary">
                                                <i class="fi-rs-shopping-cart"></i> Commander maintenant
                                            </a>
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-outline">
                                                Demander un devis
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Slide 2 - Camion sable : Livraison --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-camion-sable.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">&lt; 24h</span>
                                        <span class="deco-label">livraison express</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge badge-promo">
                                            <i class="fi-rs-truck"></i> LIVRAISON EXPRESS
                                        </span>
                                        <h1 class="slider-title">Livraison express<br><span class="accent">sur tous vos chantiers</span></h1>
                                        <p class="slider-desc">Recevez vos matériaux directement sur site en moins de 24h, partout en Côte d'Ivoire. Flotte de camions dédiée.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Livraison rapide</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Suivi en temps réel</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Toute la CI</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-primary">
                                                <i class="fi-rs-marker"></i> Demander une livraison
                                            </a>
                                            <a href="#popular-categories" class="btn-slider-outline">
                                                Voir les produits
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Slide 3 - Camion gravier : Tarifs --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-camion-gravier.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">-15%</span>
                                        <span class="deco-label">sur commandes en gros</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge badge-hot">
                                            <i class="fi-rs-percentage"></i> OFFRE LIMITÉE
                                        </span>
                                        <h1 class="slider-title">Des prix qui défient<br><span class="accent">toute concurrence</span></h1>
                                        <p class="slider-desc">Profitez de tarifs professionnels sur toute notre gamme de matériaux. Plus vous commandez, plus vous économisez.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Tarifs dégressifs</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Devis gratuit</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Aucun frais caché</span>
                                        </div>
                                        <div class="slider-price">
                                            <span class="slider-price-label">À partir de</span>
                                            <span class="slider-price-value">15 000 FCFA / T</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="#popular-categories" class="btn-slider-primary">
                                                <i class="fi-rs-eye"></i> Voir nos offres
                                            </a>
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-outline">
                                                Devis personnalisé
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Slide 4 - Ciment : Qualité --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-ciment.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">100%</span>
                                        <span class="deco-label">certifié & contrôlé</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge">
                                            <i class="fi-rs-shield-check"></i> QUALITÉ CERTIFIÉE
                                        </span>
                                        <h1 class="slider-title">Du ciment de qualité<br><span class="accent">pour des fondations solides</span></h1>
                                        <p class="slider-desc">Large gamme de ciment certifié pour tous types de constructions : maison, bâtiment, ouvrages d'art.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Norme CEM I & II</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Sacs 50 kg</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Stock permanent</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="#popular-categories" class="btn-slider-primary">
                                                <i class="fi-rs-list"></i> Voir le catalogue
                                            </a>
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-outline">
                                                Commander en gros
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Slide 5 - Barres de fer : Solidité --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-fer.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">Ø 6→32</span>
                                        <span class="deco-label">tous diamètres</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge badge-hot">
                                            <i class="fi-rs-medal"></i> BESTSELLER
                                        </span>
                                        <h1 class="slider-title">Barres de fer & armatures<br><span class="accent">au meilleur tarif</span></h1>
                                        <p class="slider-desc">Renforcez vos ouvrages avec nos fers à béton de qualité supérieure. Disponibles en tous diamètres et longueurs.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Acier haute résistance</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Sur-mesure possible</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Coupe gratuite</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="#popular-categories" class="btn-slider-primary">
                                                <i class="fi-rs-shopping-bag"></i> Acheter maintenant
                                            </a>
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-outline">
                                                Calculer le besoin
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Slide 6 - Briques : Volumes --}}
                                <div class="single-hero-slider single-animation-wrap" style="background-image: url('{{asset('frontend/assets/imgs/slider/slide-briques.jpg')}}')">
                                    <div class="slider-decoration">
                                        <span class="deco-value">Gros</span>
                                        <span class="deco-label">volumes -20%</span>
                                    </div>
                                    <div class="slider-content">
                                        <span class="slider-badge badge-promo">
                                            <i class="fi-rs-gift"></i> REMISE GROS VOLUMES
                                        </span>
                                        <h1 class="slider-title">Briques & parpaings<br><span class="accent">pour murs et clôtures</span></h1>
                                        <p class="slider-desc">Commandez en gros et bénéficiez de remises exceptionnelles. Briques pleines, creuses et parpaings standards.</p>
                                        <div class="slider-features">
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Plusieurs formats</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Robustesse certifiée</span>
                                            <span class="slider-feature"><i class="fi-rs-check"></i> Stock permanent</span>
                                        </div>
                                        <div class="slider-actions">
                                            <a href="#popular-categories" class="btn-slider-primary">
                                                <i class="fi-rs-shopping-cart"></i> Passer commande
                                            </a>
                                            <a href="{{ url('/demande-de-livraison') }}" class="btn-slider-outline">
                                                Devis gratuit
                                            </a>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div class="slider-arrow hero-slider-1-arrow"></div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                var checkSlider = setInterval(function() {
                                    var slider = document.querySelector('.hero-slider-1');
                                    if (slider && slider.classList.contains('slick-initialized')) {
                                        slider.style.visibility = 'visible';
                                        document.getElementById('sliderLoader').classList.add('hidden');
                                        clearInterval(checkSlider);
                                    }
                                }, 100);
                                // Fallback : masquer le loader après 3s max
                                setTimeout(function() {
                                    var s = document.querySelector('.hero-slider-1');
                                    if (s) s.style.visibility = 'visible';
                                    var l = document.getElementById('sliderLoader');
                                    if (l) l.classList.add('hidden');
                                }, 3000);
                            });
                        </script>
                    </section>
                    <!--End hero-->

                    {{-- liste des produit par catégorie --}}
                    <section class="product-tabs position-relative" style="padding-top: 5px; margin-top: 0;">
                        <div class="section-title style-2">
                            <h3>Produits populaires</h3>
                            <ul class="nav nav-tabs links" id="myTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="nav-tab-one" data-bs-toggle="tab" data-bs-target="#tab-one" type="button" role="tab" aria-controls="tab-one" aria-selected="true">Tout les produits</button>
                                </li>
                                @foreach ($categories as $categorie )
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="nav-tab-{{$categorie->id}}" data-bs-toggle="tab" data-bs-target="#tab-{{$categorie->id}}" type="button" role="tab" aria-controls="tab-{{$categorie->id}}" aria-selected="true"> {{$categorie->nom}} </button>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <!--End nav-tabs-->
                        {{-- @dd(d($client->produits)) --}}
                        {{-- Tab one --}}
                        <div class="tab-content" id="myTabContent">

                            {{-- Tab one --}}
                            {{-- Tab one --}}
                            <div class="tab-pane fade show active" id="tab-one" role="tabpanel" aria-labelledby="tab-one">
                                <div class="row product-grid-4">
                                    @foreach($produits as $produit)
                                            <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="product-cart-wrap mb-30">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="{{route('client.produit.info',$produit)}}">
                                                                @if($produit->image->first())
                                                                    <img class="default-img" src="{{asset('storage/'.$produit->image->first()->image)}}" alt="" loading="lazy" decoding="async" />
                                                                @endif
                                                            </a>
                                                        </div>

                                                        <div class="product-action-1">
                                                            <a aria-label="J'aime" class="action-btn" onclick="jaime({{$produit->id}})">
                                                                <i class="fi-rs-heart"></i>
                                                            </a>
                                                            <a aria-label="Vue rapide" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickView{{$produit->id}}">
                                                                <i class="fi-rs-eye"></i>
                                                            </a>
                                                        </div>

                                                        <div class="product-badges product-badges-position product-badges-mrg"></div>
                                                    </div>

                                                    <div class="product-content-wrap">

                                                        <div class="product-category">
                                                            @php $i=1; @endphp
                                                            @foreach($produit->categories as $categorie)
                                                                {{ ( $i>1 ) ? '|' : '' }}
                                                                <a href="">{{$categorie->nom}}</a>
                                                                @php $i++ @endphp
                                                            @endforeach
                                                        </div>

                                                        <h2>
                                                            <a href="{{route('client.produit.info',$produit)}}">{{$produit->nom}}</a>
                                                        </h2>

                                                        <div class="product-rate-cover">
                                                            <div class="product-rate d-inline-block">
                                                                <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                            </div>
                                                            <span class="font-small ml-5 text-muted">({{round(($produit->meilleur_note*5)/100,1)}})</span>
                                                        </div>

                                                        <div class="product-card-bottom d-flex flex-column">

                                                            <div class="product-price d-flex flex-column mb-2">
                                                                @if(isset($prixPerso[$produit->id]))
                                                                    <span class="fw-bold fs-5">
                                                                        {{ number_format($prixPerso[$produit->id],0,'',' ') }} fcfa
                                                                    </span>
                                                                    @if($produit->prix_moyen > $prixPerso[$produit->id])
                                                                        <span class="old-price text-muted text-decoration-line-through">
                                                                            {{ number_format($produit->prix_moyen,0,'',' ') }} fcfa
                                                                        </span>
                                                                    @endif
                                                                @else
                                                                    <span class="fw-bold fs-5">
                                                                        {{ number_format($produit->prix_moyen,0,'',' ') }} fcfa
                                                                    </span>
                                                                    @if($produit->prix_reduction > $produit->prix_moyen)
                                                                        <span class="old-price text-muted text-decoration-line-through">
                                                                            {{ number_format($produit->prix_reduction,0,'',' ') }} fcfa
                                                                        </span>
                                                                    @endif
                                                                @endif
                                                            </div>

                                                            <!-- BOUTON AJOUTER -->
                                                            <div class="add-cart w-100">
                                                                <a class="add btn btn-primary w-100"
                                                                onclick="ajouter({{$produit->id}})">
                                                                    <i class="fi-rs-shopping-cart me-2"></i> Ajouter
                                                                </a>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                    @endforeach
                                    <!--end product card-->

                                </div>
                                <!--End product-grid-4-->
                                <div class="pagination-area mt-20 mb-20">
                                    {{ $produits->links('vendor.pagination.custom') }}
                                </div>
                            </div>
                            <!--En tab one-->

                            {{-- Tab two --}}

                            @foreach($categories as $categorie)
                                {{-- {{$categorie->id}} --}}
                                <div class="tab-pane fade" id="tab-{{$categorie->id}}" role="tabpanel" aria-labelledby="tab-{{$categorie->id}}">
                                    <div class="row product-grid-4">

                                        <!--end product card-->
                                        @foreach($categorie->produits as $produit)
                                            <div class="col-lg-3 col-md-4 col-sm-6">
                                                <div class="product-cart-wrap mb-30">
                                                    <div class="product-img-action-wrap">
                                                        <div class="product-img product-img-zoom">
                                                            <a href="{{route('client.produit.info',$produit)}}">
                                                                @if($produit->image->first())
                                                                    <img class="default-img" src="storage/{{$produit->image->first()->image}}" alt="" loading="lazy" decoding="async" />
                                                                @endif
                                                            </a>
                                                        </div>
                                                        <div class="product-action-1">
                                                            <a aria-label="j'aime" class="action-btn" onclick="jaime({{$produit->id}})"><i class="fi-rs-heart"></i></a>
                                                            {{-- <a aria-label="Compare" class="action-btn" href="shop-compare.html"><i class="fi-rs-shuffle"></i></a> --}}
                                                            <a aria-label="Vue rapide" class="action-btn" data-bs-toggle="modal" data-bs-target="#quickView{{$produit->id}}"><i class="fi-rs-eye"></i></a>
                                                        </div>
                                                        <div class="product-badges product-badges-position product-badges-mrg">
                                                            <span class="sale">Promo</span>
                                                        </div>
                                                    </div>
                                                    <div class="product-content-wrap">
                                                        <div class="product-category">
                                                            <a href="shop-grid-right.html"> {{$categorie->nom}} </a>
                                                        </div>
                                                        <h2><a href="{{route('client.produit.info',$produit)}}"> {{$produit->nom}} </a></h2>
                                                        <div class="product-rate-cover">
                                                            <div class="product-rate d-inline-block">
                                                                <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                            </div>
                                                            <span class="font-small ml-5 text-muted"> ({{round(($produit->meilleur_note*5)/100,1)}})</span>
                                                        </div>
                                                        <div>
                                                            {{-- <span class="font-small text-muted">By <a href="vendor-details-1.html">Stouffer</a></span> --}}
                                                        </div>
                                                        <div class="product-card-bottom">
                                                            <div class="product-price">
                                                                @if(isset($prixPerso[$produit->id]))
                                                                    <span> {{number_format($prixPerso[$produit->id],0,'',' ')}} fcfa </span>
                                                                    @if($produit->prix_moyen > $prixPerso[$produit->id])
                                                                        <span class="old-price"> {{number_format($produit->prix_moyen,0,'',' ')}} fcfa </span>
                                                                    @endif
                                                                @else
                                                                    <span> {{number_format($produit->prix_moyen,0,'',' ')}} fcfa </span>
                                                                    @if($produit->prix_reduction > $produit->prix_moyen)
                                                                        <span class="old-price"> {{number_format($produit->prix_reduction,0,'',' ')}} fcfa </span>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                            <div class="add-cart">
                                                                <a class="add"  onclick="ajouter({{$produit->id}})"><i class="fi-rs-shopping-cart mr-5"></i>Ajouter </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                        <!--end product card-->

                                    </div>
                                    <!--End product-grid-4-->
                                </div>
                            @endforeach
                            <!--En tab two-->

                        </div>
                        <!--End tab-content-->
                    </section>

                    {{-- Deal of th day --}}
                    <!--Products Tabs-->
                    {{-- <section id="deal" class="section-padding pb-5">
                        <div class="section-title">
                            <h3 class="">Deals du jour</h3>
                            <a class="show-all" href="shop-grid-right.html">
                                Tous les deals
                                <i class="fi-rs-angle-right"></i>
                            </a>
                        </div>
                        <div class="row">
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <div class="product-cart-wrap style-2">
                                    <div class="product-img-action-wrap">
                                        <div class="product-img">
                                            <a href="shop-product-right.html">
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/22222.png')}}" alt="" />
                                            </a>
                                        </div>
                                    </div>
                                    <div class="product-content-wrap">
                                        <div class="deals-countdown-wrap">
                                            <div class="deals-countdown" data-countdown="{{Carbon::tomorrow()}}"></div>
                                        </div>
                                        <div class="deals-content">
                                            <h2><a href="shop-product-right.html">Seeds of Change Organic Quinoa, Brown</a></h2>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: 90%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (4.0)</span>
                                            </div>
                                            <div>
                                                <span class="font-small text-muted">By <a href="vendor-details-1.html">NestFood</a></span>
                                            </div>
                                            <div class="product-card-bottom">
                                                <div class="product-price">
                                                    <span>$32.85</span>
                                                    <span class="old-price">$33.8</span>
                                                </div>
                                                <div class="add-cart">
                                                    <a class="add" href="shop-cart.html"><i class="fi-rs-shopping-cart mr-5"></i>Add </a>
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
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/3.png')}}" alt="" />
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
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/55.png')}}" alt="" />
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
                                                <img src="{{asset('frontend/assets/imgs/theme/produit/5.png')}}" alt="" />
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
                    </section> --}}
                    <!--End Deals-->

                    {{-- bottom deal of the day --}}
                    {{-- <section class="banners">
                        <div class="row">
                            <div class="col-lg-4 col-md-6">
                                <div class="banner-img">
                                    <img src="assets/imgs/banner/banner-1.png" alt="" />
                                    <div class="banner-text">
                                        <h4>
                                            Everyday Fresh & <br />Clean with Our<br />
                                            Produits
                                        </h4>
                                        <a href="shop-grid-right.html" class="btn btn-xs">Shop Now <i class="fi-rs-arrow-small-right"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <div class="banner-img">
                                    <img src="assets/imgs/banner/banner-2.png" alt="" />
                                    <div class="banner-text">
                                        <h4>
                                            Make your Breakfast<br />
                                            Healthy and Easy
                                        </h4>
                                        <a href="shop-grid-right.html" class="btn btn-xs">Shop Now <i class="fi-rs-arrow-small-right"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 d-md-none d-lg-flex">
                                <div class="banner-img mb-sm-0">
                                    <img src="assets/imgs/banner/banner-3.png" alt="" />
                                    <div class="banner-text">
                                        <h4>The best Organic <br />Products Online</h4>
                                        <a href="shop-grid-right.html" class="btn btn-xs">Shop Now <i class="fi-rs-arrow-small-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section> --}}
                    <!--End banners-->
                </div>

                {{-- catégorie & Fill by --}}
                @include('client.categorieEtFiltreur')
            </div>
        </div>

        {{-- slide de catégorie --}}
        <section class="popular-categories section-padding" id="popular-categories">
            <div class="container">
                <div class="section-title">
                    <div class="title">
                        <h3>Achat par catégorie</h3>
                        <a class="show-all" href="shop-grid-right.html">
                            Toutes les catégories
                            <i class="fi-rs-angle-right"></i>
                        </a>
                    </div>
                    <div class="slider-arrow slider-arrow-2 flex-right carausel-8-columns-arrow" id="carausel-8-columns-arrows"></div>
                </div>
                <div class="carausel-8-columns-cover position-relative">
                    <div class="carausel-8-columns" id="carausel-8-columns">
                        @foreach($categories as $categorie)
                            <div class="card-1">
                                <figure class="img-hover-scale overflow-hidden">
                                    <a href="{{route('product.categorie',$categorie->nom)}}"><img src="storage/{{$categorie->icon}}" alt="" loading="lazy" decoding="async" /></a>
                                </figure>
                                <h6>
                                    <a href="{{route('product.categorie',$categorie->nom)}}"> {{$categorie->nom}} </a>
                                </h6>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
        <!--End category slider-->


        <section class="section-padding mb-30">
            <div class="container">

                <div class="row">
                    {{-- top selling --}}
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-sm-5 mb-md-0">
                        <h4 class="section-title style-1 mb-30 animated animated">Top Selling</h4>
                        <div class="product-list-small animated animated">
                            @foreach ($produits as $produit )
                            @if ($produit->statut == 1)
                                @if ($produit->meilleur_note >= 90)

                                    <article class="row align-items-center hover-up">
                                        <figure class="col-md-4 mb-0">
                                            @foreach ($produit->image as $image )
                                                <a href="shop-product-right.html"><img src="storage/{{$image->image}}" alt="" loading="lazy" decoding="async" /></a>
                                            @endforeach
                                        </figure>
                                        <div class="col-md-8 mb-0">
                                            <h6>
                                                <a href="shop-product-right.html"> {{$produit->nom}} </a>
                                            </h6>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> ({{round(($produit->meilleur_note*5)/100,1)}})</span>
                                            </div>
                                            <div class="product-price">
                                                @if(isset($prixPerso[$produit->id]))
                                                    <span>{{ number_format($prixPerso[$produit->id],0,'',' ') }} fcfa</span>
                                                    <span class="old-price">{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @else
                                                    <span>{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @endif
                                            </div>
                                        </div>
                                    </article>

                                @endif
                            @endif

                            @endforeach
                        </div>
                    </div>

                    {{-- Trending products --}}
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-md-0">
                        <h4 class="section-title style-1 mb-30 animated animated">Prouduit tendance</h4>
                        <div class="product-list-small animated animated">
                            @foreach ($produits as $produit)

                            @if ($produit->statut == 1)
                                @if($produit->meilleur_note >= 90)
                                    <article class="row align-items-center hover-up">
                                        <figure class="col-md-4 mb-0">
                                        @foreach ($produit->image as $image )
                                            <a href="shop-product-right.html"><img src="storage/{{$image->image}}" alt="" loading="lazy" decoding="async" /></a>
                                        @endforeach
                                        </figure>
                                        <div class="col-md-8 mb-0">
                                            <h6>
                                                <a href="shop-product-right.html">{{$produit->nom}}</a>
                                            </h6>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (({{round(($produit->meilleur_note*5)/100,1)}}))</span>
                                            </div>
                                            <div class="product-price">
                                                @if(isset($prixPerso[$produit->id]))
                                                    <span>{{ number_format($prixPerso[$produit->id],0,'',' ') }} fcfa</span>
                                                    <span class="old-price">{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @else
                                                    <span>{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endif
                            @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Recently added --}}
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-sm-5 mb-md-0">
                        <h4 class="section-title style-1 mb-30 animated animated">Ajoutés récement </h4>
                        <div class="product-list-small animated animated">
                            @foreach ($produits as $produit )

                                @if ($produit->meilleur_note >= 90)

                                    <article class="row align-items-center hover-up">
                                        <figure class="col-md-4 mb-0">
                                            @foreach ($produit->image as $image )
                                                <a href="shop-product-right.html"><img src="storage/{{$image->image}}" alt="" loading="lazy" decoding="async" /></a>
                                            @endforeach
                                        </figure>
                                        <div class="col-md-8 mb-0">
                                            <h6>
                                                <a href="shop-product-right.html"> {{$produit->nom}} </a>
                                            </h6>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> ({{round(($produit->meilleur_note*5)/100,1)}})</span>
                                            </div>
                                            <div class="product-price">
                                                @if(isset($prixPerso[$produit->id]))
                                                    <span>{{ number_format($prixPerso[$produit->id],0,'',' ') }} fcfa</span>
                                                    <span class="old-price">{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @else
                                                    <span>{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @endif
                                            </div>
                                        </div>
                                    </article>

                                @endif

                            @endforeach
                        </div>
                    </div>

                    {{-- Top rated --}}
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-md-0">
                        <h4 class="section-title style-1 mb-30 animated animated">Meilleure note</h4>
                        <div class="product-list-small animated animated">
                            @foreach ($produits as $produit)
                                @if($produit->meilleur_note >= 90)
                                    <article class="row align-items-center hover-up">
                                        <figure class="col-md-4 mb-0">
                                        @foreach ($produit->image as $image )
                                            <a href="shop-product-right.html"><img src="storage/{{$image->image}}" alt="" loading="lazy" decoding="async" /></a>
                                        @endforeach
                                        </figure>
                                        <div class="col-md-8 mb-0">
                                            <h6>
                                                <a href="shop-product-right.html">{{$produit->nom}}</a>
                                            </h6>
                                            <div class="product-rate-cover">
                                                <div class="product-rate d-inline-block">
                                                    <div class="product-rating" style="width: {{$produit->meilleur_note}}%"></div>
                                                </div>
                                                <span class="font-small ml-5 text-muted"> (({{round(($produit->meilleur_note*5)/100,1)}}))</span>
                                            </div>
                                            <div class="product-price">
                                                @if(isset($prixPerso[$produit->id]))
                                                    <span>{{ number_format($prixPerso[$produit->id],0,'',' ') }} fcfa</span>
                                                    <span class="old-price">{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @else
                                                    <span>{{ number_format($produit->prix_moyen,0,'',' ') }} fcfa</span>
                                                @endif
                                            </div>
                                        </div>
                                    </article>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!--End 4 columns-->

        <!-- 6-slide advertising moved to the top -->
    </main>

@endsection

