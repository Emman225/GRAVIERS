@php
    use Illuminate\Support\Carbon;
    $totalBlogs    = $blogs->count();
    $totalEnLigne  = $blogs->where('publie', true)->count();
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
                            <th class="text-center">Statut</th>
                            <th class="text-end">Actions</th>
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
                                <td class="text-center">
                                    @if ($blog->publie)
                                        <span class="badge bg-success">En ligne</span>
                                    @else
                                        <span class="badge bg-secondary">Hors ligne</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="dropdown">
                                        <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm">
                                            <i class="material-icons md-more_horiz"></i> Actions
                                        </a>
                                        <div class="dropdown-menu">
                                            <a href="{{ route('show.modificationDeBlogPage', $blog->id) }}" class="dropdown-item">
                                                <i class="material-icons md-edit"></i> Modifier
                                            </a>
                                            @if ($blog->publie)
                                                <a href="{{ route('show.supprimerPublierBlog', $blog->id) }}"
                                                   class="dropdown-item text-danger"
                                                   data-confirm-msg="Voulez-vous vraiment retirer le blog « {{ $blog->titre }} » du site ?">
                                                    <i class="material-icons md-visibility_off"></i> Retirer
                                                </a>
                                            @else
                                                <a href="{{ route('show.supprimerPublierBlog', $blog->id) }}"
                                                   class="dropdown-item text-success"
                                                   data-confirm-msg="Voulez-vous vraiment republier le blog « {{ $blog->titre }} » sur le site ?">
                                                    <i class="material-icons md-public"></i> Republier
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
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
                        { orderable: false, targets: [0, 4] }
                    ]
                });
            }
        });
    </script>
    @notifyJs
@endsection
