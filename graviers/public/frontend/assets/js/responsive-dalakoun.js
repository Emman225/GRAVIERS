/* SITE GRAND PUBLIC 100 % RESPONSIVE — DALAKOUN (12/09/2026)
   Sous 992 px, devant chaque tableau plus large que l'écran, une ligne
   indique qu'il faut le faire glisser. Rien n'est ajouté sur ordinateur. */
(function () {
    'use strict';

    function marquer() {
        var large = window.innerWidth < 992;
        var conteneurs = document.querySelectorAll('.table-responsive, .dt-container .dt-layout-full');
        Array.prototype.forEach.call(conteneurs, function (c) {
            // Un .table-responsive qui contient un DataTables laisse ce dernier gérer son propre défilement.
            if (c.classList.contains('table-responsive') && c.querySelector('.dt-container')) {
                return;
            }
            var deborde = large && c.clientWidth > 0 && c.scrollWidth > c.clientWidth + 4;
            var precedent = c.previousElementSibling;
            var deja = precedent && precedent.classList && precedent.classList.contains('indication-defilement');
            if (deborde && !deja) {
                var p = document.createElement('p');
                p.className = 'indication-defilement';
                p.textContent = 'Faites glisser le tableau pour voir toutes les colonnes';
                c.parentNode.insertBefore(p, c);
            } else if (!deborde && deja) {
                precedent.parentNode.removeChild(precedent);
            }
        });
    }

    function plusTard() { setTimeout(marquer, 60); }

    document.addEventListener('DOMContentLoaded', marquer);
    window.addEventListener('load', function () { setTimeout(marquer, 600); });
    window.addEventListener('resize', plusTard);
    // Onglets (Mon compte) et tableaux redessinés : un tableau caché mesure 0.
    document.addEventListener('shown.bs.tab', plusTard, true);
    if (window.jQuery) {
        window.jQuery(document).on('init.dt draw.dt', plusTard);
    }
})();
