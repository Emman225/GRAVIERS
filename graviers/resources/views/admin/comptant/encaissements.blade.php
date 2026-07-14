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
                            <th class="text-center">Date Encaissement</th>
                            <th class="text-center">N° Commande</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Agence</th>
                            <th class="text-end">Montant Encaissé</th>
                            <th class="text-center">Mode</th>
                            <th class="text-center">Caissier</th>
                            <th class="text-center">Reçu N°</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if($l->en_attente ?? false) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date_encaissement ? Carbon::parse($l->date_encaissement)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->numero_commande }}</td>
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
                                <td class="text-center">{{ $l->numero_recu ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($l->peut_valider ?? false)
                                        <form action="{{ route('show.comptant.encaissements.valider', $l->paiement_id) }}"
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
                                        <a href="{{ route('show.comptant.recu', $l->paiement_id) }}" class="btn btn-sm btn-info" title="Voir reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.comptant.recuPdf', $l->paiement_id) }}" class="btn btn-sm btn-secondary" title="Télécharger PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                    @elseif (($l->paiement_id ?? null) && ($l->en_attente ?? false) && !($l->peut_valider ?? false))
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">
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
                                <td colspan="4"></td>
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
                            <i class="material-icons md-payments"></i> Encaissement en agence
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            {{-- Sélection commande --}}
                            <div class="col-md-12">
                                <label for="numero_commande" class="form-label fw-bold">Commande à encaisser <span class="text-danger">*</span></label>
                                <select class="form-control" name="numero_commande" id="numero_commande" required>
                                    <option value="">— Sélectionner une commande —</option>
                                    @foreach ($commandesNonSoldees as $cmd)
                                        <option value="{{ $cmd->numero }}"
                                            data-client="{{ $cmd->client_nom }}"
                                            data-total="{{ $cmd->total_a_payer }}"
                                            data-reste="{{ $cmd->reste }}"
                                            data-agence="{{ $cmd->agence_id }}">
                                            {{ $cmd->numero }} — {{ $cmd->client_nom }} — Reste : {{ Help::formatNombre($cmd->reste, true) }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Liste des commandes des clients ordinaires non soldées</small>
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
                                <label for="agence_id" class="form-label">Agence <span class="text-danger">*</span></label>
                                <select class="form-control" name="agence_id" id="agence_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($agences as $ag)
                                        <option value="{{ $ag->id }}">{{ $ag->code }} — {{ $ag->nom }}</option>
                                    @endforeach
                                </select>
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
                                <textarea class="form-control" name="notes" id="notes" rows="2" placeholder="Ex: Acompte 50%, retiré le ..."></textarea>
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

            // ====== MODAL ENCAISSEMENT ======
            var fmt = function (n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            };

            // Quand commande sélectionnée → AJAX historique + récap + agence par défaut
            $('#numero_commande').on('change', function () {
                var $opt = $(this).find(':selected');
                var numero = $opt.val();
                if (!numero) {
                    $('#recapCommande, #historiqueWrap').hide();
                    $('#montant').val('').attr('max', '');
                    return;
                }
                // Pré-remplissage rapide depuis data attributes
                var reste = parseFloat($opt.data('reste') || 0);
                var agence = $opt.data('agence') || '';
                $('#rcClient').text($opt.data('client') || '-');
                $('#rcTotal').text(fmt($opt.data('total') || 0));
                $('#rcReste').text(fmt(reste));
                $('#montant').attr('max', reste).val(reste);
                if (agence) $('#agence_id').val(agence);
                $('#recapCommande').show();

                // AJAX pour historique complet + total payé exact
                $.getJSON('/comptant/commande/' + encodeURIComponent(numero) + '/historique', function (data) {
                    if (data.error) return;
                    $('#rcPaye').text(fmt(data.commande.total_paye));
                    $('#rcReste').text(fmt(data.commande.reste_a_payer));
                    $('#montant').attr('max', data.commande.reste_a_payer).val(data.commande.reste_a_payer);

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
                                '<td class="text-center">' +
                                    '<a href="' + h.recu_url + '" target="_blank" class="btn btn-xs btn-info" title="Voir"><i class="material-icons md-visibility"></i></a> ' +
                                    '<a href="' + h.recu_pdf_url + '" class="btn btn-xs btn-secondary" title="PDF"><i class="material-icons md-picture_as_pdf"></i></a>' +
                                '</td>' +
                                '</tr>'
                            );
                        });
                    }
                    $('#historiqueWrap').show();
                });
            });

            // Validation côté client
            $('#formEncaissement').on('submit', function (e) {
                var montant = parseFloat($('#montant').val() || 0);
                var max = parseFloat($('#montant').attr('max') || 0);
                if (montant <= 0) {
                    e.preventDefault();
                    alert('Le montant doit être supérieur à 0.');
                    return false;
                }
                if (max > 0 && montant > max) {
                    e.preventDefault();
                    alert('Le montant dépasse le reste à payer (' + fmt(max) + ').');
                    return false;
                }
            });
        });
    </script>
@endsection
