/* =============================================================
   TABLE DROPDOWN FIX v6
   - Bootstrap garde le contrôle de l'ouverture/fermeture (clic OK)
   - On écoute shown.bs.dropdown et on force le menu en position:fixed
     avec coordonnées calculées => échappe à TOUS les conteneurs
   ============================================================= */
(function () {
    'use strict';

    var fixedMenu  = null;
    var fixedToggle = null;

    function isInTable(el) {
        if (!el || !el.closest) return false;
        return !!el.closest('table, .dataTable, .table-responsive, .dataTables_wrapper');
    }

    function fixPosition() {
        if (!fixedMenu || !fixedToggle) return;
        if (!document.body.contains(fixedToggle) || !document.body.contains(fixedMenu)) {
            cleanup();
            return;
        }

        var rect = fixedToggle.getBoundingClientRect();
        var mw = fixedMenu.offsetWidth || 200;
        var mh = fixedMenu.offsetHeight || 100;
        var winW = window.innerWidth;
        var winH = window.innerHeight;

        // Position par défaut : sous le bouton, aligné à droite
        var top  = rect.bottom + 4;
        var left = rect.right - mw;

        // Bascule au-dessus si pas la place en bas
        if (top + mh > winH - 10 && rect.top - mh - 4 > 10) {
            top = rect.top - mh - 4;
        }
        if (left < 8) left = 8;
        if (left + mw > winW - 8) left = winW - mw - 8;
        if (top < 8) top = 8;

        fixedMenu.style.setProperty('position', 'fixed', 'important');
        fixedMenu.style.setProperty('top',     top + 'px', 'important');
        fixedMenu.style.setProperty('left',    left + 'px', 'important');
        fixedMenu.style.setProperty('right',   'auto', 'important');
        fixedMenu.style.setProperty('bottom',  'auto', 'important');
        fixedMenu.style.setProperty('transform', 'none', 'important');
        fixedMenu.style.setProperty('margin',  '0', 'important');
        fixedMenu.style.setProperty('inset',   'auto', 'important');
        fixedMenu.style.setProperty('z-index', '99999', 'important');
    }

    function cleanup() {
        if (fixedMenu) {
            ['position','top','left','right','bottom','transform','margin','inset','z-index']
                .forEach(function (p) { fixedMenu.style.removeProperty(p); });
        }
        fixedMenu = null;
        fixedToggle = null;
    }

    function findMenu(toggle) {
        var parent = toggle.parentElement;
        if (!parent) return null;
        // Chercher le menu sibling
        var menu = null;
        for (var i = 0; i < parent.children.length; i++) {
            if (parent.children[i].classList && parent.children[i].classList.contains('dropdown-menu')) {
                menu = parent.children[i];
                break;
            }
        }
        return menu;
    }

    function takeOver(toggle) {
        if (!toggle || !isInTable(toggle)) return;
        var menu = findMenu(toggle);
        if (!menu) return;
        cleanup();
        fixedMenu = menu;
        fixedToggle = toggle;
        // Position immédiate puis re-positionnements pour battre Popper
        fixPosition();
        requestAnimationFrame(fixPosition);
        setTimeout(fixPosition, 50);
        setTimeout(fixPosition, 150);
    }

    // Bootstrap 5 events
    document.addEventListener('show.bs.dropdown', function (e) {
        if (isInTable(e.target)) takeOver(e.target);
    });
    document.addEventListener('shown.bs.dropdown', function (e) {
        if (isInTable(e.target)) takeOver(e.target);
    });
    document.addEventListener('hide.bs.dropdown', function () { cleanup(); });
    document.addEventListener('hidden.bs.dropdown', function () { cleanup(); });

    // jQuery (Bootstrap 4)
    if (window.jQuery) {
        jQuery(document).on('show.bs.dropdown shown.bs.dropdown', function (e) {
            if (isInTable(e.target)) takeOver(e.target);
        });
        jQuery(document).on('hide.bs.dropdown hidden.bs.dropdown', cleanup);
    }

    // Filet de sécurité : observer les clicks
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest && e.target.closest('[data-bs-toggle="dropdown"], [data-toggle="dropdown"]');
        if (toggle && isInTable(toggle)) {
            // Délais multiples pour battre Popper
            setTimeout(function () { takeOver(toggle); }, 0);
            setTimeout(function () { takeOver(toggle); }, 50);
            setTimeout(function () { takeOver(toggle); }, 150);
        } else if (fixedMenu && !fixedMenu.contains(e.target)) {
            // Click ailleurs → fermer
            cleanup();
        }
    });

    // Repositionnement sur scroll/resize
    window.addEventListener('scroll', fixPosition, true);
    window.addEventListener('resize', fixPosition);
})();
