<div class="mobile-header-active mobile-header-wrapper-style">
    <div class="mobile-header-wrapper-inner">
        <div class="mobile-header-top">
            <div class="mobile-header-logo">
                <a href="{{route('client.index')}}"><img src="{{asset('frontend/assets/imgs/logo/omer 1.png')}}" alt="logo" /></a>
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

                        {{-- home --}}
                        <li class="menu-item"> <a href="#deal">Deals</a></li>
                        <li class="menu-item">
                            <a href="{{route('client.index')}}">Accueil</a>
                        </li>
                        <li>
                            <a href="{{route('client.location')}}">Location</a>
                        </li>
                        <li>
                            <a href="{{route('client.demandeLivraison')}}">Demande de livraison</a>
                        </li>
                        <li>
                            <a href="{{route('client.blog')}}">Blog</a>
                        </li>
                        <li class="menu-item">
                           <a href="{{route('aPropos')}}">A propos</a>
                        </li>
                        {{-- Blog --}}
                         <li class="menu-item">
                           <a href="{{route('contact')}}">Contact</a>
                        </li>

                    </ul>
                </nav>
                <!-- mobile menu end -->
            </div>
            <div class="mobile-header-info-wrap">
                @auth
                    <div class="single-mobile-header-info">
                        <a href="{{route('client.monCompte')}}"><i class="fi-rs-user"></i>Mon compte</a>
                        <a href="{{route('client.listeDevis')}}"><i class="fi-rs-user"></i>Mes dévis</a>
                    </div>
                @endauth
                <div class="single-mobile-header-info">
                    <a href="{{route('enConstruction')}}"><i class="fi-rs-marker"></i> Notre localisation </a>
                </div>
                @guest
                    <div class="single-mobile-header-info">
                        <a href="{{route('client.login')}}"><i class="fi-rs-user"></i>Se connecter </a>
                        <a href="{{route('client.register')}}"><i class="fi-rs-user"></i>S'inscrire </a>
                    </div>
                    {{-- <a href="{{route('client.listeDevis')}}"><i class="fi fi-rs-settings-sliders mr-10"></i>Faire un devis</a> --}}
                @endguest
                <div class="single-mobile-header-info">
                    <a href="#"><i class="fi-rs-headphones"></i>(+225) 07 333 - 333 </a>
                </div>
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
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-instagram-white.svg')}}" alt="" /></a>
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-pinterest-white.svg')}}" alt="" /></a>
                <a href="#"><img src="{{asset('frontend/assets/imgs/theme/icons/icon-youtube-white.svg')}}" alt="" /></a>
            </div>
            <div class="site-copyright"> &copy; Immobilier - Location - Distribution. |
                <script>
                    document.write(new Date().getFullYear());
                </script></div>
        </div>
    </div>
</div>
