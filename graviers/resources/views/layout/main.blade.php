@include('layout.head')


<div class="screen-overlay"></div>
<aside class="navbar-aside" id="offcanvas_aside">
    <div class="aside-top">
        <a href="index.html" class="brand-wrap">
            <img src="{{ asset(config("constantes.logo")) }}" class="logo" alt="Nest Dashboard" />
        </a>
        <div>
            <button class="btn btn-icon btn-aside-minimize"><i
                    class="text-muted material-icons md-menu_open"></i></button>
        </div>
    </div>
    {{-- INSERT NAVBAR --}}
    @include('layout.navbar')
</aside>

<main class="main-wrap">
    {{-- INSERT HEADER --}}

    @include('layout.header')
    <section class="content-main">

        {{-- Bandeau "stock insuffisant" (déplacé depuis header.blade.php pour
             qu'il ne passe plus sous le header fixe). Placé ici, il bénéficie du
             padding-top de .content-main et s'affiche entièrement sous le header. --}}
        @if (session('finDeStock'))
            @php
                $prod = [];
                foreach (session('produits') as $produit) {
                    $unProd = App\Models\Produit::find($produit);
                    if ($unProd) {
                        array_push($prod, $unProd);
                    }
                }
            @endphp
            @if (count($prod) > 0)
                <div class="text-center alert alert-danger">
                    Le stock des produits suivant est insuffisant : <br>
                    @foreach ($prod as $key => $unProduit)
                        {{ ucfirst($unProduit->nom.' ') }}{{ $key == count($prod) - 1 ? '' : ',' }}
                    @endforeach
                </div>
            @endif
        @endif

        {{-- INSERT CARD --}}

        <section class="content-main">

            @php
                // Routes des dashboards / pages d'accueil et toutes les "premières pages"
                // accessibles directement depuis la sidebar — pas de bouton retour ici
                // (ce sont des points d'entrée : on navigue depuis la sidebar).
                $homeRoutes = [
                    // Dashboards & login
                    'show.home', 'livreur.home', 'sellers.home',
                    'apporteur.home', 'client.home', 'client.index', 'client.accueil',
                    'show.login', 'apporteur.login', 'livreur.login', 'sellers.login', 'client.login',

                    // Etat (navAdmin)
                    'show.recapLivraison', 'show.CADetaille', 'show.CAParFamille',
                    'show.clientATerme', 'show.balanceAgee', 'show.etatParrainage',
                    'show.reapprovisionnement',
                    'show.recapCreances.dashboard', 'show.recapCreances.detailTerme',
                    'show.recapCreances.detailComptant',
                    'show.recapDettes.tableauBord', 'show.recapDettes.detailFournisseurs',
                    'show.recapDettes.detailLivreurs', 'show.recapDettes.detailApporteurs',

                    // Comptabilité (navAdmin)
                    'show.comptabilite.tvaCollectee', 'show.comptabilite.airsiCollectee',
                    'show.comptabilite.beneficesLivraisons',
                    'show.comptabilite.recapVentes', 'show.comptabilite.recapLocations',

                    // Client (navAdmin)
                    'show.listClient',
                    'show.comptant.commandes', 'show.comptant.encaissements', 'show.comptant.synthese',
                    'show.encaissements.locations',
                    'show.listClientATerme', 'show.listeDemandeClient',
                    'show.creancesTerme.factures', 'show.creancesTerme.paiements',
                    'show.creancesTerme.relances', 'show.creancesTerme.synthese',

                    // Gestionnaires, Admin & Agent
                    'show.listeGestionnaire', 'show.registerGestionnaire',
                    'show.listeAdmin', 'show.registerAdmin',
                    'show.listeAgent', 'show.AgentRegister',

                    // Commandes
                    'orders.list', 'orders.commandesTraitees', 'show.listeRetourProduit',
                    'orders.listeDesDevis', 'show.ticketSAV',

                    // Livraisons
                    'show.livraisonEnCours', 'show.livraisonValidees', 'show.livraisonHistorique',

                    // Locations
                    'show.listeLocationEnAttente',

                    // Demandes de livraison
                    'show.demandeLivraisonlist', 'show.demandeLivraisonTraitee',
                    'show.comptant.livraisons.encaissements', 'show.grilleTarifaire',

                    // Fournisseurs
                    'show.listSeller', 'show.registerSeller', 'show.listSellerPourBon',
                    'show.fournisseurs.enlevements', 'show.fournisseurs.paiements',
                    'show.fournisseurs.synthese',

                    // Factures & Bons d'enlèvement
                    'show.bonAttente', 'show.bonValides',
                    'orders.facturesNonValidees', 'orders.facturesValidees',

                    // Apporteur d'affaire
                    'show.listApporteur', 'show.commissions',
                    'show.apporteurs.commissions', 'show.apporteurs.paiements',
                    'show.apporteurs.synthese',

                    // Livreurs
                    'show.list', 'show.registerLivreur',
                    'show.livreurs.livraisons', 'show.livreurs.paiements', 'show.livreurs.synthese',

                    // Produits
                    'product.list', 'product.category', 'product.add', 'product.pourcentage',

                    // Code promo / Modération / Paiements reçu
                    'show.creationDeCodePromo',
                    'show.moderationCommentaire', 'show.moderationCommentairesBlog',
                    'paye.list',

                    // Demandes de paiement
                    'show.listeDeDemandeLivreur', 'show.listeDeDemandeApporteur',
                    'show.listeDeDemandeFournisseur', 'show.historiqueDemande',

                    // Dettes
                    'show.dettesApporteurs', 'show.dettesFournisseurs', 'show.dettesLivreurs',

                    // Divers
                    'show.creationDeBlog', 'show.listeDesBlogs', 'show.creationDeBanniere', 'show.listeDesBannieres',
                    'show.listeDesSlides', 'show.creationDeSlide', 'show.lesRegions',
                    'dest.lesVilles',
                    'show.agences.*', 'show.statutMetier.*', 'show.typeVehiculeLivreur.*',

                    // Grands livres
                    'grandLivre.clientOrdinaire', 'grandLivre.clientATerme',
                    'grandLivre.livreur', 'grandLivre.fournisseur',

                    // Paramètre
                    'show.parametre',
                ];
                // LA LISTE CI-DESSUS NE SUFFISAIT PLUS.
                //
                // Elle était tenue à la main, et chaque entrée ajoutée au menu
                // depuis gardait son bouton « Retour » — un retour qui ne veut
                // rien dire sur une page ouverte d'un clic dans le menu.
                //
                // On y ajoute donc ce que le MENU cite lui-même. La liste
                // manuelle reste : elle couvre des écrans hors menu qui, eux
                // aussi, n'ont pas de « précédent » (une page d'accueil de
                // profil, par exemple).
                $hideBack = auth()->guest()
                    || request()->routeIs(...$homeRoutes)
                    || \App\Support\MenusLateraux::contientLaRouteCourante();
            @endphp

            @auth
                @unless($hideBack)
                    @php
                        // Page précédente réellement visitée, tenue côté serveur par
                        // MemoriserPagePrecedente. Le bouton devient un VRAI lien : il
                        // ramène exactement là d'où l'on vient.
                        //
                        // L'ancienne version rejouait l'historique du navigateur —
                        // history.back(), et history.go(-2) quand la page semblait
                        // s'être redirigée sur elle-même. Ce second cas se déclenchait
                        // aussi sur un simple rechargement, et le retour sautait une
                        // page de trop.
                        $urlRetour = session(\App\Http\Middleware\MemoriserPagePrecedente::CLE_PRECEDENTE);
                    @endphp
                    <script>
                        /**
                         * Repli, pour les boutons « Retour » propres à une page qui
                         * appellent encore l'historique, et pour la toute première page
                         * d'une session, où le serveur n'a pas encore de précédente.
                         */
                        function retourPagePrecedente() {
                            var cible = @json($urlRetour);

                            if (cible) {
                                location.href = cible;
                            } else {
                                history.back();
                            }
                            return false;
                        }
                    </script>
                    <div class="back-button-wrapper mb-3" id="globalBackBtn">
                        <a href="{{ $urlRetour ?: 'javascript:retourPagePrecedente()' }}" class="btn btn-light btn-back">
                            <i class="material-icons md-arrow_back"></i>
                            <span>Retour</span>
                        </a>
                    </div>
                @endunless
            @endauth

            @yield('contenu')

            @auth
                @unless($hideBack)
                    {{-- Détecte les boutons retour custom : leur applique le design premium et masque le bouton global --}}
                    <script>
                        (function () {
                            var globalBtn = document.getElementById('globalBackBtn');
                            if (!globalBtn) return;
                            var links = document.querySelectorAll('a[href]');
                            var customFound = false;
                            for (var i = 0; i < links.length; i++) {
                                var a = links[i];
                                if (globalBtn.contains(a)) continue;
                                var href = a.getAttribute('href') || '';
                                var txt  = (a.textContent || '').trim().toLowerCase();
                                var hasArrowBack = !!a.querySelector('.md-arrow_back, .md-keyboard_backspace, .md-chevron_left');
                                var hasHistoryBack = /history\.(back|go\(-1\))/.test(href);
                                var isRetourLink = (txt.indexOf('retour') > -1 || txt === '') && (hasArrowBack || hasHistoryBack);
                                if (hasHistoryBack || isRetourLink) {
                                    customFound = true;
                                    // Boutons "Retour" propres à une page qui rejouent l'historique :
                                    // on leur applique le même retour intelligent que le bouton global
                                    // (cf. retourPagePrecedente). Les liens pointant vers une VRAIE
                                    // route ne sont pas touchés : ils doivent continuer à y mener.
                                    if (hasHistoryBack) {
                                        a.setAttribute('href', 'javascript:retourPagePrecedente()');
                                    }
                                    // Applique le design premium au bouton custom
                                    a.classList.remove('btn-outline-secondary', 'btn-sm', 'btn-light');
                                    a.classList.add('btn', 'btn-back');
                                    // Si le lien n'a pas de texte (icône seule), ajoute "Retour"
                                    if (!a.querySelector('span') && txt === '') {
                                        var span = document.createElement('span');
                                        span.textContent = 'Retour';
                                        a.appendChild(span);
                                    }
                                }
                            }
                            if (customFound) globalBtn.style.display = 'none';
                        })();
                    </script>
                @endunless
            @endauth

        </section>


        <!-- card end// -->

    </section>


    @include('layout.footer')
