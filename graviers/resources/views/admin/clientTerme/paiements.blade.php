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
                            <th class="text-center">Date Paiement</th>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Code Client</th>
                            <th class="text-center">Client</th>
                            <th class="text-end">Montant Reçu</th>
                            <th class="text-center">Mode de paiement</th>
                            <th class="text-center">Référence transaction</th>
                            <th class="text-center">Notes</th>
                            <th class="text-center">Reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_paiement ? Carbon::parse($l->date_paiement)->format('d/m/Y') : '-' }}</td>
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
                                <td>{{ $l->notes ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.creancesTerme.paiements.valider', $l->paiement_id) }}"
                                              method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation du paiement"
                                              data-confirm-text="Confirmez-vous la validation de ce paiement client à terme ? Le reçu deviendra définitif."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider">
                                                <i class="material-icons md-check_circle"></i> Valider
                                            </button>
                                        </form>
                                    @elseif (($l->paiement_id ?? null) && !($l->en_attente ?? false))
                                        <a href="{{ route('show.creancesTerme.recu', $l->paiement_id) }}" target="_blank" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.creancesTerme.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @elseif (($l->en_attente ?? false))
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">
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
                        <h5 class="modal-title"><i class="material-icons md-payments"></i> Enregistrer un encaissement client à terme</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="numero_facture" class="form-label fw-bold">Facture à encaisser <span class="text-danger">*</span></label>
                                <select class="form-control" name="numero_facture" id="numero_facture" required>
                                    <option value="">— Sélectionner une facture —</option>
                                    @foreach ($facturesNonSoldees as $f)
                                        <option value="{{ $f->numero }}"
                                                data-client="{{ $f->client_nom }}"
                                                data-total="{{ $f->total_a_payer }}"
                                                data-reste="{{ $f->reste }}">
                                            {{ $f->numero }} — {{ $f->client_nom }} — Reste : {{ Help::formatNombre($f->reste, true) }}
                                        </option>
                                    @endforeach
                                </select>
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
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer & générer le reçu</button>
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

            $('#numero_facture').on('change', function () {
                var $opt = $(this).find(':selected');
                var num = $opt.val();
                if (!num) { $('#recapFacture, #historiqueWrap').hide(); $('#montant').val('').attr('max',''); return; }
                $('#recClient').text($opt.data('client'));
                $('#recTotal').text(fmt($opt.data('total') || 0));
                $('#recReste').text(fmt($opt.data('reste') || 0));
                $('#montant').attr('max', $opt.data('reste')).val($opt.data('reste'));
                $('#recapFacture').show();

                $.getJSON('/clients-terme/facture/' + encodeURIComponent(num) + '/historique', function (data) {
                    if (data.error) return;
                    $('#recPaye').text(fmt(data.facture.total_paye));
                    $('#recReste').text(fmt(data.facture.reste_a_payer));
                    $('#montant').attr('max', data.facture.reste_a_payer).val(data.facture.reste_a_payer);

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
                                '<td class="text-center"><a href="'+h.recu_url+'" target="_blank" class="btn btn-xs btn-info"><i class="material-icons md-visibility"></i></a> <a href="'+h.recu_pdf_url+'" class="btn btn-xs btn-secondary"><i class="material-icons md-picture_as_pdf"></i></a></td>' +
                                '</tr>');
                        });
                    }
                    $('#historiqueWrap').show();
                });
            });

            $('#formPaiementCT').on('submit', function (e) {
                var m = parseFloat($('#montant').val() || 0), max = parseFloat($('#montant').attr('max') || 0);
                if (m <= 0) { e.preventDefault(); alert('Montant > 0'); return false; }
                if (max > 0 && m > max) { e.preventDefault(); alert('Dépasse le reste à payer ('+fmt(max)+')'); return false; }
            });
        });
    </script>
@endsection
