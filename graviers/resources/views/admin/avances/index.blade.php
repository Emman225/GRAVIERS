@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Avances clients')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Avances clients</h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAvance">
                <i class="material-icons md-add"></i> Nouvelle avance
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

    <div class="alert alert-info py-2">
        <i class="material-icons md-info" style="font-size:16px;vertical-align:middle;"></i>
        Une avance est un dépôt <strong>sans commande</strong>. Les commandes du client réglées
        « en agence » s'en déduisent automatiquement, du dépôt le plus ancien au plus récent.
        Un client qui a une affaire non soldée ne dépose pas d'avance : il la règle d'abord,
        et le surplus versé devient son avance. {{ $mention }}
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    <p class="d-flex justify-content-between mb-0">
                        <span class="h5">Avances : <strong>{{ $lignes->count() }}</strong></span>
                        <span class="text-warning h5">En attente de validation :
                            <strong>{{ Help::formatNombre($totalEnAttente, true) }}</strong></span>
                        <span class="text-success h5">Total disponible :
                            <strong id="totalDisponible">{{ Help::formatNombre($totalDisponible, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="listeAvances" filename="avances-clients" title="Avances clients" />
            <div class="table-responsive">
                <table class="table table-striped" id="listeAvances">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date</th>
                            <th class="text-center">Reçu n°</th>
                            <th>Client</th>
                            <th class="text-center">Origine</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end">Consommé</th>
                            <th class="text-end">Solde</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Agence</th>
                            <th class="text-center">Mode</th>
                            <th>Observations / Notes</th>
                            <th class="text-center">Initié par</th>
                            <th class="text-center">Validé par</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $l)
                            <tr @if ($l->en_attente) style="background-color: #fff8e1;" @endif>
                                <td class="text-center">{{ $l->date ? \Help::dateHeure($l->date) : '-' }}</td>
                                <td class="text-center">{{ $l->numero_recu ?? '-' }}</td>
                                <td>
                                    {{ $l->client_nom }}
                                    @if ($l->client_terme)
                                        <span class="badge bg-light text-dark" style="font-size:0.7rem;">à terme</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $l->origine }}</td>
                                <td class="text-end"><strong>{{ Help::formatNombre($l->montant, true) }}</strong></td>
                                <td class="text-end">{{ Help::formatNombre($l->consomme, true) }}</td>
                                <td class="text-end {{ $l->solde >= 1 ? 'text-success' : 'text-muted' }}"><strong>{{ Help::formatNombre($l->solde, true) }}</strong></td>
                                <td class="text-center">
                                    @if ($l->en_attente)
                                        <span class="badge bg-warning text-dark">En attente de validation</span>
                                    @elseif ($l->statut === 'Disponible')
                                        <span class="badge bg-success">Disponible</span>
                                    @elseif ($l->statut === 'Épuisée')
                                        <span class="badge bg-secondary">Épuisée</span>
                                    @else
                                        <span class="badge bg-danger">{{ $l->statut }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($l->agence_code !== '-')
                                        <span class="badge bg-light text-dark" title="{{ $l->agence_nom }}">{{ $l->agence_code }}</span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="text-center">{{ $l->mode }}</td>
                                <td class="small text-danger">{{ $l->notes }}</td>
                                <td class="text-center small">{{ $l->initie_par }}</td>
                                <td class="text-center small">{{ $l->valide_par }}</td>
                                <td class="text-nowrap text-center">
                                    @if ($l->peut_valider)
                                        <form action="{{ route('show.avances.valider', $l->id) }}" method="POST"
                                              class="d-inline js-delete-form"
                                              data-confirm-mode="confirm"
                                              data-confirm-title="Validation de l'avance"
                                              data-confirm-text="Confirmez-vous la validation de l'avance {{ $l->numero_recu }} ({{ Help::formatNombre($l->montant, true) }}) ? Elle deviendra disponible pour les commandes du client, et le reçu lui sera envoyé."
                                              data-confirm-button="Oui, valider">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success" title="Valider l'avance"><i class="material-icons md-check_circle"></i></button>
                                        </form>
                                    @elseif ($l->en_attente)
                                        <span class="text-muted small"><em>En attente d'un autre admin</em></span>
                                    @endif
                                    @if (!$l->en_attente)
                                        <a href="{{ route('show.avances.recu', $l->id) }}" class="btn btn-sm btn-info" title="Voir le reçu">
                                            <i class="material-icons md-receipt"></i>
                                        </a>
                                        <a href="{{ route('show.avances.recuPdf', $l->id) }}" class="btn btn-sm btn-secondary" title="Télécharger le reçu en PDF">
                                            <i class="material-icons md-picture_as_pdf"></i>
                                        </a>
                                        <form action="{{ route('show.avances.envoyerRecu', $l->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary"
                                                    title="Informer le client : lui envoyer le reçu, avec la mention « non remboursable, à utiliser »{{ $l->recu_envoye ? ' (déjà envoyé le ' . \Help::dateHeure($l->recu_envoye) . ')' : '' }}">
                                                <i class="material-icons md-mail"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted">Aucune avance enregistrée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <h5 class="mb-0"><i class="material-icons md-history"></i> Historique des mouvements</h5>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="listeMouvements" filename="mouvements-avances" title="Mouvements des avances" />
            <div class="table-responsive">
                <table class="table table-sm table-striped" id="listeMouvements">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Date</th>
                            <th>Client</th>
                            <th class="text-center">Avance</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Montant</th>
                            <th class="text-end">Reste de l'avance</th>
                            <th class="text-center">Affaire</th>
                            <th>Libellé</th>
                            <th class="text-center">Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mouvements as $m)
                            <tr>
                                <td class="text-center">{{ $m->created_at?->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $m->client?->display_name ?? '-' }}</td>
                                <td class="text-center">{{ $m->avance?->numero_recu ?? '-' }}</td>
                                <td class="text-center">
                                    @if ($m->type === 'DEPOT')
                                        <span class="badge bg-success">Dépôt</span>
                                    @else
                                        <span class="badge bg-primary text-white">Déduction</span>
                                    @endif
                                </td>
                                <td class="text-end {{ $m->type === 'DEPOT' ? 'text-success' : 'text-primary' }}">
                                    {{ $m->type === 'DEPOT' ? '+' : '−' }} {{ Help::formatNombre($m->montant, true) }}
                                </td>
                                {{-- Solde de l'avance après ce mouvement (10/09/2026). --}}
                                <td class="text-end js-reste-avance"><strong>{{ Help::formatNombre($m->reste_apres ?? 0, true) }}</strong></td>
                                <td class="text-center">{{ $m->libelleAffaire() }}</td>
                                <td class="small">{{ $m->libelle }}</td>
                                <td class="text-center small">{{ $m->auteur?->nom_prenoms ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aucun mouvement.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============================================================
         MODAL : Nouvelle avance (dépôt sans commande)
         ============================================================ --}}
    <div class="modal fade" id="modalAvance" tabindex="-1" aria-labelledby="modalAvanceLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('show.avances.store') }}" id="formAvance">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalAvanceLabel">
                            <i class="material-icons md-savings"></i> Dépôt d'une avance
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="client_id" class="form-label">Client <span class="text-danger">*</span></label>
                                {{-- Liste avec recherche (10/09/2026) : le caissier tape un n° de
                                     compte, un nom, un prénom, un courriel ou un téléphone ; le
                                     libellé de chaque option les porte tous, la recherche de
                                     select2 trouve sur n'importe lequel. --}}
                                <select class="form-control" name="client_id" id="client_id" required
                                        data-placeholder="Tapez un n° de compte, un nom, un prénom, un courriel ou un téléphone">
                                    <option value=""></option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c->id }}">N° {{ $c->compte }} — {{ $c->nom }}{{ $c->prenom !== '' && !str_contains($c->nom, $c->prenom) ? ' ' . $c->prenom : '' }}{{ $c->email ? ' — ' . $c->email : '' }}{{ $c->contact ? ' — ' . $c->contact : '' }}{{ $c->terme ? ' (à terme)' : '' }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted" id="infoClientAvance"></small>
                            </div>
                            <div class="col-md-6">
                                <label for="date_depot" class="form-label">Date du dépôt <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_depot" name="date_depot" value="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="montant" class="form-label">Montant déposé (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="montant" name="montant" min="1" step="1" required placeholder="Ex: 100000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Agence</label>
                                @if ($monAgence)
                                    <input type="text" class="form-control" value="{{ $monAgence->code ? $monAgence->code . ' — ' : '' }}{{ $monAgence->nom }}" readonly>
                                    <small class="text-muted">Votre agence de rattachement.</small>
                                @else
                                    <input type="text" class="form-control" value="Aucune agence" readonly>
                                    <small class="text-danger">Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez encaisser.</small>
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
                                <input type="text" class="form-control" id="reference" name="reference" placeholder="N° transaction MoMo / Wave / chèque...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Caissier</label>
                                <input type="text" class="form-control" value="{{ Auth::user()?->nom_prenoms ?? '-' }}" readonly>
                            </div>
                            <div class="col-md-12">
                                <label for="notes" class="form-label">Notes <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="notes" id="notes" rows="2" required placeholder="Objet du dépôt (obligatoire)"></textarea>
                            </div>
                            <div class="col-md-12">
                                <div class="alert alert-warning py-2 mb-0 small">{{ $mention }} Le client en sera informé sur son reçu et par courriel.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons md-save"></i> Enregistrer le dépôt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/select2.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    {{-- Plusieurs jQuery se succèdent dans le pied de page : select2 doit
         s'attacher au dernier, donc se charger ici, après eux. --}}
    <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            // Le client du dépôt : liste avec recherche, ancrée dans la fenêtre
            // (dropdownParent) sinon le menu s'ouvre derrière elle.
            if ($.fn.select2) {
                $('#client_id').select2({
                    placeholder: $('#client_id').data('placeholder'),
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalAvance'),
                    language: { noResults: function () { return 'Aucun client ne correspond'; } }
                });
            }

            ['#listeAvances', '#listeMouvements'].forEach(function (id) {
                var $table = $(id);
                if ($table.find('tbody tr').length > 0 &&
                    $table.find('tbody tr td[colspan]').length === 0) {
                    $table.DataTable({
                        columnDefs: [{ targets: '_all', defaultContent: '-' }],
                        language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                        order: [[0, 'desc']],
                    });
                }
            });

            var fmt = function (n) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
            };

            // Dès qu'un client est choisi : son solde actuel, et ce qui
            // empêcherait le dépôt (affaire non soldée), avant d'enregistrer.
            $('#client_id').on('change', function () {
                var id = $(this).val();
                var $info = $('#infoClientAvance').removeClass('text-danger').text('');
                if (!id) return;
                $.getJSON('/avances/solde/' + encodeURIComponent(id), function (d) {
                    if (d.affaires && d.affaires.length) {
                        $info.addClass('text-danger').text(
                            "Dépôt impossible : affaire(s) non soldée(s) — " + d.affaires.join(', ') +
                            ". Encaissez d'abord ce qui est dû ; le surplus deviendra une avance.");
                    } else {
                        $info.text('Solde d\'avance actuel : ' + fmt(d.solde || 0) + '.');
                    }
                });
            });
        });
    </script>
@endsection
