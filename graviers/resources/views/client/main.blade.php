@include('client.head')

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

@include('client.header')

@yield('content')


@include('client.footer')
