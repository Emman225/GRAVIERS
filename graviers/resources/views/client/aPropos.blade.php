@extends('client.main')
@section('title','À propos')
@section('content')

<style>
    .apropos-hero{
        background: linear-gradient(135deg, #0b3d2e 0%, #14855a 100%);
        color:#fff; padding:70px 0; text-align:center;
    }
    .apropos-hero h1{ color:#fff; font-weight:800; font-size:2.4rem; margin-bottom:12px; }
    .apropos-hero p{ color:#e8f5ee; max-width:760px; margin:0 auto; font-size:1.05rem; }
    .apropos-section{ padding:55px 0; }
    .apropos-section h2{ font-weight:800; margin-bottom:18px; color:#0b3d2e; }
    .apropos-lead{ color:#4a4a4a; line-height:1.8; font-size:1.02rem; }
    .apropos-card{
        background:#fff; border:1px solid #eee; border-radius:14px; padding:28px 24px;
        height:100%; transition:transform .15s ease, box-shadow .15s ease;
    }
    .apropos-card:hover{ transform:translateY(-4px); box-shadow:0 12px 30px rgba(0,0,0,.08); }
    .apropos-card .ico{
        width:56px; height:56px; border-radius:12px; display:flex; align-items:center; justify-content:center;
        background:#e7f6ee; color:#14855a; font-size:26px; margin-bottom:16px;
    }
    .apropos-card h5{ font-weight:700; margin-bottom:10px; color:#0b3d2e; }
    .apropos-card p{ color:#666; margin:0; }
    .apropos-stats{ background:#f6f9f7; }
    .apropos-stat{ text-align:center; padding:20px 10px; }
    .apropos-stat .num{ font-size:2.2rem; font-weight:800; color:#14855a; }
    .apropos-stat .lbl{ color:#555; font-weight:500; }
    .apropos-cta{
        background:#0b3d2e; color:#fff; border-radius:16px; padding:40px; text-align:center;
    }
    .apropos-cta h3{ color:#fff; font-weight:800; margin-bottom:10px; }
    .apropos-cta p{ color:#cfe6db; margin-bottom:22px; }
    .apropos-cta .btn-cta{
        background:#14855a; color:#fff; padding:12px 30px; border-radius:30px; font-weight:700;
        display:inline-block; text-decoration:none;
    }
    .apropos-cta .btn-cta:hover{ background:#0f6e49; }
</style>

<main class="main">

    <section class="apropos-hero">
        <div class="container">
            <h1>DALAKOUN SARL</h1>
            <p>Votre partenaire de confiance pour la fourniture et la livraison de matériaux de
               construction en Côte d'Ivoire&nbsp;: gravier, sable, ciment, fer à béton et briques,
               au juste prix et dans les délais.</p>
        </div>
    </section>

    <section class="apropos-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="{{ asset('storage/productsImage/catalogue/gravier.jpg') }}"
                         alt="Matériaux de construction DALAKOUN SARL"
                         style="width:100%; border-radius:16px; object-fit:cover; max-height:380px;" />
                </div>
                <div class="col-lg-6">
                    <h2>Qui sommes-nous&nbsp;?</h2>
                    <p class="apropos-lead">
                        <strong>DALAKOUN SARL</strong>, à travers sa plateforme <strong>gravier.com</strong>,
                        est une entreprise ivoirienne spécialisée dans la commercialisation de matériaux de
                        construction. Depuis Abidjan, nous approvisionnons particuliers, artisans et
                        entreprises du BTP en granulats et matériaux de qualité.
                    </p>
                    <p class="apropos-lead">
                        Notre ambition est simple&nbsp;: rendre l'achat de matériaux de construction
                        <strong>simple, transparent et fiable</strong>, grâce à une boutique en ligne moderne,
                        des prix clairs et une logistique de livraison maîtrisée sur tout le district d'Abidjan
                        et au-delà.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="apropos-section" style="background:#fbfdfc;">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Pourquoi nous choisir&nbsp;?</h2>
                <p class="apropos-lead" style="max-width:680px;margin:0 auto;">
                    Des engagements concrets pour mener vos chantiers sereinement.
                </p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="apropos-card">
                        <div class="ico"><i class="fi-rs-shield-check"></i></div>
                        <h5>Qualité garantie</h5>
                        <p>Des matériaux calibrés et sélectionnés, conformes aux exigences de vos chantiers.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="apropos-card">
                        <div class="ico"><i class="fi-rs-truck-side"></i></div>
                        <h5>Livraison rapide</h5>
                        <p>Une flotte adaptée pour livrer vos commandes en vrac, en big bag ou en sacs.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="apropos-card">
                        <div class="ico"><i class="fi-rs-tags"></i></div>
                        <h5>Prix justes</h5>
                        <p>Des tarifs transparents affichés en FCFA, sans mauvaise surprise au moment de payer.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="apropos-card">
                        <div class="ico"><i class="fi-rs-headset"></i></div>
                        <h5>Support 7j/7</h5>
                        <p>Une équipe à votre écoute pour vous conseiller et suivre vos commandes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="apropos-stats apropos-section">
        <div class="container">
            <div class="row">
                <div class="col-6 col-lg-3"><div class="apropos-stat"><div class="num">5</div><div class="lbl">Catégories de matériaux</div></div></div>
                <div class="col-6 col-lg-3"><div class="apropos-stat"><div class="num">7j/7</div><div class="lbl">Service client</div></div></div>
                <div class="col-6 col-lg-3"><div class="apropos-stat"><div class="num">100%</div><div class="lbl">Paiement sécurisé</div></div></div>
                <div class="col-6 col-lg-3"><div class="apropos-stat"><div class="num">Abidjan</div><div class="lbl">& environs livrés</div></div></div>
            </div>
        </div>
    </section>

    <section class="apropos-section">
        <div class="container">
            <div class="apropos-cta">
                <h3>Prêt à démarrer votre projet&nbsp;?</h3>
                <p>Parcourez notre catalogue et commandez vos matériaux en quelques clics.</p>
                <a class="btn-cta" href="{{ route('client.index') }}">Voir le catalogue</a>
                <a class="btn-cta" style="background:transparent;border:1px solid #14855a;margin-left:10px;" href="{{ route('contact') }}">Nous contacter</a>
            </div>
        </div>
    </section>

</main>

@endsection
