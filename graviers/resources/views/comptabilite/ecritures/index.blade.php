@extends('layout.main')
@section('title', 'Journal des écritures comptables')

@php
    use App\Models\DeversementComptable;
    use App\Models\EcritureComptable;
    $francs = fn ($montant) => number_format((float) $montant, 0, ',', ' ');
    $aExporter = $resume[EcritureComptable::ETAT_A_EXPORTER];
    $exportees = $resume[EcritureComptable::ETAT_EXPORTEE];
    $enAnomalie = $resume[EcritureComptable::ETAT_ANOMALIE];
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Journal des <span class="dash-welcome-name">écritures</span></h2>
                <p class="dash-welcome-subtitle">
                    Les écritures produites par les factures normalisées et par les mouvements d'argent,
                    puis leur transmission au logiciel comptable — {{ \Help::phrase($periode['du']->locale('fr')->isoFormat('MMMM YYYY')) }}.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.comptabilite.parametrage') }}" class="btn btn-primary">
                    <i class="material-icons md-settings"></i> Paramétrage comptable
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul></div>
    @endif

    {{-- ===== ÉTAT DE LA PÉRIODE ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-menu_book"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Écritures de la période</div>
                    <div class="kpi-card-value">{{ $resume['total']['nombre'] }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-send"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">À transmettre</div>
                    <div class="kpi-card-value">{{ $aExporter['nombre'] }}</div>
                    <div class="kpi-card-label">{{ $francs($aExporter['debit']) }} F</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-check_circle"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Déjà transmises</div>
                    <div class="kpi-card-value">{{ $exportees['nombre'] }}</div>
                    <div class="kpi-card-label">{{ $francs($exportees['debit']) }} F</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card {{ $enAnomalie['nombre'] ? 'kpi-card-warning' : 'kpi-card-success' }}">
                <div class="kpi-card-icon"><i class="material-icons {{ $enAnomalie['nombre'] ? 'md-error' : 'md-check_circle' }}"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En anomalie</div>
                    <div class="kpi-card-value">{{ $enAnomalie['nombre'] }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TRANSMISSION ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-send text-primary"></i> Transmettre au logiciel comptable</h5>
        </div>
        <div class="card-body">
            @include('comptabilite.ecritures._periode', [
                'action' => route('show.comptabilite.ecritures.index'),
                'champsConserves' => $filtres,
            ])

            <hr>

            @if ($anomalies->isNotEmpty())
                <div class="alert alert-warning">
                    <strong>{{ $anomalies->count() }} anomalie(s) sur cette période.</strong>
                    Rien ne peut être transmis tant qu'elles ne sont pas réglées : un logiciel comptable refuse un lot incomplet,
                    et une écriture fausse déjà partie ne se rattrape qu'en l'annulant.
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <a href="{{ route('show.comptabilite.ecritures.anomalies', request()->only(['mode_periode', 'periode', 'du', 'au'])) }}" class="btn btn-sm btn-primary">
                            Voir le rapport d'anomalies
                        </a>
                        <form method="POST" action="{{ route('show.comptabilite.ecritures.reprendre') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-primary">Reprendre après correction</button>
                        </form>
                    </div>
                </div>
            @elseif ($apercu['nombre'] === 0)
                <div class="alert alert-info mb-0">
                    Aucune écriture à transmettre sur cette période : elles ont déjà été transmises, ou il n'y en a pas.
                </div>
            @else
                <div class="alert alert-success">
                    <strong>{{ $apercu['nombre'] }} écriture(s)</strong>, {{ $apercu['lignes'] }} ligne(s),
                    total débit {{ $francs($apercu['debit']) }} F = total crédit {{ $francs($apercu['credit']) }} F.
                </div>
            @endif

            <div class="d-flex flex-wrap gap-2 align-items-end">
                <form method="POST" action="{{ route('show.comptabilite.ecritures.transmettre', request()->only(['mode_periode', 'periode', 'du', 'au'])) }}"
                      class="d-flex flex-wrap gap-2 align-items-end js-confirmer"
                      data-confirm-title="Transmettre les écritures ?"
                      data-confirm-text="Les {{ $apercu['nombre'] }} écriture(s) de la période passeront à « transmise » et ne pourront plus être modifiées : toute correction ultérieure passera par une écriture d'annulation.">
                    @csrf
                    <div>
                        <label class="form-label" for="format">Format</label>
                        <select name="format" id="format" class="form-select form-select-sm" style="min-width:220px;">
                            @foreach (DeversementComptable::FORMATS as $cle => $libelle)
                                <option value="{{ $cle }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" {{ $apercu['possible'] ? '' : 'disabled' }}>
                            <i class="material-icons md-send"></i> Transmettre et télécharger
                        </button>
                    </div>
                </form>
                <div>
                    <a href="{{ route('show.comptabilite.ecritures.telecharger', array_merge(['format' => 'sage'], request()->only(['mode_periode', 'periode', 'du', 'au', 'etat', 'journal', 'origine']))) }}"
                       class="btn btn-primary">
                        <i class="material-icons md-visibility"></i> Voir le fichier sans transmettre
                    </a>
                    <small class="text-muted d-block mt-1">Un contrôle : rien n'est enregistré, aucune écriture ne change d'état.</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== LES ÉCRITURES ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-list text-primary"></i> Écritures de la période</h5>
            <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
                @foreach (request()->only(['mode_periode', 'periode', 'du', 'au']) as $nom => $valeur)
                    <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
                @endforeach
                <select name="etat" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">— Tous les états —</option>
                    @foreach (EcritureComptable::ETATS as $cle => $libelle)
                        <option value="{{ $cle }}" {{ $filtres['etat'] === $cle ? 'selected' : '' }}>{{ $libelle }}</option>
                    @endforeach
                </select>
                <select name="origine" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">— Toutes les origines —</option>
                    @foreach (EcritureComptable::ORIGINES as $cle => $libelle)
                        <option value="{{ $cle }}" {{ $filtres['origine'] === $cle ? 'selected' : '' }}>{{ $libelle }}</option>
                    @endforeach
                </select>
                <select name="journal" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">— Tous les journaux —</option>
                    @foreach ($journaux as $journal)
                        <option value="{{ $journal->id }}" {{ (int) $filtres['journal'] === (int) $journal->id ? 'selected' : '' }}>{{ $journal->designation }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableEcritures" filename="journal-des-ecritures" title="Journal des écritures comptables" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableEcritures">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mois</th>
                            <th>Année</th>
                            <th>Journal</th>
                            <th>N° facture / pièce</th>
                            <th>Libellé</th>
                            <th>Origine</th>
                            <th class="text-end">Débit</th>
                            <th class="text-end">Crédit</th>
                            <th class="text-center">État</th>
                            <th class="text-end">Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ecritures as $ecriture)
                            <tr>
                                <td data-order="{{ $ecriture->date_ecriture?->format('Ymd') }}" class="text-nowrap">{{ $ecriture->date_ecriture?->format('d/m/Y') }}</td>
                                {{-- Le mois se trie sur son numéro, pas sur son nom : « août » ne vient pas après « avril ». --}}
                                <td data-order="{{ $ecriture->date_ecriture?->format('m') }}" class="text-nowrap">{{ $ecriture->date_ecriture ? \Help::phrase($ecriture->date_ecriture->locale('fr')->isoFormat('MMMM')) : '-' }}</td>
                                <td class="text-nowrap">{{ $ecriture->date_ecriture?->format('Y') ?: '-' }}</td>
                                <td class="text-nowrap">{{ $ecriture->journal_code ?: '-' }}</td>
                                <td class="text-nowrap">{{ $ecriture->piece }}</td>
                                <td class="td-texte-long">{{ $ecriture->libelle }}</td>
                                <td class="text-nowrap">{{ EcritureComptable::ORIGINES[$ecriture->origine] ?? $ecriture->origine }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ecriture->total_debit) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ecriture->total_credit) }}</td>
                                <td class="text-center">
                                    @if ($ecriture->etat === EcritureComptable::ETAT_EXPORTEE)
                                        <span class="badge bg-success">Transmise</span>
                                    @elseif ($ecriture->etat === EcritureComptable::ETAT_ANOMALIE)
                                        <span class="badge bg-warning text-dark">En anomalie</span>
                                    @else
                                        <span class="badge bg-info">À transmettre</span>
                                    @endif
                                    @if ($ecriture->annulee_par_id) <span class="badge bg-secondary">Annulée</span> @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('show.comptabilite.ecritures.detail', $ecriture) }}" class="btn btn-sm btn-primary rounded" title="Voir le détail">
                                        <i class="material-icons md-visibility"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center text-muted">Aucune écriture sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== SUIVI DES DÉVERSEMENTS ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-history text-primary"></i> Suivi des déversements</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Ce qui a été transmis, quand, par qui, et sous quelle forme — la preuve de l'envoi.</p>
            <x-export-buttons table-id="tableDeversements" filename="suivi-des-deversements" title="Suivi des déversements" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableDeversements" data-ordre="desc">
                    <thead>
                        <tr>
                            <th>Transmis le</th>
                            <th>N°</th>
                            <th>Période</th>
                            <th>Mois</th>
                            <th>Année</th>
                            <th>Format</th>
                            <th>Journaux</th>
                            <th class="text-end">Écritures</th>
                            <th class="text-end">Factures</th>
                            <th class="text-end">Débit</th>
                            <th>Par</th>
                            <th class="text-center">État</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deversements as $deversement)
                            <tr>
                                <td data-order="{{ $deversement->created_at?->format('YmdHis') }}" class="text-nowrap">{{ \Help::dateHeure($deversement->created_at) }}</td>
                                <td class="text-nowrap"><strong>{{ $deversement->numero }}</strong></td>
                                <td class="text-nowrap">{{ $deversement->libelle_periode }}</td>
                                @php
                                    // Un envoi fait en dates libres peut chevaucher deux mois : on ne
                                    // lui invente pas un mois, on dit « plusieurs ».
                                    $memeMois = $deversement->du && $deversement->au
                                        && $deversement->du->format('Y-m') === $deversement->au->format('Y-m');
                                    $memeAnnee = $deversement->du && $deversement->au
                                        && $deversement->du->format('Y') === $deversement->au->format('Y');
                                    $resumeLigne = $resumeDeversements[$deversement->id] ?? ['journaux' => '—', 'factures' => 0];
                                @endphp
                                <td data-order="{{ $memeMois ? $deversement->du->format('m') : '99' }}" class="text-nowrap">
                                    {{ $memeMois ? \Help::phrase($deversement->du->locale('fr')->isoFormat('MMMM')) : 'Plusieurs' }}
                                </td>
                                <td class="text-nowrap">{{ $memeAnnee ? $deversement->du->format('Y') : 'Plusieurs' }}</td>
                                <td class="text-nowrap">{{ $deversement->libelle_format }}</td>
                                <td class="text-nowrap">{{ $resumeLigne['journaux'] }}</td>
                                <td class="text-end">{{ $deversement->nombre_ecritures }}</td>
                                <td class="text-end">{{ $resumeLigne['factures'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($deversement->total_debit) }}</td>
                                <td class="text-nowrap">{{ $deversement->user?->nom_prenoms ?: '-' }}</td>
                                <td class="text-center">
                                    @if ($deversement->etat === DeversementComptable::ACCUSE_RECU)
                                        <span class="badge bg-success">Accusé reçu</span>
                                    @elseif ($deversement->estRejete())
                                        <span class="badge bg-danger">Rejeté</span>
                                    @else
                                        <span class="badge bg-info">Transmis</span>
                                    @endif
                                    @if ($deversement->motif_rejet) <div><small class="text-muted">{{ $deversement->motif_rejet }}</small></div> @endif
                                </td>
                                <td class="text-nowrap text-end">
                                    <a href="{{ route('show.comptabilite.ecritures.facturesDuDeversement', $deversement) }}"
                                       class="btn btn-sm btn-light rounded" title="Voir les factures emportées par cet envoi">
                                        <i class="material-icons md-list"></i>
                                    </a>
                                    @unless ($deversement->estRejete())
                                        <a href="{{ route('show.comptabilite.ecritures.telechargerDeversement', ['deversement' => $deversement, 'format' => strtolower($deversement->format)]) }}"
                                           class="btn btn-sm btn-primary rounded" title="Retélécharger le fichier transmis">
                                            <i class="material-icons md-get_app"></i>
                                        </a>
                                        @if ($deversement->etat === DeversementComptable::TRANSMIS)
                                            <form action="{{ route('show.comptabilite.ecritures.accuser', $deversement) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success rounded" title="Le logiciel comptable a bien reçu le lot">
                                                    <i class="material-icons md-check_circle"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-sm btn-warning rounded js-rejeter"
                                                    data-action="{{ route('show.comptabilite.ecritures.rejeter', $deversement) }}"
                                                    data-numero="{{ $deversement->numero }}" title="Le logiciel comptable a refusé le lot">
                                                <i class="material-icons md-undo"></i>
                                            </button>
                                        @endif
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" class="text-center text-muted">Aucune transmission enregistrée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form id="formulaireRejet" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="motif_rejet" id="motifRejet">
    </form>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var langue = { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' };
            $('.js-table').each(function () {
                var $table = $(this);
                if ($table.find('tbody tr').length === 0 || $table.find('tbody tr td[colspan]').length > 0) { return; }
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: langue,
                    order: [[0, $table.data('ordre') === 'desc' ? 'desc' : 'asc']],
                });
            });

            // Transmission : une confirmation, parce qu'elle fige les écritures.
            $('form.js-confirmer').on('submit', function (evenement) {
                var formulaire = this;
                if (formulaire.dataset.confirme) { return; }
                evenement.preventDefault();
                Swal.fire({
                    title: formulaire.getAttribute('data-confirm-title'),
                    text: formulaire.getAttribute('data-confirm-text'),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, transmettre',
                    cancelButtonText: 'Annuler',
                }).then(function (reponse) {
                    if (reponse.isConfirmed) {
                        formulaire.dataset.confirme = '1';
                        formulaire.submit();
                    }
                });
            });

            // Rejet d'un déversement : le motif est obligatoire.
            $('.js-rejeter').on('click', function () {
                var action = this.getAttribute('data-action');
                var numero = this.getAttribute('data-numero');
                Swal.fire({
                    title: 'Rejeter le déversement ' + numero + ' ?',
                    text: 'Ses écritures repartiront à « à transmettre ». Indiquez le motif du refus du logiciel comptable.',
                    input: 'text',
                    inputPlaceholder: 'Motif du rejet',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Rejeter',
                    cancelButtonText: 'Annuler',
                    inputValidator: function (valeur) {
                        return valeur ? null : 'Le motif est obligatoire.';
                    },
                }).then(function (reponse) {
                    if (!reponse.isConfirmed) { return; }
                    var formulaire = document.getElementById('formulaireRejet');
                    formulaire.action = action;
                    document.getElementById('motifRejet').value = reponse.value;
                    formulaire.submit();
                });
            });
        });
    </script>
@endsection
