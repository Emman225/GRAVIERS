{{--
    LA FENÊTRE DE DÉTAIL DU JOURNAL D'AUDIT.

    Elle est séparée du journal, et placée HORS du panneau d'onglet et hors de
    la <section> — exactement comme les fenêtres « Voir les produits » de cet
    écran. Deux versions de Bootstrap sont chargées ensemble dans le projet :
    laissée dans le panneau et ouverte par « data-bs-toggle », la fenêtre
    recevait deux voiles et deux pièges à focus concurrents, et tremblait.

    L'ouverture et la fermeture sont pilotées en JavaScript, par le même
    mécanisme que les autres (classe « param-modal », boutons
    « btn-close-modal »).
--}}
{{-- ===== MODAL DÉTAILS =====
     Une seule fenêtre, remplie au clic : en générer une par ligne
     alourdirait la page de plusieurs centaines de blocs. --}}
<div class="modal fade param-modal" id="modalAudit" tabindex="-1"
     role="dialog" aria-hidden="true" style="display:none;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détail de l'opération</h5>
                <button type="button" class="btn-close btn-close-modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                {{-- ===== CE QUI S'EST PASSÉ =====
                     En premier, et sans jargon : le journal existe pour être
                     relu par quelqu'un qui ne code pas — un gérant qui cherche
                     qui a relevé un plafond, un comptable qui veut savoir qui
                     a annulé une facture. La partie technique reste dessous,
                     repliée, pour qui doit vraiment y aller. --}}
                <div class="alert alert-primary" role="status">
                    <div class="fw-bold mb-1">Ce qui s'est passé</div>
                    <div id="auditRecit">-</div>
                </div>

                <h6 class="mt-3">Détail de l'opération</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody id="auditElements">
                            <tr><td class="text-muted">-</td></tr>
                        </tbody>
                    </table>
                </div>

                {{-- ===== SECTION TECHNIQUE =====
                     Repliée par défaut : elle ne sert qu'au diagnostic, et
                     l'ouvrir d'emblée noyait l'essentiel. --}}
                <div class="mt-4">
                    <button class="btn btn-sm btn-outline-secondary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#auditTechnique"
                            aria-expanded="false" aria-controls="auditTechnique">
                        Détail technique (pour les développeurs)
                    </button>

                    <div class="collapse mt-3" id="auditTechnique">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Méthode</dt>
                            <dd class="col-sm-9" id="auditMethode">-</dd>

                            <dt class="col-sm-3">URL</dt>
                            <dd class="col-sm-9 text-break" id="auditUrl">-</dd>

                            <dt class="col-sm-3">Route</dt>
                            <dd class="col-sm-9" id="auditRoute">-</dd>

                            <dt class="col-sm-3">Navigateur</dt>
                            <dd class="col-sm-9 text-break small" id="auditAgent">-</dd>
                        </dl>

                        <h6 class="mt-3">Données brutes</h6>
                        <pre class="bg-light p-3 rounded small" id="auditDonnees" style="max-height:340px; overflow:auto;">-</pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-close-modal">Fermer</button>
            </div>
        </div>
    </div>
</div>
