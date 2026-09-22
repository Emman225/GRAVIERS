@extends('layout.main')
@section('title', 'Rapport d\'anomalies comptables')

@php
    $ouvertes = $anomalies->whereNull('resolue_le');
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Rapport d'<span class="dash-welcome-name">anomalies</span></h2>
                <p class="dash-welcome-subtitle">
                    Pour chaque erreur : la facture ou l'opération, l'écriture, la ligne, la colonne à corriger et la cause.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.comptabilite.ecritures.index', request()->only(['mode_periode', 'periode', 'du', 'au'])) }}" class="btn btn-primary">
                    <i class="material-icons md-arrow_back"></i> Journal des écritures
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header">
            <h5 class="dash-card-title"><i class="material-icons md-filter_list text-primary"></i> Période et état</h5>
        </div>
        <div class="card-body">
            @include('comptabilite.ecritures._periode', [
                'action' => route('show.comptabilite.ecritures.anomalies'),
                'champsConserves' => ['etat' => $etat === 'corrigees' ? 'corrigees' : null],
            ])
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('show.comptabilite.ecritures.anomalies', request()->only(['mode_periode', 'periode', 'du', 'au'])) }}"
                   class="btn btn-sm {{ $etat === 'ouvertes' ? 'btn-primary' : 'btn-light' }}">Anomalies ouvertes</a>
                <a href="{{ route('show.comptabilite.ecritures.anomalies', array_merge(request()->only(['mode_periode', 'periode', 'du', 'au']), ['etat' => 'corrigees'])) }}"
                   class="btn btn-sm {{ $etat === 'corrigees' ? 'btn-primary' : 'btn-light' }}">Anomalies corrigées</a>
                <form method="POST" action="{{ route('show.comptabilite.ecritures.reprendre') }}" class="d-inline ms-auto">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="material-icons md-refresh"></i> Reprendre après correction du paramétrage
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-body">
            @if ($anomalies->isEmpty())
                <div class="alert alert-success mb-0">
                    @if ($etat === 'corrigees')
                        Aucune anomalie corrigée sur cette période.
                    @else
                        Aucune anomalie sur cette période : les écritures peuvent être transmises.
                    @endif
                </div>
            @else
                <x-export-buttons table-id="tableAnomalies" filename="anomalies-comptables" title="Rapport d'anomalies comptables" />
                <div class="table-responsive">
                    <table class="table dash-table align-middle mb-0 js-table" id="tableAnomalies">
                        <thead>
                            <tr>
                                <th>Pièce</th>
                                <th>Écriture</th>
                                <th>Ligne</th>
                                <th>Concerné</th>
                                <th>Cause</th>
                                <th>Colonne à corriger</th>
                                <th class="text-center">État</th>
                                <th class="text-end">Corriger</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($anomalies as $anomalie)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ $anomalie->ecriture?->piece ?: '-' }}
                                        @if ($anomalie->facture) <small class="d-block text-muted">Facture n° {{ $anomalie->facture->numero }}</small> @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($anomalie->ecriture)
                                            <a href="{{ route('show.comptabilite.ecritures.detail', $anomalie->ecriture) }}">{{ $anomalie->ecriture->identifiant }}</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $anomalie->rang_ligne ?: '-' }}</td>
                                    <td><strong>{{ $anomalie->objet }}</strong></td>
                                    <td class="td-texte-long">{{ $anomalie->cause }}</td>
                                    <td>{{ $anomalie->colonne }}</td>
                                    <td class="text-center">
                                        @if ($anomalie->resolue_le)
                                            <span class="badge bg-success">Corrigée</span>
                                            <small class="d-block text-muted">{{ \Help::dateHeure($anomalie->resolue_le) }}</small>
                                        @else
                                            <span class="badge bg-warning text-dark">Ouverte</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if (!$anomalie->resolue_le && $anomalie->onglet)
                                            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => $anomalie->onglet]) }}" class="btn btn-sm btn-primary text-nowrap">
                                                Ouvrir le paramétrage
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($etat === 'ouvertes' && $ouvertes->isNotEmpty())
                    <div class="alert alert-warning mt-3 mb-0">
                        Corrigez le paramétrage, puis lancez « Reprendre » : les écritures sont réécrites et les anomalies réglées sont datées, non effacées.
                    </div>
                @endif
            @endif
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
            var $table = $('#tableAnomalies');
            if ($table.length && $table.find('tbody tr').length > 0 && $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'asc']],
                });
            }
        });
    </script>
@endsection
