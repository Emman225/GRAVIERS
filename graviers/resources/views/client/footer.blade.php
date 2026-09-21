<footer class="main">

    {{-- services part --}}
    <section class="featured section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6 mb-md-4 mb-xl-0">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-1.svg')}}" alt=""/>
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Meilleurs prix</h3>
                            <p>Tarifs dégressifs selon le volume</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-2.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Livraison sur chantier</h3>
                            <p>Suivi de votre commande en ligne</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-3.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Devis gratuit</h3>
                            <p>Réponse rapide, sans engagement</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-4.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Large choix</h3>
                            <p>Gravier, sable, ciment, fer, brique…</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-5.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Paiement flexible</h3>
                            <p>Mobile Money, virement ou espèces</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6 d-xl-none">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-6.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Retrait sur place</h3>
                            <p>À l'agence, aux heures d'ouverture</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Image & email form --}}
    <section class="newsletter mb-15">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="position-relative newsletter-inner">
                        <div class="newsletter-content">
                            <h2 class="mb-20">
                                Nous ne vendons que <br />
                                de la qualité
                            </h2>
                            {{-- Lot 108 : « DALAKOUN » était bleu sur bleu (text-brand), donc invisible. --}}
                            <p class="mb-30">Commencez vos achats avec <span style="color:#FFB300; font-weight:700;">Mon Gravier</span></p>
                            {{-- Lot 109 ter (17/09/2026) : la section dit ce qu'elle est. --}}
                            <p class="newsletter-intitule" style="color:#fff !important; font-weight:700; margin:0 0 6px; font-size:15px;">
                                <i class="fi-rs-envelope" style="margin-right:6px;"></i>Newsletter
                            </p>
                            <p class="newsletter-explication" style="color:#dce9f7 !important; margin:0 0 14px; font-size:14px;">
                                Laissez votre adresse e-mail pour recevoir nos offres, promotions et conseils chantier.
                            </p>
                            {{-- Ce formulaire n'était relié à rien : ni action, ni méthode, ni
                                 nom de champ. Le visiteur croyait s'inscrire et rechargeait
                                 simplement la page ; aucune adresse n'était conservée. --}}
                            <form class="form-subcriber d-flex" action="{{ route('newsletter.store') }}" method="POST">
                                @csrf
                                <input type="email" name="email" placeholder="Votre adresse e-mail" required maxlength="150"
                                       value="{{ old('email') }}" />
                                <button class="btn" type="submit">S'inscrire</button>
                            </form>
                            @error('email')
                                <p class="mt-2 mb-0" style="color:#ffd7d7;">{{ $message }}</p>
                            @enderror
                        </div>
                        <img src="{{asset('frontend/assets/imgs/theme/produit/banner2.png')}}" alt="newsletter" />
                    </div>
                </div>
            </div>
        </div>
    </section>



    {{-- Last part --}}
    <section class="section-padding footer-mid">
        <div class="container pt-15 pb-20">
            <div class="row">
                <div class="col">
                    <div class="widget-about font-md mb-md-3 mb-lg-3 mb-xl-0">
                        <div class="logo mb-30">
                            <a href="{{route('client.index')}}" class="mb-15"><img src="{{ asset(config('constantes.logo')) }}" alt="logo" style="max-height:80px;width:auto;max-width:100%;height:auto;" /></a>
                            <p class="font-lg text-heading">Meilleur endroit pour vos matériels de construction</p>
                        </div>
                        <ul class="contact-infor">
                            {{-- Chemins corrigés : « assets/… » manquait le préfixe « frontend/ »,
                                 et ces quatre icônes tombaient en 404 sur toutes les pages. --}}
                            <li><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-location.svg') }}" alt="" /><strong>Adresse: </strong> <span>Abidjan - Yopougon, rue 12, avenue Jean Marshall</span></li>
                            <li><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-contact.svg') }}" alt="" /><strong>Contact:</strong><span><a href="tel:+2250700130798" style="color:inherit;">(+225) - 07 00 13 07 98</a></span></li>
                            <li><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-email-2.svg') }}" alt="" /><strong>Email:</strong><span><a href="mailto:{{ \Help::emailContact() }}">{{ \Help::emailContact() }}</a></span></li>
                            <li><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-clock.svg') }}" alt="" /><strong>Heure d'ouverture:</strong><span>08:00 - 18:00, du lundi au samedi</span></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-link-widget col">
                    <h4 class="widget-title">DALAKOUN</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        {{-- Ces entrées renvoyaient toutes vers « Site en construction ».
                             Elles mènent désormais à de vraies pages. --}}
                        <li><a href="{{route('aPropos')}}">A propos de nous</a></li>
                        <li><a href="{{route('infosLivraisons')}}">Informations sur les livraisons</a></li>
                        <li><a href="{{route('confidentialite')}}">Politique et confidentialité</a></li>
                        <li><a href="{{route('termesConditions')}}">Termes &amp; Conditions</a></li>
                        <li><a href="{{route('contact')}}">Nous contacter</a></li>
                        {{-- centreAidePublic, et non show.centreAide : ce dernier est le
                             centre d'aide du personnel et exige d'être connecté. --}}
                        <li><a href="{{route('centreAidePublic')}}">Centre d'aide</a></li>
                        {{-- <li><a href="#">Careers</a></li> --}}
                    </ul>
                </div>
                {{-- <div class="footer-link-widget col">
                    <h4 class="widget-title">Compte</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        <li><a href="{{route('client.login')}}">Se connecter</a></li>
                        <li><a href="{{route('client.register')}}">Créer un compte</a></li>
                        <li><a href="{{route('client.monPanier')}}">Mon panier</a></li>
                    </ul>
                </div> --}}
                <div class="footer-link-widget col">
                    <h4 class="widget-title">Partenaires</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        {{-- Pages publiques de candidature, à ne pas confondre avec
                             show.registerLivreur / show.registerSeller, qui sont les
                             formulaires du back-office réservés aux administrateurs. --}}
                        <li><a href="{{route('devenirLivreur')}}">Devenir Livreur</a></li>
                        <li><a href="{{route('devenirFournisseur')}}">Devenir Fournisseur</a></li>
                        <li><a href="#deal">Voir les promotions</a></li>
                        {{-- <li><a href="#">Farm Careers</a></li>
                        <li><a href="#">Our Suppliers</a></li>
                        <li><a href="#">Accessibility</a></li>
                        <li><a href="#">Promotions</a></li> --}}
                    </ul>
                    {{-- Lot 108 bis (17/09/2026) : l'application mobile sous les éléments « Partenaires ». --}}
                    <div class="widget-install-app mt-30">
                        <h4 class="widget-title">Installez l'application mobile</h4>
                        <p class="wow fadeIn animated">Sur l'App store ou Play store</p>
                        <div class="download-app">
                            {{-- Lot 110 : vers la section « applications mobiles » de l'accueil (téléchargement direct). --}}
                            <a href="{{ route('client.index') }}#applications" class="hover-up mb-sm-2 mb-lg-0"><img class="active" src="{{asset('frontend/assets/imgs/theme/app-store.jpg')}}" alt="Applications mobiles" /></a>
                            <a href="{{ route('client.index') }}#applications" class="hover-up mb-sm-2"><img src="{{asset('frontend/assets/imgs/theme/google-play.jpg')}}" alt="Applications mobiles" /></a>
                        </div>
                    </div>
                </div>
                <div class="footer-link-widget col">
                    <h4 class="widget-title">Nos produits</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        @foreach ($categories as $categorie )
                            <li><a href="{{route('product.categorie',$categorie->nom)}}"> {{$categorie->nom}} </a></li>
                        @endforeach
                        {{-- <li><a href="#">Gravier</a></li>
                        <li><a href="#">Sable</a></li>
                        <li><a href="#">Coulis et ancre</a></li>
                        <li><a href="#">Electrique</a></li>
                        <li><a href="#">Etanche</a></li>
                        <li><a href="#">Peinture</a></li> --}}
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Info entreprise --}}
    <div class="container barre-du-bas">
        <div class="row align-items-center">
            <div class="col-12">
                <div class="footer-bottom"></div>
            </div>
            <div class="col-xl-6 col-lg-6 col-md-6">
                {{-- Année courante : le pied de page annonçait 2024 en dur, ce qui
                     vieillit le site chaque 1er janvier sans que personne n'y pense. --}}
                <p class="font-sm mb-0" style="white-space:nowrap;">&copy; {{ date('Y') }}, <strong class="text-brand">Mon Gravier</strong> - <a href="https://www.dalakoun.com" target="_blank" rel="noopener" class="ck-lien-pied" title="Site de DALAKOUN SARLU">DALAKOUN SARLU</a>. Tous droits réservés <span class="ck-sep">·</span> <a href="#" data-ck-ouvrir class="ck-lien-pied">Gérer mes cookies</a></p>
            </div>
            {{-- Lot 108 bis (17/09/2026) : la « hotline » du gabarit (numéro fictif) est retirée. --}}
            <div class="col-xl-6 col-lg-6 col-md-6 text-end d-none d-md-block">
                <div class="mobile-social-icon">
                    <h6>Suivez-nous</h6>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-facebook-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-twitter-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-youtube-white.svg') }}" alt="" /></a>
                </div>
                <p class="font-sm"></p>
            </div>
        </div>
    </div>

    {{-- Lot 108 quater (17/09/2026) : bouton WhatsApp flottant, au-dessus du bouton « remonter »
         (#scrollUp, posé par le thème en bas à droite). Le rappel des cookies (.ck-rappel) monte
         d'un cran, même axe vertical. --}}
    <a class="whatsapp-flottant" href="https://wa.me/2250700130798" target="_blank" rel="noopener" title="Nous écrire sur WhatsApp" aria-label="Nous écrire sur WhatsApp">
        <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8.6 8.9c.2-.5.5-.5.8-.5h.6c.2 0 .4.1.5.4l.8 1.9c.1.2 0 .4-.1.6l-.5.6c-.1.1-.1.3 0 .4a6 6 0 0 0 2.9 2.7c.2.1.3 0 .4-.1l.6-.7c.2-.2.4-.2.6-.1l1.9.9c.2.1.3.3.3.5 0 .5-.2 1.1-.6 1.4-.5.5-1.1.7-1.8.6a8.3 8.3 0 0 1-6.6-6.3c-.1-.8.1-1.6.6-2.3z" fill="currentColor"/></svg>
    </a>
</footer>
<!-- Preloader Start -->
{{-- <div id="preloader-active">
    <div class="preloader d-flex align-items-center justify-content-center">
        <div class="preloader-inner position-relative">
            <div class="text-center">
                <img src="{{ asset('frontend/assets/imgs/theme/2.gif') }}" alt="" />
            </div>
        </div>
    </div>
</div> --}}
<!-- Vendor JS-->
<!-- Dans resources/views/layouts/app.blade.php -->
@if (Session::has('success'))
<script>
    toastr.success("{{ Session::get('success')}}");

</script>
@endif

@if (Session::has('email'))
<script>
    //toastr.success("{{ Session::get('error')}}");
    toastr.warning("{{ Session::get('email')}}");
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{asset('frontend/assets/js/jquery-3.7.1.min.js')}}"></script>
<script src="{{asset('frontend/assets/js/ajoutProduit.js?v=3.8')}}"></script>
<script src="{{ asset('frontend/assets/js/vendor/modernizr-3.6.0.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/vendor/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/vendor/jquery-migrate-3.3.0.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/vendor/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/slick.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/jquery.syotimer.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/wow.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/slider-range.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/magnific-popup.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/select2.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/waypoints.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/counterup.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/jquery.countdown.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/images-loaded.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/isotope.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/scrollup.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/jquery.vticker-min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/jquery.theia.sticky.js') }}"></script>
<script src="{{ asset('frontend/assets/js/plugins/jquery.elevatezoom.js') }}"></script>
<!-- Template  JS -->
<script src="{{ asset('frontend/assets/js/main.js?v=6.3') }}"></script>
<script src="{{ asset('frontend/assets/js/responsive-dalakoun.js?v=1.0') }}"></script>
<script src="{{ asset('frontend/assets/js/shop.js?v=6.0') }}"></script>
<!-- Leaflet + Geocoder (hébergés en local pour éviter la dépendance au CDN unpkg) -->
<link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}" />
<link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />
<script src="{{ asset('frontend/assets/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('frontend/assets/leaflet/Control.Geocoder.js') }}"></script>
<script src="{{ asset('frontend/assets/leaflet/recherche-lieu.js') }}"></script>
<script>
    let notification = document.getElementById('notify');

    setTimeout(() => {
        if (notification) {
            notification.classList.add("off")
        }
    },3000)
</script>
@yield('jspart')

    <script>
        function togglePassword() {
            const input = document.getElementById("password");
            if (input.type === "password") {
                input.type = "text";
                document.getElementById("oeil").innerHTML = '<i class="fa-solid fa-eye"></i>';
            } else {
                input.type = "password";
                document.getElementById("oeil").innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
            }
        }
    </script>

    @if(session('success') || session('succes'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-center",
                timeOut: 8000,
                extendedTimeOut: 3000,
                showMethod: "fadeIn",
                hideMethod: "fadeOut"
            };
            toastr.success("{{ session('success') ?? session('succes') }}", "Compte cree avec succes !");
        });
    </script>
    @endif

    {{-- Messages d'ERREUR. Le site n'en affichait AUCUN : la seule ligne toastr
         prévue pour eux était commentée plus haut dans ce fichier, et aucune page
         du parcours d'achat ne lit session('error'). Un refus renvoyé par un
         contrôleur — plafond de crédit dépassé, par exemple — restait donc
         totalement invisible : le client revenait sur la page précédente sans
         explication.
         @json plutôt que des guillemets : le message contient des apostrophes et
         des espaces insécables que Blade échapperait en entités HTML, affichées
         telles quelles par toastr. --}}
    @if(session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: "toast-top-center",
                timeOut: 12000,
                extendedTimeOut: 5000,
                showMethod: "fadeIn",
                hideMethod: "fadeOut"
            };
            toastr.error(@json(session('error')));
        });
    </script>
    @endif
</body>

</html>
