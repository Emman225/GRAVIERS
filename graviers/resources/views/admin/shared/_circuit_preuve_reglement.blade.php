{{-- POINT 20 SUR LES RÈGLEMENTS DE DETTES (09/09/2026).

     Deux morceaux réutilisés par les trois historiques (fournisseurs,
     livreurs, apporteurs) :
       - « etat »   : la cellule État (badge) d'une ligne $l ;
       - « actions »: les icônes du circuit (voir la preuve, téléverser,
                      finaliser), à la suite des boutons du reçu ;
       - « modal »  : la fenêtre de téléversement, une seule par page.
     Attendu : $l (ligne du contrôleur) et $prefixe (fournisseurs|livreurs|apporteurs). --}}
@php
    $partie = $partie ?? 'etat';
    // Base des routes : « show.<prefixe>.paiements » par défaut ; les encaissements
    // en agence passent leur propre base (« show.comptant.encaissements »).
    $base = $base ?? ('show.' . ($prefixe ?? '') . '.paiements');
@endphp
@if ($partie === 'etat')
    {{-- Repère de la ligne (tests, scripts) : le règlement, quelle que soit l'icône affichée. --}}
    <span class="d-none" data-reglement="{{ $l->paiement_id }}"></span>
    @if ($l->en_attente ?? false)
        <span class="badge bg-warning text-dark">En attente de validation</span>
    @elseif (($l->etat_reglement ?? null) === \App\Models\DemandePaiement::EFFECTUEE)
        <span class="badge bg-success">Effectuée</span>
    @elseif (($l->etat_reglement ?? null) === \App\Models\DemandePaiement::PREUVE_JOINTE)
        <span class="badge bg-info text-dark">À payer — preuve jointe</span>
    @elseif (($l->etat_reglement ?? null) === \App\Models\DemandePaiement::A_PAYER)
        <span class="badge bg-warning text-dark">À payer</span>
    @else
        <span class="badge bg-success">Payé</span>
    @endif
@elseif ($partie === 'actions')
    @if ($l->a_preuve ?? false)
        <a href="{{ route($base . '.voirPreuve', $l->paiement_id) }}" target="_blank"
           class="btn btn-sm btn-light" title="Voir la preuve de paiement">
            <i class="material-icons md-visibility"></i>
        </a>
    @endif
    @if ($l->peut_joindre ?? false)
        <button type="button" class="btn btn-sm btn-primary js-joindre-preuve-reglement"
                data-url="{{ route($base . '.preuve', $l->paiement_id) }}"
                data-libelle="{{ $libellePreuve ?? '' }}"
                title="{{ ($l->a_preuve ?? false) ? 'Remplacer la preuve de paiement' : 'Téléverser la preuve de paiement' }}">
            <i class="material-icons md-cloud_upload"></i>
        </button>
    @endif
    @if ($l->peut_finaliser ?? false)
        {{-- Confirmation SweetAlert2 (09/09/2026), la même que pour la validation :
             le formulaire porte la classe js-delete-form et ses attributs data-confirm-*. --}}
        <form method="POST" action="{{ route($base . '.effectuer', $l->paiement_id) }}" class="d-inline js-delete-form"
              data-confirm-mode="confirm"
              data-confirm-title="Finaliser le règlement ?"
              data-confirm-text="Déclarer ce règlement EFFECTUÉ ? Le bénéficiaire le verra dans son espace et son application, et le reçu deviendra disponible."
              data-confirm-button="Oui, finaliser">
            @csrf
            <button type="submit" class="btn btn-sm btn-success" title="Finaliser : règlement effectué">
                <i class="material-icons md-task_alt"></i>
            </button>
        </form>
    @endif
    @if ($l->attend_troisieme ?? false)
        {{-- Le validateur connecté ne peut ni téléverser ni finaliser : c'est à un troisième administrateur. --}}
        <span class="text-muted small d-block" title="Sécurité : la preuve et la finalisation reviennent à un administrateur qui n'a pas validé ce règlement."><em>Preuve et finalisation : un troisième administrateur</em></span>
    @endif
@elseif ($partie === 'modal')
    <div class="modal" id="modalPreuveReglement" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="formPreuveReglement" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="material-icons md-cloud_upload"></i> Téléverser la preuve de paiement</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2"><strong id="preuveReglementLibelle"></strong></p>
                        <label class="form-label" for="preuveReglementFichier">Preuve (PDF, JPG ou PNG — 5 Mo maximum) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="preuveReglementFichier" name="preuve" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Reçu de virement, capture du mobile money, bordereau… Une fois jointe, le règlement peut être finalisé.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer la preuve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.js-joindre-preuve-reglement');
            if (!b) return;
            document.getElementById('formPreuveReglement').setAttribute('action', b.getAttribute('data-url'));
            document.getElementById('preuveReglementLibelle').textContent = b.getAttribute('data-libelle') || '';
            var fenetre = document.getElementById('modalPreuveReglement');
            // Rattachée à <body> : aucune carte survolée du thème ne peut la capturer.
            if (fenetre.parentNode !== document.body) document.body.appendChild(fenetre);
            new bootstrap.Modal(fenetre).show();
        });
    </script>
@endif
