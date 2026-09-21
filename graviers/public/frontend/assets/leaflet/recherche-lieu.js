/**
 * RECHERCHE DE LIEU — UNE SEULE CONFIGURATION POUR TOUT LE SITE.
 *
 * Onze barres de recherche existaient, réparties dans huit vues, et TOUTES
 * étaient construites sans aucune option de géocodage :
 *
 *     L.Control.geocoder({ placeholder: '...', collapsed: false })
 *
 * Le contrôle retombait donc sur le Nominatim par défaut : recherche mondiale,
 * réponses en anglais, aucune suggestion pendant la frappe. Concrètement, un
 * client qui tapait « Cocody » recevait des résultats sans rapport, et rien ne
 * se passait tant qu'il n'avait pas validé sa saisie.
 *
 * Quatre corrections, réunies ici pour que les onze barres se comportent
 * pareil — et pour que l'application mobile puisse reprendre exactement la
 * même règle :
 *
 *   1. RECHERCHE LIMITÉE À LA CÔTE D'IVOIRE (`countrycodes: 'ci'`). C'est la
 *      correction décisive : « Cocody », « Yopougon », « Bingerville » ne
 *      remontaient pas en premier, noyés parmi des homonymes du monde entier.
 *
 *   2. RÉPONSES EN FRANÇAIS (`accept-language`), avec le détail de l'adresse.
 *
 *   3. SUGGESTIONS PENDANT LA FRAPPE, à partir de trois caractères et au plus
 *      une requête toutes les 400 ms. Cela rend la recherche utilisable —
 *      et respecte au passage la politique d'usage de Nominatim, qui limite le
 *      débit et bloque les applications trop bavardes.
 *
 *   4. MESSAGES EN FRANÇAIS quand rien n'est trouvé, au lieu du texte anglais
 *      par défaut.
 *
 * Les vues gardent la main : tout ce qu'elles passent en second argument écrase
 * ces valeurs (le placeholder, `collapsed`, `defaultMarkGeocode`...).
 */
(function () {
    'use strict';

    /**
     * Paramètres envoyés à Nominatim à chaque interrogation.
     *
     * `countrycodes` FILTRE réellement les résultats : c'est voulu. L'entreprise
     * livre en Côte d'Ivoire, et une adresse de livraison hors du pays n'aurait
     * aucun sens — mieux vaut une liste courte et juste qu'une liste mondiale
     * dans laquelle le bon résultat se perd.
     */
    var PARAMETRES_NOMINATIM = {
        countrycodes: 'ci',
        'accept-language': 'fr',
        addressdetails: 1,
        limit: 8
    };

    /**
     * Construit le géocodeur Nominatim, ET lui ajoute la méthode `suggest`
     * qui lui manque.
     *
     * C'est ce qui rendait la recherche pénible : le contrôle n'écoute la
     * frappe QUE si le géocodeur expose `suggest` —
     *
     *     if (this.options.geocoder.suggest) {
     *         L.DomEvent.addListener(input, 'input', this._change, this);
     *     }
     *
     * — et, dans cette version du plugin, la classe Nominatim est la seule à
     * ne pas la définir. Sans elle, aucun caractère saisi ne déclenche quoi
     * que ce soit : il faut valider par Entrée pour voir enfin une réponse.
     * Les options `suggestMinLength` et `suggestTimeout` restaient donc sans
     * effet, puisque rien ne les atteignait jamais.
     *
     * Les autres géocodeurs du même fichier résolvent cela d'une ligne —
     * `suggest` renvoie simplement `geocode`. On applique la même règle, sur
     * l'instance uniquement : le fichier du plugin n'est pas modifié, il
     * pourra être remplacé lors d'une mise à jour sans perdre ce correctif.
     */
    function echapper(texte) {
        return String(texte)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Libelle d'une suggestion : le LIEU en premiere ligne, sa localisation en
     * seconde.
     *
     * Le gabarit fourni par le plugin n'assemble que rue, ville, region et
     * pays — il ignore purement et simplement le nom du lieu. Une recherche
     * sur « Riviera » produisait donc deux propositions ecrites
     * « Yamoussoukro » et « Abidjan » : impossible de savoir laquelle est
     * laquelle, ni meme de reconnaitre ce qu'on avait cherche. Le meme travers
     * frappe tous les reperes sans rue (marches, gares, carrefours), qui sont
     * justement ce qu'un client saisit pour se faire livrer.
     *
     * Nominatim renvoie toujours `display_name`, du plus precis au plus large.
     * On prend donc le premier segment comme nom, le reste comme contexte :
     * c'est vrai pour toutes les reponses, la ou l'assemblage par champs
     * d'adresse laisse des trous.
     *
     * Les donnees viennent d'un service exterieur : elles sont echappees avant
     * d'etre inserees dans la page.
     */
    function libelleSuggestion(reponse) {
        var complet = reponse.display_name || reponse.name || '';
        var segments = complet.split(',').map(function (m) { return m.trim(); })
                              .filter(function (m) { return m.length > 0; });

        if (segments.length === 0) {
            return echapper(complet);
        }

        var lieu = echapper(segments[0]);
        var contexte = echapper(segments.slice(1).join(', '));

        if (!contexte) {
            return lieu;
        }

        return lieu + '<br><span class="leaflet-control-geocoder-address-detail">'
             + contexte + '</span>';
    }

    function geocodeurAvecSuggestions() {
        var geocodeur = L.Control.Geocoder.nominatim({
            geocodingQueryParams: PARAMETRES_NOMINATIM,
            htmlTemplate: libelleSuggestion
        });

        if (typeof geocodeur.suggest !== 'function') {
            geocodeur.suggest = function (requete, rappel, contexte) {
                return this.geocode(requete, rappel, contexte);
            };
        }

        return geocodeur;
    }

    function fusionner(base, ajouts) {
        var resultat = {};
        var cle;
        for (cle in base) {
            if (Object.prototype.hasOwnProperty.call(base, cle)) {
                resultat[cle] = base[cle];
            }
        }
        for (cle in ajouts) {
            if (Object.prototype.hasOwnProperty.call(ajouts, cle)) {
                resultat[cle] = ajouts[cle];
            }
        }
        return resultat;
    }

    /**
     * Construit une barre de recherche de lieu configurée pour la Côte d'Ivoire.
     *
     * @param {Object} options Options du contrôle Leaflet, qui priment sur les
     *                         valeurs par défaut ci-dessous.
     * @returns {Object} Le contrôle, prêt à être ajouté à la carte.
     */
    window.creerRechercheLieu = function (options) {
        options = options || {};

        var parDefaut = {
            placeholder: 'Rechercher un lieu (quartier, rue, repère)…',
            errorMessage: 'Aucun lieu trouvé. Essayez un quartier ou un repère proche.',
            collapsed: false,
            defaultMarkGeocode: false,
            showResultIcons: false,

            // Trois caractères avant d'interroger le service : en deçà, la
            // réponse n'a aucune valeur et la requête est gaspillée.
            queryMinLength: 3,
            suggestMinLength: 3,

            // 400 ms de silence avant d'envoyer : on ne part pas à chaque
            // touche frappée.
            suggestTimeout: 400,

            geocoder: geocodeurAvecSuggestions()
        };

        return L.Control.geocoder(fusionner(parDefaut, options));
    };
})();
