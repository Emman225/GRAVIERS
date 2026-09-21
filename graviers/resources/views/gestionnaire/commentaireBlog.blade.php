@php
    use Illuminate\Support\Carbon;

    // Les commentaires à la corbeille sont chargés eux aussi (pour pouvoir les
    // restaurer) mais ne comptent dans aucun compteur.
    $actifs       = $commentaires->reject(fn ($c) => $c->trashed());
    $nbEnAttente  = $actifs->where('statut', \App\Models\blog_commentaire::EN_ATTENTE)->count();
    $nbPublies    = $actifs->where('statut', \App\Models\blog_commentaire::PUBLIE)->count();
    $nbRefuses    = $actifs->where('statut', \App\Models\blog_commentaire::REFUSE)->count();
    $nbCorbeille  = $commentaires->count() - $actifs->count();
@endphp

@extends('layout.main')
@section('title', 'Commentaires de l\'article')

@section('contenu')
    <x-notify::notify />

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Commentaires de <span class="dash-welcome-name">{{ $blog->titre }}</span> 💬
                </h2>
                <p class="dash-welcome-subtitle">
                    Un commentaire n'apparaît sur le site public qu'une fois publié ici.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.listeDesBlogs') }}" class="btn btn-light">
                    <i class="material-icons md-arrow_back"></i> Liste des blogs
                </a>
                <a href="{{ route('show.moderationCommentairesBlog') }}" class="btn btn-primary">
                    <i class="material-icons md-comment"></i> Tous les commentaires
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('fail'))
        <div class="alert alert-warning">{{ session('fail') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- ===== COMPTEURS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-hourglass_empty"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En attente</div>
                    <div class="kpi-card-value">{{ $nbEnAttente }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Publiés</div>
                    <div class="kpi-card-value">{{ $nbPublies }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-block"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Refusés</div>
                    <div class="kpi-card-value">{{ $nbRefuses }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    @if ($nbCorbeille > 0)
        <div class="alert alert-secondary py-2">
            <i class="material-icons md-delete align-middle"></i>
            {{ $nbCorbeille }} commentaire{{ $nbCorbeille > 1 ? 's' : '' }} à la corbeille.
        </div>
    @endif

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="listeCommentairesBlog"
                              filename="commentaires-blog"
                              title="Commentaires du blog" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="listeCommentairesBlog">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Commentaire</th>
                            <th class="text-center">Note</th>
                            <th class="text-center">Date</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($commentaires as $commentaire)
                            <tr>
                                {{-- L'ancienne version affichait le mot « identifiant » en
                                     dur dans cette colonne. --}}
                                <td>{{ $commentaire->id }}</td>
                                <td>{{ $commentaire->client->display_name }}</td>
                                <td><b>{{ $commentaire->commentaire }}</b></td>
                                <td class="text-center">
                                    {{ $commentaire->note ? $commentaire->note.'/5' : '—' }}
                                </td>
                                <td class="text-center">{{ $commentaire->created_at?->isoFormat('LL') }}</td>
                                <td class="text-center">
                                    @if ($commentaire->trashed())
                                        <span class="badge bg-dark">Corbeille</span>
                                    @else
                                        <span class="badge bg-{{ $commentaire->couleurStatut() }}">{{ $commentaire->libelleStatut() }}</span>
                                    @endif
                                </td>
                                @include('gestionnaire.partials.actionsCommentaireBlog', ['commentaire' => $commentaire])
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Aucun commentaire sur cet article.</td>
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
            var $table = $('#listeCommentairesBlog');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[4, 'desc']],
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' },
                        { orderable: false, targets: [2, 6] }
                    ]
                });
            }
        });
    </script>
    @notifyJs
@endsection
