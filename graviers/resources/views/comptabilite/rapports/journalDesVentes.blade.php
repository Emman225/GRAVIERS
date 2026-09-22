@extends('layout.main')
@section('title', 'Journal des ventes')

@php
    $francs = fn ($m) => number_format((float) $m, 0, ',', ' ');
    $totalDebit = $ecritures->sum('total_debit');
    $totalCredit = $ecritures->sum('total_credit');
@endphp

@section('contenu')
    @include('comptabilite.rapports._entete')

    <div class="card dash-card mb-4">
        <div class="card-body">
            <p class="text-muted">Présentation classique du journal des ventes de la période, dans l'ordre des pièces, prêt à imprimer ou à remettre.</p>
            <x-export-buttons table-id="tableJournalVentes" filename="journal-des-ventes" title="Journal des ventes" />
            <div class="table-responsive">
                <table class="table dash-table align-middle mb-0" id="tableJournalVentes">
                    <thead><tr><th>Date</th><th>Journal</th><th>Pièce</th><th>Libellé</th><th>Compte</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr></thead>
                    <tbody>
                        @forelse ($ecritures as $ecriture)
                            @foreach ($ecriture->lignes as $ligne)
                                <tr>
                                    <td class="text-nowrap">{{ $ecriture->date_ecriture?->format('d/m/Y') }}</td>
                                    <td>{{ $ecriture->journal_code }}</td>
                                    <td><a href="{{ route('show.comptabilite.ecritures.detail', $ecriture) }}">{{ $ecriture->piece }}</a></td>
                                    <td class="td-texte-long">{{ $ligne->libelle }}</td>
                                    <td>{{ $ligne->numero_compte ?: '-' }}</td>
                                    <td class="text-end">{{ $ligne->debit > 0 ? $francs($ligne->debit) : '' }}</td>
                                    <td class="text-end">{{ $ligne->credit > 0 ? $francs($ligne->credit) : '' }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Aucune vente sur cette période.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($ecritures->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Totaux</th>
                                <th class="text-end">{{ $francs($totalDebit) }}</th>
                                <th class="text-end">{{ $francs($totalCredit) }}</th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
