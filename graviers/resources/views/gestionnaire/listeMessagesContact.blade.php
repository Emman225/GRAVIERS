@extends('layout.main')
@section('title','Messages de contact')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Messages de contact</h2>
            <p class="text-muted mb-0">
                {{ $messages->whereNull('deleted_at')->count() }} message(s)
                @php $nonLus = $messages->whereNull('deleted_at')->where('lu', false)->count(); @endphp
                @if ($nonLus > 0)
                    — <span class="badge bg-danger">{{ $nonLus }} non lu(s)</span>
                @else
                    — tous lus
                @endif
            </p>
        </div>
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
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered" id="listeMessagesContact">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Reçu le</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Expéditeur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Coordonnées</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Sujet</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">État</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $i => $message)
                                    <tr @if(!$message->lu && !$message->trashed()) style="font-weight:600; background-color:#f2f7ff;" @endif>
                                        <td class="text-center">{{ $i + 1 }}</td>
                                        <td class="text-center text-nowrap">
                                            {{ $message->created_at ? $message->created_at->format('d/m/Y H:i') : '—' }}
                                        </td>
                                        <td>{{ $message->nom_prenoms }}</td>
                                        <td class="text-nowrap">
                                            <a href="mailto:{{ $message->email }}">{{ $message->email }}</a><br>
                                            <small class="text-muted">{{ $message->telephone }}</small>
                                        </td>
                                        <td>{{ $message->sujet }}</td>
                                        <td class="text-center">
                                            @if ($message->trashed())
                                                <span class="badge bg-dark">Corbeille</span>
                                            @elseif ($message->lu)
                                                <span class="badge bg-success">Lu</span>
                                            @else
                                                <span class="badge bg-danger">Non lu</span>
                                            @endif
                                        </td>
                                        <td class="text-center text-nowrap">
                                            {{-- Le message entier s'ouvre dans une fenêtre : la colonne
                                                 resterait illisible avec un texte long. --}}
                                            <button type="button" class="btn btn-sm btn-primary"
                                                    data-bs-toggle="modal" data-bs-target="#message{{ $message->id }}"
                                                    title="Lire le message">
                                                <i class="material-icons md-visibility"></i>
                                            </button>

                                            @if (!$message->trashed())
                                                <a href="{{ route('messagesContact.basculerLu', $message->id) }}"
                                                   class="btn btn-sm {{ $message->lu ? 'btn-secondary' : 'btn-success' }}"
                                                   title="{{ $message->lu ? 'Marquer comme non lu' : 'Marquer comme lu' }}">
                                                    <i class="material-icons md-{{ $message->lu ? 'markunread' : 'done' }}"></i>
                                                </a>
                                                <a href="{{ route('messagesContact.supprimer', $message->id) }}"
                                                   class="btn btn-sm btn-danger" title="Mettre à la corbeille">
                                                    <i class="material-icons md-delete"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('messagesContact.supprimer', $message->id) }}"
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

    {{-- Fenêtres de lecture, hors du tableau : DataTables ne reprend que les
         lignes, une fenêtre placée dedans serait retirée du document. --}}
    @foreach ($messages as $message)
        <div class="modal fade" id="message{{ $message->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header" style="background-color:#1c57a3; color:#fff;">
                        <h5 class="modal-title">{{ $message->sujet }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1"><strong>{{ $message->nom_prenoms }}</strong></p>
                        <p class="text-muted mb-3">
                            <a href="mailto:{{ $message->email }}">{{ $message->email }}</a>
                            &nbsp;·&nbsp; {{ $message->telephone }}
                            &nbsp;·&nbsp; {{ $message->created_at ? $message->created_at->format('d/m/Y à H:i') : '' }}
                        </p>
                        <div style="border-left:4px solid #1c57a3; background:#f8f9fa; padding:14px 16px; white-space:pre-wrap;">{{ $message->message }}</div>
                    </div>
                    <div class="modal-footer">
                        <a href="mailto:{{ $message->email }}?subject=RE:%20{{ rawurlencode($message->sujet) }}"
                           class="btn btn-primary">
                            <i class="material-icons md-reply"></i> Répondre
                        </a>
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#listeMessagesContact');

            // Garde-fou : initialiser DataTables sur un tableau VIDE provoque
            // « Requested unknown parameter ». On n'initialise que s'il y a des lignes.
            if ($table.find('tbody tr').length > 0) {
                $table.DataTable({
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    columnDefs: [
                        { targets: [6], orderable: false, searchable: false },
                        { targets: '_all', defaultContent: '-' }
                    ],
                    // Tri d'origine conservé : non lus d'abord, puis les plus récents.
                    order: [],
                });
            }
        });
    </script>
    @notifyJs
@endsection
