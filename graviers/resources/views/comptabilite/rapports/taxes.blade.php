@extends('layout.main')
@section('title', 'Taxes collectées')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['tva', 'airsi', 'total']);
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="tableTaxes" filename="taxes-collectees" title="État des taxes collectées" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableTaxes">
                    <thead><tr><th>Mois</th><th class="text-end">TVA facturée</th><th class="text-end">AIRSI collecté</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne['libelle'] }}</td>
                                <td class="text-end">{{ $francs($ligne['tva']) }}</td>
                                <td class="text-end">{{ $francs($ligne['airsi']) }}</td>
                                <td class="text-end"><strong>{{ $francs($ligne['total']) }}</strong></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">Aucune taxe collectée sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr>
                                <th class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totaux['tva']) }}</th>
                                <th class="text-end">{{ $francs($totaux['airsi']) }}</th>
                                <th class="text-end">{{ $francs($totaux['total']) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <small class="text-muted d-block mt-2">
                Pour préparer les déclarations mensuelles, rapprochez ces montants de ceux effectivement déclarés.
            </small>
        </div>
    </div>
@endsection
