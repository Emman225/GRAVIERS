@extends('layout.main')
@section('title', 'Créance à terme — liste')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Créance à terme — liste</h2>
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-md-12">
                    @php
                        $totalCreance = $lignes->sum('solde');
                    @endphp
                    <p class="d-flex justify-content-between">
                        <span class="text-success h5">Nombre de clients en créance :
                            <strong>{{ $lignes->count() }}</strong></span>
                        <span class="text-danger h5">Créance totale :
                            <strong>{{ Help::formatNombre($totalCreance, true) }}</strong></span>
                    </p>
                </div>
            </div>
        </header>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center">Client</th>
                            <th class="text-center">Contact</th>
                            <th class="text-end">Total facturé</th>
                            <th class="text-end">Total payé</th>
                            <th class="text-end">Solde dû</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne->client->nom }} {{ $ligne->client->prenom }}</td>
                                <td>{{ $ligne->client->contact1 ?? $ligne->client->user?->contact ?? '-' }}</td>
                                <td class="text-end">{{ Help::formatNombre($ligne->totalFacture, true) }}</td>
                                <td class="text-end">{{ Help::formatNombre($ligne->totalPaye, true) }}</td>
                                <td class="text-end text-danger">
                                    <strong>{{ Help::formatNombre($ligne->solde, true) }}</strong>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('grandLivre.clientATerme') }}?client_id={{ $ligne->client->id }}"
                                       class="btn btn-sm btn-primary">
                                        <i class="material-icons md-visibility align-middle"></i> Détails
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Aucune créance à terme en cours.
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
            // Init DataTable seulement si au moins une vraie ligne (sinon le placeholder colspan
            // déclenche l'erreur "Requested unknown parameter").
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[4, 'desc']],
                });
            }
        });
    </script>
@endsection
