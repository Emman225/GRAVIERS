<div class="mobile-header-active mobile-header-wrapper-style">
    <div class="mobile-header-wrapper-inner">
        <div class="mobile-header-top">
            <div class="mobile-header-logo">
                <a href="{{route('client.index')}}"><img src="{{asset(config('constantes.logo'))}}" alt="logo" /></a>
            </div>
            <div class="mobile-menu-close close-style-wrap close-style-position-inherit">
                <button class="close-style search-close">
                    <i class="icon-top"></i>
                    <i class="icon-bottom"></i>
                </button>
            </div>
        </div>
        <div class="mobile-header-content-area">
            <div class="mobile-search search-style-3 mobile-header-border">
                <form action="{{route('client.search')}}">
                    <input type="text" name="search" placeholder="Recherchez un article" />
                    <button type="submit"><i class="fi-rs-search"></i></button>
                </form>
            </div>
            <div class="mobile-menu-wrap mobile-header-border">
                <!-- mobile menu start -->
                <nav>
                    <ul class="mobile-menu font-heading">
                        {{-- 13/09/2026 : exactement les entrées du menu de l'ordinateur --}}
                        @php $actifMobile = fn (string ...$routes) => request()->routeIs(...$routes) ? 'active' : ''; @endphp
                        <li class="menu-item {{ $actifMobile('client.index') }}"><a href="{{route('client.index')}}">Accueil</a></li>
                        <li class="menu-item {{ $actifMobile('client.location') }}"><a href="{{route('client.location')}}">Location</a></li>
                        <li class="menu-item {{ $actifMobile('client.demandeLivraison', 'client.recapLivraison') }}"><a href="{{route('client.demandeLivraison')}}">Livraison</a></li>
                        <li class="menu-item {{ $actifMobile('client.blog', 'client.detailBlog') }}"><a href="{{route('client.blog')}}">Blog</a></li>
                        <li class="menu-item {{ $actifMobile('aPropos') }}"><a href="{{route('aPropos')}}">A propos</a></li>
                        <li class="menu-item {{ $actifMobile('contact') }}"><a href="{{route('contact')}}">Contact</a></li>
                        <li class="menu-item mobile-menu-espace-pro">
                            <span class="mobile-menu-titre">Espace pro</span>
                            <ul class="mobile-menu-sous">
                                <li><a href="{{route('livreur.login')}}">Livreur</a></li>
                                <li><a href="{{route('apporteur.login')}}">Affilié</a></li>
                                <li><a href="{{route('sellers.login')}}">Fournisseur</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
                <!-- mobile menu end -->
            </div>
            <div class="mobile-header-info-wrap">
                @auth
                    <div class="single-mobile-header-info">
                        @php $photoNav = \Help::photoDeProfil(Auth::user()); @endphp
                        <a href="{{route('client.monCompte')}}">@if($photoNav)<img class="photo-profil-entete" src="{{ $photoNav }}" alt="" style="width:24px; height:24px; border-radius:50%; object-fit:cover; border:2px solid #ffffff; box-shadow:0 0 0 1px rgba(10,37,64,0.25); background:#ffffff; margin-right:8px; vertical-align:middle;">@else<i class="fi-rs-user"></i>@endif Mon compte</a>
                        <a href="{{route('client.listeDevis')}}"><i class="fi-rs-user"></i>Mes dévis</a>
                    </div>
                @endauth
                @guest
                    <div class="single-mobile-header-info">
                        <a href="{{route('client.login')}}"><i class="fi-rs-user"></i>Se connecter </a>
                        <a href="{{route('client.register')}}"><i class="fi-rs-user"></i>S'inscrire </a>
                    </div>
                    {{-- <a href="{{route('client.listeDevis')}}"><i class="fi fi-rs-settings-sliders mr-10"></i>Faire un devis</a> --}}
                @endguest
                @auth
                <div class="single-mobile-header-info">

                    <a href="{{route('enConstruction')}}"><i class="fi-rs-user"></i>{{Auth::user()->email}}</a>
                    <form action="{{route('show.logout')}}" method="post">
                        @csrf
                        @method('delete')
                        <button class="btn mt-5 d-flex align-items-center" style="height: 30px; background: indi">Déconnexion</button>
                    </form>
                </div>
                @endauth

            </div>
            <div class="mobile-social-icon mb-50">
                <h6 class="mb-15">Suivez-nous</h6>
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-facebook-white.svg')}}" alt="" /></a>
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-twitter-white.svg')}}" alt="" /></a>
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-youtube-white.svg')}}" alt="" /></a>
            </div>
            <div class="site-copyright"> &copy; DALAKOUN SARL. |
                <script>
                    document.write(new Date().getFullYear());
                </script></div>
        </div>
    </div>
</div>
