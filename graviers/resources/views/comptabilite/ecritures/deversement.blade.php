@extends('layout.main')
@section('title', 'Factures du déversement ' . $deversement->numero)

@php
    use App\Models\EcritureComptable;
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    $factures = $ecritures->where('source_type', 'facture')->unique('source_id');
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Déversement {{ $deversement->numero }}</h2>
                <p class="dash-welcome-subtitle">
                    {{ $deversement->libelle_periode }} — {{ $deversement->libelle_format }} —
                    transmis le {{ \Help::dateHeure($deversement->created_at) }}
                    @if ($deversement->user) par {{ $deversement->user->nom_prenoms }} @endif
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.comptabilite.ecritures.index') }}" class="btn btn-primary">
                    <i class="material-icons md-arrow_back"></i> Journal des écritures
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-receipt_long text-primary"></i>
                Les écritures emportées ({{ $ecritures->count() }}, dont {{ $factures->count() }} facture(s))</h5>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableDeversementFactures" filename="deversement-{{ $deversement->numero }}" title="Déversement {{ $deversement->numero }}" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableDeversementFactures">
                    <thead>
                        <tr>
                            <th>Date</th><th>Journal</th><th>N° facture / pièce</th><th>Réf. FNE</th>
                            <th>Libellé</th><th>Origine</th>
                            <th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Détail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ecritures as $ecriture)
                            <tr>
                                <td data-order="{{ $ecriture->date_ecriture?->format('Ymd') }}" class="text-nowrap">{{ $ecriture->date_ecriture?->format('d/m/Y') }}</td>
                                <td class="text-nowrap">{{ $ecriture->journal_code ?: '-' }}</td>
                                <td class="text-nowrap"><strong>{{ $ecriture->piece }}</strong></td>
                                <td class="text-nowrap">{{ $ecriture->reference_fne ?: '-' }}</td>
                                <td class="td-texte-long">{{ $ecriture->libelle }}</td>
                                <td class="text-nowrap">{{ EcritureComptable::ORIGINES[$ecriture->origine] ?? $ecriture->origine }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ecriture->total_debit) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($ecriture->total_credit) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('show.comptabilite.ecritures.detail', $ecriture) }}" class="btn btn-sm btn-primary rounded" title="Voir le détail">
                                        <i class="material-icons md-visibility"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">Ce déversement ne porte plus aucune écriture.</td></tr>
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
    <script>
        $(function () {
            var $t = $('#tableDeversementFactures');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
