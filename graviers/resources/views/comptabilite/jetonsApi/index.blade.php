@extends('layout.main')
@section('title', 'Jetons API comptable')

@php
    use App\Http\Controllers\JetonsApiComptableController;
    $base = rtrim(config('app.url'), '/') . '/api/comptabilite';
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Jetons <span class="dash-welcome-name">API comptable</span></h2>
                <p class="dash-welcome-subtitle">
                    Le logiciel comptable de DALAKOUN récupère les écritures par cette API. Un jeton en donne l'accès :
                    créez-le ici, jamais dans le code de l'intégrateur avant qu'il n'existe.
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul></div>
    @endif

    @if (session('jetonEnClair'))
        <div class="alert alert-warning">
            <strong>Votre jeton :</strong>
            <code id="jetonEnClair" class="d-inline-block mt-1 mb-1 p-2 bg-white border rounded" style="user-select:all;">{{ session('jetonEnClair') }}</code>
            <button type="button" class="btn btn-sm btn-primary ms-2" onclick="navigator.clipboard.writeText(document.getElementById('jetonEnClair').textContent.trim())">
                <i class="material-icons md-content_copy"></i> Copier
            </button>
            <div class="mt-1">Il ne sera plus jamais affiché : transmettez-le à l'intégrateur maintenant, puis actualisez la page.</div>
        </div>
    @endif

    {{-- ===== CRÉATION ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-vpn_key text-primary"></i> Nouveau jeton</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('show.comptabilite.jetonsApi.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" for="nom">Nom du jeton <span class="text-danger">*</span></label>
                    <input type="text" name="nom" id="nom" class="form-control" required maxlength="100" placeholder="Sage — comptable DALAKOUN">
                    <small class="text-muted">Un nom qui dit qui l'utilise : il apparaît seul dans la liste, jamais le jeton lui-même.</small>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ecriture" value="1" id="ecriture">
                        <label class="form-check-label" for="ecriture">
                            Autoriser l'accusé de réception <small class="text-muted d-block">(sinon, lecture seule)</small>
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="material-icons md-add"></i> Créer le jeton</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== LISTE ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-list text-primary"></i> Jetons actifs</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0">
                    <thead><tr><th>Nom</th><th>Aptitudes</th><th>Créé le</th><th>Dernier appel</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($jetons as $jeton)
                            <tr>
                                <td><strong>{{ $jeton->name }}</strong></td>
                                <td>
                                    @foreach ((array) $jeton->abilities as $aptitude)
                                        <span class="badge bg-info text-dark">{{ $aptitude }}</span>
                                    @endforeach
                                </td>
                                <td>{{ \Help::dateHeure($jeton->created_at) }}</td>
                                <td>{{ $jeton->last_used_at ? \Help::dateHeure($jeton->last_used_at) : 'Jamais utilisé' }}</td>
                                <td class="text-end">
                                    <form action="{{ route('show.comptabilite.jetonsApi.revoquer', $jeton) }}" method="POST" class="d-inline js-delete-form"
                                          data-item-name="le jeton « {{ $jeton->name }} »"
                                          data-confirm-text="Toute application qui présente ce jeton cessera aussitôt d'accéder à l'API comptable.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger rounded" title="Révoquer le jeton">
                                            <i class="material-icons md-delete"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Aucun jeton créé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== DOCUMENTATION ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-description text-primary"></i> Documentation pour l'intégrateur</h5>
        </div>
        <div class="card-body">
            <p>
                Chaque appel porte l'en-tête <code>Authorization: Bearer &lt;jeton&gt;</code> et <code>Accept: application/json</code>.
                Un jeton absent ou révoqué répond <strong>401</strong> ; un compte non administrateur, ou un jeton sans l'aptitude requise, répond <strong>403</strong>.
            </p>
            <p>
                La période se choisit par mois (<code>mode_periode=MOIS&amp;periode=2026-09</code>) ou par dates
                (<code>mode_periode=DATES&amp;du=2026-09-01&amp;au=2026-09-30</code>) ; sans rien préciser, le mois en cours est pris.
            </p>

            <div class="table-responsive mb-3">
                <table class="table dash-table align-middle mb-0">
                    <thead><tr><th>Méthode</th><th>Adresse</th><th>Rôle</th><th>Aptitude</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge bg-primary">GET</span></td><td><code>/ecritures</code></td><td>Liste, paginée, filtrable par période, état, origine, journal, service.</td><td>lecture</td></tr>
                        <tr><td><span class="badge bg-primary">GET</span></td><td><code>/ecritures/{identifiant}</code></td><td>Le détail d'une écriture, par son identifiant stable (ex. <code>FAC-128</code>).</td><td>lecture</td></tr>
                        <tr><td><span class="badge bg-primary">GET</span></td><td><code>/ecritures/export</code></td><td>La période entière, sans pagination — <code>format=json|csv|sage</code>.</td><td>lecture</td></tr>
                        <tr><td><span class="badge bg-primary">GET</span></td><td><code>/deversements</code></td><td>Les transmissions déjà enregistrées.</td><td>lecture</td></tr>
                        <tr><td><span class="badge bg-primary">GET</span></td><td><code>/deversements/{numero}</code></td><td>Le détail d'un déversement et ses écritures.</td><td>lecture</td></tr>
                        <tr><td><span class="badge bg-warning text-dark">POST</span></td><td><code>/deversements</code></td><td>Accuse réception d'une période : ses écritures passent à « exportée ». Refusé si la période porte une anomalie.</td><td>écriture</td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold">Exemple</h6>
            <pre class="bg-light p-3 rounded"><code>curl -H "Authorization: Bearer VOTRE_JETON" -H "Accept: application/json" \
  "{{ $base }}/ecritures?mode_periode=MOIS&periode=2026-09"

curl -X POST -H "Authorization: Bearer VOTRE_JETON" -H "Accept: application/json" \
  -d "mode_periode=MOIS&periode=2026-09&format=json" \
  "{{ $base }}/deversements"</code></pre>
        </div>
    </div>
@endsection
