{{--
    Popup de règlement de dette — partagée par les écrans dettes apporteurs/fournisseurs/livreurs.
    Inclure une fois en bas de la vue. Les boutons "Régler" portent les attributs data-*.
--}}
<style>
    .rd-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0, 0, 0, 0.5); z-index: 99999;
        overflow-y: auto; padding: 30px 15px;
    }
    .rd-overlay.is-open { display: block; }
    .rd-dialog {
        background: #fff; border-radius: 6px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        max-width: 500px; margin: 60px auto; overflow: hidden;
    }
    .rd-header {
        background: #1c57a3; color: #fff; padding: 12px 18px;
        display: flex; justify-content: space-between; align-items: center;
    }
    .rd-header h5 { margin: 0; font-size: 16px; color: #fff; }
    .rd-close-x {
        background: transparent; border: 0; color: #fff;
        font-size: 24px; line-height: 1; cursor: pointer;
    }
    .rd-body { padding: 18px; }
    .rd-footer {
        padding: 12px 18px; border-top: 1px solid #eee; text-align: right;
    }
    .rd-info {
        background: #f5f8fc; border-left: 3px solid #1c57a3;
        padding: 10px 12px; margin-bottom: 14px; font-size: 13px;
    }
    .rd-info strong { color: #1c57a3; }
</style>

<div class="rd-overlay" id="rdOverlay">
    <div class="rd-dialog">
        <form method="POST" action="{{ route('show.reglerDette') }}">
            @csrf
            <input type="hidden" name="type" id="rdType">
            <input type="hidden" name="tier_id" id="rdTierId">

            <div class="rd-header">
                <h5>Régler la dette</h5>
                <button type="button" class="rd-close-x rd-close" aria-label="Fermer">&times;</button>
            </div>

            <div class="rd-body">
                <div class="rd-info">
                    Bénéficiaire : <strong id="rdNom">—</strong><br>
                    Dette actuelle : <strong id="rdSoldeFormate">—</strong>
                </div>

                <div class="mb-3">
                    <label class="form-label">Montant à régler <span class="text-danger">*</span></label>
                    <input type="number" name="montant" id="rdMontant" class="form-control"
                           min="1" step="1" required>
                    <small class="text-muted">
                        <a href="#" id="rdReglerTout">Régler tout</a>
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Numéro de compte / téléphone (optionnel)</label>
                    <input type="text" name="numero_compte" class="form-control"
                           placeholder="Ex. 07 12 34 56 78">
                </div>
            </div>

            <div class="rd-footer">
                <button type="button" class="btn btn-secondary rd-close">Annuler</button>
                <button type="submit" class="btn btn-success">
                    <i class="material-icons md-check align-middle"></i> Confirmer le règlement
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var overlay = document.getElementById('rdOverlay');
    if (!overlay) return;

    function open(data) {
        document.getElementById('rdType').value = data.type;
        document.getElementById('rdTierId').value = data.tierId;
        document.getElementById('rdNom').textContent = data.nom || '—';
        document.getElementById('rdSoldeFormate').textContent =
            new Intl.NumberFormat('fr-FR').format(data.solde) + ' fcfa';
        var $montant = document.getElementById('rdMontant');
        $montant.max = data.solde;
        $montant.value = data.solde;
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.btn-regler-dette');
        if (trigger) {
            e.preventDefault();
            open({
                type: trigger.dataset.type,
                tierId: trigger.dataset.tierId,
                nom: trigger.dataset.nom,
                solde: parseFloat(trigger.dataset.solde) || 0,
            });
            return;
        }
        if (e.target.closest('.rd-close')) { close(); return; }
        if (e.target === overlay) { close(); return; }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) close();
    });

    var $btnTout = document.getElementById('rdReglerTout');
    if ($btnTout) {
        $btnTout.addEventListener('click', function (e) {
            e.preventDefault();
            var $m = document.getElementById('rdMontant');
            $m.value = $m.max;
        });
    }
})();
</script>
