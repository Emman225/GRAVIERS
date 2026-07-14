@extends('layout.main')
@section('title', 'Dettes — Livreurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Dettes envers les livreurs</h2>
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
                <span class="text-success h5">Livreurs concernés :
                    <strong>{{ $livreurs->count() }}</strong></span>
                <span class="text-danger h5">Dette totale :
                    <strong>{{ Help::formatNombre($livreurs->sum('solde'), true) }}</strong></span>
            </p>
        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste" filename="dettes-livreurs" title="Dettes livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th>Livreur</th>
                            <th>Contact</th>
                            <th class="text-end">Montant dû</th>
                            <th class="text-center">Historique livraisons</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($livreurs as $livreur)
                            <tr>
                                <td>{{ $livreur->user?->nom_prenoms ?? '-' }}</td>
                                <td>{{ $livreur->user?->contact ?? '-' }}</td>
                                <td class="text-end text-danger">
                                    <strong>{{ Help::formatNombre($livreur->solde, true) }}</strong>
                                </td>
                                <td class="text-center">
                                    @php $livraisonsLivrees = $livreur->livraisons->where('etat_livraison', 'LIVREE'); @endphp
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-historique-livreur"
                                            data-livreur-id="{{ $livreur->id }}">
                                        <i class="material-icons md-history align-middle"></i>
                                        Voir ({{ $livraisonsLivrees->count() }})
                                    </button>
                                    <div class="d-none historique-livreur-{{ $livreur->id }}">
                                        <table class="table table-sm mt-2">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>N° livraison</th>
                                                    <th class="text-end">Coût livraison</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($livraisonsLivrees as $l)
                                                    <tr>
                                                        <td>{{ \Carbon\Carbon::parse($l->created_at)->format('d/m/Y') }}</td>
                                                        <td>{{ $l->numero ?? '-' }}</td>
                                                        <td class="text-end">{{ Help::formatNombre($l->cout_livraison ?? 0, true) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-success btn-regler-dette"
                                            data-type="livreur"
                                            data-tier-id="{{ $livreur->id }}"
                                            data-nom="{{ $livreur->user?->nom_prenoms }}"
                                            data-solde="{{ $livreur->solde }}">
                                        <i class="material-icons md-payment align-middle"></i> Régler
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Aucune dette envers un livreur.
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
                var btn = e.target.closest('.btn-historique-livreur');
                if (!btn) return;
                e.preventDefault();
                var id = btn.dataset.livreurId;
                var $hist = document.querySelector('.historique-livreur-' + id);
                if ($hist) $hist.classList.toggle('d-none');
            });
        });
    </script>
@endsection
