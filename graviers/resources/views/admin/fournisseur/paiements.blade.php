@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Paiements fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Journal des paiements fournisseurs</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaiementFourn">
                <i class="material-icons md-add"></i> Enregistrer un paiement fournisseur
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

    {{-- ===== DEMANDES INITIÉES PAR LES FOURNISSEURS =====
         Elles vivent dans une autre table que les règlements ci-dessous : un
         règlement porte sur un bon précis, une demande porte sur un montant.
         Elles n'apparaissaient donc nulle part ici, alors que c'est sur cet
         écran que l'administrateur suit ce qu'il doit aux fournisseurs. --}}
    @if (($demandesFournisseurs ?? collect())->isNotEmpty())
        <div class="card mb-4 border-warning">
            <header class="card-header bg-warning-subtle">
                <p class="d-flex justify-content-between align-items-center mb-0">
                    <span class="h5 mb-0">
                        <i class="material-icons md-outbox"></i>
                        Demandes de paiement initiées par les fournisseurs
                    </span>
                    <a href="{{ route('show.listeDeDemandeFournisseur') }}" class="btn btn-sm btn-warning">
                        Traiter les demandes
                    </a>
                </p>
            </header>

            <div class="card-body">
                <p class="text-muted small">
                    Le montant demandé est <strong>déjà retenu sur le solde du fournisseur</strong> :
                    il est réservé, pas encore versé. Le versement demande
                    <strong>deux validations par deux administrateurs différents</strong> —
                    celui qui donne la première ne peut pas donner la seconde.
                </p>

                <div class="table-responsive">
                    <table class="table table-striped" id="demandesFournisseurs">
                        <thead style="background-color: #b8860b; color: white;">
                            <tr>
                                <th class="text-center">Date</th>
                                <th class="text-center">Code Fourn.</th>
                                <th>Fournisseur</th>
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
                            @foreach ($demandesFournisseurs as $d)
                                <tr>
                                    <td class="text-center">
                                        {{ $d->date ? Carbon::parse($d->date)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="text-center">{{ $d->code_fournisseur }}</td>
                                    <td>{{ $d->fournisseur_nom }}</td>
                                    <td class="text-end">{{ Help::formatNombre($d->montant, true) }}</td>
                                    <td class="text-center">{{ $d->mode_paiement }}</td>
                                    <td class="text-center">{{ $d->numero_compte ?: '-' }}</td>
                                    <td class="text-center">
                                        {{-- Ce qui distingue ces lignes des règlements ci-dessous :
                                             la demande vient du fournisseur, pas d'un agent. --}}
                                        <span class="badge bg-info">Le fournisseur</span>
                                        <br><small class="text-muted">{{ $d->initie_par }}</small>
                                    </td>
                                    <td class="text-center small">{{ $d->valide_par_1 }}</td>
                                    <td class="text-center small">{{ $d->valide_par_2 }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $d->couleur_etat }}">{{ $d->etat }}</span>
                                    </td>
                                    <td class="text-center text-nowrap">
                                        {{-- Mêmes règles que l'écran « Demandes de paiement » :
                                             deux validations, par deux administrateurs
                                             différents. Le serveur les fait respecter de
                                             toute façon ; on n'affiche ici que les boutons
                                             qu'il acceptera. --}}
                                        @php
                                            $lienValidation = fn ($reponse) => route('show.valideDemande', [
                                                'id'      => $d->id,
                                                'type'    => 'fournisseur',
                                                'reponse' => $reponse,
                                                // Pour revenir ici, et non sur l'autre écran.
                                                'retour'  => 'show.fournisseurs.paiements',
                                            ]);
                                        @endphp

                                        @if ($d->finalisee)
                                            <span class="text-muted small">—</span>
                                        @elseif (!$d->peut_valider)
                                            <span class="text-muted small"><em>Réservé aux administrateurs</em></span>
                                        @elseif ($d->attend_1re)
                                            <a href="{{ $lienValidation('accepter') }}"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Donner la 1re validation à cette demande ?');">
                                                <i class="material-icons md-check"></i> 1re validation
                                            </a>
                                        @elseif ($d->attend_2e && $d->est_initiateur)
                                            <span class="text-muted small">
                                                <em>En attente d'un autre administrateur</em>
                                            </span>
                                        @elseif ($d->attend_2e)
                                            <a href="{{ $lienValidation('accepter') }}"
                                               class="btn btn-sm btn-success"
                                               onclick="return confirm('Accepter et payer cette demande ?');">
                                                <i class="material-icons md-check"></i> 2e validation
                                            </a>
                                            <a href="{{ $lienValidation('refuser') }}"
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Refuser cette demande ? Le montant sera restitué au solde du fournisseur.');">
                                                <i class="material-icons md-denied"></i> Rejeter
                                            </a>
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
            <x-export-buttons table-id="liste" filename="paiements-fournisseurs" title="Paiements fournisseurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date Paiement</th>
                            <th class="text-center">N° Bon Enlèvement</th>
                            <th class="text-center">Code Fourn.</th>
                            <th class="text-center">Fournisseur</th>
                            <th class="text-end">Montant Payé</th>
                            <th class="text-center">Mode de paiement</th>
                            <th class="text-center">Référence</th>
                            <th>Notes</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">Reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->numero_be }}</td>
                                <td class="text-center">{{ $l->code_fournisseur }}</td>
                                <td>
                                    {{ $l->fournisseur_nom }}
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
                                <td class="text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.fournisseurs.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement fournisseur ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                <i class="material-icons md-check_circle"></i> Valider
                                            </button>
                                        </form>
                                    @elseif (!($l->en_attente ?? false))
                                        <a href="{{ route('show.fournisseurs.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.fournisseurs.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @else
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted">
                                    Aucun paiement enregistré.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($lignes->count() > 0)
                        <tfoot style="background-color: #f0f0f0; font-weight: bold;">
                            <tr>
                                <td colspan="4" class="text-end">TOTAL</td>
                                <td class="text-end text-success">{{ Help::formatNombre($totalPaye, true) }}</td>
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Modal d'enregistrement de paiement fournisseur --}}
    <div class="modal fade" id="modalPaiementFourn" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.fournisseurs.paiements.store') }}" id="formPaiementFourn">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="material-icons md-payments"></i> Enregistrer un paiement fournisseur
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Même fonctionnement que l'écran des paiements de commission :
                                 étape 1, on choisit le FOURNISSEUR ; étape 2, on coche un ou
                                 plusieurs de ses bons. L'ancien formulaire n'acceptait qu'un
                                 bon à la fois, ce qui obligeait à autant de saisies que de
                                 bons pour un même fournisseur. --}}
                            @php
                                $fournisseursNonSoldes = collect($enlevementsNonSoldes)
                                    ->groupBy('fournisseur_id')
                                    ->map(function ($bons) {
                                        return (object) [
                                            'fournisseur_id'  => $bons->first()->fournisseur_id,
                                            'fournisseur_nom' => $bons->first()->fournisseur_nom,
                                            'nb'              => $bons->count(),
                                            'total_reste'     => $bons->sum('reste'),
                                        ];
                                    })->sortBy('fournisseur_nom')->values();
                            @endphp

                            {{-- Étape 1 : le fournisseur --}}
                            <div class="col-md-12">
                                <label for="filtreFournisseur" class="form-label fw-bold">Fournisseur <span class="text-danger">*</span></label>
                                <select class="form-control" id="filtreFournisseur">
                                    <option value="">— Sélectionner un fournisseur —</option>
                                    @foreach ($fournisseursNonSoldes as $f)
                                        <option value="{{ $f->fournisseur_id }}">
                                            {{ $f->fournisseur_nom }} — {{ $f->nb }} bon(s) non soldé(s) — Total : {{ Help::formatNombre($f->total_reste, true) }}
                                        </option>
                                    @endforeach
                                </select>
                                @if (collect($enlevementsNonSoldes)->isEmpty())
                                    <small class="text-warning">Aucun bon d'enlèvement n'est en attente de paiement.</small>
                                @endif
                            </div>

                            {{-- Étape 2 : ses bons d'enlèvement --}}
                            <div class="col-md-12" id="blocBons" style="display:none;">
                                <label class="form-label fw-bold">Bons d'enlèvement à payer <span class="text-danger">*</span></label>
                                <small class="d-block text-muted mb-1">Cochez un ou plusieurs bons :
                                    un seul = paiement par tranches possible ; plusieurs = règlement intégral de la somme.</small>
                                <div class="form-check border-bottom pb-1 mb-1">
                                    <input class="form-check-input" type="checkbox" id="toutCocher">
                                    <label class="form-check-label fw-bold" for="toutCocher">Tout cocher (payer tous les bons de ce fournisseur)</label>
                                </div>
                                <div id="listeBons" class="border rounded p-2" style="max-height:220px; overflow-y:auto;">
                                    @foreach ($enlevementsNonSoldes as $e)
                                        <div class="form-check bon-item" data-fournisseur-id="{{ $e->fournisseur_id }}" style="display:none;">
                                            <input class="form-check-input bon-check" type="checkbox"
                                                   name="enlevement_ids[]" value="{{ $e->id }}"
                                                   id="bon{{ $e->id }}"
                                                   data-fournisseur-id="{{ $e->fournisseur_id }}"
                                                   data-fournisseur="{{ $e->fournisseur_nom }}"
                                                   data-code-fournisseur="{{ $e->code_fournisseur }}"
                                                   data-produit="{{ $e->produit }}"
                                                   data-ttc="{{ $e->montant_ttc }}"
                                                   data-reste="{{ $e->reste }}">
                                            <label class="form-check-label" for="bon{{ $e->id }}">
                                                {{ $e->code_be }} — {{ $e->produit }}
                                                — Reste : <strong>{{ Help::formatNombre($e->reste, true) }}</strong>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Récap --}}
                            <div class="col-md-12" id="recapEnlevement" style="display:none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <small class="text-muted">Fournisseur</small><br>
                                                <strong id="recFournisseur">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Total dû</small><br>
                                                <strong class="text-primary" id="recTtc">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Déjà payé</small><br>
                                                <strong class="text-success" id="recPaye">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Reste à payer</small><br>
                                                <strong class="text-danger" id="recReste">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Historique --}}
                            <div class="col-md-12" id="historiqueWrap" style="display:none;">
                                <h6 class="mt-2"><i class="material-icons md-history"></i> Historique des paiements</h6>
                                <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#1c57a3; color:#fff;">
                                            <tr>
                                                <th class="text-center">Bon</th>
                                                <th class="text-center">Tranche</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-center">Mode</th>
                                                <th class="text-center">Référence</th>
                                                <th class="text-center">Reçu</th>
                                            </tr>
                                        </thead>
                                        <tbody id="historiqueBody"></tbody>
                                    </table>
                                </div>
                            </div>

                            <hr class="my-2">

                            <div class="col-md-6">
                                <label for="date_paiement" class="form-label">Date paiement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_paiement" name="date_paiement" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant à payer (FCFA) <span class="text-danger">*</span></label>
                                {{-- step 0.01 comme pour les commissions : un reste à payer peut
                                     comporter des décimales, step=1 le refusait. --}}
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="0.01" required>
                                <small class="text-muted" id="montantHint">Multi-tranches autorisé (un seul bon coché).</small>
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
                                <input type="text" class="form-control" id="reference" name="reference" placeholder="Ex: VIR20260415, OM-12345...">
                            </div>
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Ex: Acompte 50%, solde..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer & générer le bordereau
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        // Construite par Laravel : une URL écrite en dur casserait si
        // l'application était servie depuis un sous-dossier.
        var RACINE_HISTORIQUE_BON = '{{ url('/fournisseurs/enlevement') }}';

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

            // Les demandes des fournisseurs : recherche, pagination, 5 lignes.
            var $demandes = $('#demandesFournisseurs');
            if ($demandes.length &&
                $demandes.find('tbody tr').length > 0 &&
                $demandes.find('tbody tr td[colspan]').length === 0) {
                $demandes.DataTable({
                    // Garde-fou du projet : sans defaultContent, DataTables lève
                    // « Requested unknown parameter » dès qu'une cellule manque.
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' },
                        // La colonne des boutons ne se trie ni ne se cherche.
                        { targets: -1, orderable: false, searchable: false },
                    ],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, 'Tout']],
                    // Le tri vient du serveur (de la plus récente à la plus
                    // ancienne) : on ne le rejoue pas ici, la date étant au
                    // format jj/mm/aaaa que DataTables trierait comme du texte.
                    order: [],
                });
            }

            var fmt = function (n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            };

            // Étape 1 : le fournisseur choisi filtre la liste de SES bons.
            $('#filtreFournisseur').on('change', function () {
                var fournId = $(this).val();
                $('.bon-check').prop('checked', false);
                $('#toutCocher').prop('checked', false);
                if (!fournId) {
                    $('#blocBons').hide();
                    $('.bon-item').hide();
                } else {
                    $('.bon-item').hide().filter('[data-fournisseur-id="' + fournId + '"]').show();
                    $('#blocBons').show();
                }
                majSelection();
            });

            // « Tout cocher » : agit sur les bons VISIBLES, donc ceux du fournisseur choisi.
            $('#toutCocher').on('change', function () {
                var etat = $(this).is(':checked');
                $('.bon-item:visible .bon-check').prop('checked', etat);
                majSelection();
            });

            // Récapitulatif : somme des restes cochés (tous du même fournisseur,
            // garanti par le filtre ci-dessus).
            function majSelection() {
                var $cochees = $('.bon-check:checked');
                var n = $cochees.length;

                if (n === 0) {
                    $('#recapEnlevement, #historiqueWrap').hide();
                    $('#montant').val('').attr('max', '').prop('readonly', false);
                    $('#montantHint').text("Multi-tranches autorisé (un seul bon coché).");
                    return;
                }

                var somme = 0, ttc = 0;
                $cochees.each(function () {
                    somme += parseFloat($(this).data('reste')) || 0;
                    ttc   += parseFloat($(this).data('ttc')) || 0;
                });
                somme = Math.round(somme * 100) / 100;

                $('#recFournisseur').text($cochees.first().data('fournisseur'));
                $('#recTtc').text(n === 1 ? fmt(ttc) : n + ' bons — ' + fmt(ttc));
                $('#recPaye').text(fmt(Math.max(0, ttc - somme)));
                $('#recReste').text(fmt(somme));
                $('#recapEnlevement').show();

                if (n === 1) {
                    // Un seul bon : le règlement par tranches reste possible.
                    $('#montant').attr('max', somme).val(somme).prop('readonly', false);
                    $('#montantHint').text('Multi-tranches autorisé : vous pouvez saisir un montant partiel.');
                } else {
                    // Plusieurs bons : montant verrouillé sur la somme des restes.
                    $('#montant').attr('max', somme).val(somme).prop('readonly', true);
                    $('#montantHint').text('Plusieurs bons cochés : montant = somme des restes (règlement intégral).');
                }

                // L'historique s'affiche dans TOUS les cas. Il ne se montrait
                // qu'avec un seul bon coché : dès qu'on en cochait plusieurs —
                // le cas courant pour solder un fournisseur — il disparaissait,
                // et avec lui les reçus des règlements déjà passés.
                chargerHistoriqueBons($cochees);
            }

            // L'historique de CHAQUE bon coché, avec son reçu : voir et
            // télécharger le justificatif du paiement fait en agence.
            function chargerHistoriqueBons($cochees) {
                var $tbody = $('#historiqueBody').empty();
                var restant = $cochees.length;
                var totalPaye = 0;
                var totalReste = 0;
                var aucune = true;

                $cochees.each(function () {
                    var id = $(this).val();
                    var libelle = $('label[for="bon' + id + '"]').text().trim().split(' \u2014 ')[0];

                    $.getJSON(RACINE_HISTORIQUE_BON + '/' + id + '/historique', function (data) {
                        if (!data || data.error) { return; }

                        totalPaye  += parseFloat(data.enlevement.montant_paye) || 0;
                        totalReste += parseFloat(data.enlevement.reste_a_payer) || 0;

                        (data.historique || []).forEach(function (h) {
                            aucune = false;
                            $tbody.append(
                                '<tr>' +
                                '<td class="text-center">' + libelle + '</td>' +
                                '<td class="text-center"><span class="badge bg-info">' + h.tranche + '</span></td>' +
                                '<td class="text-center">' + (h.date || '-') + '</td>' +
                                '<td class="text-end text-success"><strong>' + fmt(h.montant) + '</strong></td>' +
                                '<td class="text-center">' + (h.mode || '-') + '</td>' +
                                '<td class="text-center">' + (h.reference || '-') + '</td>' +
                                '<td class="text-center">' +
                                    '<a href="' + h.recu_url + '" target="_blank" class="btn btn-sm btn-info" title="Voir le recu">' +
                                        '<i class="material-icons md-visibility"></i></a> ' +
                                    '<a href="' + h.recu_pdf_url + '" class="btn btn-sm btn-secondary" title="Telecharger le recu">' +
                                        '<i class="material-icons md-picture_as_pdf"></i></a>' +
                                '</td>' +
                                '</tr>'
                            );
                        });
                    }).always(function () {
                        restant--;

                        if (restant === 0) {
                            if (aucune) {
                                $tbody.append('<tr><td colspan="7" class="text-center text-muted">' +
                                    'Aucun paiement precedent sur ces bons.</td></tr>');
                            }

                            $('#recPaye').text(fmt(totalPaye));
                            $('#recReste').text(fmt(totalReste));

                            if ($cochees.length === 1) {
                                $('#montant').attr('max', totalReste).val(totalReste);
                            }

                            $('#historiqueWrap').show();
                        }
                    });
                });
            }

            $(document).on('change', '.bon-check', function () {
                var $visibles = $('.bon-item:visible .bon-check');
                $('#toutCocher').prop('checked', $visibles.length > 0 && $visibles.length === $visibles.filter(':checked').length);
                majSelection();
            });

            $('#formPaiementFourn').on('submit', function (e) {
                var n = $('.bon-check:checked').length;
                if (n === 0) { e.preventDefault(); alert("Cochez au moins un bon d'enlèvement."); return false; }
                var montant = parseFloat($('#montant').val() || 0);
                var max = parseFloat($('#montant').attr('max') || 0);
                if (montant <= 0) { e.preventDefault(); alert('Le montant doit être supérieur à 0.'); return false; }
                if (max > 0 && montant > max + 0.01) { e.preventDefault(); alert('Le montant dépasse le reste à payer (' + fmt(max) + ').'); return false; }
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
                var $tiers = $('#filtreFournisseur');

                if ($tiers.find('option[value="' + tierARegler + '"]').length) {
                    $tiers.val(tierARegler).trigger('change');
                    $('[data-bs-target="#modalPaiementFourn"]').first().trigger('click');
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
