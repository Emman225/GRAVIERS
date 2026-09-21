/* =============================================================
   CHAMPS OBLIGATOIRES (lot 81, 15/09/2026)
   Tout champ « required » d'un formulaire porte un astérisque rouge
   sur son libellé — ou, sans libellé, dans son texte d'invite — pour
   que le client sache ce qui est exigé avant d'envoyer. Les libellés
   qui portent déjà leur astérisque (back-office) sont laissés tels
   quels. Les champs ajoutés après le chargement (lignes de formulaire
   dynamiques) sont marqués à leur tour.
   ============================================================= */
(function () {
    'use strict';

    var CLASSE = 'champ-obligatoire';

    function poserLeStyle() {
        if (document.getElementById('style-champs-obligatoires')) return;
        var style = document.createElement('style');
        style.id = 'style-champs-obligatoires';
        style.textContent = '.' + CLASSE + '{color:#d9534f;font-weight:bold;margin-left:2px;}';
        document.head.appendChild(style);
    }

    function porteDejaUnAsterisque(element) {
        return /\*/.test(element.textContent || '');
    }

    /** Le libellé d'un champ : label[for], label enveloppant, sinon le label qui le précède dans son groupe. */
    function libelleDe(champ) {
        if (champ.id) {
            var direct = document.querySelector('label[for="' + champ.id.replace(/"/g, '\\"') + '"]');
            if (direct) return direct;
        }
        var enveloppe = champ.closest('label');
        if (enveloppe) return enveloppe;

        var noeud = champ;
        for (var niveau = 0; niveau < 3 && noeud && noeud.parentElement; niveau++) {
            noeud = noeud.parentElement;
            var labels = noeud.querySelectorAll('label');
            var trouve = null;
            for (var i = 0; i < labels.length; i++) {
                var l = labels[i];
                // Un label placé AVANT le champ, qui ne désigne pas un autre champ.
                if (!(l.compareDocumentPosition(champ) & Node.DOCUMENT_POSITION_FOLLOWING)) continue;
                if (l.htmlFor && l.htmlFor !== champ.id) continue;
                if (l.querySelector('input, select, textarea')) continue;
                trouve = l;
            }
            if (trouve) return trouve;
            if (noeud.tagName === 'FORM') break;
        }
        return null;
    }

    /**
     * L'astérisque suit le TEXTE du libellé. Quand le label enveloppe le champ
     * (<label><span>Nom</span><input></label>), l'ajouter en fin de label le
     * ferait tomber sous la case : il va alors dans le premier élément de texte
     * qui précède le champ, ou juste avant le champ à défaut.
     */
    function poserDansLeLibelle(libelle, champ, etoile) {
        if (!libelle.contains(champ)) {
            libelle.appendChild(etoile);
            return;
        }
        var enfants = Array.prototype.slice.call(libelle.children);
        for (var i = 0; i < enfants.length; i++) {
            var e = enfants[i];
            if (e.contains(champ)) break;
            if ((e.textContent || '').trim() !== '') {
                e.appendChild(etoile);
                return;
            }
        }
        var porteur = champ;
        while (porteur.parentElement && porteur.parentElement !== libelle) porteur = porteur.parentElement;
        libelle.insertBefore(etoile, porteur);
    }

    function marquer(champ) {
        if (champ.dataset.obligatoireMarque) return;
        champ.dataset.obligatoireMarque = '1';

        var type = (champ.getAttribute('type') || '').toLowerCase();
        if (['hidden', 'submit', 'button', 'radio', 'checkbox', 'reset', 'image'].indexOf(type) >= 0) return;
        if (champ.closest('.js-sans-asterisque')) return;

        var libelle = libelleDe(champ);
        if (libelle) {
            if (porteDejaUnAsterisque(libelle)) return;
            var etoile = document.createElement('span');
            etoile.className = CLASSE;
            etoile.setAttribute('aria-hidden', 'true');
            etoile.textContent = ' *';
            poserDansLeLibelle(libelle, champ, etoile);
            return;
        }

        var invite = champ.getAttribute('placeholder');
        if (invite && !/\*/.test(invite)) {
            champ.setAttribute('placeholder', invite + ' *');
        }
    }

    function marquerTout(racine) {
        poserLeStyle();
        (racine || document).querySelectorAll('input[required], select[required], textarea[required]').forEach(marquer);
    }

    function demarrer() {
        marquerTout();
        if (!window.MutationObserver) return;
        var attente = null;
        new MutationObserver(function () {
            if (attente) return;
            attente = setTimeout(function () { attente = null; marquerTout(); }, 150);
        }).observe(document.body, { childList: true, subtree: true });
    }

    window.marquerChampsObligatoires = marquerTout;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})();
