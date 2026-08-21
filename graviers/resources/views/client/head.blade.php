<!DOCTYPE html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8" />
    <title>@yield('title')</title>
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <meta name="description" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:title" content="" />
    <meta property="og:type" content="" />
    <meta property="og:url" content="" />
    <meta property="og:image" content="" />

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Favicon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset(config("constantes.logo")) }}" />
    <!-- Material Icons (Round) -->
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/material-icon-round.css') }}" />

    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/plugins/slider-range.css') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/main.css?v=6.0') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/myStyle.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-client.css') }}">
    {{-- v1.1 : texte et icônes du menu latéral passés en blanc. Le numéro de
         version doit être incrémenté à chaque modification du fichier, sinon les
         navigateurs continuent de servir la feuille mise en cache. --}}
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-client-account.css?v=1.1') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-auth.css?v=4.0') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-product-detail.css?v=1.3') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/premium-responsive.css?v=1.0') }}">
    <script defer src="{{ asset('frontend/assets/js/table-dropdown-fix.js?v=6.0') }}"></script>
    <script defer src="{{ asset('frontend/assets/js/delete-confirm.js?v=2.0') }}"></script>

    {{-- carte (Leaflet hébergé en local pour éviter la dépendance au CDN unpkg) --}}
    <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('frontend/assets/leaflet/Control.Geocoder.css') }}" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js" integrity="sha512-VEd+nq25CkR676O+pLBnDW09R7VQX9Mdiij052gVCp5yVH3jGtH70Ho/UUv4mJDsEdTvqRCFZg0NKGiojGnUCw==" crossorigin="anonymous" referrerpolicy="no-referrer" ></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    {{-- SweetAlert2 (popups premium) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Feuilles de style propres à une page. Le gabarit ne proposait qu'un point
         d'insertion pour le JS (@yield('jspart') dans le pied de page) : une page
         ayant besoin de sa propre CSS n'avait aucun moyen de la déclarer, et
         devait tout écrire en <style> dans son corps. --}}
    @yield('cssPart')
</head>

<body class="client-layout">

{{-- Réserve sous l'en-tête fixe, mesurée au lieu d'être devinée.

     L'en-tête public est en « position: fixed » (myStyle.css) : il ne pousse
     pas le contenu, c'est à la page de lui réserver sa hauteur. Cette réserve
     valait 80px en dur — une seule valeur pour deux hauteurs réelles :

       · au-delà de 992px : 54px (le bandeau promotionnel est masqué)
             → 26px de vide au-dessus du contenu ;
       · à 992px et moins : 97px (le bandeau s'ajoute, 43px)
             → 17px de contenu passant SOUS l'en-tête, sur toutes les pages.

     On additionne donc la hauteur réelle des blocs de l'en-tête. La somme est
     préférée à la hauteur du conteneur parce que le thème passe la barre du bas
     en « stick » au défilement : elle sort alors du flux et la hauteur du
     conteneur s'effondre à celle du seul bandeau. --}}
<script>
    (function () {
        function ajusterReserveEntete() {
            var entete = document.querySelector('body.client-layout .header-area');
            if (!entete) { return; }

            var hauteur = 0;
            Array.prototype.forEach.call(entete.children, function (bloc) {
                if (window.getComputedStyle(bloc).display === 'none') { return; }
                hauteur += bloc.getBoundingClientRect().height;
            });
            hauteur = Math.round(hauteur);

            // Garde-fou : un relevé nul ou aberrant (en-tête pas encore mis en
            // page) laisse la valeur de repli de la feuille de style.
            if (hauteur < 40) { return; }

            document.body.style.setProperty('padding-top', hauteur + 'px', 'important');
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ajusterReserveEntete);
        } else {
            ajusterReserveEntete();
        }
        window.addEventListener('load', ajusterReserveEntete);
        window.addEventListener('resize', ajusterReserveEntete);
    })();
</script>


