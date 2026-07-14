@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Paiements livreurs')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Paiements livreurs (lecture seule)</h2>
    </div>

    <div class="alert alert-info">
        <i class="material-icons md-info"></i>
        Journal <strong>en lecture seule</strong> des paiements livreur. Les demandes sont
        créées par les livreurs depuis l'application mobile et validées par deux administrateurs
        (menu « Demandes de paiement livreur »). L'enregistrement manuel ici est désactivé
        pour éviter les doubles paiements.
    </div>

    <div class="card mb-4">
        <header class="card-header">
            <span class="text-success h5">Total payé : {{ number_format($totalPaye, 0, '', ' ') }} fcfa</span>
        </header>
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="paiements-livreurs" title="Paiements livreurs" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead style="background-color: #1c57a3; color: white;">
                        <tr>
                            <th class="text-center" style="color:#fff;">Livreur</th>
                            <th class="text-center" style="color:#fff;">N° demande</th>
                            <th class="text-center" style="color:#fff;">Montant</th>
                            <th class="text-center" style="color:#fff;">Mode</th>
                            <th class="text-center" style="color:#fff;">N° compte / Tél</th>
                            <th class="text-center" style="color:#fff;">Date demande</th>
                            <th class="text-center" style="color:#fff;">Statut</th>
                            <th class="text-center" style="color:#fff;">Validé par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($paiements as $p)
                            <tr>
                                <td class="text-center">{{ $p->user?->nom_prenoms }}</td>
                                <td class="text-center">{{ $p->numero ?: 'DP-' . str_pad($p->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="text-center">{{ number_format($p->montant, 0, '', ' ') }} fcfa</td>
                                <td class="text-center">{{ $p->modePaiement?->libelle ?? '-' }}</td>
                                <td class="text-center">{{ $p->numero_compte ?? '-' }}</td>
                                <td class="text-center">{{ $p->created_at ? Carbon::parse($p->created_at)->format('d/m/Y H:i') : '-' }}</td>
                                <td class="text-center">
                                    @if ($p->paye == 1)
                                        <span class="badge bg-success">Payé</span>
                                    @elseif ($p->paye == 2)
                                        <span class="badge bg-danger">Refusé</span>
                                    @else
                                        <span class="badge bg-warning">En attente</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $p->userValide?->nom_prenoms ?? '-' }}</td>
                            </tr>
                        @endforeach
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
            $('#liste').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                // Robustesse : empêche l'erreur "Requested unknown parameter" si une
                // ligne n'a pas exactement le nombre de colonnes de l'en-tête
                // (ex. ancienne ligne vide en colspan). Table vide -> message natif DataTables.
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
            });
        });
    </script>
@endsection
