@include('client.head')

{{-- Le dessin des cartes produit, defini UNE fois pour tout le site public.
     Il vivait dans un bloc <style> de l'accueil : la meme carte n'avait donc
     pas la meme tete selon la page. --}}
@include('client._styleCartesProduits')

{{-- La fenêtre « vue rapide » génère UNE boîte de dialogue par produit du
     catalogue. Elle était incluse dans TOUTES les pages du site (47 vues
     étendent ce gabarit) alors que seules celles qui affichent des vignettes
     de produit peuvent l'ouvrir : sur « Mon compte », par exemple, cela
     représentait 80 boîtes invisibles, 240 requêtes SQL et près de 350 Ko
     d'HTML pour rien.

     Elle n'est désormais rendue que si la page le demande, en déclarant une
     section « quickView ». Les pages concernées sont celles qui contiennent un
     bouton data-bs-target="#quickView{id}" : accueil, location, recherche et
     liste par catégorie. --}}
@hasSection('quickView')
    @include('client.quickView')
@endif

{{-- Lot 114 bis (19/09/2026) : pendant le mode « site en construction », un visiteur NON connecté ne peut
     ouvrir que les pages de connexion et de mot de passe oublié. Elles s'affichent alors seules : ni
     en-tête, ni menu mobile, ni bandeaux, ni pied de page, ni bouton flottant — tous leurs liens
     ramèneraient à la page « site en construction ». Le pied reste INCLUS (il porte les scripts du
     site), il est seulement masqué. Mode désactivé ou personne connectée : rien ne change. --}}
@php $visiteurEnConstruction = !Auth::check() && \App\Models\Configuration::siteEnConstruction(); @endphp
@if ($visiteurEnConstruction)
    <style id="style-visiteur-en-construction">
        header.header-area, .mobile-header-active, .mobile-promotion, footer.main, .whatsapp-flottant, #scrollUp,
        .hero-login__trust, .hero-login__divider, .hero-login__signup { display: none !important; }
        /* L'en-tête fixe est masqué : le corps n'a plus à lui réserver 80 px en haut. */
        body.client-layout { padding-top: 0 !important; }
    </style>
@endif

@include('client.header')

@yield('content')


@include('client.footer')

{{-- Le consentement aux cookies vaut pour TOUT le site : un visiteur qui
     arrive par une fiche produit ou un lien partage doit pouvoir choisir
     comme les autres. Le popup publicitaire, lui, ne concerne que l'accueil
     et s'inclut dans cette page-la. --}}
@include('client._cookies')
