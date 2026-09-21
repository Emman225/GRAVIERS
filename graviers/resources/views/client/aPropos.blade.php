@extends('client.main')
@section('title','À propos')
@section('content')

<style>
    /* Lot 109 (17/09/2026) : page À propos réécrite — palette de la charte, plus de contenu
       (matériaux, services, étapes, engagements, chiffres, espace professionnels). */
    .ap-hero{ background:linear-gradient(135deg,#0A2540 0%,#1C57A3 100%); color:#fff; padding:64px 0; text-align:center; position:relative; overflow:hidden; }
    .ap-hero::after{ content:""; position:absolute; right:-100px; top:-100px; width:360px; height:360px; border-radius:50%; background:rgba(255,179,0,.14); }
    .ap-hero::before{ content:""; position:absolute; left:-120px; bottom:-140px; width:320px; height:320px; border-radius:50%; background:rgba(255,255,255,.06); }
    .ap-hero > .container{ position:relative; }
    .ap-hero .ap-sur{ display:inline-block; background:rgba(255,179,0,.18); color:#FFB300; font-weight:700; letter-spacing:.6px; text-transform:uppercase;
        font-size:12px; padding:6px 14px; border-radius:20px; margin-bottom:14px; }
    .ap-hero h1{ color:#fff !important; font-weight:800; font-size:2.5rem; margin-bottom:12px; }
    .ap-hero p{ color:#dce9f7 !important; max-width:760px; margin:0 auto 24px; font-size:1.05rem; }
    .ap-btn{ display:inline-block; padding:12px 28px; border-radius:30px; font-weight:700; text-decoration:none; margin:4px; }
    .ap-btn--plein{ background:#FFB300; color:#0A2540 !important; }
    .ap-btn--plein:hover{ background:#ffc333; color:#0A2540; }
    .ap-btn--vide{ border:2px solid rgba(255,255,255,.7); color:#fff !important; }
    .ap-btn--vide:hover{ background:rgba(255,255,255,.12); color:#fff !important; }
    .ap-section{ padding:56px 0; }
    .ap-section--gris{ background:#f7f9fc; }
    .ap-titre{ font-weight:800; color:#0A2540; margin-bottom:14px; }
    .ap-titre::after{ content:""; display:block; width:56px; height:4px; border-radius:2px; background:#FFB300; margin-top:10px; }
    .text-center .ap-titre::after{ margin-left:auto; margin-right:auto; }
    .ap-lead{ color:#4a5568; line-height:1.8; font-size:1.02rem; }
    .ap-carte{ background:#fff; border:1px solid #e9eef5; border-radius:14px; padding:26px 22px; height:100%; transition:transform .15s ease, box-shadow .15s ease; }
    .ap-carte:hover{ transform:translateY(-4px); box-shadow:0 12px 30px rgba(10,37,64,.08); }
    .ap-carte .ico{ width:54px; height:54px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:#e8f0fa; color:#1C57A3; font-size:25px; margin-bottom:14px; }
    .ap-carte h5{ font-weight:700; margin-bottom:8px; color:#0A2540; }
    .ap-carte p{ color:#56637a; margin:0; }
    .ap-carte a.ap-lien{ display:inline-block; margin-top:12px; color:#1C57A3; font-weight:700; }
    .ap-materiaux{ display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:16px; }
    .ap-materiau{ background:#fff; border:1px solid #e9eef5; border-radius:14px; padding:18px; text-align:center; color:#0A2540; transition:transform .15s ease, box-shadow .15s ease; }
    .ap-materiau:hover{ transform:translateY(-4px); box-shadow:0 12px 30px rgba(10,37,64,.08); color:#1C57A3; }
    .ap-materiau .visuel{ width:72px; height:72px; border-radius:50%; margin:0 auto 12px; background:#eef3fa; display:flex; align-items:center; justify-content:center; overflow:hidden; font-weight:800; font-size:26px; color:#1C57A3; }
    .ap-materiau .visuel img{ width:100%; height:100%; object-fit:cover; }
    .ap-materiau strong{ display:block; font-size:15px; }
    .ap-materiau small{ color:#8794ab; }
    .ap-etapes{ display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:18px; }
    @media (max-width: 991px){ .ap-etapes{ grid-template-columns:repeat(2, minmax(0,1fr)); } }
    @media (max-width: 575px){ .ap-etapes{ grid-template-columns:1fr; } }
    .ap-etape{ position:relative; background:#fff; border:1px solid #e9eef5; border-radius:14px; padding:26px 20px 22px; }
    .ap-etape .num{ width:40px; height:40px; border-radius:50%; background:#0A2540; color:#FFB300 !important; font-weight:800; display:flex; align-items:center; justify-content:center; margin-bottom:14px; }
    .ap-etape h6{ font-weight:700; color:#0A2540; margin-bottom:6px; }
    .ap-etape p{ color:#56637a; margin:0; font-size:14.5px; }
    .ap-stats{ background:#0A2540; color:#fff; }
    .ap-stat{ text-align:center; padding:18px 10px; }
    .ap-stat .num{ font-size:2.2rem; font-weight:800; color:#FFB300 !important; line-height:1.1; }
    .ap-stat .lbl{ color:#cddff0 !important; font-weight:500; }
    .ap-cta{ background:linear-gradient(135deg,#0A2540,#1C57A3); color:#fff; border-radius:16px; padding:40px; text-align:center; }
    .ap-cta h3{ color:#fff !important; font-weight:800; margin-bottom:10px; }
    .ap-cta p{ color:#cddff0 !important; margin-bottom:22px; }
</style>

<main class="main">

    <section class="ap-hero">
        <div class="container">
            <span class="ap-sur">Une plateforme de DALAKOUN SARLU · Abidjan</span>
            <h1>Mon Gravier</h1>
            <p>Votre partenaire de confiance pour la fourniture, la livraison et le transport de matériaux de
               construction en Côte d'Ivoire&nbsp;: gravier, sable, ciment, fer à béton et briques, au juste prix
               et dans les délais.</p>
            <a class="ap-btn ap-btn--plein" href="{{ route('client.index') }}">Voir le catalogue</a>
            <a class="ap-btn ap-btn--vide" href="{{ route('client.demandeLivraison') }}">Demander une livraison</a>
        </div>
    </section>

    <section class="ap-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <img src="{{ asset('storage/productsImage/catalogue/gravier.jpg') }}"
                         alt="Matériaux de construction Mon Gravier"
                         style="width:100%; border-radius:16px; object-fit:cover; max-height:380px;" />
                </div>
                <div class="col-lg-6">
                    <h2 class="ap-titre">Qui sommes-nous&nbsp;?</h2>
                    <p class="ap-lead">
                        <strong>Mon Gravier</strong> (mongravier.com) est la plateforme de vente et de livraison
                        de matériaux de construction de <strong>DALAKOUN SARLU</strong>, entreprise ivoirienne
                        établie à Abidjan (Yopougon). Depuis notre agence, nous approvisionnons particuliers,
                        artisans et entreprises du BTP en granulats et matériaux de qualité.
                    </p>
                    <p class="ap-lead">
                        Notre ambition est simple&nbsp;: rendre l'achat de matériaux de construction
                        <strong>simple, transparent et fiable</strong>, grâce à une boutique en ligne moderne,
                        des prix clairs, une logistique de livraison maîtrisée et un suivi de chaque commande,
                        sur le site comme sur l'application mobile.
                    </p>
                </div>
            </div>
        </div>
    </section>

    @if (isset($categories) && $categories->count())
    <section class="ap-section ap-section--gris">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ap-titre">Nos matériaux</h2>
                <p class="ap-lead" style="max-width:680px;margin:0 auto;">Tout ce qu'il faut pour vos fondations, vos murs et vos finitions, disponible à la commande.</p>
            </div>
            <div class="ap-materiaux">
                @foreach ($categories as $categorie)
                    @php
                        $icone = $categorie->icon;
                        $adresseIcone = $icone ? (str_starts_with($icone, 'http') ? $icone : asset('storage/' . ltrim($icone, '/'))) : null;
                        $nombre = $categorie->produits->count();
                    @endphp
                    <a class="ap-materiau" href="{{ route('product.categorie', $categorie->nom) }}">
                        <span class="visuel">
                            @if ($adresseIcone)<img src="{{ $adresseIcone }}" alt="{{ $categorie->nom }}" loading="lazy">@else{{ mb_substr($categorie->nom, 0, 1) }}@endif
                        </span>
                        <strong>{{ $categorie->nom }}</strong>
                        <small>{{ $nombre }} {{ $nombre > 1 ? 'produits' : 'produit' }}</small>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="ap-section">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ap-titre">Nos services</h2>
                <p class="ap-lead" style="max-width:680px;margin:0 auto;">Au-delà de la vente, nous accompagnons votre chantier de bout en bout.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-shopping-cart"></i></div>
                        <h5>Vente de matériaux</h5>
                        <p>Commande en ligne ou en agence, prix affichés en FCFA, devis gratuit pour les gros volumes.</p>
                        <a class="ap-lien" href="{{ route('client.index') }}">Voir le catalogue →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-marker"></i></div>
                        <h5>Livraison sur chantier</h5>
                        <p>Vos matériaux livrés à l'adresse de votre choix, avec un code de livraison et un suivi en ligne.</p>
                        <a class="ap-lien" href="{{ route('client.index') }}">Commander →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-box"></i></div>
                        <h5>Transport de marchandise</h5>
                        <p>Une marchandise à déplacer d'un point à un autre&nbsp;? Décrivez-la, nous calculons le coût avant validation.</p>
                        <a class="ap-lien" href="{{ route('client.demandeLivraison') }}">Demander une livraison →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-time-fast"></i></div>
                        <h5>Location de matériel</h5>
                        <p>Engins et équipements de chantier à la journée, livrés ou à retirer sur place.</p>
                        <a class="ap-lien" href="{{ route('client.location') }}">Voir la location →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-credit-card"></i></div>
                        <h5>Paiement flexible</h5>
                        <p>Mobile Money, virement, espèces à l'agence, et un compte client à terme pour les entreprises.</p>
                        <a class="ap-lien" href="{{ route('client.register') }}">Créer un compte →</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-document"></i></div>
                        <h5>Documents en règle</h5>
                        <p>Proforma, facture, bon de livraison et reçus envoyés par courriel et disponibles dans votre compte.</p>
                        <a class="ap-lien" href="{{ route('client.register') }}">Ouvrir mon espace →</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ap-section ap-section--gris">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ap-titre">Comment ça marche&nbsp;?</h2>
            </div>
            <div class="ap-etapes">
                <div class="ap-etape"><span class="num">1</span><h6>Choisissez</h6><p>Parcourez le catalogue, comparez les prix et ajoutez vos matériaux au panier.</p></div>
                <div class="ap-etape"><span class="num">2</span><h6>Commandez</h6><p>Validez votre commande en ligne ou demandez un devis pour un gros volume.</p></div>
                <div class="ap-etape"><span class="num">3</span><h6>Réglez</h6><p>Mobile Money, virement, espèces à l'agence, ou à terme pour les entreprises.</p></div>
                <div class="ap-etape"><span class="num">4</span><h6>Recevez</h6><p>Livraison sur votre chantier avec code de livraison, ou retrait sur place.</p></div>
            </div>
        </div>
    </section>

    <section class="ap-section">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ap-titre">Pourquoi nous choisir&nbsp;?</h2>
                <p class="ap-lead" style="max-width:680px;margin:0 auto;">Des engagements concrets pour mener vos chantiers sereinement.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-shield-check"></i></div>
                        <h5>Qualité garantie</h5>
                        <p>Des matériaux calibrés et sélectionnés, conformes aux exigences de vos chantiers.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-time-fast"></i></div>
                        <h5>Livraison rapide</h5>
                        <p>Une flotte adaptée pour livrer vos commandes en vrac, en big bag ou en sacs.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-label"></i></div>
                        <h5>Prix justes</h5>
                        <p>Des tarifs transparents affichés en FCFA, dégressifs selon le volume, sans surprise au paiement.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-headset"></i></div>
                        <h5>À votre écoute</h5>
                        <p>Du lundi au samedi, de 08:00 à 18:00, par téléphone, WhatsApp ou courriel.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ap-stats">
        <div class="container">
            <div class="row py-4">
                <div class="col-6 col-lg-3"><div class="ap-stat"><div class="num">{{ isset($categories) ? $categories->count() : 5 }}</div><div class="lbl">Catégories de matériaux</div></div></div>
                <div class="col-6 col-lg-3"><div class="ap-stat"><div class="num">6j/7</div><div class="lbl">Service client</div></div></div>
                <div class="col-6 col-lg-3"><div class="ap-stat"><div class="num">100%</div><div class="lbl">Paiement sécurisé</div></div></div>
                <div class="col-6 col-lg-3"><div class="ap-stat"><div class="num">Abidjan</div><div class="lbl">&amp; environs livrés</div></div></div>
            </div>
        </div>
    </section>

    <section class="ap-section">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="ap-titre">Espace professionnels</h2>
                <p class="ap-lead" style="max-width:680px;margin:0 auto;">Vous transportez, vous produisez ou vous apportez des clients&nbsp;? Rejoignez le réseau Mon Gravier.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-box"></i></div>
                        <h5>Livreur</h5>
                        <p>Vous avez un camion ou une benne&nbsp;? Recevez des courses et suivez vos livraisons depuis l'application.</p>
                        <a class="ap-lien" href="{{ route('devenirLivreur') }}">Devenir livreur →</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-building"></i></div>
                        <h5>Fournisseur</h5>
                        <p>Carrière, cimenterie, briqueterie&nbsp;: vendez vos produits à notre clientèle et suivez vos livraisons.</p>
                        <a class="ap-lien" href="{{ route('devenirFournisseur') }}">Devenir fournisseur →</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="ap-carte">
                        <div class="ico"><i class="fi-rs-users"></i></div>
                        <h5>Affilié</h5>
                        <p>Apportez des clients et percevez une commission sur les commandes qu'ils passent.</p>
                        <a class="ap-lien" href="{{ route('apporteur.register') }}">Devenir affilié →</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="ap-section pt-0">
        <div class="container">
            <div class="ap-cta">
                <h3>Prêt à démarrer votre projet&nbsp;?</h3>
                <p>Parcourez notre catalogue et commandez vos matériaux en quelques clics, ou écrivez-nous.</p>
                <a class="ap-btn ap-btn--plein" href="{{ route('client.index') }}">Voir le catalogue</a>
                <a class="ap-btn ap-btn--vide" href="{{ route('contact') }}">Nous contacter</a>
            </div>
        </div>
    </section>

</main>

@endsection
