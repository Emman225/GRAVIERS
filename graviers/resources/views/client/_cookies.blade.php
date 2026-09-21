{{--
    LE BANDEAU DE CONSENTEMENT AUX COOKIES.

    Il s'affiche sur TOUTES les pages du site public, pas seulement l'accueil :
    un visiteur qui arrive par une fiche produit ou un lien partagé doit pouvoir
    donner — ou refuser — son accord comme les autres.

    Trois choix, et refuser est aussi accessible qu'accepter : un bandeau qui
    n'offre que « Tout accepter » ne recueille pas un consentement, il l'extorque.

    Le choix est conservé un an dans un cookie de premier niveau. Les cookies
    NÉCESSAIRES — session de connexion, panier, jeton anti-falsification — ne
    sont pas soumis au choix : sans eux le site ne fonctionne pas, et ils ne
    servent à aucun suivi.
--}}

<div class="ck-bandeau" id="ckBandeau" hidden>
    <div class="ck-corps">
        <div class="ck-texte">
            <strong>Ce site utilise des cookies.</strong>
            Certains sont nécessaires au fonctionnement du site — votre connexion, votre
            panier. Les autres ne sont déposés qu'avec votre accord.
            <a href="{{ route('confidentialite') }}">En savoir plus</a>
        </div>

        <div class="ck-boutons">
            <button type="button" class="ck-btn ck-btn-lien" id="ckGerer">Gérer</button>
            <button type="button" class="ck-btn ck-btn-secondaire" id="ckRefuser">Refuser</button>
            <button type="button" class="ck-btn ck-btn-principal" id="ckAccepter">Tout accepter</button>
        </div>
    </div>
</div>

<div class="ck-overlay" id="ckOverlay" hidden>
    <div class="ck-panneau" role="dialog" aria-modal="true" aria-labelledby="ckPanneauTitre">
        <div class="ck-panneau-tete">
            <h2 id="ckPanneauTitre">Gérer mes cookies</h2>
            <button type="button" class="ck-panneau-fermer" id="ckPanneauFermer" aria-label="Fermer">&times;</button>
        </div>

        <div class="ck-panneau-corps">
            <div class="ck-categorie">
                <label>
                    <input type="checkbox" checked disabled>
                    <span class="ck-categorie-nom">Nécessaires <em>(toujours actifs)</em></span>
                </label>
                <p>
                    Votre session de connexion, votre panier et la protection des formulaires.
                    Sans eux, vous ne pourriez ni vous connecter ni commander.
                </p>
            </div>

            <div class="ck-categorie">
                <label>
                    <input type="checkbox" id="ckMesure">
                    <span class="ck-categorie-nom">Mesure d'audience</span>
                </label>
                <p>
                    Comprendre quelles pages sont consultées, pour améliorer le site.
                    Ces informations ne servent à aucune publicité.
                </p>
            </div>

            <div class="ck-categorie">
                <label>
                    <input type="checkbox" id="ckMarketing">
                    <span class="ck-categorie-nom">Publicité et offres</span>
                </label>
                <p>
                    Vous proposer des offres adaptées à ce que vous consultez, sur le site
                    comme en dehors.
                </p>
            </div>
        </div>

        <div class="ck-panneau-pied">
            <button type="button" class="ck-btn ck-btn-secondaire" id="ckToutRefuser">Tout refuser</button>
            <button type="button" class="ck-btn ck-btn-principal" id="ckEnregistrer">Enregistrer mes choix</button>
        </div>
    </div>
</div>

{{-- 17/09/2026 : la pastille flottante de rappel est retirée à la demande du client ;
     le consentement se retire depuis le lien « Gérer mes cookies » du pied de page
     (data-ck-ouvrir), qui ouvre le même panneau. --}}

<style>
    .ck-bandeau{position:fixed;left:0;right:0;bottom:0;z-index:2100;background:#fff;
        border-top:1px solid #e3e3e3;box-shadow:0 -6px 24px rgba(0,0,0,.12);padding:16px;}
    .ck-bandeau[hidden]{display:none;}
    .ck-corps{max-width:1180px;margin:0 auto;display:flex;flex-wrap:wrap;gap:14px;
        align-items:center;justify-content:space-between;}
    .ck-texte{flex:1 1 420px;font-size:13.5px;color:#444;line-height:1.55;}
    .ck-texte a{color:#1c57a3;text-decoration:underline;}
    .ck-boutons{display:flex;flex-wrap:wrap;gap:8px;}
    .ck-btn{border:0;border-radius:6px;padding:10px 18px;font-size:13.5px;font-weight:600;
        cursor:pointer;}
    .ck-btn-principal{background:#1c57a3;color:#fff;}
    .ck-btn-secondaire{background:#eceff3;color:#333;}
    .ck-btn-lien{background:transparent;color:#1c57a3;text-decoration:underline;padding:10px 8px;}
    .ck-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2200;
        display:flex;align-items:center;justify-content:center;padding:16px;}
    .ck-overlay[hidden]{display:none;}
    .ck-panneau{background:#fff;border-radius:10px;width:min(560px,100%);max-height:90vh;
        display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35);}
    .ck-panneau-tete{display:flex;align-items:center;justify-content:space-between;
        padding:18px 20px;border-bottom:1px solid #eee;}
    .ck-panneau-tete h2{font-size:19px;margin:0;font-weight:700;}
    .ck-panneau-fermer{border:0;background:transparent;font-size:28px;line-height:1;
        color:#666;cursor:pointer;}
    .ck-panneau-corps{padding:8px 20px 4px;overflow-y:auto;}
    .ck-categorie{padding:14px 0;border-bottom:1px solid #f1f1f1;}
    .ck-categorie:last-child{border-bottom:0;}
    .ck-categorie label{display:flex;align-items:center;gap:10px;cursor:pointer;}
    .ck-categorie-nom{font-weight:600;font-size:14.5px;}
    .ck-categorie-nom em{font-weight:400;color:#8a8a8a;font-style:normal;font-size:12.5px;}
    .ck-categorie p{margin:7px 0 0 28px;font-size:12.5px;color:#666;line-height:1.5;}
    .ck-panneau-pied{display:flex;gap:8px;justify-content:flex-end;padding:16px 20px;
        border-top:1px solid #eee;}
    @media (max-width:640px){
        .ck-boutons{width:100%;}
        .ck-btn{flex:1 1 auto;}
    }
</style>

<script>
    (function () {
        var NOM = 'consentement_cookies';
        var DUREE = 365;

        var bandeau = document.getElementById('ckBandeau');
        var overlay = document.getElementById('ckOverlay');
        var rappel = document.getElementById('ckRappel');
        var mesure = document.getElementById('ckMesure');
        var marketing = document.getElementById('ckMarketing');

        function lireCookie(nom) {
            var parts = ('; ' + document.cookie).split('; ' + nom + '=');
            if (parts.length !== 2) return null;
            try { return decodeURIComponent(parts.pop().split(';').shift()); } catch (e) { return null; }
        }

        function ecrireCookie(nom, valeur, jours) {
            var expire = new Date();
            expire.setTime(expire.getTime() + jours * 864e5);

            // SameSite=Lax : le choix voyage avec la navigation normale mais pas
            // depuis un site tiers. Secure seulement en HTTPS, sinon le cookie
            // serait refusé en developpement.
            var secure = location.protocol === 'https:' ? '; Secure' : '';

            document.cookie = nom + '=' + encodeURIComponent(valeur)
                + '; expires=' + expire.toUTCString() + '; path=/; SameSite=Lax' + secure;
        }

        function lireChoix() {
            var brut = lireCookie(NOM);
            if (!brut) return null;
            try { return JSON.parse(brut); } catch (e) { return null; }
        }

        function enregistrer(choix) {
            choix.date = new Date().toISOString();
            ecrireCookie(NOM, JSON.stringify(choix), DUREE);

            bandeau.hidden = true;
            overlay.hidden = true;
            if (rappel) { rappel.hidden = false; }
            document.body.style.overflow = '';

            appliquer(choix);

            // LE POPUP PUBLICITAIRE ATTEND CE MOMENT.
            //
            // Empiler deux fenetres l'une sur l'autre les fait se recouvrir sur
            // un ecran court, et faire de la publicite avant d'avoir demande le
            // consentement met la charrue avant les boeufs. Le choix fait, la
            // page previent qui veut l'entendre.
            document.dispatchEvent(new CustomEvent('cookies-choisis'));
        }

        // LE CHOIX DOIT AVOIR UN EFFET, sinon le bandeau n'est qu'un décor.
        //
        // Les scripts de mesure et de publicité se déclarent avec l'attribut
        // type="text/plain" et data-cookie="mesure" ou "marketing" : ils ne
        // s'exécutent QUE si la catégorie est acceptée. Aucun n'est présent
        // aujourd'hui ; le jour où l'on en ajoutera un, il sera soumis au choix
        // du visiteur sans autre intervention.
        function appliquer(choix) {
            document.querySelectorAll('script[data-cookie]').forEach(function (balise) {
                var categorie = balise.getAttribute('data-cookie');

                if (!choix[categorie] || balise.dataset.active === 'oui') return;

                var actif = document.createElement('script');
                if (balise.src) actif.src = balise.src;
                else actif.textContent = balise.textContent;

                document.head.appendChild(actif);
                balise.dataset.active = 'oui';
            });
        }

        function ouvrirPanneau() {
            var choix = lireChoix() || {};
            mesure.checked = !!choix.mesure;
            marketing.checked = !!choix.marketing;
            overlay.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        document.getElementById('ckAccepter').addEventListener('click', function () {
            enregistrer({ necessaires: true, mesure: true, marketing: true });
        });

        document.getElementById('ckRefuser').addEventListener('click', function () {
            enregistrer({ necessaires: true, mesure: false, marketing: false });
        });

        document.getElementById('ckToutRefuser').addEventListener('click', function () {
            enregistrer({ necessaires: true, mesure: false, marketing: false });
        });

        document.getElementById('ckGerer').addEventListener('click', ouvrirPanneau);
        // Lien « Gérer mes cookies » du pied de page (remplace la pastille flottante).
        document.querySelectorAll('[data-ck-ouvrir]').forEach(function (lien) {
            lien.addEventListener('click', function (e) { e.preventDefault(); ouvrirPanneau(); });
        });

        document.getElementById('ckEnregistrer').addEventListener('click', function () {
            enregistrer({ necessaires: true, mesure: mesure.checked, marketing: marketing.checked });
        });

        document.getElementById('ckPanneauFermer').addEventListener('click', function () {
            overlay.hidden = true;
            document.body.style.overflow = '';

            // Fermer le panneau sans choisir ne vaut pas consentement : le
            // bandeau reste, tant que rien n'a été décidé.
            if (!lireChoix()) bandeau.hidden = false;
        });

        // Le popup interroge cet indicateur au chargement : un visiteur qui a
        // deja choisi ne doit pas attendre un evenement qui ne viendra pas.
        window.consentementCookiesDonne = !!lireChoix();

        var choixExistant = lireChoix();

        if (choixExistant) {
            if (rappel) { rappel.hidden = false; }
            appliquer(choixExistant);
        } else {
            bandeau.hidden = false;
        }
    })();
</script>
