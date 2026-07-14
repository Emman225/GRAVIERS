@extends('document.layouts.fne_base')

@section('titre', 'Reçu paiement agence')
@section('type_document', 'Reçu paiement agence')

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Désignation</th>
                <th class="col-pu">P.U HT</th>
                <th class="col-qte">Qté</th>
                <th class="col-unite">Unité</th>
                <th class="col-taxes">Taxes (%)</th>
                <th class="col-rem">Rem. (%)</th>
                <th class="col-montant">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalMontant = 0;
            @endphp

            @foreach($ligne as $index => $l)
                @php
                    $totalMontant += $l->montant ?? 0;
                @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">
                        Paiement {{ $l->service ?? '' }} - {{ $l->paiement?->code ?? '' }}
                        <br><small>Moyen : {{ $l->moyen_paiement ?? 'N/A' }} | Réf : {{ $l->reference ?? 'N/A' }}</small>
                    </td>
                    <td class="col-pu">{{ number_format($l->montant ?? 0, 0, '', ' ') }}</td>
                    <td class="col-qte">1</td>
                    <td class="col-unite">Forfait</td>
                    <td class="col-taxes">0</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($l->montant ?? 0, 0, '', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalMontant, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TVA</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($totalMontant, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">AUTRES TAXES</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL A PAYER</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalMontant, 0, '', ' ') }}</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <div class="fne-resume-titre">RESUME DE LA FACTURE</div>
    <table class="fne-resume">
        <thead>
            <tr>
                <th>CATEGORIE</th>
                <th>SOUS-TOTAL</th>
                <th>TAUX (%)</th>
                <th>TOTAL TAXES</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>TVA exo.lég - Pas de TVA sur HT 00,00% - D</td>
                <td class="text-right">{{ number_format($totalMontant, 0, '', ' ') }}</td>
                <td class="text-center">0%</td>
                <td class="text-right">0</td>
            </tr>
        </tbody>
    </table></td></tr></table>
@endsection
