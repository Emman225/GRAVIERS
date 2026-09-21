{{--
    LE JOURNAL D'AUDIT — filtres, tableau et fenêtre de détail.

    Ce bloc était le corps de la page « /audit ». Il vit maintenant dans
    l'onglet « Audit » de « Paramètre », à la demande du 29/08/2026. Il reste
    un partiel plutôt qu'un écran pour qu'il n'existe qu'UNE version du
    journal : deux copies finissent toujours par diverger.

    - $audits, $utilisateurs, $actions, $filtres, $limite : voir Audit::journal()
    - $actionFiltre : l'adresse vers laquelle le formulaire de filtre renvoie
    - $champsCaches : couples nom/valeur à reporter dans le formulaire (par
      exemple l'onglet à rouvrir), pour que filtrer ne fasse pas perdre sa page.

    LA FENÊTRE DE DÉTAIL N'EST PAS ICI : elle vit dans
    `admin/audit/_fenetre-detail.blade.php`, à inclure HORS du panneau
    d'onglet — sans quoi elle tremble (deux Bootstrap en concurrence).
--}}
@php
    $total         = $audits->count();
    $champsCaches  = $champsCaches ?? [];
@endphp

{{-- ===== FILTRES ===== --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filtrer</h5>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ $actionFiltre }}" class="row g-3">
            @foreach ($champsCaches as $nom => $valeur)
                <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
            @endforeach

            <div class="col-md-3">
                <label class="form-label" for="audit_user_id">Utilisateur</label>
                <select class="form-control" name="user_id" id="audit_user_id">
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
                <label class="form-label" for="audit_action">Type d'action</label>
                <select class="form-control" name="action" id="audit_action">
                    <option value="">Toutes</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected(($filtres['action'] ?? '') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label" for="audit_du">Du</label>
                <input type="date" class="form-control" name="du" id="audit_du" value="{{ $filtres['du'] ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label" for="audit_au">Au</label>
                <input type="date" class="form-control" name="au" id="audit_au" value="{{ $filtres['au'] ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label" for="audit_recherche">Recherche libre</label>
                <input type="text" class="form-control" name="recherche" id="audit_recherche"
                       placeholder="Nom, action, URL, IP…" value="{{ $filtres['recherche'] ?? '' }}">
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="material-icons md-search"></i> Filtrer
                </button>
                <a href="{{ $actionFiltre }}{{ $champsCaches ? '?' . http_build_query($champsCaches) : '' }}"
                   class="btn btn-light">Réinitialiser</a>
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

        <x-export-buttons table-id="journalAudit"
                          filename="journal-d-audit"
                          title="Journal d'audit" />

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
                                {{-- PAS DE « data-bs-toggle » ICI.
                                     Bootstrap 4 et 5 sont chargés ensemble sur cet
                                     écran : les deux répondaient à l'attribut, chacun
                                     posait son voile et son piège à focus, et la
                                     fenêtre tremblait. L'ouverture passe par le même
                                     mécanisme manuel que les autres fenêtres de la
                                     page « Paramètre ». --}}
                                <button type="button" class="btn btn-sm btn-info voir-audit"
                                        data-modal-id="modalAudit"
                                        data-recit="{{ $a->recit() }}"
                                        data-elements="{{ json_encode($a->elementsLisibles(), JSON_UNESCAPED_UNICODE) }}"
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
