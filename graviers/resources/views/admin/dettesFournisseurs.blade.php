@extends('layout.main')
@section('title', 'Dettes — Fournisseurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Dettes envers les fournisseurs</h2>
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
                <span class="text-success h5">Fournisseurs concernés :
                    <strong>{{ $fournisseurs->count() }}</strong></span>
                <span class="text-danger h5">Dette totale :
                    <strong>{{ Help::formatNombre($fournisseurs->sum('solde'), true) }}</strong></span>
            </p>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="dettes-fournisseurs" title="Dettes fournisseurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th>Fournisseur</th>
                            <th>Contact</th>
                            <th class="text-end">Montant dû</th>
                            <th class="text-center">Historique enlèvements</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fournisseurs as $fournisseur)
                            <tr>
                                <td>{{ $fournisseur->user?->nom_prenoms ?? '-' }}</td>
                                <td>{{ $fournisseur->user?->contact ?? '-' }}</td>
                                <td class="text-end text-danger">
                                    <strong>{{ Help::formatNombre($fournisseur->solde, true) }}</strong>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-historique-fournisseur"
                                            data-fournisseur-id="{{ $fournisseur->id }}">
                                        <i class="material-icons md-history align-middle"></i>
                                        Voir ({{ $fournisseur->enlevements->count() }})
                                    </button>
                                    <div class="d-none historique-fournisseur-{{ $fournisseur->id }}">
                                        <table class="table table-sm mt-2">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Code bon</th>
                                                    <th>Produit</th>
                                                    <th class="text-end">Qté</th>
                                                    <th class="text-end">Qté servie</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($fournisseur->enlevements as $e)
                                                    <tr>
                                                        <td>{{ \Carbon\Carbon::parse($e->created_at)->format('d/m/Y') }}</td>
                                                        <td>{{ $e->code_enleve }}</td>
                                                        <td>{{ $e->produit?->nom ?? '-' }}</td>
                                                        <td class="text-end">{{ $e->qte }}</td>
                                                        <td class="text-end">{{ $e->qte_servi ?? '-' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success btn-regler-dette"
                                            data-type="fournisseur"
                                            data-tier-id="{{ $fournisseur->id }}"
                                            data-nom="{{ $fournisseur->user?->nom_prenoms }}"
                                            data-solde="{{ $fournisseur->solde }}">
                                        <i class="material-icons md-payment align-middle"></i> Régler
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Aucune dette envers un fournisseur.
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

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.btn-historique-fournisseur');
                if (!btn) return;
                e.preventDefault();
                var id = btn.dataset.fournisseurId;
                var $hist = document.querySelector('.historique-fournisseur-' + id);
                if ($hist) $hist.classList.toggle('d-none');
            });
        });
    </script>
@endsection
