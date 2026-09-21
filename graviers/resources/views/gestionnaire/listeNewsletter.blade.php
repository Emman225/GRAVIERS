@extends('layout.main')
@section('title','Lettre d\'information')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Lettre d'information</h2>
            <p class="text-muted mb-0">
                {{ $abonnes->count() }} adresse(s) — dont {{ $abonnes->where('statut', 1)->whereNull('deleted_at')->count() }} abonnée(s)
            </p>
        </div>
        {{-- LES BOUTONS D'EXPORT ONT REJOINT LE TABLEAU.
             Ils étaient ici, en haut à droite ; le balayage du 29/08/2026 en a
             posé un second jeu au-dessus du tableau, comme sur toutes les
             autres listes. Deux jeux pour la même chose : celui-ci part.
             Mais ce sont bien CES adresses-là que l'autre jeu appelle
             désormais — voir « excel-url / word-url / pdf-url » plus bas. --}}
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center" id="notify">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger text-center">{{ session('error') }}</div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    {{-- LES DOCUMENTS VIENNENT DU SERVEUR, ET NON DU TABLEAU.
                         L'écran affiche AUSSI la corbeille (`withTrashed`) :
                         un export reconstitué depuis les lignes affichées y
                         embarquerait les adresses mises au rebut. Les trois
                         adresses ci-dessous produisent la liste entière, la
                         corbeille exclue. --}}
                    <x-export-buttons table-id="listeNewsletter"
                                      filename="abonnes-lettre-information"
                                      title="Abonnés à la lettre d information"
                                      :excel-url="route('newsletter.exportExcel')"
                                      :word-url="route('newsletter.exportWord')"
                                      :pdf-url="route('newsletter.exportPdf')" />
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="listeNewsletter">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Adresse e-mail</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Statut</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Origine</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Inscrit le</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($abonnes as $i => $abonne)
                                    <tr>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td>{{ $abonne->email }}</td>
                                        <td class="text-center">
                                            @if ($abonne->trashed())
                                                <span class="badge bg-dark">Corbeille</span>
                                            @elseif ($abonne->statut == 1)
                                                <span class="badge bg-success">Abonné</span>
                                            @else
                                                <span class="badge bg-secondary">Désabonné</span>
                                            @endif
                                        </td>
                                        <td>{{ $abonne->origine ?: '—' }}</td>
                                        <td class="text-center">
                                            {{ $abonne->created_at ? $abonne->created_at->format('d/m/Y H:i:s') : '—' }}
                                        </td>
                                        <td class="text-center text-nowrap">
                                            @if (!$abonne->trashed())
                                                @if ($abonne->statut == 1)
                                                    <a href="{{ route('newsletter.basculerStatut', $abonne->id) }}"
                                                       class="btn btn-sm btn-warning" title="Désabonner">
                                                        <i class="material-icons md-visibility_off"></i>
                                                    </a>
                                                @else
                                                    <a href="{{ route('newsletter.basculerStatut', $abonne->id) }}"
                                                       class="btn btn-sm btn-success" title="Réabonner">
                                                        <i class="material-icons md-visibility"></i>
                                                    </a>
                                                @endif

                                                <a href="{{ route('newsletter.supprimer', $abonne->id) }}"
                                                   class="btn btn-sm btn-danger" title="Mettre à la corbeille">
                                                    <i class="material-icons md-delete"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('newsletter.supprimer', $abonne->id) }}"
                                                   class="btn btn-sm btn-success" title="Restaurer">
                                                    <i class="material-icons md-restore_from_trash"></i>
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
        $(function () {
            var $table = $('#listeNewsletter');

            // Garde-fou : initialiser DataTables sur un tableau VIDE provoque
            // « Requested unknown parameter ». On n'initialise que s'il y a des lignes.
            if ($table.find('tbody tr').length > 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    columnDefs: [
                        { targets: [5], orderable: false, searchable: false },
                        { targets: '_all', defaultContent: '-' }
                    ],
                    order: [[4, 'desc']],
                });
            }
        });
    </script>
    @notifyJs
@endsection
