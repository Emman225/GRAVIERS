@extends('layout.main')
@section('title', 'Suivi des déversements')

@php $francs = fn ($m) => number_format((float) $m, 0, ',', ' '); @endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-send text-primary"></i> Par mois</h5>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableDeversements" filename="suivi-des-deversements" title="Suivi des déversements" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableDeversements">
                    <thead>
                        <tr>
                            <th>Mois</th>
                            <th class="text-end">Déversées</th>
                            <th class="text-end">Montant déversé</th>
                            <th class="text-end">Non déversées</th>
                            <th class="text-end">Montant non déversé</th>
                            <th class="text-end">En anomalie</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td>{{ $ligne['libelle'] }}</td>
                                <td class="text-end">{{ $ligne['deverse'] }}</td>
                                <td class="text-end">{{ $francs($ligne['montant_deverse']) }}</td>
                                <td class="text-end">{{ $ligne['non_deverse'] }}</td>
                                <td class="text-end">{{ $francs($ligne['montant_non_deverse']) }}</td>
                                <td class="text-end">{{ $ligne['anomalie'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Aucune écriture sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-history text-primary"></i> Transmissions de la période</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0">
                    <thead><tr><th>N°</th><th>Transmis le</th><th>Format</th><th class="text-end">Écritures</th><th class="text-end">Débit</th><th>Par</th><th class="text-center">État</th></tr></thead>
                    <tbody>
                        @forelse ($transmissions as $deversement)
                            <tr>
                                <td><strong>{{ $deversement->numero }}</strong></td>
                                <td>{{ \Help::dateHeure($deversement->created_at) }}</td>
                                <td>{{ $deversement->libelle_format }}</td>
                                <td class="text-end">{{ $deversement->nombre_ecritures }}</td>
                                <td class="text-end">{{ $francs($deversement->total_debit) }}</td>
                                <td>{{ $deversement->user?->nom_prenoms ?: '-' }}</td>
                                <td class="text-center">{{ $deversement->libelle_etat }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Aucune transmission sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
