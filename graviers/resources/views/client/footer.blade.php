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
                            <h3 class="icon-box-title">Meilleur prix et offre</h3>
                            <p>A partir de 10.000fcfa</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-2.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Livreur rapide</h3>
                            <p>Un bon service de livraison</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-3.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Des promo par jour</h3>
                            <p>Quand vous êtes inscrit</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-4.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Wide assortment</h3>
                            <p>Mega Discounts</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-5.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Un retour rapide</h3>
                            <p>En moins de 30 jours</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-1-5 col-md-4 col-12 col-sm-6 d-xl-none">
                    <div class="banner-left-icon d-flex align-items-center wow fadeIn animated">
                        <div class="banner-icon">
                            <img src="{{asset('frontend/assets/imgs/theme/icons/icon-6.svg')}}" alt="" />
                        </div>
                        <div class="banner-text">
                            <h3 class="icon-box-title">Livraison garanti </h3>
                            <p>En moins de 30 jours</p>
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
                                De la qualité
                            </h2>
                            <p class="mb-45">Commencer vos achat avec <span class="text-brand"> IMLOD </span></p>
                            <form class="form-subcriber d-flex">
                                <input type="email" placeholder="Your emaill address" />
                                <button class="btn" type="submit">S'inscrire</button>
                            </form>
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
                            <a href="{{route('client.index')}}" class="mb-15"><img src="{{config('constantes.logo')}}" alt="logo" style="max-height:80px;width:auto;max-width:100%;height:auto;" /></a>
                            <p class="font-lg text-heading">Meilleur endroit pour vos matériels de construction</p>
                        </div>
                        <ul class="contact-infor">
                            <li><img src="assets/imgs/theme/icons/icon-location.svg" alt="" /><strong>Adresse: </strong> <span>Abidjan - Yopougon Rue 12 Avenu Jean Marshall</span></li>
                            <li><img src="assets/imgs/theme/icons/icon-contact.svg" alt="" /><strong>Contact:</strong><span>(+225) - 07 27 3333 - 3333</span></li>
                            <li><img src="assets/imgs/theme/icons/icon-email-2.svg" alt="" /><strong>Email:</strong><span>IMLOD.bat@gmail.com</span></li>
                            <li><img src="assets/imgs/theme/icons/icon-clock.svg" alt="" /><strong>Heure d'ouverture:</strong><span>08:00 - 18:00, du lundi au Samedi</span></li>
                        </ul>
                    </div>
                </div>
                <div class="footer-link-widget col">
                    <h4 class="widget-title">IMLOD</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        <li><a href="{{route('aPropos')}}">A propos de nous</a></li>
                        <li><a href="{{route('enConstruction')}}">Informations sur les livraisons</a></li>
                        <li><a href="{{route('enConstruction')}}">Politique et confidentialité</a></li>
                        <li><a href="{{route('enConstruction')}}">Termes &amp; Conditions</a></li>
                        <li><a href="{{route('contact')}}">Nous contacter</a></li>
                        <li><a href="{{route('enConstruction')}}">Centre d'aide</a></li>
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
                    <h4 class="widget-title">Coorperation</h4>
                    <ul class="footer-list mb-sm-5 mb-md-0">
                        <li><a href="{{route('enConstruction')}}">Devenir Livreur</a></li>
                        <li><a href="{{route('enConstruction')}}">Devenir Fournisseur</a></li>
                        <li><a href="#deal">Voir les promotions</a></li>
                        {{-- <li><a href="#">Farm Careers</a></li>
                        <li><a href="#">Our Suppliers</a></li>
                        <li><a href="#">Accessibility</a></li>
                        <li><a href="#">Promotions</a></li> --}}
                    </ul>
                </div>
                <div class="footer-link-widget col">
                    <h4 class="widget-title">Populaire</h4>
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
                <div class="footer-link-widget widget-install-app col">
                    <h4 class="widget-title">Installez l'application mobile</h4>
                    <p class="wow fadeIn animated">Sur l'App store ou Play store</p>
                    <div class="download-app">
                        <a href="{{route('enConstruction')}}" class="hover-up mb-sm-2 mb-lg-0"><img class="active" src="{{asset('frontend/assets/imgs/theme/app-store.jpg')}}" alt="" /></a>
                        <a href="{{route('enConstruction')}}" class="hover-up mb-sm-2"><img src="{{asset('frontend/assets/imgs/theme/google-play.jpg')}}" alt="" /></a>
                    </div>
                    {{-- <p class="mb-20">Secured Payment Gateways</p>
                    <img class="wow fadeIn animated" src="{{asset('frontend/assets/imgs/theme/payment-method.png')}}" alt="" /> --}}
                </div>
            </div>
        </div>
    </section>

    {{-- Info entreprise --}}
    <div class="container pb-30">
        <div class="row align-items-center">
            <div class="col-12 mb-30">
                <div class="footer-bottom"></div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6">
                <p class="font-sm mb-0">&copy; 2024, <strong class="text-brand">gravierci</strong> - Immobilier - Location - Distribution <br />Tous droits reservés</p>
            </div>
            <div class="col-xl-4 col-lg-6 text-center d-none d-xl-block">
                <div class="hotline d-lg-inline-flex mr-30">
                    <img src="{{asset('frontend/assets/imgs/theme/icons/phone-call.svg')}}" alt="hotline" />
                    <p class="h5">+225 07 333 - 333<span>Ouvert de 8:00 à 22:00</span></p>
                    {{-- <p><span>24/7 Centre d'aide</span></p> --}}
                </div>
                <div class="hotline d-lg-inline-flex">
                    {{-- <img src="{{asset('frontend/assets/imgs/theme/icons/phone-call.svg')}}" alt="hotline" /> --}}
                    <span></span>
                </div>
            </div>
            <div class="col-xl-4 col-lg-6 col-md-6 text-end d-none d-md-block">
                <div class="mobile-social-icon">
                    <h6>Suivez-nous</h6>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-facebook-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-twitter-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-instagram-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-pinterest-white.svg') }}" alt="" /></a>
                    <a href="#"><img src="{{ asset('frontend/assets/imgs/theme/icons/icon-youtube-white.svg') }}" alt="" /></a>
                </div>
                <p class="font-sm"></p>
            </div>
        </div>
    </div>

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
<script src="{{ asset('frontend/assets/js/main.js?v=6.0') }}"></script>
<script src="{{ asset('frontend/assets/js/shop.js?v=6.0') }}"></script>
<!-- Leaflet + Geocoder (hébergés en local pour éviter la dépendance au CDN unpkg) -->
<link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}" />
<link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />
<script src="{{ asset('frontend/assets/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('frontend/assets/leaflet/Control.Geocoder.js') }}"></script>
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
</body>

</html>
