@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Paiements apporteurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Journal des paiements apporteurs d'affaires</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaiementApp">
                <i class="material-icons md-add"></i> Enregistrer un paiement apporteur
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

    {{-- ===== DEMANDES INITIÉES PAR LES APPORTEURS =====
         Elles vivent dans une autre table que les règlements ci-dessous : un
         règlement porte sur une commission précise, une demande porte sur un
         montant. Elles n'apparaissaient donc nulle part ici, alors que c'est
         sur cet écran que l'administrateur suit ce qu'il doit aux apporteurs. --}}
    @if (($demandesApporteurs ?? collect())->isNotEmpty())
        <div class="card mb-4 border-warning">
            <header class="card-header bg-warning-subtle">
                <p class="d-flex justify-content-between align-items-center mb-0">
                    <span class="h5 mb-0">
                        <i class="material-icons md-send"></i>
                        Demandes de paiement initiées par les apporteurs
                    </span>
                    <a href="{{ route('show.listeDeDemandeApporteur') }}" class="btn btn-sm btn-warning">
                        Traiter les demandes
                    </a>
                </p>
            </header>

            <div class="card-body">
                <p class="text-muted small">
                    Le montant demandé est <strong>déjà retenu sur le solde de l'apporteur</strong> :
                    il est réservé, pas encore versé. Le versement demande
                    <strong>deux validations par deux administrateurs différents</strong> —
                    celui qui donne la première ne peut pas donner la seconde.
                </p>

                <div class="table-responsive">
                    <table class="table table-striped" id="demandesApporteurs">
                        <thead style="background-color: #b8860b; color: white;">
                            <tr>
                                <th class="text-center">Date</th>
                                <th class="text-center">Code App.</th>
                                <th>Apporteur</th>
                                <th class="text-end">Montant demandé</th>
                                <th class="text-center">Mode de paiement</th>
                                <th class="text-center">N° de compte</th>
                                <th class="text-center">Initié par</th>
                                <th class="text-center">1re validation</th>
                                <th class="text-center">2e validation</th>
                                <th class="text-center">État</th>
                                <th class="text-center">Validation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($demandesApporteurs as $d)
                                <tr>
                                    <td class="text-center">
                                        {{ $d->date ? Carbon::parse($d->date)->format('d/m/Y H:i:s') : '-' }}
                                    </td>
                                    <td class="text-center">{{ $d->code_apporteur }}</td>
                                    <td>{{ $d->apporteur_nom }}</td>
                                    <td class="text-end">{{ Help::formatNombre($d->montant, true) }}</td>
                                    <td class="text-center">{{ $d->mode_paiement }}</td>
                                    <td class="text-center">{{ $d->numero_compte ?: '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-info">L'apporteur</span>
                                        <br><small class="text-muted">{{ $d->initie_par }}</small>
                                    </td>
                                    <td class="text-center small">{{ $d->valide_par_1 }}</td>
                                    <td class="text-center small">{{ $d->valide_par_2 }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $d->couleur_etat }}">{{ $d->etat }}</span>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        {{-- Mêmes règles que l'écran dédié : deux validations,
                                             par deux administrateurs différents. Le serveur les
                                             fait respecter de toute façon ; on n'affiche que les
                                             boutons qu'il acceptera. --}}
                                        @php
                                            $lienValidation = fn ($reponse) => route('show.valideDemande', [
                                                'id'      => $d->id,
                                                'type'    => 'apporteur',
                                                'reponse' => $reponse,
                                                // Pour revenir ici, et non sur l'autre écran.
                                                'retour'  => 'show.apporteurs.paiements',
                                            ]);
                                        @endphp

                                        @if ($d->finalisee)
                                            <span class="text-muted small">—</span>
                                        @elseif (!$d->peut_valider)
                                            <span class="text-muted small"><em>Réservé aux administrateurs</em></span>
                                        @elseif ($d->attend_1re)
                                            <a href="{{ $lienValidation('accepter') }}"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Donner la 1re validation à cette demande ?');" title="1re validation"><i class="material-icons md-check"></i></a>
                                        @elseif ($d->attend_2e && $d->est_initiateur)
                                            <span class="text-muted small">
                                                <em>En attente d'un autre administrateur</em>
                                            </span>
                                        @elseif ($d->attend_2e)
                                            <a href="{{ $lienValidation('accepter') }}"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Accepter et payer cette demande ?');" title="2e validation"><i class="material-icons md-check"></i></a>
                                            <a href="{{ $lienValidation('refuser') }}"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Refuser cette demande ? Le montant sera restitué au solde de l\'apporteur.');" title="Rejeter"><i class="material-icons md-block"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    <p class="d-flex justify-content-between">
                        <span class="h5">Nombre de paiements :
                            <strong>{{ $lignes->count() }}</strong></span>
                        <span class="text-success h5">Total payé :
                            <strong>{{ Help::formatNombre($totalPaye, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="paiements-apporteurs" title="Paiements apporteurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date paiement</th>
                            <th class="text-center">N° Commission</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Code apporteur</th>
                            <th class="text-center">Nom apporteur</th>
                            <th class="text-end">Montant payé</th>
                            <th class="text-center">Mode de paiement</th>
                            <th class="text-center">Référence</th>
                            <th>Notes</th>
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
                                <td class="text-center">{{ $l->numero_com }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
                                <td class="text-center">{{ $l->code_apporteur }}</td>
                                <td>
                                    {{ $l->nom_apporteur }}
                                    @if($l->en_attente ?? false)
                                        <br><span class="badge bg-warning text-dark" style="font-size:0.7rem;">
                                            <i class="material-icons md-hourglass_empty" style="font-size:12px;vertical-align:middle;"></i>
                                            En attente de validation
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end {{ ($l->en_attente ?? false) ? 'text-muted' : 'text-success' }}">
                                    <strong>{{ Help::formatNombre($l->montant, true) }}</strong>
                                </td>
                                <td class="text-center">{{ $l->mode_paiement }}</td>
                                <td class="text-center">{{ $l->reference ?? '-' }}</td>
                                <td>{{ $l->notes ?? '-' }}</td>
                                <td class="text-center small">{{ $l->initie_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->valide_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->troisieme_par ?? '-' }}</td>
                                <td class="text-center">@include('admin.shared._circuit_preuve_reglement', ['partie' => 'etat'])</td>
                                <td class="text-nowrap text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.apporteurs.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement apporteur ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider"><i class="material-icons md-check_circle"></i></button>
                                        </form>
                                    @elseif (!($l->en_attente ?? false))
                                        {{-- Le reçu n'est visible qu'une fois le règlement FINALISÉ (effectué)
                                             (09/09/2026) ; un règlement d'avant le circuit, sans preuve, le garde. --}}
                                        @if ((($l->etat_reglement ?? null) === \App\Models\DemandePaiement::EFFECTUEE) || empty($l->etat_reglement ?? null))
                                        <a href="{{ route('show.apporteurs.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.apporteurs.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                        @endif
                                        @include('admin.shared._circuit_preuve_reglement', ['partie' => 'actions', 'prefixe' => 'apporteurs',
                                            'libellePreuve' => $l->nom_apporteur . ' — ' . Help::formatNombre($l->montant, true)])
                                    @else
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">
                                    Aucun paiement enregistré.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="5" class="text-end">TOTAL</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Modal d'enregistrement de paiement apporteur --}}
    <div class="modal fade" id="modalPaiementApp" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.apporteurs.paiements.store') }}" id="formPaiementApp">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="material-icons md-payments"></i> Enregistrer un paiement de commission</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info small">
                            <i class="material-icons md-info"></i>
                            <strong>Règle métier :</strong> Une commission n'est due QUE si le client a effectivement payé sa commande. Seules les commissions <strong>Dues</strong> ou <strong>Partiellement dues</strong> sont listées ci-dessous.
                        </div>
                        <div class="row g-3">
                            {{-- Étape 1 : choisir l'APPORTEUR, ce qui filtre ses commissions dues --}}
                            @php
                                $apporteursDues = $commissionsDues->groupBy('apporteur_id')->map(function ($coms) {
                                    return (object) [
                                        'apporteur_id'  => $coms->first()->apporteur_id,
                                        'apporteur_nom' => $coms->first()->apporteur_nom,
                                        'code'          => $coms->first()->code_apporteur ?? '',
                                        'email'         => $coms->first()->apporteur_email ?? '',
                                        'nb'            => $coms->count(),
                                        'total_reste'   => $coms->sum('reste'),
                                    ];
                                })->sortBy('apporteur_nom')->values();
                            @endphp
                            <div class="col-md-12">
                                <label for="filtreApporteur" class="form-label fw-bold">Apporteur <span class="text-danger">*</span></label>
                                {{-- Liste avec recherche (08/09/2026) : code apporteur, nom ou courriel. --}}
                                <select class="form-control" id="filtreApporteur"
                                        data-placeholder="— Sélectionner un apporteur : tapez un code, un nom ou un courriel —">
                                    <option value=""></option>
                                    @foreach ($apporteursDues as $app)
                                        <option value="{{ $app->apporteur_id }}">
                                            {{ $app->code ? $app->code . ' — ' : '' }}{{ $app->apporteur_nom }}{{ $app->email ? ' — ' . $app->email : '' }} — {{ $app->nb }} commission(s) due(s) — Total : {{ number_format($app->total_reste, fmod($app->total_reste, 1) == 0 ? 0 : 2, ',', ' ') }} FCFA
                                        </option>
                                    @endforeach
                                </select>
                                @if ($commissionsDues->isEmpty())
                                    <small class="text-warning">Aucune commission n'est actuellement due. Une commission devient due quand le client a payé sa commande.</small>
                                @endif
                            </div>

                            {{-- Étape 2 : cocher les commissions de l'apporteur sélectionné --}}
                            <div class="col-md-12" id="blocCommissions" style="display:none;">
                                <label class="form-label fw-bold">Commissions à payer <span class="text-danger">*</span></label>
                                <small class="d-block text-muted mb-1">Cochez une ou plusieurs commissions :
                                    une seule = paiement par tranches possible ; plusieurs = règlement intégral de la somme.</small>
                                <div class="form-check border-bottom pb-1 mb-1">
                                    <input class="form-check-input" type="checkbox" id="toutCocher">
                                    <label class="form-check-label fw-bold" for="toutCocher">Tout cocher (payer toutes les commissions de cet apporteur)</label>
                                </div>
                                {{-- UN TABLEAU (08/09/2026), comme pour les fournisseurs : les
                                     lignes gardent .com-item / .com-check, le script ne change
                                     pas ; les cases sont centrées dans leur colonne. --}}
                                <div id="listeCommissions" class="border rounded" style="max-height:260px; overflow-y:auto;">
                                    <table class="table table-sm table-hover mb-0" id="tableCommissions">
                                        <thead style="background:#1c57a3; color:#fff; position:sticky; top:0;">
                                            <tr>
                                                <th style="width:44px"></th>
                                                <th>Commission</th>
                                                <th>Commande</th>
                                                <th class="text-end">Reste</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($commissionsDues as $com)
                                                <tr class="com-item" data-apporteur-id="{{ $com->apporteur_id }}" style="display:none;">
                                                    <td class="text-center">
                                                        <input class="form-check-input com-check" type="checkbox"
                                                               name="commission_ids[]" value="{{ $com->id }}"
                                                               id="com{{ $com->id }}"
                                                               data-apporteur-id="{{ $com->apporteur_id }}"
                                                               data-apporteur="{{ $com->apporteur_nom }}"
                                                               data-calc="{{ $com->commission_calc }}"
                                                               data-reste="{{ $com->reste }}">
                                                    </td>
                                                    <td><label class="form-check-label mb-0 fw-bold" for="com{{ $com->id }}">{{ $com->code_com }}</label></td>
                                                    <td>{{ $com->numero_cmd }}</td>
                                                    <td class="text-end"><strong>{{ number_format($com->reste, fmod($com->reste, 1) == 0 ? 0 : 2, ',', ' ') }} FCFA</strong></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-12" id="recapCom" style="display:none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-4"><small class="text-muted">Apporteur</small><br><strong id="recApp">-</strong></div>
                                            <div class="col-md-4"><small class="text-muted">Commission calculée</small><br><strong class="text-primary" id="recCalc">-</strong></div>
                                            <div class="col-md-4"><small class="text-muted">Reste à payer</small><br><strong class="text-danger" id="recReste">-</strong></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12" id="historiqueWrap" style="display:none;">
                                <h6 class="mt-2"><i class="material-icons md-history"></i> Historique des paiements</h6>
                                <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#1c57a3; color:#fff;">
                                            <tr><th class="text-center">Tranche</th><th class="text-center">Date</th><th class="text-end">Montant</th><th class="text-center">Mode</th><th class="text-center">Reçu</th></tr>
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
                                {{-- step 0.01 : les commissions calculées en % ont des décimales
                                     (ex. 11,8) ; step=1 les refusait ("valeur valide la plus proche : 11"). --}}
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="0.01" required>
                                <small class="text-muted" id="montantHint">Multi-tranches autorisé (une seule commission cochée).</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-control" name="mode_paiement_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($modesPaiement as $mp)<option value="{{ $mp->id }}">{{ $mp->libelle }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Référence transaction</label>
                                <input type="text" class="form-control" name="reference">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer & générer le bordereau</button>
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
         s'attacher à celui que la page utilise (même règle que les guichets). --}}
    <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            // Les demandes des apporteurs : recherche, pagination, 5 lignes.
            var $demandesApp = $('#demandesApporteurs');
            if ($demandesApp.length &&
                $demandesApp.find('tbody tr').length > 0 &&
                $demandesApp.find('tbody tr td[colspan]').length === 0) {
                $demandesApp.DataTable({
                    // Garde-fou du projet : sans defaultContent, DataTables lève
                    // « Requested unknown parameter » dès qu'une cellule manque.
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' },
                        { targets: -1, orderable: false, searchable: false },
                    ],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Tout']],
                    // Le tri vient du serveur (de la plus récente à la plus ancienne).
                    order: [],
                });
            }


            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'desc']],
                });
            }

            // Montants avec décimales possibles (commission = % du total, ex. 11,8 FCFA)
            var fmt = function (n) {
                var v = parseFloat(n) || 0;
                return new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 2}).format(v) + ' FCFA';
            };

            // Étape 1 : sélection de l'apporteur -> n'afficher QUE ses commissions
            // Liste avec recherche : code, nom, courriel.
            if ($.fn.select2) {
                $('#filtreApporteur').select2({
                    placeholder: $('#filtreApporteur').data('placeholder'),
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalPaiementApp'),
                    language: { noResults: function () { return 'Aucun apporteur ne correspond'; } }
                });
            }

            $('#filtreApporteur').on('change', function () {
                var appId = $(this).val();
                $('.com-check').prop('checked', false);
                $('#toutCocher').prop('checked', false);
                if (!appId) {
                    $('#blocCommissions').hide();
                    $('.com-item').hide();
                } else {
                    $('.com-item').hide().filter('[data-apporteur-id="' + appId + '"]').show();
                    $('#blocCommissions').show();
                }
                majSelection();
            });

            // "Tout cocher" : coche/décoche toutes les commissions VISIBLES (apporteur filtré)
            $('#toutCocher').on('change', function () {
                var etat = $(this).is(':checked');
                $('.com-item:visible .com-check').prop('checked', etat);
                majSelection();
            });

            // Multi-commissions : somme des restes cochés (tous du même apporteur,
            // garanti par le filtre apporteur ci-dessus).
            function majSelection() {
                var $cochees = $('.com-check:checked');
                var n = $cochees.length;

                if (n === 0) {
                    $('#recapCom, #historiqueWrap').hide();
                    $('#montant').val('').attr('max', '').prop('readonly', false);
                    $('#montantHint').text('Multi-tranches autorisé (une seule commission cochée).');
                    return;
                }

                var somme = 0;
                $cochees.each(function () { somme += parseFloat($(this).data('reste')) || 0; });
                somme = Math.round(somme * 100) / 100;

                $('#recApp').text($cochees.first().data('apporteur'));
                $('#recCalc').text(n === 1 ? fmt($cochees.first().data('calc') || 0) : n + ' commissions');
                $('#recReste').text(fmt(somme));
                $('#recapCom').show();

                if (n === 1) {
                    // Une seule commission : tranche partielle possible + historique
                    $('#montant').attr('max', somme).val(somme).prop('readonly', false);
                    $('#montantHint').text('Multi-tranches autorisé : vous pouvez saisir un montant partiel.');
                    var id = $cochees.first().val();
                    $.getJSON('/apporteurs/commission/' + id + '/historique', function (data) {
                        if (data.error) return;
                        var $tbody = $('#historiqueBody').empty();
                        if (data.historique.length === 0) {
                            $tbody.append('<tr><td colspan="5" class="text-center text-muted">Aucun paiement précédent — ce sera la 1ère tranche.</td></tr>');
                        } else {
                            data.historique.forEach(function (h) {
                                $tbody.append('<tr>' +
                                    '<td class="text-center"><span class="badge bg-info">'+h.tranche+'</span></td>' +
                                    '<td class="text-center">'+(h.date||'-')+'</td>' +
                                    '<td class="text-end text-success"><strong>'+fmt(h.montant)+'</strong></td>' +
                                    '<td class="text-center">'+h.mode+'</td>' +
                                    '<td class="text-nowrap text-center"><a href="'+h.recu_url+'" target="_blank" class="btn btn-xs btn-info" title="Voir le reçu"><i class="material-icons md-visibility"></i></a> <a href="'+h.recu_pdf_url+'" class="btn btn-xs btn-secondary" title="Télécharger le reçu en PDF"><i class="material-icons md-picture_as_pdf"></i></a></td>' +
                                    '</tr>');
                            });
                        }
                        $('#historiqueWrap').show();
                    });
                } else {
                    // Plusieurs commissions : montant verrouillé = somme des restes
                    $('#montant').attr('max', somme).val(somme).prop('readonly', true);
                    $('#montantHint').text('Plusieurs commissions cochées : montant = somme des restes (règlement intégral).');
                    $('#historiqueWrap').hide();
                }
            }

            $(document).on('change', '.com-check', function () {
                // Garde "Tout cocher" synchronisée avec l'état réel des cases visibles
                var $visibles = $('.com-item:visible .com-check');
                $('#toutCocher').prop('checked', $visibles.length > 0 && $visibles.length === $visibles.filter(':checked').length);
                majSelection();
            });

            $('#formPaiementApp').on('submit', function (e) {
                var n = $('.com-check:checked').length;
                if (n === 0) { e.preventDefault(); alerte('Cochez au moins une commission.'); return false; }
                var m = parseFloat($('#montant').val() || 0), max = parseFloat($('#montant').attr('max') || 0);
                if (m <= 0) { e.preventDefault(); alerte('Montant > 0'); return false; }
                if (max > 0 && m > max + 0.01) { e.preventDefault(); alerte('Dépasse le reste à payer ('+fmt(max)+')'); return false; }
            });

            // Arrivée depuis l'écran des dettes : le tiers est passé en
            // paramètre, on ouvre le guichet sur lui plutôt que de laisser
            // l'agent le rechercher dans la liste.
            //
            // EN FIN D'INITIALISATION, et non au début : `trigger('change')`
            // n'a d'effet qu'une fois le gestionnaire du filtre attaché.
            // Placé plus haut, le tiers était bien sélectionné et le
            // formulaire s'ouvrait, mais la liste de ses pièces restait vide.
            //
            // L'ouverture passe par un clic sur le bouton existant : la
            // version de Bootstrap embarquée ici n'expose pas
            // Modal.getOrCreateInstance, et l'appeler ne faisait rien.
            var tierARegler = new URLSearchParams(window.location.search).get('regler');

            if (tierARegler) {
                var $tiers = $('#filtreApporteur');

                if ($tiers.find('option[value="' + tierARegler + '"]').length) {
                    $tiers.val(tierARegler).trigger('change');
                    $('[data-bs-target="#modalPaiementApp"]').first().trigger('click');
                } else {
                    // Plus rien à payer pour ce tiers : le dire, plutôt que
                    // d'ouvrir un formulaire vide sans explication.
                    $('.content-header').after(
                        '<div class="alert alert-info">Ce tiers n\'a plus de pièce en attente de règlement.</div>'
                    );
                }
            }
        });
    </script>
@endsection
