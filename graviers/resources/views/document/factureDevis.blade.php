@php
    $config = $config ?? App\Models\Configuration::first();
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Devis')
@section('type_document', 'Devis')

@if($devis->adresse_livraison_id && $devis->adresseLivraison)
    @section('adresse_livraison', $devis->adresseLivraison->affichage)
@endif

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
                $totalHT = 0;
            @endphp

            @foreach ($devis->detailDevis as $index => $detail)
                @if($detail->deleted_at == null)
                    @php
                        $prixUnitaire = $detail->prix ?? $detail->produit->prix_moyen;
                        $montantLigne = $prixUnitaire * $detail->qte;
                        $totalHT += $montantLigne;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ $detail->produit->nom }}</td>
                        <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
                        <td class="col-qte">{{ $detail->qte }}</td>
                        <td class="col-unite">{{ $detail->produit->uniteProduit->libelle ?? 'U' }}</td>
                        <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                        <td class="col-rem">0</td>
                        <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $montantTVA = $devis->tva ?? 0;
        $coutLivraison = $devis->cout_livraison ?? 0;
        $coutReduction = $devis->cout_reduction ?? 0;
        $totalTTC = $totalHT + $montantTVA;
        $totalAPayer = ($devis->montant + $montantTVA + $coutLivraison) - $coutReduction;
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TVA ({{ $config->tva ?? 0 }}%)</td>
            <td class="valeur">{{ number_format($montantTVA, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($totalTTC, 0, '', ' ') }}</td>
        </tr>
        @if($coutLivraison > 0)
        <tr>
            <td class="label">Coût de livraison</td>
            <td class="valeur">{{ number_format($coutLivraison, 0, '', ' ') }}</td>
        </tr>
        @endif
        @if($coutReduction > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($coutReduction, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">AUTRES TAXES</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL A PAYER</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td>
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
            @if(($config->tva ?? 0) > 0)
                <tr>
                    <td>TVA {{ $config->tva }}% sur HT</td>
                    <td class="text-right">{{ number_format($totalHT, 0, '', ' ') }}</td>
                    <td class="text-center">{{ $config->tva }}%</td>
                    <td class="text-right">{{ number_format($montantTVA, 0, '', ' ') }}</td>
                </tr>
            @else
                <tr>
                    <td>TVA exo.lég - Pas de TVA sur HT 00,00% - D</td>
                    <td class="text-right">{{ number_format($totalHT, 0, '', ' ') }}</td>
                    <td class="text-center">0%</td>
                    <td class="text-right">0</td>
                </tr>
            @endif
        </tbody>
    </table></td></tr></table>
@endsection
