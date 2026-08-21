@php
    use Illuminate\Support\Str;

    // Petite aide pour construire les liens de filtre en gardant l'onglet courant
    // visible sans dupliquer la route à chaque bouton.
    $lien = fn ($valeur) => $valeur === null
        ? route('show.moderationCommentairesBlog')
        : route('show.moderationCommentairesBlog', ['statut' => $valeur]);
@endphp

@extends('layout.main')
@section('title', 'Modération des commentaires de blog')

@section('contenu')
    <x-notify::notify />

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Modération des <span class="dash-welcome-name">commentaires de blog</span> 💬
                </h2>
                <p class="dash-welcome-subtitle">
                    Tous articles confondus — un commentaire n'est visible sur le site qu'une fois publié.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.listeDesBlogs') }}" class="btn btn-light">
                    <i class="material-icons md-article"></i> Liste des blogs
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

    {{-- ===== COMPTEURS CLIQUABLES (ils servent aussi de filtre) ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="{{ $lien('1') }}" class="text-decoration-none">
                <div class="kpi-card kpi-card-warning">
                    <div class="kpi-card-icon"><i class="material-icons md-hourglass_empty"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">En attente</div>
                        <div class="kpi-card-value">{{ $nbEnAttente }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ $lien('2') }}" class="text-decoration-none">
                <div class="kpi-card kpi-card-success">
                    <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">Publiés</div>
                        <div class="kpi-card-value">{{ $nbPublies }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ $lien('3') }}" class="text-decoration-none">
                <div class="kpi-card kpi-card-primary">
                    <div class="kpi-card-icon"><i class="material-icons md-block"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">Refusés</div>
                        <div class="kpi-card-value">{{ $nbRefuses }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ $lien('corbeille') }}" class="text-decoration-none">
                <div class="kpi-card">
                    <div class="kpi-card-icon"><i class="material-icons md-delete"></i></div>
                    <div class="kpi-card-body">
                        <div class="kpi-card-label">Corbeille</div>
                        <div class="kpi-card-value">{{ $nbCorbeille }}</div>
                    </div>
                    <div class="kpi-card-shape"></div>
                </div>
            </a>
        </div>
    </div>

    {{-- ===== FILTRE COURANT ===== --}}
    <div class="mb-3">
        <a href="{{ $lien(null) }}" class="btn btn-sm {{ $statutFiltre ? 'btn-outline-secondary' : 'btn-secondary' }}">Tous</a>
        <a href="{{ $lien('1') }}" class="btn btn-sm {{ $statutFiltre === '1' ? 'btn-warning' : 'btn-outline-warning' }}">En attente</a>
        <a href="{{ $lien('2') }}" class="btn btn-sm {{ $statutFiltre === '2' ? 'btn-success' : 'btn-outline-success' }}">Publiés</a>
        <a href="{{ $lien('3') }}" class="btn btn-sm {{ $statutFiltre === '3' ? 'btn-danger' : 'btn-outline-danger' }}">Refusés</a>
        <a href="{{ $lien('corbeille') }}" class="btn btn-sm {{ $statutFiltre === 'corbeille' ? 'btn-dark' : 'btn-outline-dark' }}">Corbeille</a>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="moderationBlog">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Article</th>
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
                                <td>{{ $commentaire->id }}</td>
                                <td>
                                    @if ($commentaire->blog)
                                        <a href="{{ route('show.commentaireBlogs', $commentaire->blog->id) }}"
                                           class="text-decoration-none">
                                            {{ Str::limit($commentaire->blog->titre, 45) }}
                                        </a>
                                        @if ($commentaire->blog->trashed())
                                            <span class="badge bg-dark ms-1">article à la corbeille</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Article supprimé</span>
                                    @endif
                                </td>
                                <td>{{ $commentaire->client->display_name }}</td>
                                <td>{{ $commentaire->commentaire }}</td>
                                <td class="text-center">{{ $commentaire->note ? $commentaire->note.'/5' : '—' }}</td>
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
                                <td colspan="8" class="text-center text-muted">Aucun commentaire pour ce filtre.</td>
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
            var $table = $('#moderationBlog');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[5, 'desc']],
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' },
                        { orderable: false, targets: [3, 7] }
                    ]
                });
            }
        });
    </script>
    @notifyJs
@endsection
