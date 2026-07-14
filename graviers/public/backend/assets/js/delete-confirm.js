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

        // Mode: 'delete' (rouge, "Voulez-vous vraiment supprimer X ?") OU
        //       'confirm' (générique, le texte fourni est utilisé tel quel)
        var mode = (opts.mode === 'confirm') ? 'confirm' : 'delete';

        var defaultTitle = (mode === 'confirm') ? 'Confirmation' : 'Confirmation de suppression';
        var defaultConfirmBtn = (mode === 'confirm') ? 'Oui, confirmer' : 'Oui, supprimer';
        var iconColor = (mode === 'confirm') ? '#10b981' : '#ef4444';
        var confirmBtnColor = (mode === 'confirm') ? '#10b981' : '#ef4444';
        var confirmBtnClass = (mode === 'confirm') ? 'btn btn-success' : 'btn btn-danger';

        var title = opts.title || defaultTitle;
        var text;
        if (opts.html) {
            // HTML brut fourni : bypass complet du template "supprimer X"
            text = opts.html;
        } else if (mode === 'confirm') {
            // Mode confirmation générique : pas de template "Voulez-vous vraiment supprimer..."
            text = opts.text ? escapeHtml(opts.text) : 'Confirmer cette action ?';
        } else if (opts.itemName) {
            text = 'Voulez-vous vraiment supprimer <strong>' + escapeHtml(opts.itemName) + '</strong> ?<br><small class="text-muted">' + escapeHtml(opts.text || 'Cette action est irréversible.') + '</small>';
        } else {
            text = escapeHtml(opts.text || 'Cette action est irréversible.');
        }

        if (!swal) {
            if (window.confirm((opts.itemName && mode === 'delete' ? 'Supprimer ' + opts.itemName + ' ? ' : '') + (opts.text || ''))) {
                proceed();
            }
            return;
        }

        swal.fire({
            title: title,
            html: text,
            icon: 'warning',
            iconColor: iconColor,
            showCancelButton: true,
            confirmButtonText: opts.confirmText || defaultConfirmBtn,
            cancelButtonText: opts.cancelText || 'Annuler',
            confirmButtonColor: confirmBtnColor,
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            focusCancel: true,
            customClass: {
                popup: 'swal-premium-popup',
                confirmButton: confirmBtnClass,
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

    window.confirmDelete = confirmDelete;

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
    // Neutralise les data-bs-toggle="modal" pointant vers #deleteModal-* :
    // on retire les attributs dès le chargement pour empêcher Bootstrap 5
    // d'ouvrir le modal en parallèle de notre SweetAlert (double confirmation).
    // ============================================================
    function neutralizeDeleteModalTriggers(root) {
        root = root || document;
        var nodes = root.querySelectorAll(
            '[data-bs-target^="#deleteModal-"], [data-target^="#deleteModal-"]'
        );
        for (var i = 0; i < nodes.length; i++) {
            var btn = nodes[i];
            if (btn.dataset.swalDeleteProcessed) continue;
            btn.dataset.swalDeleteProcessed = '1';

            var targetSel = btn.getAttribute('data-bs-target') || btn.getAttribute('data-target');
            if (targetSel) {
                btn.dataset.swalTarget = targetSel;
            }
            // Retirer les attributs qui déclencheraient l'ouverture du modal
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.removeAttribute('data-toggle');
            btn.removeAttribute('data-target');
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest(
            '[data-swal-target^="#deleteModal-"], [data-bs-target^="#deleteModal-"], [data-target^="#deleteModal-"]'
        );
        if (!btn) return;

        var targetSel = btn.getAttribute('data-swal-target')
            || btn.getAttribute('data-bs-target')
            || btn.getAttribute('data-target');
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
    // PATTERN D : Forms <form class="js-delete-form">
    // Approche moderne : pas de modal, juste un form avec @csrf @method('DELETE').
    // Le submit déclenche SweetAlert avant l'envoi du form.
    //
    // Attributs supportés sur le <form> :
    //   data-item-name        — Nom de l'élément (template "supprimer X")
    //   data-confirm-text     — Texte d'avertissement secondaire
    //   data-confirm-title    — Titre custom (override "Confirmation de suppression")
    //   data-confirm-button   — Texte du bouton OK (default "Oui, supprimer" ou "Oui, confirmer")
    //   data-confirm-mode     — "delete" (rouge, default) OU "confirm" (vert, validation)
    //   data-confirm-html     — HTML brut, bypass le template "supprimer X"
    // ============================================================
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.classList || !form.classList.contains('js-delete-form')) return;
        if (form.dataset.swalConfirmed === '1') {
            // Déjà confirmé, on laisse passer le submit réel
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        var mode = form.dataset.confirmMode || 'delete';
        confirmDelete({
            mode: mode,
            title: form.dataset.confirmTitle || null,
            itemName: form.dataset.itemName || form.dataset.nom || '',
            text: form.dataset.confirmText || null,
            html: form.dataset.confirmHtml || null,
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
        var form = el.closest('form');

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
    function initialNeutralize() {
        neutralizeNativeConfirms(document);
        neutralizeDeleteModalTriggers(document);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialNeutralize);
    } else {
        initialNeutralize();
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
                    if (node.matches && node.matches('[data-bs-target^="#deleteModal-"], [data-target^="#deleteModal-"]')) {
                        neutralizeDeleteModalTriggers(node.parentNode || document);
                    } else if (node.querySelector && node.querySelector('[data-bs-target^="#deleteModal-"], [data-target^="#deleteModal-"]')) {
                        neutralizeDeleteModalTriggers(node);
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
