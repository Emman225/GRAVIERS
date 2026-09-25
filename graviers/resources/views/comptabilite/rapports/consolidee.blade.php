@extends('layout.main')
@section('title', 'Balance consolidée')

@php $francs = fn ($m) => number_format((float) $m, 0, ',', ' '); @endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-equalizer text-primary"></i> Par classe de comptes</h5>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableClasses" filename="balance-consolidee-classes" title="Balance consolidée — par classe" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableClasses">
                    <thead>
                        <tr>
                            <th>Classe</th><th>Libellé</th><th class="text-end">Comptes</th>
                            <th class="text-end">Ouverture débit</th><th class="text-end">Ouverture crédit</th>
                            <th class="text-end">Mouvement débit</th><th class="text-end">Mouvement crédit</th>
                            <th class="text-end">Solde final débit</th><th class="text-end">Solde final crédit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($consolidee['classes'] as $c)
                            <tr>
                                <td><strong>{{ $c['classe'] }}</strong></td>
                                <td>{{ $c['libelle'] }}</td>
                                <td class="text-end text-nowrap">{{ $c['comptes'] }}</td>
                                <td class="text-end text-nowrap">{{ $c['ouverture_debit'] > 0 ? $francs($c['ouverture_debit']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $c['ouverture_credit'] > 0 ? $francs($c['ouverture_credit']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['debit']) }}</td>
                                <td class="text-end text-nowrap">{{ $francs($c['credit']) }}</td>
                                <td class="text-end text-nowrap">{{ $c['solde_debit'] > 0 ? $francs($c['solde_debit']) : '' }}</td>
                                <td class="text-end text-nowrap">{{ $c['solde_credit'] > 0 ? $francs($c['solde_credit']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted">Aucun mouvement sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-category text-primary"></i> Par grande famille</h5>
        </div>
        <div class="card-body">
            <x-export-buttons table-id="tableFamillesConso" filename="balance-consolidee-familles" title="Balance consolidée — par grande famille" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableFamillesConso">
                    <thead><tr><th>Grande famille</th><th class="text-end">Écritures</th><th class="text-end">Chiffre d'affaires</th></tr></thead>
                    <tbody>
                        @forelse ($consolidee['familles'] as $f)
                            <tr>
                                <td><strong>{{ $f['famille'] }}</strong></td>
                                <td class="text-end text-nowrap">{{ $f['nombre'] }}</td>
                                <td class="text-end text-nowrap">{{ $francs($f['montant']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">Aucune vente sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
