@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Paiements - Clients à terme')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Journal des paiements reçus - Clients à terme</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaiementCT">
                <i class="material-icons md-add"></i> Enregistrer un encaissement
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    <p class="d-flex justify-content-between">
                        <span class="h5">Nombre de paiements :
                            <strong>{{ $lignes->count() }}</strong></span>
                        <span class="text-success h5">Total encaissé :
                            <strong>{{ Help::formatNombre($totalEncaisse, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="paiements-clients-a-terme" title="Paiements clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date paiement</th>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Code client</th>
                            <th class="text-center">Client</th>
                            <th class="text-end">Montant reçu</th>
                            <th class="text-center">Mode de paiement</th>
                            <th class="text-center">Référence transaction</th>
                            <th class="text-center">Observations</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">3e validateur</th>
                            <th class="text-center">État</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_paiement ? \Help::dateHeure($l->date_paiement) : '-' }}</td>
                                <td class="text-center">{{ $l->numero_facture }}</td>
                                <td class="text-center">{{ $l->code_client }}</td>
                                <td>
                                    {{ $l->client_nom }}
                                    @if($l->en_attente ?? false)
                                        <br><span class="badge bg-warning text-dark" style="font-size:0.7rem;">
                                            <i class="material-icons md-hourglass_empty" style="font-size:12px;vertical-align:middle;"></i>
                                            En attente de validation
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end {{ ($l->en_attente ?? false) ? 'text-muted' : 'text-success' }}">
                                    <strong>{{ Help::formatNombre($l->montant_recu, true) }}</strong>
                                </td>
                                <td class="text-center">{{ $l->mode_paiement }}</td>
                                <td class="text-center">{{ $l->reference_transaction ?? '-' }}</td>
                                <td class="text-danger">{{ $l->notes ?? '-' }}</td>
                                <td class="text-center small">{{ $l->initie_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->valide_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->troisieme_par ?? '-' }}</td>
                                <td class="text-center">@include('admin.shared._circuit_preuve_reglement', ['partie' => 'etat'])</td>
                                <td class="text-nowrap text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.creancesTerme.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement client à terme ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider"><i class="material-icons md-check_circle"></i></button>
                                        </form>
                                    @elseif (($l->paiement_id ?? null) && !($l->en_attente ?? false))
                                        {{-- Le reçu n'est visible qu'une fois le règlement FINALISÉ (effectué)
                                             (09/09/2026) ; un règlement d'avant le circuit, sans preuve, le garde. --}}
                                        @if ((($l->etat_reglement ?? null) === \App\Models\DemandePaiement::EFFECTUEE) || empty($l->etat_reglement ?? null))
                                        <a href="{{ route('show.creancesTerme.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.creancesTerme.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                        @endif
                                        @include('admin.shared._circuit_preuve_reglement', ['partie' => 'actions', 'prefixe' => 'creancesTerme',
                                            'libellePreuve' => $l->client_nom . ' — ' . Help::formatNombre($l->montant_recu, true)])
                                    @elseif (($l->en_attente ?? false))
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted">
                                    Aucun paiement enregistré pour les clients à terme.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal d'enregistrement encaissement client à terme --}}
    <div class="modal fade" id="modalPaiementCT" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.creancesTerme.paiements.store') }}" id="formPaiementCT">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="material-icons md-payments"></i> <span id="titreModalEncaissement" data-titre="Enregistrer un encaissement client à terme">Enregistrer un encaissement client à terme</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Sélection des factures : UNE OU PLUSIEURS (point 22, 07/09/2026),
                                 du même client ; le montant s'impute de la plus ancienne à la
                                 plus récente, chaque facture reçoit son règlement et son reçu. --}}
                            {{-- DÉPÔT D'AVANCE (point 19, 07/09/2026) : le client verse SANS
                                 affaire ; ses commandes « en agence » suivantes s'en déduiront
                                 d'elles-mêmes. Cochée, la case remplace le choix des affaires
                                 par celui du client, et le formulaire part vers /avances. --}}
                            <div class="col-md-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="depotAvance" value="1">
                                    <label class="form-check-label fw-bold" for="depotAvance">Dépôt d'avance (sans commande ni facture)</label>
                                </div>
                                <small class="text-muted">Le client dépose une somme à valoir sur ses prochaines commandes réglées en agence. Refusé s'il a une affaire non soldée : encaissez-la d'abord, le surplus versé deviendra son avance.</small>
                            </div>
                            <div class="col-md-12" id="blocClientAvance" style="display:none;">
                                <label class="form-label fw-bold">Client <span class="text-danger">*</span></label>
                                <select class="form-control" name="client_id" id="clientAvance" disabled>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($clientsPourAvance as $c)
                                        <option value="{{ $c->id }}">{{ $c->nom }}{{ $c->contact ? ' — ' . $c->contact : '' }}{{ $c->terme ? ' (à terme)' : '' }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="infoClientAvance"></small>
                                <div class="alert alert-warning py-1 mt-2 mb-0 small">{{ $mentionAvance }} Le client en sera informé sur son reçu et par courriel.</div>
                                <input type="hidden" name="retour" value="{{ url()->current() }}">
                            </div>
                            <div class="col-md-12" id="blocAffaires">
                                <label class="form-label fw-bold">Facture(s) à encaisser <span class="text-danger">*</span></label>
                                {{-- Le filtre est une LISTE DES CLIENTS avec recherche (08/09/2026) :
                                     numéro de compte, nom, prénom ou courriel. Choisir un client ne
                                     laisse que ses affaires à cocher ; vider le champ les remontre toutes. --}}
                                <select class="form-control form-control-sm mb-1" id="filtreClientFactures" data-placeholder="Tous les clients à terme — tapez un n° de compte, un nom ou un courriel">
                                    <option value=""></option>
                                    @foreach ($clientsPourFiltre as $cf)
                                        <option value="{{ $cf->id }}">N° {{ $cf->compte }} — {{ $cf->nom }}{{ $cf->email ? ' — ' . $cf->email : '' }}{{ $cf->contact ? ' — ' . $cf->contact : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="border rounded" style="max-height:240px; overflow-y:auto;">
                                    <table class="table table-sm table-hover mb-0" id="tableFactures">
                                        <thead style="background:#1c57a3; color:#fff; position:sticky; top:0;">
                                            <tr>
                                                <th style="width:44px" class="text-center">
                                                    {{-- Tout cocher / tout décocher (08/09/2026). --}}
                                                    <input class="form-check-input" type="checkbox" id="toutCocherFactures" title="Tout cocher / tout décocher">
                                                </th>
                                                <th>N° facture</th>
                                                <th>Client</th>
                                                <th>Date</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Reste</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($facturesNonSoldees as $f)
                                                <tr class="fac-item" data-texte="{{ strtolower($f->numero . ' ' . $f->client_nom) }}">
                                                    <td class="text-center">
                                                        <input class="form-check-input fac-check" type="checkbox"
                                                               name="numeros_facture[]" value="{{ $f->numero }}"
                                                               id="fac{{ $loop->index }}"
                                                               data-client-id="{{ $f->client_id ?? '' }}"
                                                               data-client="{{ $f->client_nom }}"
                                                               data-total="{{ $f->total_a_payer }}"
                                                               data-avance="{{ $f->solde_avance ?? 0 }}"
                                                               data-reste="{{ $f->reste }}">
                                                    </td>
                                                    <td>
                                                        <label class="form-check-label mb-0 fw-bold" for="fac{{ $loop->index }}">{{ $f->numero }}</label>
                                                        @if (($f->avance_imputee ?? 0) >= 1)
                                                            {{-- L'avance a déjà réglé une partie de la commande : cette
                                                                 facture ne porte que le reste (10/09/2026). --}}
                                                            <br><small class="text-muted js-avance-imputee">{{ $f->libelle_affaire ?? ('Commande ' . $f->numero_commande) }} : {{ Help::formatNombre($f->total_commande, true) }},
                                                                dont {{ Help::formatNombre($f->avance_imputee, true) }} réglés par l'avance</small>
                                                        @endif
                                                    </td>
                                                    <td><label class="form-check-label mb-0" for="fac{{ $loop->index }}">{{ $f->client_nom }}</label></td>
                                                    <td>{{ !empty($f->date) ? \Help::dateHeure($f->date) : '-' }}</td>
                                                    <td class="text-end">{{ Help::formatNombre($f->total_a_payer, true) }}</td>
                                                    <td class="text-end"><strong>{{ Help::formatNombre($f->reste, true) }}</strong></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted">Aucune facture n'attend un règlement.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Cochez une ou plusieurs factures d'un même client, ou la case d'en-tête pour tout cocher : le montant s'impute de la plus ancienne à la plus récente.</small>
                            </div>
                            <div class="col-md-12" id="recapFacture" style="display:none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-3"><small class="text-muted">Client</small><br><strong id="recClient">-</strong></div>
                                            <div class="col-md-3"><small class="text-muted">Total facture</small><br><strong class="text-primary" id="recTotal">-</strong></div>
                                            <div class="col-md-3"><small class="text-muted">Déjà payé</small><br><strong class="text-success" id="recPaye">-</strong></div>
                                            <div class="col-md-3"><small class="text-muted">Reste à payer</small><br><strong class="text-danger" id="recReste">-</strong></div>
                                        </div>
                                        <div class="small text-muted mt-1 text-center" id="rcAvance"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- Surplus → avance (point 19, Q3) : dit explicitement, jamais en silence. --}}
                            <div class="col-md-12" id="blocSurplus" style="display:none;">
                                <div class="alert alert-warning py-2 mb-0">
                                    Le montant saisi dépasse le reste à payer de <strong id="montantSurplus">0</strong>.
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" name="surplus_en_avance" value="1" id="surplusEnAvance">
                                        <label class="form-check-label" for="surplusEnAvance">Enregistrer le surplus comme <strong>avance</strong> du client (non remboursable, déduite de ses prochaines commandes en agence)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12" id="historiqueWrap" style="display:none;">
                                <h6 class="mt-2"><i class="material-icons md-history"></i> Historique des paiements</h6>
                                <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#1c57a3; color:#fff;">
                                            <tr><th class="text-center">Tranche</th><th class="text-center">Date</th><th class="text-end">Montant</th><th class="text-center">Mode</th><th class="text-center">Réf.</th><th class="text-center">Reçu</th></tr>
                                        </thead>
                                        <tbody id="historiqueBody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <hr class="my-2">
                            <div class="col-md-6">
                                <label class="form-label">Date paiement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_paiement" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="1" required>
                                <small class="text-muted">Multi-tranches autorisé.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-control" name="mode_paiement_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($modesPaiement as $mp)<option value="{{ $mp->id }}">{{ $mp->libelle }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                {{-- L'agence n'est plus CHOISIE : c'est celle de la personne
                                     connectée. Tant qu'elle était sélectionnée dans une liste,
                                     un caissier pouvait imputer sa recette à un autre guichet
                                     que le sien, et la caisse d'une agence se retrouvait
                                     créditée d'un versement qu'elle n'avait jamais reçu. --}}
                                <label class="form-label">Agence</label>
                                @if ($monAgence)
                                    <input type="text" class="form-control" value="{{ $monAgence->nom }}" readonly>
                                    <small class="text-muted">Votre agence de rattachement.</small>
                                @else
                                    <input type="text" class="form-control" value="Aucune agence" readonly>
                                    <small class="text-danger">
                                        Vous n'êtes rattaché à aucune agence : un administrateur doit vous
                                        affecter à un guichet avant que vous puissiez encaisser.
                                    </small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Référence transaction</label>
                                <input type="text" class="form-control" name="reference">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes / Observations <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="notes" rows="2" required></textarea>
                                <small class="text-muted">Obligatoire : elle est reprise dans la colonne « Observations » du journal.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer & générer le reçu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @include('admin.shared._circuit_preuve_reglement', ['partie' => 'modal'])
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/select2.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    {{-- Plusieurs jQuery se succèdent dans le pied de page : select2 doit
         s'attacher à celui que la page utilise (même règle que Paramètres). --}}
    <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'desc']],
                });
            }

            var fmt = function (n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            };

            // SÉLECTION D'UNE OU PLUSIEURS FACTURES (point 22) — même
            // fonctionnement que le guichet des ventes.
            var rafraichirSelection = function () {
                var $coches = $('.fac-check:checked');
                if ($coches.length === 0) { $('#recapFacture, #historiqueWrap').hide(); $('#montant').val('').attr('data-reste',''); return; }
                var total = 0, reste = 0;
                $coches.each(function () {
                    total += parseFloat($(this).data('total') || 0);
                    reste += parseFloat($(this).data('reste') || 0);
                });
                $('#recClient').text($coches.first().data('client'));
                $('#recTotal').text(fmt(total));
                $('#recPaye').text(fmt(Math.max(0, total - reste)));
                $('#recReste').text(fmt(reste));
                var avance = parseFloat($coches.first().data('avance') || 0);
                $('#rcAvance').text(avance >= 1 ? "Avance disponible du client : " + fmt(avance) + " — elle s'impute d'elle-même sur ses commandes réglées en agence." : '');
                $('#montant').attr('data-reste', Math.round(reste)).val(Math.round(reste));
                $('#blocSurplus').hide();
                $('#surplusEnAvance').prop('checked', false);
                $('#recapFacture').show();

                if ($coches.length !== 1) { $('#historiqueWrap').hide(); return; }

                var num = $coches.first().val();
                $.getJSON('/clients-terme/facture/' + encodeURIComponent(num) + '/historique', function (data) {
                    if (data.error) return;
                    $('#recPaye').text(fmt(data.facture.total_paye));
                    $('#recReste').text(fmt(data.facture.reste_a_payer));
                    $('#montant').attr('data-reste', data.facture.reste_a_payer).val(data.facture.reste_a_payer);

                    var $tbody = $('#historiqueBody').empty();
                    if (data.historique.length === 0) {
                        $tbody.append('<tr><td colspan="6" class="text-center text-muted">Aucun paiement précédent — ce sera la 1ère tranche.</td></tr>');
                    } else {
                        data.historique.forEach(function (h) {
                            $tbody.append('<tr>' +
                                '<td class="text-center"><span class="badge bg-info">'+h.tranche+'</span></td>' +
                                '<td class="text-center">'+(h.date||'-')+'</td>' +
                                '<td class="text-end text-success"><strong>'+fmt(h.montant)+'</strong></td>' +
                                '<td class="text-center">'+h.mode+'</td>' +
                                '<td class="text-center">'+(h.reference||'-')+'</td>' +
                                '<td class="text-nowrap text-center"><a href="'+h.recu_url+'" target="_blank" class="btn btn-xs btn-info" title="Voir le reçu"><i class="material-icons md-visibility"></i></a> <a href="'+h.recu_pdf_url+'" class="btn btn-xs btn-secondary" title="Télécharger le reçu en PDF"><i class="material-icons md-picture_as_pdf"></i></a></td>' +
                                '</tr>');
                        });
                    }
                    $('#historiqueWrap').show();
                });
            };

            $(document).on('change', '.fac-check', function () {
                var $autres = $('.fac-check:checked').not(this);
                if (this.checked && $autres.length && String($autres.first().data('client-id')) !== String($(this).data('client-id'))) {
                    this.checked = false;
                    alerte("Cette facture est celle d'un autre client : un encaissement vaut pour un seul client à la fois.");
                    return;
                }
                rafraichirSelection();
            });


            // Liste des clients avec recherche : numéro de compte, nom, courriel.
            if ($.fn.select2) {
                $('#filtreClientFactures').select2({
                    placeholder: $('#filtreClientFactures').data('placeholder'),
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalPaiementCT'),
                    language: { noResults: function () { return 'Aucun client ne correspond'; } }
                });
            }

            // TOUT COCHER (08/09/2026) : les affaires visibles, à condition
            // qu'elles soient d'un seul client — sinon on demande de choisir
            // le client dans la liste d'abord.
            $('#toutCocherFactures').on('change', function () {
                var $visibles = $('.fac-item:visible .fac-check');
                if (this.checked) {
                    var clients = {};
                    $visibles.each(function () { clients[String($(this).data('client-id'))] = true; });
                    if (Object.keys(clients).length > 1) {
                        this.checked = false;
                        alerte("Les affaires affichées appartiennent à plusieurs clients : choisissez d'abord un client dans la liste, puis cochez tout.");
                        return;
                    }
                }
                $visibles.prop('checked', this.checked);
                rafraichirSelection();
            });
            $('#filtreClientFactures').on('change', function () { $('#toutCocherFactures').prop('checked', false); });

            $('#filtreClientFactures').on('change', function () {
                var id = String($(this).val() || '');
                $('.fac-item').each(function () {
                    var mien = !id || String($(this).find('.fac-check').data('client-id')) === id;
                    $(this).toggle(mien);
                    if (!mien) $(this).find('.fac-check').prop('checked', false);
                });
                rafraichirSelection();
            });


            // ====== DÉPÔT D'AVANCE ET SURPLUS (point 19, 07/09/2026) ======
            var $form = $('#formPaiementCT');
            var actionEncaissement = $form.attr('action');
            var enDepot = function () { return $('#depotAvance').is(':checked'); };

            $('#depotAvance').on('change', function () {
                var depot = this.checked;
                $('#blocAffaires').toggle(!depot);
                if (depot) { $('#recapCommande, #recapFacture, #historiqueWrap, #blocSurplus').hide(); }
                $('#blocClientAvance').toggle(depot);
                $('#clientAvance').prop('disabled', !depot).prop('required', depot);
                $('.fac-check').prop('disabled', depot);
                if (depot) { $('#montant').removeAttr('data-reste').val(''); } else { rafraichirSelection(); }
                $form.attr('action', depot ? '{{ route('show.avances.store') }}' : actionEncaissement);
                $('#titreModalEncaissement').text(depot ? "Dépôt d'avance client" : $('#titreModalEncaissement').data('titre'));
            });

            $('#clientAvance').on('change', function () {
                var id = $(this).val();
                var $info = $('#infoClientAvance').removeClass('text-danger').text('');
                if (!id) return;
                $.getJSON('/avances/solde/' + encodeURIComponent(id), function (d) {
                    if (d.affaires && d.affaires.length) {
                        $info.addClass('text-danger').text("Dépôt impossible : affaire(s) non soldée(s) — " + d.affaires.join(', ') + ". Encaissez d'abord ce qui est dû ; le surplus deviendra une avance.");
                    } else {
                        $info.text("Solde d'avance actuel : " + fmt(d.solde || 0) + '.');
                    }
                });
            });

            var surplusSaisi = function () {
                if (enDepot()) return 0;
                var montant = parseFloat($('#montant').val() || 0);
                // data-reste et non max : max faisait refuser le formulaire par le
                // navigateur avant que le surplus puisse devenir une avance (10/09/2026).
                var max = parseFloat($('#montant').attr('data-reste') || 0);
                return max > 0 && montant > max ? Math.round(montant - max) : 0;
            };
            $('#montant').on('input change', function () {
                var s = surplusSaisi();
                $('#montantSurplus').text(fmt(s));
                $('#blocSurplus').toggle(s > 0);
                if (s <= 0) $('#surplusEnAvance').prop('checked', false);
            });

            $('#formPaiementCT').on('submit', function (e) {
                var m = parseFloat($('#montant').val() || 0);
                if (m <= 0) { e.preventDefault(); alerte('Le montant doit être supérieur à 0.'); return false; }
                if (enDepot()) {
                    if (!$('#clientAvance').val()) { e.preventDefault(); alerte('Choisissez le client qui dépose l\'avance.'); return false; }
                    return true;
                }
                if ($('.fac-check:checked').length === 0) { e.preventDefault(); alerte('Cochez au moins une facture à encaisser.'); return false; }
                var s = surplusSaisi();
                if (s > 0 && !$('#surplusEnAvance').is(':checked')) { e.preventDefault(); alerte('Le montant dépasse le reste à payer de ' + fmt(s) + '. Cochez « Enregistrer le surplus comme avance » pour le conserver au client, ou corrigez le montant.'); return false; }
            });
        });
    </script>
@endsection
