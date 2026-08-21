@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    // Les diapositives à la corbeille sont chargées pour pouvoir être restaurées,
    // mais ne comptent dans aucun total.
    $corbeille  = $slides->filter(fn ($s) => $s->trashed())->count();
    $actives    = $slides->reject(fn ($s) => $s->trashed());
    $total      = $actives->count();
    $enLigne    = $actives->where('statut', 1)->count();
    $horsLigne  = $total - $enLigne;
@endphp

@extends('layout.main')
@section('title', 'Diapositives du carrousel')

@section('contenu')
    <x-notify::notify />

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Carrousel de la <span class="dash-welcome-name">page d'accueil</span> 🖼️
                </h2>
                <p class="dash-welcome-subtitle">
                    Les diapositives s'affichent dans l'ordre indiqué — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.creationDeSlide') }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Ajout diapositive
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ===== COMPTEURS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-image"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total diapositives</div>
                    <div class="kpi-card-value">{{ $total }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Affichées</div>
                    <div class="kpi-card-value">{{ $enLigne }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon"><i class="material-icons md-visibility_off"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Retirées</div>
                    <div class="kpi-card-value">{{ $horsLigne }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    @if ($corbeille > 0)
        <div class="alert alert-secondary py-2">
            <i class="material-icons md-delete align-middle"></i>
            {{ $corbeille }} diapositive{{ $corbeille > 1 ? 's' : '' }} à la corbeille.
        </div>
    @endif

    @if ($enLigne === 0 && $total > 0)
        <div class="alert alert-warning">
            <i class="material-icons md-warning align-middle"></i>
            Aucune diapositive n'est affichée : le carrousel de la page d'accueil est vide.
        </div>
    @endif

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="listeSlides">
                    <thead>
                        <tr>
                            <th class="text-center">Ordre</th>
                            <th class="text-center">Image</th>
                            <th>Titre</th>
                            <th>Pastille</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($slides as $slide)
                            <tr>
                                <td class="text-center"><strong>{{ $slide->num_ordre }}</strong></td>
                                <td class="text-center">
                                    @if ($slide->image)
                                        <img src="{{ $slide->urlImage() }}"
                                             style="width:96px;height:54px;object-fit:cover;border-radius:6px;"
                                             alt="{{ $slide->titre }}" />
                                    @else
                                        <div style="width:96px;height:54px;background:#f3f4f6;border-radius:6px;margin:auto;"></div>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $slide->titre }}</strong>
                                    @if ($slide->titre_accent)
                                        <br><span class="text-primary">{{ $slide->titre_accent }}</span>
                                    @endif
                                    @if ($slide->description)
                                        <br><small class="text-muted">{{ Str::limit($slide->description, 70) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($slide->badge_texte)
                                        <span class="badge bg-info">{{ $slide->badge_texte }}</span>
                                        <br><small class="text-muted">{{ $slide->badge_type }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($slide->trashed())
                                        <span class="badge bg-dark">Corbeille</span>
                                    @elseif ($slide->statut == 1)
                                        <span class="badge bg-success">Affichée</span>
                                    @else
                                        <span class="badge bg-secondary">Retirée</span>
                                    @endif
                                </td>

                                <td class="text-center text-nowrap">
                                    <a href="{{ route('show.modificationDeSlidePage', $slide->id) }}"
                                       class="btn btn-sm btn-primary" title="Modifier">
                                        <i class="material-icons md-edit"></i>
                                    </a>

                                    @if (!$slide->trashed())
                                        @if ($slide->statut == 1)
                                            <a href="{{ route('show.supprimerPublierSlide', ['id' => $slide->id]) }}"
                                               class="btn btn-sm btn-warning" title="Retirer du carrousel">
                                                <i class="material-icons md-visibility_off"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('show.supprimerPublierSlide', ['id' => $slide->id]) }}"
                                               class="btn btn-sm btn-success" title="Remettre en ligne">
                                                <i class="material-icons md-visibility"></i>
                                            </a>
                                        @endif

                                        <a href="{{ route('show.supprimerPublierSlide', ['id' => $slide->id, 'action' => 'supprimer']) }}"
                                           class="btn btn-sm btn-danger" title="Mettre à la corbeille">
                                            <i class="material-icons md-delete"></i>
                                        </a>
                                    @else
                                        <a href="{{ route('show.supprimerPublierSlide', ['id' => $slide->id, 'action' => 'supprimer']) }}"
                                           class="btn btn-sm btn-success" title="Restaurer">
                                            <i class="material-icons md-restore_from_trash"></i>
                                        </a>

                                        {{-- js-delete-form : la boîte de dialogue passe par SweetAlert2
                                             (public/backend/assets/js/delete-confirm.js, motif D), comme
                                             sur les autres écrans d'administration. L'ancien
                                             onsubmit="return confirm(...)" affichait la fenêtre grise
                                             du navigateur, que ce script n'intercepte pas : il ne
                                             reprend que les onclick. --}}
                                        <form action="{{ route('show.suppressionDefinitiveSlide', $slide->id) }}" method="POST" class="d-inline js-delete-form"
                                              data-item-name="{{ $slide->titre }}"
                                              data-confirm-text="La diapositive et son image seront supprimées définitivement. Cette action est irréversible.">
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
                                    Aucune diapositive. <a href="{{ route('show.creationDeSlide') }}">Créer la première</a>.
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
            var $table = $('#listeSlides');
            // Garde-fou : initialiser DataTables sur un tableau sans ligne
            // déclenche « Requested unknown parameter ».
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'asc']],
                    columnDefs: [
                        { targets: '_all', defaultContent: '-' },
                        { orderable: false, targets: [1, 5] }
                    ]
                });
            }
        });
    </script>
    @notifyJs
@endsection
