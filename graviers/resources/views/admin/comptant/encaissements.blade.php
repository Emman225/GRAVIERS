@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Encaissements en agence')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Journal des encaissements en agence</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEncaissement">
                <i class="material-icons md-add"></i> Enregistrer un paiement en agence
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
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    <p class="d-flex justify-content-between">
                        <span class="h5">Nombre d'encaissements :
                            <strong>{{ $lignes->count() }}</strong></span>
                        <span class="text-success h5">Total encaissé :
                            <strong>{{ Help::formatNombre($totalEncaisse, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="encaissements-agence" title="Encaissements en agence" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date encaissement</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Agence</th>
                            <th class="text-end">Montant encaissé</th>
                            <th class="text-center">Mode</th>
                            <th class="text-center">Caissier</th>
                            <th class="text-center">Observations</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">Reçu n°</th>
                            <th class="text-center">3e validateur</th>
                            <th class="text-center">État</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_encaissement ? \Help::dateHeure($l->date_encaissement) : '-' }}</td>
                                <td class="text-center">
                                    {{ $l->numero_commande }}
                                    @if ($l->commande_supprimee ?? false)
                                        {{-- La commande est à la corbeille, mais le règlement
                                             a bien eu lieu : on le dit, on ne le cache pas. --}}
                                        <br><span class="badge bg-secondary" style="font-size:0.7rem;">
                                            commande supprimée
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    {{ $l->client_nom }}
                                    @if($l->en_attente ?? false)
                                        <br><span class="badge bg-warning text-dark" style="font-size:0.7rem;">
                                            <i class="material-icons md-hourglass_empty" style="font-size:12px;vertical-align:middle;"></i>
                                            En attente de validation
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($l->agence_code !== '-')
                                        <span class="badge bg-light text-dark" title="{{ $l->agence_nom }}">{{ $l->agence_code }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-end {{ ($l->en_attente ?? false) ? 'text-muted' : 'text-success' }}">
                                    <strong>{{ Help::formatNombre($l->montant_encaisse, true) }}</strong>
                                </td>
                                <td class="text-center">{{ $l->mode_paiement }}</td>
                                <td class="text-center">{{ $l->caissier }}</td>
                                <td class="small text-danger">{{ $l->observations ?? '-' }}</td>
                                <td class="text-center small">{{ $l->initie_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->valide_par ?? '-' }}</td>
                                <td class="text-center">{{ $l->numero_recu ?? '-' }}</td>
                                <td class="text-center small">{{ $l->troisieme_par ?? '-' }}</td>
                                <td class="text-center">@include('admin.shared._circuit_preuve_reglement', ['partie' => 'etat'])</td>
                                <td class="text-nowrap text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.comptant.encaissements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation de l'encaissement"
                                              data-confirm-text="Confirmez-vous la validation de l'encaissement {{ $l->numero_recu }} ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider l'encaissement"><i class="material-icons md-check_circle"></i></button>
                                        </form>
                                    @endif
                                    @if (($l->paiement_id ?? null) && !($l->en_attente ?? false))
                                        {{-- Le reçu n'est visible qu'une fois le règlement FINALISÉ (effectué)
                                             (09/09/2026) ; un règlement d'avant le circuit, sans preuve, le garde. --}}
                                        @if ((($l->etat_reglement ?? null) === \App\Models\DemandePaiement::EFFECTUEE) || empty($l->etat_reglement ?? null))
                                        <a href="{{ route('show.recu', $l->paiement_id) }}" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="Télécharger PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                        @endif
                                        @include('admin.shared._circuit_preuve_reglement', ['partie' => 'actions', 'base' => 'show.comptant.encaissements',
                                            'libellePreuve' => $l->client_nom . ' — ' . Help::formatNombre($l->montant_encaisse, true)])
                                    @elseif (($l->paiement_id ?? null) && ($l->en_attente ?? false) && !($l->peut_valider ?? false))
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">
                                    Aucun encaissement enregistré.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="4" class="text-end">TOTAL</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalEncaisse, true) }}</td>
                                <td colspan="7"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODAL : Enregistrer un paiement en agence
         ============================================================ --}}
    <div class="modal fade" id="modalEncaissement" tabindex="-1" aria-labelledby="modalEncaissementLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.comptant.encaissements.store') }}" id="formEncaissement">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalEncaissementLabel">
                            <i class="material-icons md-payments"></i> <span id="titreModalEncaissement" data-titre="Encaissement en agence">Encaissement en agence</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Sélection des commandes : UNE OU PLUSIEURS (point 22, 07/09/2026).
                                 Le montant saisi s'impute de la plus ancienne à la plus récente ;
                                 chaque commande reçoit son règlement et son reçu. Les commandes
                                 cochées doivent être du même client. --}}
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
                                <label class="form-label fw-bold">Commande(s) à encaisser <span class="text-danger">*</span></label>
                                {{-- Le filtre est une LISTE DES CLIENTS avec recherche (08/09/2026) :
                                     numéro de compte, nom, prénom ou courriel. Choisir un client ne
                                     laisse que ses affaires à cocher ; vider le champ les remontre toutes. --}}
                                <select class="form-control form-control-sm mb-1" id="filtreClientCommandes" data-placeholder="Tous les clients ordinaires — tapez un n° de compte, un nom ou un courriel">
                                    <option value=""></option>
                                    @foreach ($clientsPourFiltre as $cf)
                                        <option value="{{ $cf->id }}">N° {{ $cf->compte }} — {{ $cf->nom }}{{ $cf->email ? ' — ' . $cf->email : '' }}{{ $cf->contact ? ' — ' . $cf->contact : '' }}</option>
                                    @endforeach
                                </select>
                                <div class="border rounded" style="max-height:240px; overflow-y:auto;">
                                    <table class="table table-sm table-hover mb-0" id="tableCommandes">
                                        <thead style="background:#1c57a3; color:#fff; position:sticky; top:0;">
                                            <tr>
                                                <th style="width:44px" class="text-center">
                                                    {{-- Tout cocher / tout décocher (08/09/2026). --}}
                                                    <input class="form-check-input" type="checkbox" id="toutCocherCommandes" title="Tout cocher / tout décocher">
                                                </th>
                                                <th>N° commande</th>
                                                <th>Client</th>
                                                <th>Date</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Reste</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($commandesNonSoldees as $cmd)
                                                <tr class="cmd-item" data-texte="{{ strtolower($cmd->numero . ' ' . $cmd->client_nom) }}">
                                                    <td class="text-center">
                                                        {{-- Présélection quand on arrive depuis la liste des commandes
                                                             (« Encaisser le paiement » -> ?commande=NUMERO). --}}
                                                        <input class="form-check-input cmd-check" type="checkbox"
                                                               name="numeros_commande[]" value="{{ $cmd->numero }}"
                                                               id="cmd{{ $loop->index }}"
                                                               @checked(request('commande') == $cmd->numero)
                                                               data-client-id="{{ $cmd->client_id }}"
                                                               data-client="{{ $cmd->client_nom }}"
                                                               data-total="{{ $cmd->total_a_payer }}"
                                                               data-reste="{{ $cmd->reste }}"
                                                               data-avance="{{ $cmd->solde_avance ?? 0 }}"
                                                               data-agence="{{ $cmd->agence_id }}">
                                                    </td>
                                                    <td><label class="form-check-label mb-0 fw-bold" for="cmd{{ $loop->index }}">{{ $cmd->numero }}</label></td>
                                                    <td><label class="form-check-label mb-0" for="cmd{{ $loop->index }}">{{ $cmd->client_nom }}</label></td>
                                                    <td>{{ $cmd->date ? \Help::dateHeure($cmd->date) : '-' }}</td>
                                                    <td class="text-end">{{ Help::formatNombre($cmd->total_a_payer, true) }}</td>
                                                    <td class="text-end"><strong>{{ Help::formatNombre($cmd->reste, true) }}</strong></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="text-center text-muted">Aucune commande de client ordinaire n'attend un règlement.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">Commandes des clients ordinaires non soldées. Cochez-en une ou plusieurs (même client), ou cochez la case d'en-tête pour tout cocher : le montant s'impute de la plus ancienne à la plus récente.</small>
                            </div>

                            {{-- Récap commande sélectionnée --}}
                            <div class="col-md-12" id="recapCommande" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <small class="text-muted">Client</small><br>
                                                <strong id="rcClient">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Total commande</small><br>
                                                <strong class="text-primary" id="rcTotal">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Déjà payé</small><br>
                                                <strong class="text-success" id="rcPaye">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Reste à payer</small><br>
                                                <strong class="text-danger" id="rcReste">-</strong>
                                            </div>
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

                            {{-- Historique paiements --}}
                            <div class="col-md-12" id="historiqueWrap" style="display: none;">
                                <h6 class="mt-2"><i class="material-icons md-history"></i> Historique des paiements</h6>
                                <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                    <table class="table table-sm table-bordered" id="tableHistorique">
                                        <thead style="background-color: #1c57a3; color: white;">
                                            <tr>
                                                <th class="text-center">Tranche</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-center">Mode</th>
                                                <th class="text-center">Agence</th>
                                                <th class="text-center">Caissier</th>
                                                <th class="text-center">Reçu</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="historiqueBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <hr class="my-2">

                            {{-- Champs du nouvel encaissement --}}
                            <div class="col-md-6">
                                <label for="date_encaissement" class="form-label">Date encaissement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_encaissement" name="date_encaissement" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant à encaisser (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="1" required placeholder="Ex: 100000">
                                <small class="text-muted">Le paiement peut se faire en une ou plusieurs tranches.</small>
                            </div>
                            <div class="col-md-6">
                                {{-- L'agence n'est plus CHOISIE : c'est celle de la personne
                                     connectée. Tant qu'elle était sélectionnée dans une liste,
                                     un caissier pouvait imputer sa recette à un autre guichet
                                     que le sien, et la caisse d'une agence se retrouvait
                                     créditée d'un versement qu'elle n'avait jamais reçu. --}}
                                <label for="agence_id" class="form-label">Agence</label>
                                @if ($monAgence)
                                    <input type="text" class="form-control" id="agence_id" value="{{ $monAgence->code ? $monAgence->code . ' — ' : '' }}{{ $monAgence->nom }}" readonly>
                                    <small class="text-muted">Votre agence de rattachement.</small>
                                @else
                                    <input type="text" class="form-control" id="agence_id" value="Aucune agence" readonly>
                                    <small class="text-danger">
                                        Vous n'êtes rattaché à aucune agence : un administrateur doit vous
                                        affecter à un guichet avant que vous puissiez encaisser.
                                    </small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label for="mode_paiement_id" class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-control" name="mode_paiement_id" id="mode_paiement_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($modesPaiement as $mp)
                                        <option value="{{ $mp->id }}">{{ $mp->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="reference" class="form-label">Référence transaction</label>
                                <input type="text" class="form-control" id="reference" name="reference" placeholder="N° transaction MoMo / Wave...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Caissier</label>
                                <input type="text" class="form-control" value="{{ Auth::user()?->nom_prenoms ?? '-' }}" readonly>
                                <small class="text-muted">Vous (utilisateur connecté)</small>
                            </div>
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes / Observations <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="notes" id="notes" rows="2" required placeholder="Ex: Acompte 50%, retiré le ..."></textarea>
                                <small class="text-muted">Obligatoire : elle est reprise dans la colonne « Observations » du journal.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer & générer le reçu
                        </button>
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

            // ====== MODAL ENCAISSEMENT ======
            var fmt = function (n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            };

            // SÉLECTION D'UNE OU PLUSIEURS COMMANDES (point 22).
            //
            // Le récapitulatif additionne les commandes cochées ; l'historique
            // ne s'affiche que pour une sélection unique (il n'aurait pas de
            // sens pour plusieurs). Les commandes doivent être du même client.
            var rafraichirSelection = function () {
                var $coches = $('.cmd-check:checked');
                if ($coches.length === 0) {
                    $('#recapCommande, #historiqueWrap').hide();
                    $('#montant').val('').attr('data-reste', '');
                    return;
                }
                var total = 0, reste = 0, agence = '';
                $coches.each(function () {
                    total += parseFloat($(this).data('total') || 0);
                    reste += parseFloat($(this).data('reste') || 0);
                    if (!agence) agence = $(this).data('agence') || '';
                });
                $('#rcClient').text($coches.first().data('client') || '-');
                $('#rcTotal').text(fmt(total));
                $('#rcPaye').text(fmt(Math.max(0, total - reste)));
                $('#rcReste').text(fmt(reste));
                var avance = parseFloat($coches.first().data('avance') || 0);
                $('#rcAvance').text(avance >= 1 ? "Avance disponible du client : " + fmt(avance) + " — elle s'impute d'elle-même sur ses commandes réglées en agence." : '');
                // Le franc CFA n'a pas de subdivision : plafond et valeur
                // proposée sont arrondis, comme le montant affiché.
                $('#montant').attr('data-reste', Math.round(reste)).val(Math.round(reste));
                $('#blocSurplus').hide();
                $('#surplusEnAvance').prop('checked', false);
                if (agence) $('#agence_id').val(agence);
                $('#recapCommande').show();

                if ($coches.length !== 1) {
                    $('#historiqueWrap').hide();
                    return;
                }

                var numero = $coches.first().val();
                $.getJSON('/comptant/commande/' + encodeURIComponent(numero) + '/historique', function (data) {
                    if (data.error) return;
                    $('#rcPaye').text(fmt(data.commande.total_paye));
                    $('#rcReste').text(fmt(data.commande.reste_a_payer));
                    var resteArrondi = Math.round(data.commande.reste_a_payer);
                    $('#montant').attr('data-reste', resteArrondi).val(resteArrondi);

                    var $tbody = $('#historiqueBody').empty();
                    if (data.historique.length === 0) {
                        $tbody.append('<tr><td colspan="8" class="text-center text-muted">Aucun paiement précédent — ce sera la 1ère tranche.</td></tr>');
                    } else {
                        data.historique.forEach(function (h) {
                            $tbody.append(
                                '<tr>' +
                                '<td class="text-center"><span class="badge bg-info">' + h.tranche + '</span></td>' +
                                '<td class="text-center">' + (h.date || '-') + '</td>' +
                                '<td class="text-end text-success"><strong>' + fmt(h.montant) + '</strong></td>' +
                                '<td class="text-center">' + h.mode + '</td>' +
                                '<td class="text-center">' + h.agence_code + '</td>' +
                                '<td class="text-center">' + h.caissier + '</td>' +
                                '<td class="text-center">' + h.numero_recu + '</td>' +
                                '<td class="text-nowrap text-center">' +
                                    '<a href="' + h.recu_url + '" target="_blank" class="btn btn-xs btn-info" title="Voir"><i class="material-icons md-visibility"></i></a> ' +
                                    '<a href="' + h.recu_pdf_url + '" class="btn btn-xs btn-secondary" title="PDF"><i class="material-icons md-picture_as_pdf"></i></a>' +
                                '</td>' +
                                '</tr>'
                            );
                        });
                    }
                    $('#historiqueWrap').show();
                });
            };

            $(document).on('change', '.cmd-check', function () {
                // Un seul client par encaissement : on refuse la case qui en
                // mélangerait deux, et on dit pourquoi.
                var $autres = $('.cmd-check:checked').not(this);
                if (this.checked && $autres.length && String($autres.first().data('client-id')) !== String($(this).data('client-id'))) {
                    this.checked = false;
                    alerte("Cette commande est celle d'un autre client : un encaissement vaut pour un seul client à la fois.");
                    return;
                }
                rafraichirSelection();
            });


            // Liste des clients avec recherche : numéro de compte, nom, courriel.
            if ($.fn.select2) {
                $('#filtreClientCommandes').select2({
                    placeholder: $('#filtreClientCommandes').data('placeholder'),
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalEncaissement'),
                    language: { noResults: function () { return 'Aucun client ne correspond'; } }
                });
            }

            // TOUT COCHER (08/09/2026) : les affaires visibles, à condition
            // qu'elles soient d'un seul client — sinon on demande de choisir
            // le client dans la liste d'abord.
            $('#toutCocherCommandes').on('change', function () {
                var $visibles = $('.cmd-item:visible .cmd-check');
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
            $('#filtreClientCommandes').on('change', function () { $('#toutCocherCommandes').prop('checked', false); });

            $('#filtreClientCommandes').on('change', function () {
                var id = String($(this).val() || '');
                $('.cmd-item').each(function () {
                    var mien = !id || String($(this).find('.cmd-check').data('client-id')) === id;
                    $(this).toggle(mien);
                    if (!mien) $(this).find('.cmd-check').prop('checked', false);
                });
                rafraichirSelection();
            });

            // Commande présélectionnée depuis la liste des commandes.
            if ($('.cmd-check:checked').length) {
                rafraichirSelection();
                $('#modalEncaissement').modal('show');
            }


            // ====== DÉPÔT D'AVANCE ET SURPLUS (point 19, 07/09/2026) ======
            var $form = $('#formEncaissement');
            var actionEncaissement = $form.attr('action');
            var enDepot = function () { return $('#depotAvance').is(':checked'); };

            $('#depotAvance').on('change', function () {
                var depot = this.checked;
                $('#blocAffaires').toggle(!depot);
                if (depot) { $('#recapCommande, #recapFacture, #historiqueWrap, #blocSurplus').hide(); }
                $('#blocClientAvance').toggle(depot);
                $('#clientAvance').prop('disabled', !depot).prop('required', depot);
                $('.cmd-check').prop('disabled', depot);
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
                // Le reste est porté par data-reste, PAS par l'attribut max : avec
                // max, le navigateur refusait le formulaire (« La valeur doit être
                // inférieure ou égale à … ») avant même que le surplus puisse
                // devenir une avance (10/09/2026).
                var max = parseFloat($('#montant').attr('data-reste') || 0);
                return max > 0 && montant > max ? Math.round(montant - max) : 0;
            };
            $('#montant').on('input change', function () {
                var s = surplusSaisi();
                $('#montantSurplus').text(fmt(s));
                $('#blocSurplus').toggle(s > 0);
                if (s <= 0) $('#surplusEnAvance').prop('checked', false);
            });

            // Validation côté client
            $('#formEncaissement').on('submit', function (e) {
                var montant = parseFloat($('#montant').val() || 0);
                if (montant <= 0) {
                    e.preventDefault();
                    alerte('Le montant doit être supérieur à 0.');
                    return false;
                }
                if (enDepot()) {
                    if (!$('#clientAvance').val()) {
                        e.preventDefault();
                        alerte('Choisissez le client qui dépose l\'avance.');
                        return false;
                    }
                    return true;
                }
                if ($('.cmd-check:checked').length === 0) {
                    e.preventDefault();
                    alerte('Cochez au moins une commande à encaisser.');
                    return false;
                }
                var s = surplusSaisi();
                if (s > 0 && !$('#surplusEnAvance').is(':checked')) {
                    e.preventDefault();
                    alerte('Le montant dépasse le reste à payer de ' + fmt(s) + '. Cochez « Enregistrer le surplus comme avance » pour le conserver au client, ou corrigez le montant.');
                    return false;
                }
            });
        });
    </script>
@endsection
