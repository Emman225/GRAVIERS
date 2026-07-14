@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Relances - Clients à terme')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Suivi des relances - Clients à terme</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRelance">
                <i class="material-icons md-add"></i> Enregistrer une relance
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

    {{-- ============================================================
         SECTION : À RELANCER AUJOURD'HUI (basée sur configuration.delai_relance_standard)
         ============================================================ --}}
    @if (isset($aRelancer) && $aRelancer->count() > 0)
        <div class="card mb-4 border-warning">
            <header class="card-header" style="background:#fff3cd; border-bottom:1px solid #ffc107;">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <h5 class="mb-0" style="color:#664d03;">
                            <i class="material-icons md-warning align-middle"></i>
                            À relancer aujourd'hui
                            <span class="badge bg-warning text-dark ms-2">{{ $aRelancer->count() }}</span>
                        </h5>
                        <small class="text-muted">
                            Factures en retard d'au moins <strong>{{ $delaiRelance }} jour(s)</strong>
                            sans relance récente. Seuil paramétrable dans
                            <a href="{{ route('show.parametre') }}#tab-creance">Paramètres → Créances à terme</a>.
                        </small>
                    </div>
                </div>
            </header>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead style="background:#fff3cd;">
                            <tr>
                                <th>N° Facture</th>
                                <th>Client</th>
                                <th class="text-center">Échéance</th>
                                <th class="text-center">Retard</th>
                                <th class="text-end">Reste à payer</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($aRelancer as $r)
                                <tr>
                                    <td><strong>{{ $r->numero }}</strong></td>
                                    <td>{{ $r->client_nom }} <small class="text-muted">#{{ $r->client_id }}</small></td>
                                    <td class="text-center">
                                        {{ $r->date_echeance ? Carbon::parse($r->date_echeance)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger">{{ $r->jours_retard }} j</span>
                                    </td>
                                    <td class="text-end"><strong>{{ \Help::formatNombre($r->reste, true) }}</strong></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-warning btn-prefill-relance"
                                                data-bs-toggle="modal" data-bs-target="#modalRelance"
                                                data-client-id="{{ $r->client_id }}"
                                                data-facture-id="{{ $r->facture_id }}"
                                                data-niveau="{{ $r->jours_retard >= ($delaiRelance * 3) ? '3' : ($r->jours_retard >= ($delaiRelance * 2) ? '2' : '1') }}">
                                            <i class="material-icons md-call"></i> Relancer
                                        </button>
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
                    <p class="h5">Nombre de relances :
                        <strong>{{ $lignes->count() }}</strong></p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="relances-clients-a-terme" title="Relances clients à terme" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date Relance</th>
                            <th class="text-center">N° Facture</th>
                            <th class="text-center">Code Client</th>
                            <th class="text-center">Client</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Niveau</th>
                            <th>Réponse client</th>
                            <th>Action suivante</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            @php
                                $badgeNiveau = match(strtolower((string) $l->niveau)) {
                                    '1'                  => 'bg-info',
                                    '2'                  => 'bg-warning text-dark',
                                    '3'                  => 'bg-danger',
                                    'mise en demeure'    => 'bg-dark',
                                    default              => 'bg-light text-dark',
                                };
                            @endphp
                            <tr>
                                <td class="text-center">{{ $l->date_relance ? Carbon::parse($l->date_relance)->format('d/m/Y') : '-' }}</td>
                                <td class="text-center">{{ $l->numero_facture }}</td>
                                <td class="text-center">{{ $l->code_client }}</td>
                                <td>{{ $l->client_nom }}</td>
                                <td class="text-center">{{ $l->type_relance ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $badgeNiveau }}">{{ $l->niveau ?? '-' }}</span>
                                </td>
                                <td>{{ $l->reponse_client ?? '-' }}</td>
                                <td>{{ $l->action_suivante ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    Aucune relance enregistrée.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modal d'enregistrement de relance --}}
    <div class="modal fade" id="modalRelance" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.creancesTerme.relances.store') }}">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="material-icons md-call"></i> Enregistrer une relance</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Date relance <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="date_relance" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Client <span class="text-danger">*</span></label>
                                <select class="form-control" name="client_id" id="rel_client_id" required>
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($clientsListe as $c)
                                        <option value="{{ $c->id }}">{{ $c->nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Facture concernée</label>
                                <select class="form-control" name="facture_id" id="rel_facture_id">
                                    <option value="">— Toutes / Aucune en particulier —</option>
                                    @foreach ($facturesListe as $f)
                                        <option value="{{ $f->id }}" data-client="{{ $f->client_id }}">
                                            {{ $f->numero }} (Reste : {{ Help::formatNombre($f->reste, true) }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Filtrera selon le client choisi.</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="type_relance" required>
                                    <option value="Téléphone">Téléphone</option>
                                    <option value="Email">Email</option>
                                    <option value="SMS">SMS</option>
                                    <option value="Visite">Visite</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Niveau <span class="text-danger">*</span></label>
                                <select class="form-control" name="niveau" required>
                                    <option value="1">1 (Standard)</option>
                                    <option value="2">2 (Insistante)</option>
                                    <option value="3">3 (Ferme)</option>
                                    <option value="Mise en demeure">Mise en demeure</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Réponse client</label>
                                <textarea class="form-control" name="reponse_client" rows="2" placeholder="Ex: Promet paiement sous 7j, en attente, conteste..."></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Action suivante</label>
                                <textarea class="form-control" name="action_suivante" rows="2" placeholder="Ex: Relance niveau 2 si pas payé, mise en demeure le 25/05..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer la relance</button>
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

            // Filtrage des factures par client sélectionné
            $('#rel_client_id').on('change', function () {
                var cid = $(this).val();
                $('#rel_facture_id option').each(function () {
                    var val = $(this).val();
                    if (!val) return;
                    var fclient = $(this).data('client');
                    if (cid && String(fclient) !== String(cid)) {
                        $(this).hide();
                    } else {
                        $(this).show();
                    }
                });
                $('#rel_facture_id').val('');
            });

            // Pré-remplissage du modal depuis la section "À relancer aujourd'hui"
            $(document).on('click', '.btn-prefill-relance', function () {
                var clientId  = $(this).data('client-id');
                var factureId = $(this).data('facture-id');
                var niveau    = $(this).data('niveau');

                $('#rel_client_id').val(String(clientId)).trigger('change');
                // setTimeout pour laisser le filtre des factures s'appliquer avant la sélection
                setTimeout(function () {
                    $('#rel_facture_id').val(String(factureId));
                }, 50);
                $('select[name="niveau"]').val(String(niveau));
                $('input[name="date_relance"]').val(new Date().toISOString().slice(0, 10));
            });
        });
    </script>
@endsection
