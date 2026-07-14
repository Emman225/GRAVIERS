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
                                <td colspan="9" class="text-center text-muted">
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
                                <td colspan="4"></td>
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
                            <div class="col-md-12">
                                <label for="enlevement_id" class="form-label fw-bold">Bon d'enlèvement à payer <span class="text-danger">*</span></label>
                                <select class="form-control" name="enlevement_id" id="enlevement_id" required>
                                    <option value="">— Sélectionner un bon d'enlèvement —</option>
                                    @foreach ($enlevementsNonSoldes as $e)
                                        <option value="{{ $e->id }}"
                                                data-fournisseur="{{ $e->fournisseur_nom }}"
                                                data-code-fournisseur="{{ $e->code_fournisseur }}"
                                                data-produit="{{ $e->produit }}"
                                                data-ttc="{{ $e->montant_ttc }}"
                                                data-reste="{{ $e->reste }}">
                                            {{ $e->code_be }} — {{ $e->fournisseur_nom }} — {{ $e->produit }} — Reste : {{ Help::formatNombre($e->reste, true) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Liste des enlèvements non soldés</small>
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
                                                <small class="text-muted">Total TTC</small><br>
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
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="1" required>
                                <small class="text-muted">Multi-tranches autorisé.</small>
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

            $('#enlevement_id').on('change', function () {
                var $opt = $(this).find(':selected');
                var id = $opt.val();
                if (!id) {
                    $('#recapEnlevement, #historiqueWrap').hide();
                    $('#montant').val('').attr('max', '');
                    return;
                }
                var reste = parseFloat($opt.data('reste') || 0);
                var ttc = parseFloat($opt.data('ttc') || 0);
                $('#recFournisseur').text($opt.data('fournisseur'));
                $('#recTtc').text(fmt(ttc));
                $('#recReste').text(fmt(reste));
                $('#montant').attr('max', reste).val(reste);
                $('#recapEnlevement').show();

                $.getJSON('/fournisseurs/enlevement/' + id + '/historique', function (data) {
                    if (data.error) return;
                    $('#recPaye').text(fmt(data.enlevement.montant_paye));
                    $('#recReste').text(fmt(data.enlevement.reste_a_payer));
                    $('#montant').attr('max', data.enlevement.reste_a_payer).val(data.enlevement.reste_a_payer);

                    var $tbody = $('#historiqueBody').empty();
                    if (data.historique.length === 0) {
                        $tbody.append('<tr><td colspan="6" class="text-center text-muted">Aucun paiement précédent — ce sera la 1ère tranche.</td></tr>');
                    } else {
                        data.historique.forEach(function (h) {
                            $tbody.append(
                                '<tr>' +
                                '<td class="text-center"><span class="badge bg-info">' + h.tranche + '</span></td>' +
                                '<td class="text-center">' + (h.date || '-') + '</td>' +
                                '<td class="text-end text-success"><strong>' + fmt(h.montant) + '</strong></td>' +
                                '<td class="text-center">' + h.mode + '</td>' +
                                '<td class="text-center">' + (h.reference || '-') + '</td>' +
                                '<td class="text-center">' +
                                    '<a href="' + h.recu_url + '" target="_blank" class="btn btn-xs btn-info"><i class="material-icons md-visibility"></i></a> ' +
                                    '<a href="' + h.recu_pdf_url + '" class="btn btn-xs btn-secondary"><i class="material-icons md-picture_as_pdf"></i></a>' +
                                '</td>' +
                                '</tr>'
                            );
                        });
                    }
                    $('#historiqueWrap').show();
                });
            });

            $('#formPaiementFourn').on('submit', function (e) {
                var montant = parseFloat($('#montant').val() || 0);
                var max = parseFloat($('#montant').attr('max') || 0);
                if (montant <= 0) { e.preventDefault(); alert('Le montant doit être supérieur à 0.'); return false; }
                if (max > 0 && montant > max) { e.preventDefault(); alert('Le montant dépasse le reste à payer (' + fmt(max) + ').'); return false; }
            });
        });
    </script>
@endsection
