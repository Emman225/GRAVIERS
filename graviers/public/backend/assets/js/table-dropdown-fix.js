/* =============================================================
   TABLE DROPDOWN FIX v8
   Contexte : la page mélange plusieurs frameworks (CoreUI Pro 5,
   Bootstrap 4, Bootstrap 5-beta1, Alpine). Le dropdown d'actions
   « data-bs-toggle="dropdown" » dans les tables réagit mal
   (parfois rien, parfois géré par Bootstrap en position:absolute
   et donc rogné par .table-responsive sur les lignes du bas).

   Stratégie DÉTERMINISTE :
   1) on NEUTRALISE l'attribut data-bs-toggle/data-toggle des boutons
      d'action de table => aucun framework ne les gère ;
   2) on gère nous-mêmes l'ouverture/fermeture en position:fixed
      => le menu échappe à TOUT conteneur (jamais rogné).
   Les items qui ouvrent une modale (data-bs-toggle="modal") ne sont
   PAS touchés et continuent de fonctionner normalement.
   ============================================================= */
(function () {
    'use strict';

    var openMenu   = null;
    var openToggle = null;
    var savedParent = null; // emplacement d'origine du menu (pour le restaurer à la fermeture)
    var savedNext   = null;

    function isInTable(el) {
        if (!el || !el.closest) return false;
        return !!el.closest('table, .dataTable, .table-responsive, .dataTables_wrapper');
    }

    function findMenu(toggle) {
        var parent = toggle.parentElement;
        if (!parent) return null;
        for (var i = 0; i < parent.children.length; i++) {
            var child = parent.children[i];
            if (child.classList && child.classList.contains('dropdown-menu')) return child;
        }
        return parent.querySelector('.dropdown-menu');
    }

    function positionMenu() {
        if (!openMenu || !openToggle) return;
        if (!document.body.contains(openToggle) || !document.body.contains(openMenu)) { closeMenu(); return; }

        var rect = openToggle.getBoundingClientRect();
        var mw = openMenu.offsetWidth  || 200;
        var mh = openMenu.offsetHeight || 100;
        var winW = window.innerWidth;
        var winH = window.innerHeight;

        var top  = rect.bottom + 4;
        var left = rect.right - mw;

        if (top + mh > winH - 10 && rect.top - mh - 4 > 10) top = rect.top - mh - 4; // bascule au-dessus
        if (left < 8) left = 8;
        if (left + mw > winW - 8) left = winW - mw - 8;
        if (top < 8) top = 8;

        // IMPORTANT : 'inset' est le raccourci de top/right/bottom/left.
        // On le pose EN PREMIER (pour neutraliser un éventuel inset de Popper),
        // puis on définit les longhands ensuite — sinon inset écraserait top/left.
        openMenu.style.setProperty('inset',    'auto', 'important');
        openMenu.style.setProperty('position', 'fixed', 'important');
        openMenu.style.setProperty('top',      top + 'px', 'important');
        openMenu.style.setProperty('left',     left + 'px', 'important');
        openMenu.style.setProperty('right',    'auto', 'important');
        openMenu.style.setProperty('bottom',   'auto', 'important');
        openMenu.style.setProperty('transform','none', 'important');
        openMenu.style.setProperty('margin',   '0', 'important');
        openMenu.style.setProperty('z-index',  '99999', 'important');
        openMenu.style.setProperty('display',  'block', 'important');
    }

    function closeMenu() {
        if (openMenu) {
            ['position','top','left','right','bottom','transform','margin','inset','z-index','display']
                .forEach(function (p) { openMenu.style.removeProperty(p); });
            openMenu.classList.remove('show');
            // Remet le menu à son emplacement d'origine dans le tableau
            if (savedParent) {
                if (savedNext && savedNext.parentNode === savedParent) {
                    savedParent.insertBefore(openMenu, savedNext);
                } else {
                    savedParent.appendChild(openMenu);
                }
            }
        }
        if (openToggle) {
            openToggle.classList.remove('show');
            openToggle.setAttribute('aria-expanded', 'false');
        }
        openMenu = null;
        openToggle = null;
        savedParent = null;
        savedNext = null;
    }

    function openFor(toggle) {
        var menu = findMenu(toggle);
        if (!menu) return;
        closeMenu();
        openMenu = menu;
        openToggle = toggle;
        // Détache le menu de la cellule du tableau et l'attache au <body> :
        // il sort ainsi de TOUT contexte d'empilement (sinon le contenu du
        // tableau au survol repasse au-dessus malgré le z-index).
        savedParent = menu.parentNode;
        savedNext   = menu.nextSibling;
        document.body.appendChild(menu);
        menu.classList.add('show');
        toggle.classList.add('show');
        toggle.setAttribute('aria-expanded', 'true');
        positionMenu();
        requestAnimationFrame(positionMenu);
        setTimeout(positionMenu, 50);
    }

    // Retire les attributs de toggle des frameworks sur les boutons d'action de table
    function neutralize(root) {
        root = root || document;
        var nodes;
        try {
            nodes = root.querySelectorAll('[data-bs-toggle="dropdown"], [data-toggle="dropdown"]');
        } catch (e) { return; }
        for (var i = 0; i < nodes.length; i++) {
            var t = nodes[i];
            if (!isInTable(t)) continue;
            t.classList.add('tdf-toggle');
            t.removeAttribute('data-bs-toggle');
            t.removeAttribute('data-toggle');
            t.setAttribute('aria-haspopup', 'true');
            if (!t.hasAttribute('aria-expanded')) t.setAttribute('aria-expanded', 'false');
        }
    }

    // --- Clic en capture : on gère nous-mêmes ---
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest &&
                     e.target.closest('.tdf-toggle, [data-bs-toggle="dropdown"], [data-toggle="dropdown"]');

        if (toggle && isInTable(toggle)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // neutralise à la volée si pas encore fait
            if (toggle.hasAttribute('data-bs-toggle') || toggle.hasAttribute('data-toggle')) {
                toggle.classList.add('tdf-toggle');
                toggle.removeAttribute('data-bs-toggle');
                toggle.removeAttribute('data-toggle');
            }
            if (openToggle === toggle && openMenu && openMenu.classList.contains('show')) closeMenu();
            else openFor(toggle);
            return;
        }

        // clic sur un item du menu ouvert => laisser l'action (ex. modale) puis fermer
        if (openMenu && openMenu.contains(e.target)) {
            if (e.target.closest('.dropdown-item')) setTimeout(closeMenu, 0);
            return;
        }

        // clic ailleurs => fermer
        if (openMenu) closeMenu();
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && openMenu) closeMenu();
    });

    window.addEventListener('scroll', positionMenu, true);
    window.addEventListener('resize', positionMenu);

    // Neutralisation initiale + après init DataTables + sur redraw (pagination/recherche)
    function boot() {
        neutralize(document);
        setTimeout(function () { neutralize(document); }, 300);
        setTimeout(function () { neutralize(document); }, 1200);
        if (window.MutationObserver) {
            var scheduled = false;
            var mo = new MutationObserver(function () {
                if (scheduled) return;
                scheduled = true;
                setTimeout(function () { scheduled = false; neutralize(document); }, 50);
            });
            mo.observe(document.body, { childList: true, subtree: true });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
