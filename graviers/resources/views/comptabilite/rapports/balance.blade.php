@extends('layout.main')
@section('title', 'Balance')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    use App\Services\Comptabilite\RapportsComptables;
    $totaux = RapportsComptables::totaux($lignes, ['ouverture', 'debit', 'credit', 'solde_debit', 'solde_credit']);
    $entete = ['generale' => 'Compte', 'tiers' => 'Compte tiers', 'analytique' => 'Compte analytique'][$sorte];
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <div class="btn-group mb-3" role="group">
                @foreach (['generale' => 'Balance générale', 'tiers' => 'Balance des tiers', 'analytique' => 'Balance analytique'] as $cle => $libelle)
                    <a href="{{ route('show.comptabilite.rapports.balance', array_merge(request()->only(['mode_periode', 'periode', 'du', 'au']), ['sorte' => $cle])) }}"
                       class="btn btn-sm {{ $sorte === $cle ? 'btn-primary' : 'btn-light' }}">{{ $libelle }}</a>
                @endforeach
            </div>

            <x-export-buttons table-id="tableBalance" filename="balance-{{ $sorte }}" title="Balance {{ strtolower($entete) }}" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableBalance">
                    <thead>
                        <tr>
                            <th>{{ $entete }}</th>
                            <th>Libellé</th>
                            <th class="text-end">Solde d'ouverture</th>
                            <th class="text-end">Débit</th>
                            <th class="text-end">Crédit</th>
                            <th class="text-end">Solde débiteur</th>
                            <th class="text-end">Solde créditeur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['cle'] }}</strong></td>
                                <td class="td-texte-long">{{ $ligne['libelle'] }}</td>
                                <td class="text-end">{{ $francs($ligne['ouverture']) }}</td>
                                <td class="text-end">{{ $francs($ligne['debit']) }}</td>
                                <td class="text-end">{{ $francs($ligne['credit']) }}</td>
                                <td class="text-end">{{ $ligne['solde_debit'] > 0 ? $francs($ligne['solde_debit']) : '' }}</td>
                                <td class="text-end">{{ $ligne['solde_credit'] > 0 ? $francs($ligne['solde_credit']) : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Aucun mouvement sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if (count($lignes))
                        <tfoot>
                            <tr>
                                <th colspan="2" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totaux['ouverture']) }}</th>
                                <th class="text-end">{{ $francs($totaux['debit']) }}</th>
                                <th class="text-end">{{ $francs($totaux['credit']) }}</th>
                                <th class="text-end">{{ $francs($totaux['solde_debit']) }}</th>
                                <th class="text-end">{{ $francs($totaux['solde_credit']) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            @if (count($lignes) && abs($totaux['solde_debit'] - $totaux['solde_credit']) > 1)
                <div class="alert alert-warning mt-3 mb-0">
                    Le total débiteur ne correspond pas au total créditeur : écart de {{ $francs(abs($totaux['solde_debit'] - $totaux['solde_credit'])) }} F.
                </div>
            @elseif (count($lignes))
                <div class="alert alert-success mt-3 mb-0">La balance est équilibrée.</div>
            @endif
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
            var $t = $('#tableBalance');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' }, order: [[0, 'asc']] });
            }
        });
    </script>
@endsection
