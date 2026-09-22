@extends('layout.main')
@section('title', 'Grand livre')

@php $francs = fn ($m) => number_format((float) $m, 0, ',', ' '); @endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-menu_book text-primary"></i> Choisir le compte</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                @foreach (request()->only(['mode_periode', 'periode', 'du', 'au']) as $nom => $valeur)
                    <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
                @endforeach
                <div class="col-md-6">
                    <label class="form-label" for="compte">Compte mouvementé sur la période</label>
                    <select name="compte" id="compte" class="form-select" onchange="this.form.submit()">
                        @forelse ($comptes as $c)
                            <option value="{{ $c['numero'] }}" {{ $compte === $c['numero'] ? 'selected' : '' }}>{{ $c['numero'] }} — {{ $c['libelle'] }}</option>
                        @empty
                            <option value="">Aucun compte mouvementé sur cette période</option>
                        @endforelse
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if ($livre)
        <div class="card dash-card mb-4">
            <div class="card-header dash-card-header">
                <h5 class="dash-card-title">{{ $livre['compte'] }} — {{ $livre['libelle'] }}</h5>
            </div>
            <div class="card-body">
                <x-export-buttons table-id="tableGrandLivre" filename="grand-livre-{{ $livre['compte'] }}" title="Grand livre — {{ $livre['compte'] }} {{ $livre['libelle'] }}" />
                <div class="table-responsive">
                    <table class="table dash-table align-middle mb-0" id="tableGrandLivre">
                        <thead>
                            <tr>
                                <th>Date</th><th>Journal</th><th>Pièce</th><th>Réf. FNE</th><th>Libellé</th><th>Tiers</th><th>Lettre</th>
                                <th class="text-end">Débit</th><th class="text-end">Crédit</th><th class="text-end">Solde</th><th class="text-center">Déversé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td colspan="9" class="text-end fw-bold">Solde d'ouverture</td>
                                <td class="text-end fw-bold">{{ $francs($livre['ouverture']) }}</td>
                                <td></td>
                            </tr>
                            @forelse ($livre['mouvements'] as $m)
                                <tr>
                                    <td class="text-nowrap">{{ $m['date']->format('d/m/Y') }}</td>
                                    <td>{{ $m['journal'] ?: '-' }}</td>
                                    <td>
                                        <a href="{{ route('show.comptabilite.ecritures.detail', $m['ecriture_id']) }}">{{ $m['piece'] }}</a>
                                    </td>
                                    <td>{{ $m['reference'] ?: '-' }}</td>
                                    <td class="td-texte-long">{{ $m['libelle'] }}</td>
                                    <td>{{ $m['tiers'] ?: '-' }}</td>
                                    <td>{{ $m['lettre'] ?: '-' }}</td>
                                    <td class="text-end">{{ $m['debit'] > 0 ? $francs($m['debit']) : '' }}</td>
                                    <td class="text-end">{{ $m['credit'] > 0 ? $francs($m['credit']) : '' }}</td>
                                    <td class="text-end">{{ $francs($m['solde']) }}</td>
                                    <td class="text-center">
                                        @if ($m['deverse']) <i class="material-icons md-check_circle text-success"></i> @else <i class="material-icons md-schedule text-muted"></i> @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="11" class="text-center text-muted">Aucun mouvement sur cette période.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="7" class="text-end">Totaux de la période</th>
                                <th class="text-end">{{ $francs($livre['debit']) }}</th>
                                <th class="text-end">{{ $francs($livre['credit']) }}</th>
                                <th class="text-end">{{ $francs($livre['cloture']) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
