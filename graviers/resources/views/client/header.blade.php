@php
    $cart = Cart::content()->count();
    $like = 0;
    $client = null;
    $authUser = Auth::user();

    if ($authUser) {
        $client = $authUser->client ?? App\Models\Client::where('user_id', $authUser->id)->first();
    }

    if ($client) {
        // Compteur de favoris : exclut les likes "soft-supprimés" (deleted_at != null)
        $like = App\Models\like::where('client_id', $client->id)
            ->whereNull('deleted_at')
            ->count();
    }
@endphp

{{-- @dd($cart); --}}
<style>
    /* Correctif header grand écran : garantir l'affichage de TOUS les éléments du menu.
       Le thème laissait les items en inline-block sans gestion du débordement, ce qui
       rognait/masquait les derniers liens sur les écrans larges. */
    @media (min-width: 992px) {
        /* Header sur UNE seule ligne : logo · menu (avec "Espace pro") · recherche · actions.
           Espace gagné en regroupant les accès pro et en masquant les libellés texte des icônes. */
        .header-wrap.header-space-between {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            /* 17/09/2026 : 8 px d'air au-dessus et au-dessous — le logo de 46 px touchait
               les bords d'une barre de 48 px. */
            padding-top: 8px;
            padding-bottom: 8px;
        }
        /* Sécurité : masquer les éléments "mobile" sur grand écran */
        .header-wrap > .header-action-icon-2.d-lg-none,
        .header-wrap > .header-action-right.d-lg-none { display: none !important; }

        .header-wrap > .logo { flex: 0 0 auto; }
        /* Logo un peu plus compact pour libérer de la largeur.
           17/09/2026 (lot 106) : main.css impose min-width:135px à l'image (.logo.logo-width-1 a img) —
           le logo carré de 46 px flottait, centré, dans une boîte de 135 px, donc décalé de 44 px
           du bord ; et l'image en ligne laissait 6 px de descendante sous elle, d'où 3 px trop haut.
           Largeur naturelle, image en bloc : le logo est collé au bord et centré verticalement. */
        .header-wrap > .logo a { display: block; line-height: 0; }
        .header-wrap > .logo a img { display: block; height: 46px !important; width: auto !important; min-width: 0 !important; max-width: 120px !important; }
        /* Le thème pose 70px de marge à droite du logo (.logo-width-1, main.css) — un
           réglage hérité d'un en-tête sur deux lignes, où le logo était seul à gauche.
           Ici le rang est saturé : ces 70px sont pris à la barre de recherche, qui
           tombait à 220px pour un visiteur contre 516px pour un client connecté (les
           boutons « Se connecter / S'inscrire » et « Espace pro » occupant le reste).
           Le conteneur applique déjà un gap de 12px ; 16px suffisent à détacher le
           logo du menu. Le thème réinitialise déjà cette marge sous 768px. */
        .header-wrap > .logo.logo-width-1 { margin-right: 16px !important; }
        /* Lot 109 : la page courante en jaune dans le menu (couleur seule, sans soulignement). */
        .main-menu > nav > ul > li > a.active { color: var(--premium-secondary, #FFB300) !important; }
        /* Lot 107 : menu « Mon compte » au survol (Profil, Devenir un client à terme, Déconnexion). */
        .menu-compte-entete { width: 240px !important; padding: 8px 0 !important; text-align: left; }
        .menu-compte-entete ul { list-style: none; margin: 0; padding: 0; }
        .menu-compte-entete ul li { display: block; margin: 0; }
        .menu-compte-entete ul li + li { border-top: 1px solid #f0f0f0; }
        .menu-compte-entete ul li a, .menu-compte-entete ul li button {
            display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 18px !important;
            font-size: 14px !important; font-weight: 600; color: #253D4E !important; background: none !important; border: 0 !important;
            border-radius: 0 !important; box-shadow: none !important; text-align: left; cursor: pointer; line-height: 1.3; font-family: inherit;
            /* !important : le thème habille tout <button> en bouton bleu (premium-client.css). */
        }
        .menu-compte-entete ul li a:hover, .menu-compte-entete ul li button:hover { color: #1C57A3 !important; background: #f2f6fb !important; }
        .menu-compte-entete ul li i { font-size: 16px; width: 18px; text-align: center; }
        /* Menu : toujours sur UNE seule ligne (ne s'enroule pas, ne se compresse pas) */
        .header-wrap > .header-nav { flex: 0 0 auto !important; }
        .header-nav .main-menu { width: auto; }
        .main-menu > nav > ul {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0 2px;
            margin: 0;
            padding: 0;
            justify-content: flex-start;
        }
        .main-menu > nav > ul > li { white-space: nowrap; }
        .main-menu > nav > ul > li > a { font-size: 13px !important; padding: 4px 6px !important; }

        /* Recherche : occupe l'espace central (large quand il y a de la place),
           mais se réduit si nécessaire pour garder les boutons (S'inscrire) visibles. */
        .header-wrap > .premium-search { flex: 1 1 auto; min-width: 160px; margin: 0 12px; }
        .premium-search form { background: #ffffff !important; border: 1px solid #d8dde6 !important; border-radius: 50px; overflow: hidden; height: 40px; display: flex; width: 100%; }
        .premium-search input { background: #ffffff !important; color: #253D4E !important; border: none !important; padding: 0 16px !important; width: 100%; height: 100%; }
        .premium-search input::placeholder { color: #8a94a6 !important; }
        .premium-search button { background: transparent; border: none; color: #1C57A3 !important; padding: 0 14px; cursor: pointer; }

        /* Actions à droite ; icônes seules (libellés masqués) + boutons/icônes compacts
           pour libérer de la largeur au profit de la recherche. */
        .hotline { flex: 0 0 auto; }
        .hotline .header-action-icon-2 .lable { display: none !important; }
        .hotline .header-action-2 { gap: 10px; }
        .hotline .header-action-icon-2 { padding: 0 4px; }
        .header-auth-actions { gap: 6px !important; margin-left: 8px !important; margin-right: 0 !important; }
        .header-auth-btn { padding: 6px 12px !important; font-size: 12px !important; }

        /* Menu déroulant "Espace pro" */
        .header-pro { position: relative; }
        .header-pro > .header-pro__toggle i { font-size: 10px; margin-left: 2px; }
        .header-pro__menu {
            position: absolute;
            top: 100%;
            right: 0;
            min-width: 190px;
            list-style: none;
            margin: 8px 0 0;
            padding: 8px 0;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px);
            transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
            z-index: 100001;
        }
        .header-pro:hover > .header-pro__menu,
        .header-pro:focus-within > .header-pro__menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .header-pro__menu > li { display: block; }
        .header-pro__menu > li > a {
            display: block;
            padding: 9px 18px;
            font-size: 14px;
            color: #253D4E !important;
            white-space: nowrap;
        }
        .header-pro__menu > li > a:hover {
            background: rgba(28, 87, 163, 0.08);
            color: #0A2540 !important;
        }
    }
</style>
{{-- Lot 114 (19/09/2026) : rappel visible des seules personnes connectées tant que le mode est actif. --}}
@if (Auth::check() && \App\Models\Configuration::siteEnConstruction())
    <div class="rappel-site-en-construction" style="background:#FFB300; color:#0A2540; text-align:center; font-weight:700; font-size:13px; padding:6px 12px;">
        Site en construction : seules les personnes connectées voient ces pages.
    </div>
@endif
<header class="header-area header-style-1 header-height-2" style="z-index: 99999;">
    <div class="mobile-promotion">
        <span>Grande ouverture, <strong>- 15%</strong> Sur nos article. Juste <strong>3 jours</strong> </span>
    </div>
    {{-- <div class="header-top header-top-ptb-1 d-none d-lg-block">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-3 col-lg-4">
                    <div class="header-info">
                        <ul>
                            <li><a href="{{route('aPropos')}}">A propos</a></li>
                            @auth
                                <li><a href="{{route('client.monCompte')}}">Mon compte</a></li>
                                <li><a href="{{route('client.wishList')}}">Mes souhait</a></li>

                            @endauth
                        </ul>
                    </div>
                </div>
                <div class="col-xl-6 col-lg-4">
                    <div class="text-center">
                        <div id="news-flash" class="d-inline-block">
                            <ul>
                                <li>Livraisons 100% sécurisées</li>
                                <li>Economisez avec les codes promo</li>

                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-4">
                    <div class="header-info header-info-right">
                        <ul>
                            <li>Bésoin d'information? : <strong class="text-brand"> +225 27 333-333</strong></li>

                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div> --}}
    <div class="header-bottom header-bottom-bg-color sticky-bar">
        <div class="container-fluid px-4">
            <div class="header-wrap header-space-between position-relative">
                <!-- Unified Logo -->
                <div class="logo logo-width-1" style="display:flex; align-items:center;">
                    <a href="{{route('client.index')}}"><img src="{{asset(config("constantes.logo"))}}" style="height: 60px; width: auto; max-width: 160px; object-fit: contain;" alt="logo" /></a>
                </div>
                <div class="header-nav d-none d-lg-flex" style="flex: 1; justify-content: flex-start; margin-left: 0;">

                    <div class="main-menu d-none d-lg-block font-heading">
                        <nav>
                            @php
                                // Lot 109 (17/09/2026) : le lien de la page courante est mis en avant ;
                                // « Accueil » l'était en dur sur toutes les pages.
                                $lienActif = fn (string ...$routes) => request()->routeIs(...$routes) ? 'active' : '';
                            @endphp
                            <ul>
                                <li>
                                    <a class="{{ $lienActif('client.index') }}" href="{{route('client.index')}}">Accueil</a>
                                </li>

                                <li><a class="{{ $lienActif('client.location') }}" href="{{route('client.location')}}">Location</a></li>
                                <li><a class="{{ $lienActif('client.demandeLivraison', 'client.recapLivraison', 'client.valideDemandeLivraison') }}" href="{{route('client.demandeLivraison')}}">Livraison</a></li>
                                {{-- Le blog n'était accessible que depuis le menu mobile : sur
                                     ordinateur, aucun lien n'y menait, les articles publiés
                                     restaient donc invisibles pour le grand public. --}}
                                <li><a class="{{ request()->routeIs('client.blog', 'client.detailBlog') ? 'active' : '' }}" href="{{route('client.blog')}}">Blog</a></li>
                                <li><a class="{{ $lienActif('aPropos') }}" href="{{route('aPropos')}}">A propos</a></li>
                                <li><a class="{{ $lienActif('contact') }}" href="{{route('contact')}}">Contact</a></li>

                                @guest
                                    <li class="header-pro">
                                        <a href="#" class="header-pro__toggle" onclick="return false;">Espace pro <i class="fi-rs-angle-small-down"></i></a>
                                        <ul class="header-pro__menu">
                                            <li><a href="{{route('livreur.login')}}">Livreur</a></li>
                                            <li><a href="{{route('apporteur.login')}}">Affilié</a></li>
                                            <li><a href="{{route('sellers.login')}}">Fournisseur</a></li>
                                        </ul>
                                    </li>
                                @endguest

                            </ul>
                        </nav>
                    </div>
                </div>

                <div class="premium-search d-none d-lg-flex">
                    <form action="{{route('client.search')}}" method="get">
                        <input type="text" name="search" placeholder="Recherche..." />
                        <button type="submit"><i class="fi-rs-search"></i></button>
                    </form>
                </div>

                <div class="hotline d-none d-lg-flex">
                    <div class="header-action-2">
                        {{-- Icône « favoris » réservée aux connectés, comme l'icône « compte » :
                             pour un visiteur elle affichait un compteur toujours à zéro et menait
                             à la page de connexion, que le bouton « Se connecter » atteint déjà.
                             Le compteur #like n'est manipulé par ajoutProduit.js que dans la
                             branche « data.auth », donc uniquement quand l'icône est présente. --}}
                        @auth
                            <div class="header-action-icon-2">
                                <a href="{{ route('client.wishList') }}" title="Mes souhaits">
                                    <img class="svgInject" alt="Mes souhaits" src="{{ asset('frontend/assets/imgs/theme/icons/icon-heart.svg') }}" />
                                    <span class="pro-count {{ $like > 0 ? 'blue' : '' }}" id="like">{{ $like }}</span>
                                </a>
                                <a href="{{ route('client.wishList') }}">
                                    <span class="lable">Mes souhaits</span>
                                </a>
                            </div>
                        @endauth
                        <div class="header-action-icon-2">
                            <a class="mini-cart-icon" href="{{route('client.monPanier')}}">
                                <img alt="Nest" src="{{ asset('frontend/assets/imgs/theme/icons/icon-cart.svg') }}" />
                                <span class="pro-count {{$cart != 0 ? 'blue' : ''}}" id="panier"> {{$cart}} </span>
                            </a>
                            <a href="{{route('client.monPanier')}}"><span class="lable">Panier</span></a>

                        </div>
                        <div class="header-action-icon-2">
                            {{-- Icône « compte » réservée aux connectés : pour un visiteur elle
                                 pointait sur /mon-compte, page protégée qui le renvoyait aussitôt
                                 sur la connexion — soit exactement ce que fait le bouton
                                 « Se connecter » situé juste à côté. --}}
                            @auth
                                @php $photoEntete = \Help::photoDeProfil(Auth::user()); @endphp
                                <a href="{{route('client.monCompte')}}" title="Mon compte">
                                    @if($photoEntete)
                                        {{-- Lot 106 (17/09/2026) : la photo de profil remplace l'icône générique. --}}
                                        <img class="photo-profil-entete" alt="Mon compte" src="{{ $photoEntete }}"
                                             style="width:32px !important; height:32px !important; min-width:0 !important; min-height:0 !important; max-width:none !important; border-radius:50%; object-fit:cover; border:2px solid #ffffff; box-shadow:0 0 0 2px rgba(255,255,255,0.28), 0 2px 6px rgba(0,0,0,0.35); background:#ffffff; vertical-align:middle; filter:none !important;" />
                                        {{-- filter:none : premium-client.css blanchit toutes les images de l'en-tête (brightness(0) invert(1)) ; max-width:none : main.css les borne à 25 px. --}}
                                    @else
                                        <img class="svgInject" alt="Mon compte" src="{{ asset('frontend/assets/imgs/theme/icons/icon-user.svg') }}" />
                                    @endif
                                </a>
                            @endauth
                            @guest
                                <span class="header-auth-actions">
                                    <a href="{{ route('client.login') }}" class="header-auth-btn header-auth-btn--ghost">Se connecter</a>
                                    <a href="{{ route('client.register') }}" class="header-auth-btn header-auth-btn--primary">S'inscrire</a>
                                </span>
                            @endguest
                            @auth
                                <a href="{{route('client.monCompte')}}"> <span class="lable">Mon compte</span> </a>
                                {{-- Lot 107 (17/09/2026) : menu au survol de l'icône « Mon compte »
                                     (même mécanique que le panier : .header-action-icon-2:hover .cart-dropdown-wrap). --}}
                                <div class="cart-dropdown-wrap cart-dropdown-hm2 account-dropdown menu-compte-entete">
                                    <ul>
                                        <li><a href="{{ route('client.monCompte') }}"><i class="fi-rs-settings-sliders"></i>Mon compte</a></li>
                                        <li><a href="{{ route('client.monCompte') }}?onglet=account-detail"><i class="fi-rs-user"></i>Profil</a></li>
                                        <li><a href="{{ route('client.demandeClientATermePage') }}"><i class="fi-rs-briefcase"></i>Devenir un client à terme</a></li>
                                        <li>
                                            <form action="{{ route('show.logout') }}" method="post">
                                                @csrf
                                                @method('delete')
                                                <button type="submit"><i class="fi-rs-sign-out"></i>Déconnexion</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            @endauth

                        </div>
                    </div>

                </div>
                <div class="header-action-icon-2 d-block d-lg-none">
                    <div class="burger-icon burger-icon-white">
                        <span class="burger-icon-top"></span>
                        <span class="burger-icon-mid"></span>
                        <span class="burger-icon-bottom"></span>
                    </div>
                </div>
                <div class="header-action-right d-block d-lg-none">
                    <div class="header-action-2">
                        @auth
                            <div class="header-action-icon-2">
                                <a href="{{route('client.wishList')}}">
                                    <img alt="Nest" src="{{asset('frontend/assets/imgs/theme/icons/icon-heart.svg')}}" />
                                    <span class="pro-count white"> {{ $client ? $client->produits->count() : 0 }} </span>
                                </a>
                            </div>
                        @endauth
                        <div class="header-action-icon-2">
                            <a class="mini-cart-icon" href="{{route('client.monPanier')}}">
                                <img alt="Nest" src="{{asset('frontend/assets/imgs/theme/icons/icon-cart.svg')}}" />
                                <span class="pro-count white" id="panier"> {{Cart::content()->count()}} </span>
                            </a>
                            <div class="cart-dropdown-wrap cart-dropdown-hm2">
                                <ul>
                                    @if(Cart::count()>0)
                                    @foreach (Cart::content() as $produit )
                                        <li>
                                            <div class="shopping-cart-img">
                                                <a href="{{ route('client.produit.info', $produit->id) }}"><img alt="Nest" src="/storage/{{$produit->options->image}}" /></a>
                                            </div>
                                            <div class="shopping-cart-title">
                                                <h4><a href="{{ route('client.produit.info', $produit->id) }}">{{$produit->name}}</a></h4>
                                                <h3><span>{{$produit->qty}} × </span> {{$produit->price}}fcfa </h3>
                                            </div>
                                            <div class="shopping-cart-delete">
                                                <a href="#"><i class="fi-rs-cross-small"></i></a>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="shopping-cart-footer">
                                    <div class="shopping-cart-total">
                                        <h4>Total <span>{{number_format(Cart::total(),0,'','.')}} fcfa</span></h4>
                                    </div>
                                    <div class="shopping-cart-button">
                                        <a href="{{route('client.monPanier')}}">Mon panier</a>
                                        <a href="{{route('client.panierCommande')}}">Commander</a>
                                    </div>
                                @else
                                    <h4>Ajoutez des articles à votre panier</h4>
                                @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
