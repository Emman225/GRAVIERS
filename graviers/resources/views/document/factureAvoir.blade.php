@php
    // FACTURE D'AVOIR (lot 92, 16/09/2026) : même gabarit FNE que les factures.
    $lignes  = (array) ($facture->lignes_avoir ?? []);
    $totalHT = 0.0;
    foreach ($lignes as $l) {
        $totalHT += (float) ($l['quantity'] ?? 0) * (float) ($l['amount'] ?? 0);
    }
    $montantAvoir = abs((float) $facture->montant);
    $tva = (float) ($tauxTva ?? 0);
@endphp

@extends('document.layouts.fne_base')

@section('titre', "Facture d'avoir")
@section('type_document', "Facture d'avoir")

@section('vendeur', \Help::nomDuVendeur($facture->user ?? null))

@section('articles')
    <p style="font-size:9pt; margin:0 0 6px 0;">
        <strong>Avoir sur la facture n° {{ \Help::formatNumeroFacture($origine->numero ?? '') }}</strong>
        @if(!empty($origine?->fne_reference)) — référence DGI {{ $origine->fne_reference }} @endif
        @if(!empty($origine?->created_at)) du {{ \Help::dateHeure($origine->created_at) }} @endif
        <br>Motif : {{ $facture->motif_avoir }}
    </p>
    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Désignation</th>
                <th class="col-qte">Qté créditée</th>
                <th class="col-unite">Unité</th>
                <th class="col-taxes">Taxes (%)</th>
                <th class="col-montant">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lignes as $l)
                <tr>
                    <td class="col-ref">{{ $l['reference'] ?? '' }}</td>
                    <td class="col-designation">{{ $l['description'] ?? '' }}</td>
                    <td class="col-qte">{{ rtrim(rtrim(number_format((float) ($l['quantity'] ?? 0), 2, ',', ' '), '0'), ',') }}</td>
                    <td class="col-unite">{{ $l['measurementUnit'] ?? '' }}</td>
                    <td class="col-taxes">{{ $tva > 0 ? 'TVA (' . rtrim(rtrim(number_format($tva, 2, ',', ''), '0'), ',') . '%)' : 'Exonéré' }}</td>
                    <td class="col-montant">{{ number_format((float) ($l['quantity'] ?? 0) * (float) ($l['amount'] ?? 0), 0, '', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT CRÉDITÉ</td>
            <td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td>
        </tr>
        @if($tva > 0)
        <tr>
            <td class="label">TVA</td>
            <td class="valeur">{{ number_format(\Help::arrondiFranc($totalHT * $tva / 100), 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label"><strong>MONTANT DE L'AVOIR</strong></td>
            <td class="valeur"><strong>{{ number_format($montantAvoir, 0, '', ' ') }}</strong></td>
        </tr>
    </table></td></tr></table>
    <p style="font-size:8pt; margin-top:6px;">
        Ce montant est porté au crédit du client sur la facture d'origine.
    </p>
@endsection

@section('resume_fiscal')
    @if (!empty($fne_certified))
        <p style="font-size:8pt; margin-top:10px;">
            Facture d'avoir certifiée — référence {{ $fne_reference ?? '' }}
        </p>
    @else
        <p style="font-size:8pt; font-style:italic; margin-top:10px;">
            Facture d'avoir en attente de certification auprès de la DGI.
        </p>
    @endif
@endsection
