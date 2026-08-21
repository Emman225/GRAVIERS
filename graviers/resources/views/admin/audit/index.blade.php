@php
    use Carbon\Carbon;

    $total = $audits->count();
@endphp

@extends('layout.main')
@section('title', "Journal d'audit")

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Journal <span class="dash-welcome-name">d'audit</span> 🔎
                </h2>
                <p class="dash-welcome-subtitle">
                    Opérations d'écriture du back-office — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== FILTRES ===== --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filtrer</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('show.audit.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="user_id">Utilisateur</label>
                    <select class="form-control" name="user_id" id="user_id">
                        <option value="">Tous</option>
                        @foreach ($utilisateurs as $u)
                            <option value="{{ $u->user_id }}"
                                @selected(($filtres['user_id'] ?? '') == $u->user_id)>
                                {{ $u->nom_utilisateur }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="action">Type d'action</label>
                    <select class="form-control" name="action" id="action">
                        <option value="">Toutes</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected(($filtres['action'] ?? '') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="du">Du</label>
                    <input type="date" class="form-control" name="du" id="du" value="{{ $filtres['du'] ?? '' }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="au">Au</label>
                    <input type="date" class="form-control" name="au" id="au" value="{{ $filtres['au'] ?? '' }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="recherche">Recherche libre</label>
                    <input type="text" class="form-control" name="recherche" id="recherche"
                           placeholder="Nom, action, URL, IP…" value="{{ $filtres['recherche'] ?? '' }}">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons md-search"></i> Filtrer
                    </button>
                    <a href="{{ route('show.audit.index') }}" class="btn btn-light">Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== JOURNAL ===== --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Opérations enregistrées</h5>
            <span class="badge bg-primary">{{ $total }}</span>
        </div>
        <div class="card-body">
            @if ($total >= $limite)
                <div class="alert alert-info">
                    Seules les {{ $limite }} opérations les plus récentes sont affichées.
                    Affinez les filtres pour remonter plus loin.
                </div>
            @endif

            <div class="table-responsive">
                <table class="table" id="journalAudit">
                    <thead>
                        <tr>
                            <th>Date et heure</th>
                            <th>Utilisateur</th>
                            <th>Profil</th>
                            <th>Action</th>
                            <th class="text-center">Adresse IP</th>
                            <th class="text-center">Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($audits as $a)
                            <tr>
                                <td>{{ $a->created_at ? $a->created_at->format('d/m/Y H:i:s') : '-' }}</td>
                                <td>{{ $a->nom_utilisateur ?? '-' }}</td>
                                <td>{{ $a->typeUtilisateur?->nom ?? '-' }}</td>
                                <td>{{ $a->action }}</td>
                                <td class="text-center">{{ $a->adresse_ip ?? '-' }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-info voir-audit"
                                            data-bs-toggle="modal" data-bs-target="#modalAudit"
                                            data-methode="{{ $a->methode ?? '-' }}"
                                            data-url="{{ $a->url ?? '-' }}"
                                            data-route="{{ $a->route_name ?? '-' }}"
                                            data-agent="{{ $a->user_agent ?? '-' }}"
                                            data-donnees="{{ $a->donnees ? json_encode($a->donnees, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '' }}">
                                        Détails
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucune opération ne correspond à ces critères.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== MODAL DÉTAILS =====
         Une seule fenêtre, remplie au clic : en générer une par ligne
         alourdirait la page de plusieurs centaines de blocs. --}}
    <div class="modal fade" id="modalAudit" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détail de l'opération</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <dl class="row">
                        <dt class="col-sm-3">Méthode</dt>
                        <dd class="col-sm-9" id="auditMethode">-</dd>

                        <dt class="col-sm-3">URL</dt>
                        <dd class="col-sm-9 text-break" id="auditUrl">-</dd>

                        <dt class="col-sm-3">Route</dt>
                        <dd class="col-sm-9" id="auditRoute">-</dd>

                        <dt class="col-sm-3">Navigateur</dt>
                        <dd class="col-sm-9 text-break small" id="auditAgent">-</dd>
                    </dl>

                    <h6 class="mt-3">Données</h6>
                    <pre class="bg-light p-3 rounded small" id="auditDonnees" style="max-height:340px; overflow:auto;">-</pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                </div>
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
        $(function() {
            var $table = $('#journalAudit');

            // Garde-fou : DataTables lève « Requested unknown parameter » quand
            // le tableau ne contient que la ligne « aucun résultat », dont le
            // colspan ne correspond à aucune colonne déclarée.
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    // Le tri vient du serveur (du plus récent au plus ancien) :
                    // on ne le rejoue pas ici, la date étant au format jj/mm/aaaa
                    // que DataTables trierait comme du texte.
                    order: [],
                    pageLength: 25,
                });
            }

            $(document).on('click', '.voir-audit', function() {
                var $b = $(this);
                $('#auditMethode').text($b.data('methode') || '-');
                $('#auditUrl').text($b.data('url') || '-');
                $('#auditRoute').text($b.data('route') || '-');
                $('#auditAgent').text($b.data('agent') || '-');

                var donnees = $b.attr('data-donnees');
                $('#auditDonnees').text(donnees && donnees.length ? donnees : 'Aucune donnée enregistrée.');

                // L'ouverture est déclarative (data-bs-toggle), comme dans les
                // autres écrans : la version de Bootstrap embarquée ici n'expose
                // pas Modal.getOrCreateInstance, et l'appeler ne faisait rien.
            });
        });
    </script>
@endsection
