@php
    use Illuminate\Support\Carbon;
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Facture de vente')
@section('type_document', 'Facture de vente')

@section('vendeur', $facture->user?->nom_prenoms ?? 'N/A')
@section('mode_paiement', $commande->devis?->modePaiement?->libelle ?? 'N/A')

@if($commande->adresseLivraison)
    @section('adresse_livraison', ucwords($commande->adresseLivraison->affichage))
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
                $totalTVA = 0;
                $coutLivraison = 0;
                $remise = $commande->remise ?? 0;
            @endphp

            @foreach ($enlevements as $index => $env)
                @php
                    $prixUnitaire = $env->livraison->detailCommande->prix;
                    $montantLigne = $env->qte * $prixUnitaire;
                    $tvaLigne = $montantLigne * (($config->tva ?? 0) / 100);
                    $totalHT += $montantLigne;
                    $totalTVA += $tvaLigne;
                @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">{{ ucwords($env->produit->nom) }}</td>
                    <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ $env->qte }}</td>
                    <td class="col-unite">{{ $env->produit->uniteProduit->libelle ?? 'U' }}</td>
                    <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        if($livraison == 1) {
            // On préfère la TVA recalculée sur le HT (point 9 : TVA 18% sur toutes les factures).
            // On ne reprend la TvaCommande historique que si elle est strictement positive (rétro-compat).
            $tvaCommande = $facture->commande->TvaCommande->montant ?? 0;
            if ($tvaCommande > 0 && $totalTVA <= 0) {
                $totalTVA = $tvaCommande;
            }
            $coutLivraison = $facture->commande->cout_livraison_client ?? 0;
            $remise = $facture->commande->remise ?? 0;
        }
        $totalTTC = $totalHT + $totalTVA + $coutLivraison - $remise;
        $totalAPayer = $facture->montant ?? $totalTTC;
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TVA</td>
            <td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td>
        </tr>
        @if($coutLivraison > 0)
        <tr>
            <td class="label">Coût livraison</td>
            <td class="valeur">{{ number_format($coutLivraison, 0, '', ' ') }}</td>
        </tr>
        @endif
        @if($remise > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($totalHT + $totalTVA, 0, '', ' ') }}</td>
        </tr>
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
                    <td class="text-right">{{ number_format($totalTVA, 0, '', ' ') }}</td>
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
