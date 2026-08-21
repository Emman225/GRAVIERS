@php
    use Illuminate\Support\Carbon;
    // La liste inclut désormais les blogs mis à la corbeille : on les compte à part
    // pour que « en ligne » et « hors ligne » gardent leur sens.
    $totalCorbeille = $blogs->filter(fn ($b) => $b->trashed())->count();
    $actifs         = $blogs->reject(fn ($b) => $b->trashed());
    $totalBlogs     = $actifs->count();
    $totalEnLigne   = $actifs->where('publie', true)->count();
    $totalHorsLigne = $totalBlogs - $totalEnLigne;
@endphp

@extends('layout.main')
@section('title', 'Liste des blogs')

@section('contenu')
    <x-notify::notify />

    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Liste des <span class="dash-welcome-name">Blogs</span> 📝
                </h2>
                <p class="dash-welcome-subtitle">
                    Articles publiés sur le site — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.creationDeBlog') }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Nouveau blog
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-article"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total blogs</div>
                    <div class="kpi-card-value">{{ $totalBlogs }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En ligne</div>
                    <div class="kpi-card-value">{{ $totalEnLigne }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-visibility_off"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Hors ligne</div>
                    <div class="kpi-card-value">{{ $totalHorsLigne }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="listeBlogs">
                    <thead>
                        <tr>
                            <th class="text-center">Image</th>
                            <th>Titre</th>
                            <th class="text-center">Date de publication</th>
                            <th class="text-center">Commentaires</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($blogs as $blog)
                            <tr>
                                <td class="text-center">
                                    @if ($blog->image)
                                        <img src="{{ asset('storage/' . $blog->image) }}"
                                             class="img-sm img-thumbnail"
                                             style="width:60px;height:60px;object-fit:cover;border-radius:6px;"
                                             alt="{{ $blog->titre }}" />
                                    @else
                                        <div style="width:60px;height:60px;background:#f3f4f6;border-radius:6px;display:flex;align-items:center;justify-content:center;margin:auto;">
                                            <i class="material-icons md-image" style="color:#9ca3af;"></i>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('show.commentaireBlogs', $blog->id) }}" class="text-decoration-none">
                                        <strong>{{ $blog->titre }}</strong>
                                    </a>
                                </td>
                                <td class="text-center">{{ $blog->created_at?->isoFormat('LL') }}</td>

                                {{-- Repère de modération : le nombre en attente saute aux yeux
                                     sans avoir à ouvrir chaque article. --}}
                                <td class="text-center">
                                    <a href="{{ route('show.commentaireBlogs', $blog->id) }}" class="text-decoration-none">
                                        <span class="badge bg-light text-dark">{{ $blog->nb_commentaires }}</span>
                                        @if ($blog->nb_commentaires_attente > 0)
                                            <span class="badge bg-warning">{{ $blog->nb_commentaires_attente }} en attente</span>
                                        @endif
                                    </a>
                                </td>

                                <td class="text-center">
                                    {{-- Un blog à la corbeille n'apparaît plus sur le site,
                                         quel que soit son état de publication. --}}
                                    @if ($blog->trashed())
                                        <span class="badge bg-dark">Corbeille</span>
                                    @elseif ($blog->publie)
                                        <span class="badge bg-success">En ligne</span>
                                    @else
                                        <span class="badge bg-secondary">Hors ligne</span>
                                    @endif
                                </td>

                                {{-- Colonne Action : commandes en icônes, avec bulle d'aide
                                     au survol, comme sur la liste des bannières. --}}
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('show.modificationDeBlogPage', $blog->id) }}"
                                       class="btn btn-sm btn-primary" title="Modifier">
                                        <i class="material-icons md-edit"></i>
                                    </a>

                                    <a href="{{ route('show.commentaireBlogs', $blog->id) }}"
                                       class="btn btn-sm btn-info" title="Voir les commentaires">
                                        <i class="material-icons md-comment"></i>
                                    </a>

                                    @if (!$blog->trashed())
                                        @if ($blog->publie)
                                            <a href="{{ route('show.supprimerPublierBlog', ['id' => $blog->id]) }}"
                                               class="btn btn-sm btn-warning" title="Retirer du site"
                                               data-confirm-msg="Voulez-vous vraiment retirer le blog « {{ $blog->titre }} » du site ?">
                                                <i class="material-icons md-visibility_off"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('show.supprimerPublierBlog', ['id' => $blog->id]) }}"
                                               class="btn btn-sm btn-success" title="Republier"
                                               data-confirm-msg="Voulez-vous vraiment republier le blog « {{ $blog->titre }} » sur le site ?">
                                                <i class="material-icons md-public"></i>
                                            </a>
                                        @endif

                                        <a href="{{ route('show.supprimerPublierBlog', ['id' => $blog->id, 'action' => 'supprimer']) }}"
                                           class="btn btn-sm btn-danger" title="Mettre à la corbeille">
                                            <i class="material-icons md-delete"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('show.supprimerPublierBlog', ['id' => $blog->id, 'action' => 'supprimer']) }}"
                                           class="btn btn-sm btn-success" title="Restaurer">
                                            <i class="material-icons md-restore_from_trash"></i>
                                        </a>

                                        {{-- Suppression définitive : en DELETE avec confirmation.
                                             Elle emporte aussi les images et les commentaires. --}}
                                        <form action="{{ route('show.suppressionDefinitiveBlog', $blog->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Supprimer définitivement ce blog, ses images et ses commentaires ? Cette action est irréversible.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-dark" title="Supprimer définitivement">
                                                <i class="material-icons md-delete_forever"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucun blog publié. <a href="{{ route('show.creationDeBlog') }}">Créer le premier</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
            var $table = $('#listeBlogs');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[2, 'desc']],
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' }, // évite l'erreur "unknown parameter" sur table vide
                        { orderable: false, targets: [0, 5] }
                    ]
                });
            }
        });
    </script>
    @notifyJs
@endsection
