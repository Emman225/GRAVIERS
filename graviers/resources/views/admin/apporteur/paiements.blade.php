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
                            <th class="text-center">Date Paiement</th>
                            <th class="text-center">N° Commission</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Code Apporteur</th>
                            <th class="text-center">Nom Apporteur</th>
                            <th class="text-end">Montant Payé</th>
                            <th class="text-center">Mode de paiement</th>
                            <th class="text-center">Référence</th>
                            <th>Notes</th>
                            <th class="text-center">Reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
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
                                <td class="text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.apporteurs.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement apporteur ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                <i class="material-icons md-check_circle"></i> Valider
                                            </button>
                                        </form>
                                    @elseif (!($l->en_attente ?? false))
                                        <a href="{{ route('show.apporteurs.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.apporteurs.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @else
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted">
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
                                <td colspan="4"></td>
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
                                        'nb'            => $coms->count(),
                                        'total_reste'   => $coms->sum('reste'),
                                    ];
                                })->sortBy('apporteur_nom')->values();
                            @endphp
                            <div class="col-md-12">
                                <label for="filtreApporteur" class="form-label fw-bold">Apporteur <span class="text-danger">*</span></label>
                                <select class="form-control" id="filtreApporteur">
                                    <option value="">— Sélectionner un apporteur —</option>
                                    @foreach ($apporteursDues as $app)
                                        <option value="{{ $app->apporteur_id }}">
                                            {{ $app->apporteur_nom }} — {{ $app->nb }} commission(s) due(s) — Total : {{ number_format($app->total_reste, fmod($app->total_reste, 1) == 0 ? 0 : 2, ',', ' ') }} FCFA
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
                                <div id="listeCommissions" class="border rounded p-2" style="max-height:220px; overflow-y:auto;">
                                    @foreach ($commissionsDues as $com)
                                        <div class="form-check com-item" data-apporteur-id="{{ $com->apporteur_id }}" style="display:none;">
                                            <input class="form-check-input com-check" type="checkbox"
                                                   name="commission_ids[]" value="{{ $com->id }}"
                                                   id="com{{ $com->id }}"
                                                   data-apporteur-id="{{ $com->apporteur_id }}"
                                                   data-apporteur="{{ $com->apporteur_nom }}"
                                                   data-calc="{{ $com->commission_calc }}"
                                                   data-reste="{{ $com->reste }}">
                                            <label class="form-check-label" for="com{{ $com->id }}">
                                                {{ $com->code_com }} — Cmd {{ $com->numero_cmd }}
                                                — Reste : <strong>{{ number_format($com->reste, fmod($com->reste, 1) == 0 ? 0 : 2, ',', ' ') }} FCFA</strong>
                                            </label>
                                        </div>
                                    @endforeach
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
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
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

            // Montants avec décimales possibles (commission = % du total, ex. 11,8 FCFA)
            var fmt = function (n) {
                var v = parseFloat(n) || 0;
                return new Intl.NumberFormat('fr-FR', {maximumFractionDigits: 2}).format(v) + ' FCFA';
            };

            // Étape 1 : sélection de l'apporteur -> n'afficher QUE ses commissions
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
                                    '<td class="text-center"><a href="'+h.recu_url+'" target="_blank" class="btn btn-xs btn-info"><i class="material-icons md-visibility"></i></a> <a href="'+h.recu_pdf_url+'" class="btn btn-xs btn-secondary"><i class="material-icons md-picture_as_pdf"></i></a></td>' +
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
                if (n === 0) { e.preventDefault(); alert('Cochez au moins une commission.'); return false; }
                var m = parseFloat($('#montant').val() || 0), max = parseFloat($('#montant').attr('max') || 0);
                if (m <= 0) { e.preventDefault(); alert('Montant > 0'); return false; }
                if (max > 0 && m > max + 0.01) { e.preventDefault(); alert('Dépasse le reste à payer ('+fmt(max)+')'); return false; }
            });
        });
    </script>
@endsection
