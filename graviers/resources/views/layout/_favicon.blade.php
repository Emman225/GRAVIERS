{{-- FAVICON DE LA PLATEFORME (10/09/2026), un seul partiel pour toutes les pages.

     Avant : chaque gabarit déclarait la sienne — le logo entier de 2 321 px
     (277 Ko) annoncé comme « image/x-icon » dans les deux têtes principales,
     un chemin relatif inexistant (assets/imgs/theme/favicon.svg) dans six
     autres, rien du tout ailleurs — et le /favicon.ico de secours, celui que
     le navigateur prend quand le lien manque ou échoue, était le fichier VIDE
     livré avec Laravel. Selon la page, l'onglet restait donc sans icône.

     Ici : des icônes aux tailles attendues, générées depuis le logo
     (config constantes.logo), et le .ico de secours rempli. --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=4">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=4">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=4">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=4">
