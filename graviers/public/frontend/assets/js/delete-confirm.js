/* =============================================================
   DELETE CONFIRM v2 — SweetAlert2 universel
   Couvre TROIS patterns de boutons Supprimer :
   A) Boutons qui ouvrent un modal Bootstrap "deleteModal-*"
   B) Liens/boutons avec onclick="return confirm(...)"
   C) Appel manuel via window.confirmDelete(opts)
   ============================================================= */
(function () {
    'use strict';

    function ensureSwal() {
        if (typeof Swal === 'undefined' && typeof window.Swal === 'undefined') {
            console.warn('[delete-confirm] SweetAlert2 (Swal) non disponible.');
            return null;
        }
        return window.Swal || Swal;
    }

    function confirmDelete(opts) {
        var swal = ensureSwal();
        opts = opts || {};
        var title = opts.title || 'Confirmation de suppression';
        var text = opts.text || 'Cette action est irréversible.';
        if (opts.itemName) {
            text = 'Voulez-vous vraiment supprimer <strong>' + escapeHtml(opts.itemName) + '</strong> ?<br><small class="text-muted">' + escapeHtml(opts.text || 'Cette action est irréversible.') + '</small>';
        } else if (opts.text) {
            text = escapeHtml(opts.text);
        }

        if (!swal) {
            if (window.confirm((opts.itemName ? 'Supprimer ' + opts.itemName + ' ? ' : '') + (opts.text || ''))) {
                proceed();
            }
            return;
        }

        swal.fire({
            title: title,
            html: text,
            icon: 'warning',
            iconColor: '#ef4444',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Oui, supprimer',
            cancelButtonText: opts.cancelText || 'Annuler',
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                popup: 'swal-premium-popup',
                confirmButton: 'btn btn-danger',
                cancelButton: 'btn btn-secondary'
            }
        }).then(function (result) {
            if (result.isConfirmed) {
                proceed();
            }
        });

        function proceed() {
            if (typeof opts.onConfirm === 'function') {
                opts.onConfirm();
            } else if (opts.href) {
                window.location.href = opts.href;
            } else if (opts.form) {
                opts.form.submit();
            }
        }
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function extractMessage(onclickStr) {
        // Récupère le 1er argument de confirm("...")
        if (!onclickStr) return null;
        var m = onclickStr.match(/confirm\s*\(\s*['"`]([^'"`]+)['"`]/);
        return m ? m[1] : null;
    }

    // =====================================================================
    // PLUS JAMAIS DE FENÊTRE NATIVE (10/09/2026). Le client a vu un alert()
    // du navigateur sur le guichet des créances et ne veut plus en voir un
    // seul dans le projet. Deux aides, et un filet :
    //   - alerte(message, {icon, title})       : message d'information, SweetAlert2 ;
    //   - confirmer(message, {title, confirmText}) : Promise<bool>, SweetAlert2 ;
    //   - window.alert = alerte : tout alert() oublié passe par SweetAlert2.
    // Le natif ne reste qu'en repli, si SweetAlert2 n'est pas chargé.
    // =====================================================================
    var alerteNative = window.alert.bind(window);

    window.alerte = function (message, options) {
        var swal = ensureSwal();
        options = options || {};
        if (!swal) { alerteNative(String(message)); return; }
        swal.fire({
            icon: options.icon || 'warning',
            title: options.title || 'Attention',
            text: String(message),
            confirmButtonText: options.confirmText || 'OK',
            confirmButtonColor: '#1c57a3'
        });
    };
    window.alert = window.alerte;

    window.confirmer = function (message, options) {
        var swal = ensureSwal();
        options = options || {};
        if (!swal) { return Promise.resolve(window.confirm(String(message))); }
        return swal.fire({
            title: options.title || 'Confirmation',
            text: String(message),
            icon: options.icon || 'question',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Oui, confirmer',
            cancelButtonText: options.cancelText || 'Annuler',
            confirmButtonColor: options.danger ? '#ef4444' : '#10b981',
            cancelButtonColor: '#6b7280'
        }).then(function (r) { return !!(r && r.isConfirmed); });
    };

    window.confirmDelete = confirmDelete;

    // Les AVERTISSEMENTS DataTables (« Requested unknown parameter », fichier
    // de langue introuvable…) passaient par alert() : ils vont à la console.
    // Une table mal initialisée est un défaut de développement, pas un message
    // pour le caissier.
    var routerDataTables = function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.dataTable && jQuery.fn.dataTable.ext) {
            jQuery.fn.dataTable.ext.errMode = function (settings, helpPage, message) {
                console.warn('[DataTables] ' + message);
            };
            // Colonnes lisibles (10/09/2026) : la largeur suit le contenu.
            jQuery.extend(true, jQuery.fn.dataTable.defaults, { autoWidth: false });
            return true;
        }
        return false;
    };
    if (!routerDataTables()) {
        document.addEventListener('DOMContentLoaded', routerDataTables);
    }


    // ============================================================
    // COLONNES LISIBLES (10/09/2026)
    // Les cellules sont sur une ligne (premium CSS : white-space nowrap) ;
    // celles qui portent un texte long — notes, observations, adresses —
    // reçoivent .td-texte-long et reprennent le retour à la ligne dans une
    // colonne large. Rejoué à chaque page dessinée par DataTables.
    // ============================================================
    function marquerCellulesLongues(racine) {
        var cellules = (racine || document).querySelectorAll('table.table tbody td, table.table tfoot td');
        for (var i = 0; i < cellules.length; i++) {
            var td = cellules[i];
            if (td.classList.contains('td-texte-long')) continue;
            if (td.querySelector('form, .dropdown, select, textarea, input')) continue;
            var texte = (td.textContent || '').replace(/\s+/g, ' ').trim();
            if (texte.length > 70) td.classList.add('td-texte-long');
        }
    }
    window.marquerCellulesLongues = marquerCellulesLongues;
    document.addEventListener('DOMContentLoaded', function () {
        marquerCellulesLongues();
        if (window.jQuery) {
            jQuery(document).on('draw.dt', function (e) { marquerCellulesLongues(e.target); });
        }
    });

    window.showToast = function (msg, type) {
        var swal = ensureSwal();
        if (!swal) { alert(msg); return; }
        swal.fire({
            toast: true,
            position: 'top-end',
            icon: type || 'success',
            title: msg,
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });
    };

    // ============================================================
    // PATTERN A : Modals Bootstrap "deleteModal-*"
    // ============================================================
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest(
            '[data-bs-target^="#deleteModal-"], [data-target^="#deleteModal-"]'
        );
        if (!btn) return;

        var targetSel = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
        var modal = document.querySelector(targetSel);
        if (!modal) return;

        var confirmBtn = modal.querySelector(
            '.modal-footer .btn-danger, .modal-footer a.btn-danger, .modal-footer .btn-sm.btn-danger'
        );
        if (!confirmBtn) {
            var candidates = modal.querySelectorAll('.modal-footer a, .modal-footer button[type="submit"]');
            for (var i = 0; i < candidates.length; i++) {
                if (!candidates[i].hasAttribute('data-bs-dismiss')) {
                    confirmBtn = candidates[i];
                    break;
                }
            }
        }
        if (!confirmBtn) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        var itemName = btn.getAttribute('data-nom') || btn.dataset.nom || '';

        confirmDelete({
            itemName: itemName,
            onConfirm: function () {
                if (confirmBtn.tagName === 'A' && confirmBtn.href) {
                    window.location.href = confirmBtn.href;
                } else if (confirmBtn.form) {
                    confirmBtn.form.submit();
                } else {
                    confirmBtn.click();
                }
            }
        });
    }, true);

    // ============================================================
    // PATTERN D : Forms <form class="js-delete-form"> (10/09/2026)
    // Même mécanique que le back-office : @csrf @method('DELETE'), et le
    // submit passe d'abord par SweetAlert2. Attributs : data-item-name,
    // data-confirm-text, data-confirm-title, data-confirm-button.
    // ============================================================
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('js-delete-form')) return;
        if (form.dataset.swalConfirmed === '1') return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        confirmDelete({
            title: form.dataset.confirmTitle || null,
            itemName: form.dataset.itemName || form.dataset.nom || '',
            text: form.dataset.confirmText || null,
            confirmText: form.dataset.confirmButton || null,
            onConfirm: function () {
                form.dataset.swalConfirmed = '1';
                form.submit();
            }
        });
    }, true);

    // ============================================================
    // PATTERN B : onclick="return confirm(...)"
    // Au chargement DOM, on neutralise les onclick et on les remplace
    // par notre handler SweetAlert2.
    // ============================================================
    function neutralizeNativeConfirms(root) {
        root = root || document;
        var nodes = root.querySelectorAll('[onclick*="confirm("]');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            if (el.dataset.swalProcessed) continue;
            el.dataset.swalProcessed = '1';

            var onclickStr = el.getAttribute('onclick') || '';
            // Sauvegarder le message dans data-confirm-msg
            var msg = extractMessage(onclickStr);
            if (msg) {
                el.dataset.confirmMsg = msg;
            }
            // Sauvegarder l'onclick original (au cas où il fasse autre chose après)
            el.dataset.originalOnclick = onclickStr;
            // Retirer l'onclick natif (sinon double prompt)
            el.removeAttribute('onclick');
        }
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('[data-confirm-msg], [data-original-onclick]');
        if (!el) return;
        // Vérifier qu'on a bien un href ou un form
        if (el.tagName !== 'A' && el.tagName !== 'BUTTON') return;

        var msg = el.dataset.confirmMsg || 'Confirmer cette action ?';
        var href = el.getAttribute('href');
        // el.form est le formulaire PROPRIETAIRE du bouton : il respecte
        // l'attribut « form », qui permet de piloter un formulaire situe
        // ailleurs dans la page. closest() ne voyait que le formulaire
        // ENGLOBANT — un bouton place dans un formulaire mais rattache a un
        // autre soumettait donc le mauvais. Repli sur closest() pour les liens,
        // qui n'ont pas de propriete form.
        var form = el.form || el.closest('form');

        // Si c'est juste # ou vide, on ne fait rien (probablement déjà géré ailleurs)
        if (el.tagName === 'A' && (!href || href === '#' || href === 'javascript:void(0)')) {
            // Cas particulier : peut-être que l'élément utilise un click handler JS ailleurs
            // On laisse passer et on utilise SweetAlert quand même
        }

        e.preventDefault();
        e.stopPropagation();

        // Détecter si c'est une action de suppression (icône delete, mot Supprimer, etc.)
        var isDelete = /supprim|delete|retirer|annuler|cancel/i.test(el.textContent || '') ||
                       el.querySelector('.md-delete_forever, .md-delete, .fa-trash');

        confirmDelete({
            title: isDelete ? 'Confirmation de suppression' : 'Confirmation',
            text: msg,
            confirmText: isDelete ? 'Oui, supprimer' : 'Oui, confirmer',
            onConfirm: function () {
                if (el.tagName === 'BUTTON' && el.type === 'submit' && form) {
                    form.submit();
                } else if (href && href !== '#' && href !== 'javascript:void(0)') {
                    window.location.href = href;
                }
            }
        });
    }, true);

    // Au chargement initial
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            neutralizeNativeConfirms(document);
        });
    } else {
        neutralizeNativeConfirms(document);
    }

    // Si du contenu est ajouté dynamiquement (DataTables redraw, etc.), retraiter
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            for (var i = 0; i < m.addedNodes.length; i++) {
                var node = m.addedNodes[i];
                if (node.nodeType === 1) {
                    if (node.matches && node.matches('[onclick*="confirm("]')) {
                        neutralizeNativeConfirms(node.parentNode || document);
                    } else if (node.querySelector && node.querySelector('[onclick*="confirm("]')) {
                        neutralizeNativeConfirms(node);
                    }
                }
            }
        });
    });
    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', function () {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }
})();
