@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Paiements livreurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Paiements livreurs</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPaiementLivreur">
                <i class="material-icons md-add"></i> Enregistrer un paiement livreur
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

    <div class="alert alert-info">
        <i class="material-icons md-info"></i>
        Le livreur est payé par <strong>deux chemins</strong> : la demande qu'il envoie depuis
        l'application mobile, validée par deux administrateurs, et le règlement enregistré ici
        sur une de ses courses. Les deux alimentent le même compte : une course déjà payée par
        une demande n'apparaît plus dans la liste ci-dessous.
    </div>

    {{-- ===== LES DEMANDES ENVOYÉES PAR LES LIVREURS ===== --}}
    <div class="card mb-4">
        <header class="card-header">
            <p class="d-flex justify-content-between align-items-center mb-0">
                <span class="h5 mb-0">Demandes de paiement des livreurs</span>
                <span class="text-success h5 mb-0">Total payé : {{ number_format($totalPaye, 0, '', ' ') }} fcfa</span>
            </p>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="demandes-paiement-livreurs" title="Demandes de paiement livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center" style="color:#fff;">Livreur</th>
                            <th class="text-center" style="color:#fff;">N° demande</th>
                            <th class="text-center" style="color:#fff;">Montant</th>
                            <th class="text-center" style="color:#fff;">Mode</th>
                            <th class="text-center" style="color:#fff;">N° compte / Tél</th>
                            <th class="text-center" style="color:#fff;">Date demande</th>
                            <th class="text-center" style="color:#fff;">1re validation</th>
                            <th class="text-center" style="color:#fff;">2e validation</th>
                            <th class="text-center" style="color:#fff;">Statut</th>
                            <th class="text-center" style="color:#fff;">Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paiements as $p)
                            <tr>
                                <td class="text-center">{{ $p->livreur_nom }}</td>
                                <td class="text-center">{{ $p->numero }}</td>
                                <td class="text-center">{{ Help::formatNombre($p->montant, true) }}</td>
                                <td class="text-center">{{ $p->mode }}</td>
                                <td class="text-center">{{ $p->numero_compte ?: '-' }}</td>
                                <td class="text-center">{{ $p->date ? Carbon::parse($p->date)->format('d/m/Y H:i') : '-' }}</td>
                                <td class="text-center small">{{ $p->valide_par_1 }}</td>
                                <td class="text-center small">{{ $p->valide_par_2 }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $p->couleur_etat }}">{{ $p->etat }}</span>
                                </td>
                                <td class="text-center text-nowrap">
                                    {{-- Mêmes règles que l'écran « Demandes de paiement » :
                                         deux validations, par deux administrateurs
                                         différents. Le serveur les fait respecter de toute
                                         façon ; on n'affiche que les boutons qu'il
                                         acceptera. --}}
                                    @php
                                        $lienValidation = fn ($reponse) => route('show.valideDemande', [
                                            'id'      => $p->id,
                                            'type'    => 'livreur',
                                            'reponse' => $reponse,
                                            // Pour revenir ici, et non sur l'autre écran.
                                            'retour'  => 'show.livreurs.paiements',
                                        ]);
                                    @endphp

                                    @if ($p->finalisee)
                                        <span class="text-muted small">—</span>
                                    @elseif (!$p->peut_valider)
                                        <span class="text-muted small"><em>Réservé aux administrateurs</em></span>
                                    @elseif ($p->attend_1re)
                                        <a href="{{ $lienValidation('accepter') }}"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Donner la 1re validation à cette demande ?');">
                                            <i class="material-icons md-check"></i> 1re validation
                                        </a>
                                    @elseif ($p->attend_2e && $p->est_initiateur)
                                        <span class="text-muted small">
                                            <em>En attente d'un autre administrateur</em>
                                        </span>
                                    @elseif ($p->attend_2e)
                                        <a href="{{ $lienValidation('accepter') }}"
                                           class="btn btn-sm btn-success"
                                           onclick="return confirm('Accepter et payer cette demande ?');">
                                            <i class="material-icons md-check"></i> 2e validation
                                        </a>
                                        <a href="{{ $lienValidation('refuser') }}"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Refuser cette demande ? Le montant sera restitué au solde du livreur.');">
                                            <i class="material-icons md-denied"></i> Rejeter
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">Aucune demande de paiement.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== LE JOURNAL DES RÈGLEMENTS ===== --}}
    <div class="card mb-4">
        <header class="card-header">
            <p class="d-flex justify-content-between align-items-center mb-0">
                <span class="h5 mb-0">Règlements enregistrés — {{ $reglements->count() }}</span>
                <span class="text-success h5 mb-0">Total validé : {{ Help::formatNombre($totalReglements, true) }}</span>
            </p>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="reglements" filename="reglements-livreurs" title="Règlements livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="reglements">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date</th>
                            <th class="text-center">N° course</th>
                            <th class="text-center">Code livreur</th>
                            <th>Livreur</th>
                            <th class="text-end">Montant</th>
                            <th class="text-center">Mode</th>
                            <th class="text-center">Référence</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reglements as $l)
                            <tr @if($l->en_attente) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->numero_liv }}</td>
                                <td class="text-center">{{ $l->code_livreur }}</td>
                                <td>
                                    {{ $l->livreur_nom }}
                                    @if ($l->vient_demande)
                                        {{-- Ce règlement n'a pas été saisi ici : il découle d'une
                                             demande validée. Le dire évite de le prendre pour
                                             une double saisie. --}}
                                        <br><span class="badge bg-info">Issu d'une demande</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ Help::formatNombre($l->montant, true) }}</td>
                                <td class="text-center">{{ $l->mode_paiement }}</td>
                                <td class="text-center">{{ $l->reference ?: '-' }}</td>
                                <td class="text-center">{{ $l->initie_par }}</td>
                                <td class="text-center">{{ $l->valide_par }}</td>
                                <td class="text-center text-nowrap">
                                    @if ($l->peut_valider)
                                        <form action="{{ route('show.livreurs.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement livreur ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                <i class="material-icons md-check_circle"></i> Valider
                                            </button>
                                        </form>
                                    @elseif (!$l->en_attente)
                                        <a href="{{ route('show.livreurs.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.livreurs.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @else
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">Aucun règlement enregistré.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== FORMULAIRE D'ENREGISTREMENT ===== --}}
    <div class="modal fade" id="modalPaiementLivreur" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.livreurs.paiements.store') }}">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="material-icons md-payments"></i> Enregistrer un paiement livreur
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            @php
                                // Le livreur d'abord, ses courses ensuite : le même
                                // fonctionnement que les écrans fournisseur et apporteur.
                                $livreursNonSoldes = collect($coursesNonSoldees)
                                    ->groupBy('livreur_id')
                                    ->map(fn ($courses) => (object) [
                                        'livreur_id'  => $courses->first()->livreur_id,
                                        'livreur_nom' => $courses->first()->livreur_nom,
                                        'nb'          => $courses->count(),
                                        'total_reste' => $courses->sum('reste'),
                                    ])->sortBy('livreur_nom')->values();
                            @endphp

                            <div class="col-md-12">
                                <label for="filtreLivreur" class="form-label fw-bold">
                                    Livreur <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="filtreLivreur">
                                    <option value="">— Sélectionner un livreur —</option>
                                    @foreach ($livreursNonSoldes as $lv)
                                        <option value="{{ $lv->livreur_id }}">
                                            {{ $lv->livreur_nom }} — {{ $lv->nb }} course(s) non soldée(s)
                                            — Total : {{ Help::formatNombre($lv->total_reste, true) }}
                                        </option>
                                    @endforeach
                                </select>
                                @if (collect($coursesNonSoldees)->isEmpty())
                                    <small class="text-warning">Aucune course n'est en attente de paiement.</small>
                                @endif
                            </div>

                            <div class="col-md-12" id="blocCourses" style="display:none;">
                                <label class="form-label fw-bold">Courses à payer <span class="text-danger">*</span></label>
                                <small class="d-block text-muted mb-1">
                                    Cochez une ou plusieurs courses : une seule = paiement par tranches possible ;
                                    plusieurs = règlement intégral de la somme.
                                </small>
                                <div class="form-check border-bottom pb-1 mb-1">
                                    <input class="form-check-input" type="checkbox" id="toutCocherCourses">
                                    <label class="form-check-label fw-bold" for="toutCocherCourses">
                                        Tout cocher (payer toutes les courses de ce livreur)
                                    </label>
                                </div>
                                <div class="border rounded p-2" style="max-height:220px; overflow-y:auto;">
                                    @foreach ($coursesNonSoldees as $c)
                                        <div class="form-check course-item" data-livreur-id="{{ $c->livreur_id }}" style="display:none;">
                                            <input class="form-check-input course-check" type="checkbox"
                                                   name="livraison_ids[]" value="{{ $c->id }}"
                                                   id="course{{ $c->id }}"
                                                   data-livreur-id="{{ $c->livreur_id }}"
                                                   data-reste="{{ $c->reste }}">
                                            <label class="form-check-label" for="course{{ $c->id }}">
                                                {{ $c->numero_liv }}
                                                @if ($c->date) — {{ Carbon::parse($c->date)->format('d/m/Y') }} @endif
                                                — Reste : <strong>{{ Help::formatNombre($c->reste, true) }}</strong>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Récapitulatif de ce qui est coché --}}
                            <div class="col-md-12" id="recapCourse" style="display:none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <small class="text-muted">Livreur</small><br>
                                                <strong id="recLivreur">-</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Déjà payé</small><br>
                                                <strong class="text-success" id="recPayeLiv">-</strong>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">Reste à payer</small><br>
                                                <strong class="text-danger" id="recResteLiv">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Historique des règlements déjà passés sur ces courses,
                                 avec le reçu de chacun : c'est ce qui permet de voir,
                                 AVANT de saisir, ce qui a déjà été versé. --}}
                            <div class="col-md-12" id="historiqueWrapLiv" style="display:none;">
                                <h6 class="mt-2"><i class="material-icons md-history"></i> Historique des paiements</h6>
                                <div class="table-responsive" style="max-height:200px; overflow-y:auto;">
                                    <table class="table table-sm table-bordered">
                                        <thead style="background:#1c57a3; color:#fff;">
                                            <tr>
                                                <th class="text-center">Course</th>
                                                <th class="text-center">Tranche</th>
                                                <th class="text-center">Date</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-center">Mode</th>
                                                <th class="text-center">Référence</th>
                                                <th class="text-center">Reçu</th>
                                            </tr>
                                        </thead>
                                        <tbody id="historiqueBodyLiv"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="date_paiement" class="form-label fw-bold">Date paiement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_paiement" id="date_paiement"
                                       value="{{ now()->toDateString() }}" required>
                            </div>

                            <div class="col-md-6">
                                <label for="montantLivreur" class="form-label fw-bold">Montant à payer (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" class="form-control" name="montant" id="montantLivreur" required>
                                <small class="text-muted">Règlement par tranches autorisé quand une seule course est cochée.</small>
                            </div>

                            <div class="col-md-6">
                                <label for="mode_paiement_id" class="form-label fw-bold">Mode de paiement <span class="text-danger">*</span></label>
                                <select class="form-control" name="mode_paiement_id" id="mode_paiement_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($modesPaiement as $mode)
                                        <option value="{{ $mode->id }}">{{ $mode->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="reference" class="form-label fw-bold">Référence transaction</label>
                                <input type="text" class="form-control" name="reference" id="reference"
                                       placeholder="Ex: VIR20260415, OM-12345…">
                            </div>

                            <div class="col-md-12">
                                <label for="notes" class="form-label fw-bold">Notes</label>
                                <textarea class="form-control" name="notes" id="notes" rows="2"
                                          placeholder="Ex: Acompte 50%, solde…"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer le paiement
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
        // Construite par Laravel : une URL ecrite en dur casserait si
        // l'application etait servie depuis un sous-dossier.
        var RACINE_HISTORIQUE = '{{ url('/livreurs/livraison') }}';

        $(function () {

            // Garde-fou du projet : DataTables lève « Requested unknown parameter »
            // quand le tableau ne contient que la ligne « aucun résultat », dont le
            // colspan ne correspond à aucune colonne déclarée.
            ['#liste', '#reglements'].forEach(function (id) {
                var $t = $(id);
                if ($t.length &&
                    $t.find('tbody tr').length > 0 &&
                    $t.find('tbody tr td[colspan]').length === 0) {
                    $t.DataTable({
                        columnDefs: [{ targets: '_all', defaultContent: '-' }],
                        language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    });
                }
            });

            // Étape 1 : le livreur choisi filtre la liste de SES courses.
            $('#filtreLivreur').on('change', function () {
                var id = $(this).val();
                $('.course-check').prop('checked', false);
                $('#toutCocherCourses').prop('checked', false);
                $('#montantLivreur').val('');

                $('#recapCourse, #historiqueWrapLiv').hide();

                if (!id) {
                    $('#blocCourses').hide();
                    $('.course-item').hide();
                } else {
                    $('.course-item').hide().filter('[data-livreur-id="' + id + '"]').show();
                    $('#blocCourses').show();
                }
            });

            $('#toutCocherCourses').on('change', function () {
                var id = $('#filtreLivreur').val();
                $('.course-check[data-livreur-id="' + id + '"]').prop('checked', this.checked);
                recalculer();
            });

            $(document).on('change', '.course-check', function () {
                recalculer();
            });

            function fmt(n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            }

            // Le montant suit ce qui est coché : la somme des restes. L'agent
            // peut le réduire s'il ne coche qu'une course (paiement par tranches).
            function recalculer() {
                var $cochees = $('.course-check:checked');
                var total = 0;

                $cochees.each(function () {
                    total += parseFloat($(this).data('reste')) || 0;
                });

                $('#montantLivreur').val(total > 0 ? Math.round(total) : '');

                if ($cochees.length === 0) {
                    $('#recapCourse, #historiqueWrapLiv').hide();
                    $('#montantLivreur').prop('readonly', false);
                    return;
                }

                $('#recLivreur').text($('#filtreLivreur option:selected').text().trim().split(' \u2014 ')[0]);
                $('#recResteLiv').text(fmt(total));
                $('#recapCourse').show();

                // Plusieurs courses : le montant est verrouille sur la somme des
                // restes, un partage arbitraire n'aurait pas de sens.
                $('#montantLivreur').prop('readonly', $cochees.length > 1);

                chargerHistorique($cochees);
            }

            // L'historique de CHAQUE course cochee, avec son recu. Il etait
            // absent du formulaire : rien ne permettait de verifier, avant de
            // saisir, ce qui avait deja ete verse sur ces courses.
            function chargerHistorique($cochees) {
                var $tbody = $('#historiqueBodyLiv').empty();
                var restant = $cochees.length;
                var totalPaye = 0;
                var aucune = true;

                $cochees.each(function () {
                    var id = $(this).val();
                    var libelle = ($('label[for="course' + id + '"]').text().trim().split(' \u2014 ')[0]) || id;

                    $.getJSON(RACINE_HISTORIQUE + '/' + id + '/historique', function (data) {
                        if (!data || data.error) { return; }

                        totalPaye += parseFloat(data.livraison.montant_paye) || 0;

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
                                    'Aucun paiement precedent sur ces courses.</td></tr>');
                            }
                            $('#recPayeLiv').text(fmt(totalPaye));
                            $('#historiqueWrapLiv').show();
                        }
                    });
                });
            }

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
                var $tiers = $('#filtreLivreur');

                if ($tiers.find('option[value="' + tierARegler + '"]').length) {
                    $tiers.val(tierARegler).trigger('change');
                    $('[data-bs-target="#modalPaiementLivreur"]').first().trigger('click');
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
