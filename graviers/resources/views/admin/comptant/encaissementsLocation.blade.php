@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Encaissements — locations')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Encaissements en agence — locations</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalEncaissementLocation">
                <i class="material-icons md-add"></i> Enregistrer un paiement en agence
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    {{-- Clé propre : le projet utilise Flasher avec flash_bag activé, qui capte
         « error » avant la vue et le rejoue en bulle flottante. Un refus
         d'encaissement — montant supérieur au reste dû, caissier sans agence —
         doit rester sous les yeux du caissier, sinon il croit avoir encaissé. --}}
    @if (session('erreur_caisse'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('erreur_caisse') }}
        </div>
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

    {{-- Rappel de la règle, pour que le caissier comprenne pourquoi une location
         reste bloquée côté gestionnaire tant qu'il n'a pas encaissé. --}}
    <div class="alert alert-info">
        <i class="material-icons md-info align-middle"></i>
        Une location réglée en agence ne peut être <strong>validée</strong> qu'une fois
        <strong>entièrement soldée</strong>. Un encaissement ne compte qu'après sa validation par un
        second administrateur.
    </div>

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
            <x-export-buttons table-id="liste" filename="encaissements-locations" title="Encaissements locations" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date Encaissement</th>
                            <th class="text-center">N° Location</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Agence</th>
                            <th class="text-end">Montant Encaissé</th>
                            <th class="text-center">Mode</th>
                            <th class="text-center">Caissier</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">Reçu N°</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_encaissement ? Carbon::parse($l->date_encaissement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->numero_location }}</td>
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
                                <td class="text-center small">{{ $l->initie_par ?? '-' }}</td>
                                <td class="text-center small">{{ $l->valide_par ?? '-' }}</td>
                                <td class="text-center">{{ $l->numero_recu ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.encaissements.locations.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation de l'encaissement"
                                              data-confirm-text="Confirmez-vous la validation de l'encaissement {{ $l->numero_recu }} ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider l'encaissement">
                                                <i class="material-icons md-check_circle"></i> Valider
                                            </button>
                                        </form>
                                    @endif
                                    @if (($l->paiement_id ?? null) && !($l->en_attente ?? false))
                                        <a href="{{ route('show.recu', $l->paiement_id) }}" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="Télécharger PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @elseif (($l->paiement_id ?? null) && ($l->en_attente ?? false) && !($l->peut_valider ?? false))
                                        {{-- On indique POURQUOI le bouton est absent : sans cela,
                                             un gestionnaire et l'auteur de la saisie voyaient le même
                                             message et cherchaient tous deux un bouton inexistant. --}}
                                        <span class="text-muted small"><em>{{ $l->raison_blocage ?? "En attente d'un autre administrateur." }}</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted">
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
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODAL : Encaissement en agence d'une location
         ============================================================ --}}
    <div class="modal fade" id="modalEncaissementLocation" tabindex="-1" aria-labelledby="modalEncaissementLocationLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.encaissements.locations.store') }}" id="formEncaissementLocation">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalEncaissementLocationLabel">
                            <i class="material-icons md-payments"></i> Encaissement en agence
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="numero_location" class="form-label fw-bold">Location à encaisser <span class="text-danger">*</span></label>
                                <select class="form-control" name="numero_location" id="numero_location" required>
                                    <option value="">— Sélectionner une location —</option>
                                    @foreach ($locationsNonSoldees as $d)
                                        {{-- Présélection quand on arrive depuis la liste des
                                             locations (« Encaisser » -> ?location=NUMERO). --}}
                                        <option value="{{ $d->numero }}" @selected(request('location') == $d->numero)
                                            data-client="{{ $d->client_nom }}"
                                            data-materiel="{{ $d->materiel }}"
                                            data-aterme="{{ $d->client_aterme ? 1 : 0 }}"
                                            data-total="{{ $d->total_a_payer }}"
                                            data-reste="{{ $d->encaissable }}">
                                            {{ $d->numero }} — {{ $d->client_nom }}@if ($d->client_aterme) [CLIENT À TERME]@endif — Encaissable : {{ Help::formatNombre($d->encaissable, true) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Locations réglées EN AGENCE et non soldées. Celles payées en ligne
                                    n'apparaissent pas : elles sont encaissées par la passerelle.
                                    Les <strong>clients à terme</strong> figurent ici : les écrans de
                                    créances à terme ne couvrent que les commandes.
                                </small>
                            </div>

                            <div class="col-md-12" id="recapLocation" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body py-2">
                                        <div class="row text-center">
                                            <div class="col-md-3">
                                                <small class="text-muted">Client</small><br>
                                                <strong id="rlClient">-</strong>
                                                <span id="rlATerme" class="badge bg-info text-dark" style="display:none;">à terme</span>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Total à payer</small><br>
                                                <strong class="text-primary" id="rlTotal">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Déjà payé</small><br>
                                                <strong class="text-success" id="rlPaye">-</strong>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">Reste à payer</small><br>
                                                <strong class="text-danger" id="rlReste">-</strong>
                                            </div>
                                        </div>
                                        <div class="row text-center mt-2">
                                            <div class="col-md-12">
                                                <small class="text-muted">Matériel loué</small><br>
                                                <span id="rlMateriel">-</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Historique des paiements. Il manquait ici alors que le
                                 guichet des ventes le propose : un règlement en plusieurs
                                 tranches ne laissait aucune trace visible au moment
                                 d'encaisser la suivante. --}}
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

                            <div class="col-md-6">
                                <label for="date_encaissement" class="form-label">Date encaissement <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_encaissement" name="date_encaissement" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant à encaisser (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="1" required placeholder="Ex: 45000">
                                <small class="text-muted">Le paiement peut se faire en une ou plusieurs tranches.</small>
                            </div>
                            <div class="col-md-6">
                                {{-- L'agence n'est pas choisie : c'est celle de la personne
                                     connectée, sans quoi un caissier pourrait imputer sa
                                     recette au guichet d'une autre agence. --}}
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
                                <label for="notes" class="form-label">Notes / Observations</label>
                                <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Ex: Acompte 50%, réglé le ..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer &amp; générer le reçu
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
        $(function () {
            // Garde-fou d'initialisation : DataTables lève « Requested unknown
            // parameter » sur un tableau dont le corps ne contient que la ligne
            // « Aucun encaissement ».
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 && $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: []
                });
            }

            function formaterMontant(valeur) {
                var n = Math.round(parseFloat(valeur) || 0);
                return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
            }

            function majRecap() {
                var option = $('#numero_location').find('option:selected');

                if (!option.val()) {
                    $('#recapLocation, #historiqueWrap').hide();
                    $('#montant').removeAttr('max');
                    return;
                }

                var total = parseFloat(option.data('total')) || 0;
                var reste = parseFloat(option.data('reste')) || 0;

                $('#rlClient').text(option.data('client') || '-');
                $('#rlMateriel').text(option.data('materiel') || '-');
                $('#rlATerme').toggle(String(option.data('aterme')) === '1');
                $('#rlTotal').text(formaterMontant(total));
                $('#rlPaye').text(formaterMontant(total - reste));
                $('#rlReste').text(formaterMontant(reste));
                $('#recapLocation').show();

                // Le serveur refuse déjà un dépassement ; on l'empêche aussi ici
                // pour que le caissier le voie avant d'envoyer.
                $('#montant').attr('max', Math.round(reste));
                if (!$('#montant').val()) {
                    $('#montant').val(Math.round(reste));
                }

                chargerHistorique(option.val());
            }

            /**
             * Tranches déjà encaissées sur cette location. Les montants de
             * l'entête proviennent de la liste déroulante, calculée au chargement
             * de la page ; ceux-ci sont relus à l'instant, donc justes même si un
             * autre caissier vient d'encaisser.
             */
            function chargerHistorique(numero) {
                var url = '{{ route('show.encaissements.locations.historique', ['numero' => '__NUM__']) }}'
                    .replace('__NUM__', encodeURIComponent(numero));

                $.getJSON(url, function (data) {
                    if (!data || data.error) { return; }

                    $('#rlTotal').text(formaterMontant(data.location.total_a_payer));
                    $('#rlPaye').text(formaterMontant(data.location.total_paye));
                    $('#rlReste').text(formaterMontant(data.location.reste_a_payer));
                    $('#montant')
                        .attr('max', Math.round(data.location.encaissable))
                        .val(Math.round(data.location.encaissable));

                    var $corps = $('#historiqueBody').empty();

                    if (!data.historique.length) {
                        $corps.append('<tr><td colspan="8" class="text-center text-muted">'
                            + 'Aucun paiement précédent — ce sera la 1<sup>re</sup> tranche.</td></tr>');
                    } else {
                        data.historique.forEach(function (h) {
                            // Une tranche non validée ne compte pas encore et n'a pas
                            // de reçu : on l'affiche tout de même, c'est elle qui
                            // explique l'écart entre le reste dû et l'encaissable.
                            var actions = h.en_attente
                                ? '<span class="badge bg-warning text-dark">En attente de validation</span>'
                                : '<a href="' + h.recu_url + '" target="_blank" class="btn btn-xs btn-info" title="Voir"><i class="material-icons md-visibility"></i></a> '
                                  + '<a href="' + h.recu_pdf_url + '" class="btn btn-xs btn-secondary" title="PDF"><i class="material-icons md-picture_as_pdf"></i></a>';

                            $corps.append(
                                '<tr' + (h.en_attente ? ' style="background-color:#fff8e1;"' : '') + '>'
                                + '<td class="text-center"><span class="badge bg-info">' + h.tranche + '</span></td>'
                                + '<td class="text-center">' + (h.date || '-') + '</td>'
                                + '<td class="text-end ' + (h.en_attente ? 'text-muted' : 'text-success') + '"><strong>' + formaterMontant(h.montant) + '</strong></td>'
                                + '<td class="text-center">' + h.mode + '</td>'
                                + '<td class="text-center">' + h.agence_code + '</td>'
                                + '<td class="text-center">' + h.caissier + '</td>'
                                + '<td class="text-center">' + h.numero_recu + '</td>'
                                + '<td class="text-center">' + actions + '</td>'
                                + '</tr>'
                            );
                        });
                    }

                    $('#historiqueWrap').show();
                });
            }

            $('#numero_location').on('change', function () {
                $('#montant').val('');
                majRecap();
            });

            majRecap(); // cas d'une location présélectionnée par l'URL
        });
    </script>
@endsection
