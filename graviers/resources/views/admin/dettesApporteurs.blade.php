@extends('layout.main')
@section('title', 'Dettes — Apporteurs d\'affaires')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Dettes envers les apporteurs d'affaires</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <header class="card-header">
            <p class="d-flex justify-content-between mb-0">
                <span class="text-success h5">Apporteurs concernés :
                    <strong>{{ $apporteurs->count() }}</strong></span>
                <span class="text-danger h5">Dette totale :
                    <strong>{{ Help::formatNombre($apporteurs->sum('solde'), true) }}</strong></span>
            </p>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="dettes-apporteurs" title="Dettes apporteurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th>Apporteur</th>
                            <th>Contact</th>
                            <th class="text-end">Montant dû</th>
                            <th class="text-center">Historique</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apporteurs as $apporteur)
                            <tr>
                                <td>{{ $apporteur->user?->nom_prenoms ?? '-' }}</td>
                                <td>{{ $apporteur->user?->contact ?? '-' }}</td>
                                <td class="text-end text-danger">
                                    <strong>{{ Help::formatNombre($apporteur->solde, true) }}</strong>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-historique-apporteur"
                                            data-apporteur-id="{{ $apporteur->id }}">
                                        <i class="material-icons md-history align-middle"></i>
                                        Voir ({{ $apporteur->commissions->count() }})
                                    </button>
                                    <div class="d-none historique-apporteur-{{ $apporteur->id }}">
                                        <table class="table table-sm mt-2">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th class="text-end">Montant</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($apporteur->commissions as $c)
                                                    <tr>
                                                        <td>{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</td>
                                                        <td>{{ $c->type_affaire ?? '-' }}</td>
                                                        <td class="text-end">{{ Help::formatNombre($c->montant, true) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success btn-regler-dette"
                                            data-type="apporteur"
                                            data-tier-id="{{ $apporteur->id }}"
                                            data-nom="{{ $apporteur->user?->nom_prenoms }}"
                                            data-solde="{{ $apporteur->solde }}">
                                        <i class="material-icons md-payment align-middle"></i> Régler
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Aucune dette envers un apporteur d'affaires.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin._popupReglerDette')
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script>
        $(function () {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[2, 'desc']],
                });
            }

            // Toggle historique inline
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.btn-historique-apporteur');
                if (!btn) return;
                e.preventDefault();
                var id = btn.dataset.apporteurId;
                var $hist = document.querySelector('.historique-apporteur-' + id);
                if ($hist) $hist.classList.toggle('d-none');
            });
        });
    </script>
@endsection
