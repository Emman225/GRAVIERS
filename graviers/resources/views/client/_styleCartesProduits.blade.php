{{--
    LE STYLE DES CARTES PRODUIT, POUR TOUT LE SITE PUBLIC.

    Ces règles vivaient dans un bloc <style> de la page d'accueil, portées par
    « .product-grid-4 ». Résultat : la même carte n'avait pas la même tête selon
    la page. La liste par catégorie, qui emploie « .product-grid », n'en héritait
    rien ; la recherche et la location portaient bien la classe, mais la feuille
    de style, elle, ne quittait pas l'accueil.

    Elles sont désormais accrochées à la CARTE elle-même et chargées par le
    gabarit public : une seule définition, la même partout, et un seul endroit à
    reprendre le jour où le dessin changera.

    « .style-2 » est écartée : c'est la carte HORIZONTALE du gabarit — image à
    gauche, texte à droite — que ces hauteurs fixes déformeraient.
--}}

<style>
    /* Cartes produits compactes — dessin de référence de la page d'accueil. */
    .product-cart-wrap:not(.style-2) .product-img-action-wrap {
        padding: 8px !important;
    }
    .product-cart-wrap:not(.style-2) .product-img-action-wrap .product-img a {
        max-height: 120px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
    }
    .product-cart-wrap:not(.style-2) .product-img-action-wrap .product-img a img {
        object-fit: cover;
        height: 120px;
        width: 100%;
        border-radius: 16px;
    }
    .product-cart-wrap:not(.style-2) {
        margin-bottom: 12px !important;
        border-radius: 20px !important;
    }
    .product-cart-wrap:not(.style-2) .product-content-wrap {
        padding: 8px 12px 12px !important;
        min-height: auto !important;
    }
    .product-cart-wrap:not(.style-2) .product-content-wrap h2 {
        font-size: 13px !important;
        margin: 4px 0 !important;
    }
    .product-cart-wrap:not(.style-2) .product-content-wrap h2 a {
        font-size: 13px !important;
        margin-bottom: 0 !important;
    }
    .product-cart-wrap:not(.style-2) .product-rate-cover {
        margin-bottom: 0 !important;
        line-height: 1 !important;
    }
    .product-cart-wrap:not(.style-2) .product-category {
        margin-bottom: 0 !important;
        font-size: 11px;
    }
    .product-cart-wrap:not(.style-2) .product-card-bottom {
        margin-top: 4px !important;
    }
    .product-cart-wrap:not(.style-2) .product-price span {
        font-size: 1rem !important;
    }
    .product-cart-wrap:not(.style-2) .add-cart .add {
        padding: 8px 16px !important;
        font-size: 12px !important;
        margin-top: 6px !important;
    }

    /* UNE HAUTEUR DE CARTE ÉGALE SUR UNE MÊME LIGNE.
       Les noms de produit tiennent sur une ou deux lignes selon leur longueur :
       les cartes voisines finissaient décalées, et le bouton « Ajouter » ne
       tombait pas à la même hauteur d'une carte à l'autre. */
    .product-grid-4 > [class*="col-"],
    .product-grid > [class*="col-"] {
        display: flex;
    }
    .product-grid-4 > [class*="col-"] > .product-cart-wrap:not(.style-2),
    .product-grid > [class*="col-"] > .product-cart-wrap:not(.style-2) {
        display: flex;
        flex-direction: column;
        width: 100%;
    }
    .product-grid-4 > [class*="col-"] > .product-cart-wrap:not(.style-2) .product-content-wrap,
    .product-grid > [class*="col-"] > .product-cart-wrap:not(.style-2) .product-content-wrap {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
    }
    .product-grid-4 > [class*="col-"] > .product-cart-wrap:not(.style-2) .product-card-bottom,
    .product-grid > [class*="col-"] > .product-cart-wrap:not(.style-2) .product-card-bottom {
        margin-top: auto !important;
    }

    /* LE BOUTON « AJOUTER » A LA MEME HAUTEUR SUR TOUTE LA LIGNE.
       Le bas de carte est une rangee : prix a gauche, bouton a droite. Un
       produit en promotion y affiche DEUX lignes de prix — le prix barre sous
       le prix courant — et la rangee grandit d'autant. Le bouton, cale en haut
       de cette rangee, remontait alors de cinquante pixels par rapport a ses
       voisins. On l'aligne sur le bas. */
    .product-cart-wrap:not(.style-2) .product-card-bottom:not(.flex-column) {
        align-items: flex-end;
    }

    /* Le nom du produit sur deux lignes au plus : au-delà, il repoussait le
       prix et le bouton hors de l'alignement des autres cartes. */
    .product-cart-wrap:not(.style-2) .product-content-wrap h2 a {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.35;
        min-height: 2.7em;
    }
</style>
