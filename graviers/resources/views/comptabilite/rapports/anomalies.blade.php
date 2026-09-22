@extends('layout.main')
@section('title', 'Historique des anomalies')

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <p class="text-muted">
                Une ligne par cause d'anomalie rencontrée sur la période : combien sont ouvertes, combien ont été corrigées,
                et le délai moyen de correction. Le détail se lit dans le <a href="{{ route('show.comptabilite.ecritures.anomalies') }}">rapport d'anomalies</a>.
            </p>
            <x-export-buttons table-id="tableHistoAnomalies" filename="historique-anomalies" title="Historique des anomalies comptables" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0 js-table" id="tableHistoAnomalies">
                    <thead>
                        <tr><th>Cause</th><th>Colonne à corriger</th><th>Exemple</th><th class="text-end">Ouvertes</th><th class="text-end">Corrigées</th><th class="text-end">Délai moyen (h)</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($lignes as $ligne)
                            <tr>
                                <td><strong>{{ $ligne['code'] }}</strong></td>
                                <td>{{ $ligne['colonne'] }}</td>
                                <td class="td-texte-long"><small>{{ $ligne['exemple'] }}</small></td>
                                <td class="text-end">{{ $ligne['ouvertes'] }}</td>
                                <td class="text-end">{{ $ligne['corrigees'] }}</td>
                                <td class="text-end">{{ $ligne['delai_moyen'] !== null ? $ligne['delai_moyen'] : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Aucune anomalie sur cette période.</td></tr>
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
            var $t = $('#tableHistoAnomalies');
            if ($t.find('tbody tr').length && !$t.find('tbody tr td[colspan]').length) {
                $t.DataTable({ language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' } });
            }
        });
    </script>
@endsection
