@php
    use Illuminate\Support\Carbon;
    $modeLibelle = optional(\App\Models\ModePaiement::find($location->mode_paiement_id))->libelle ?? 'N/A';
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Facture de location')
@section('type_document', 'Facture de location')

@section('vendeur', $facture->user?->nom_prenoms ?? 'N/A')
@section('mode_paiement', $modeLibelle)

@if($location->adresseLivraison)
    @section('adresse_livraison', ucwords($location->adresseLivraison->affichage))
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
                $remise = $location->remise ?? 0;
            @endphp

            @foreach ($location->detailLocation as $index => $detail)
                @php
                    $qte         = (float) ($detail->qte ?: 1);
                    $jours       = (int) ($detail->nombre_jour ?: 1);
                    $montantLigne = (float) ($detail->prix ?? 0);          // total ligne = qte × pu/jour × jours
                    $puPeriode   = $qte > 0 ? $montantLigne / $qte : $montantLigne; // P.U HT (par unité, période complète)
                    $tvaLigne    = $montantLigne * (($config->tva ?? 0) / 100);
                    $totalHT    += $montantLigne;
                    $totalTVA   += $tvaLigne;
                @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">{{ ucwords($detail->produit->nom ?? 'Location') }} (location {{ $jours }} j)</td>
                    <td class="col-pu">{{ number_format($puPeriode, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ $detail->qte }}</td>
                    <td class="col-unite">{{ $detail->produit->uniteProduit->libelle ?? 'U' }}</td>
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
        // TVA NETTE stockée (sur le HT après remise) prioritaire sur la TVA recalculée
        // depuis les lignes brutes, pour rester cohérent avec le montant payé.
        $tvaLoc = $location->tvaLocation->montant ?? 0;
        if ($tvaLoc > 0) {
            $totalTVA = $tvaLoc;
        }
        $coutLivraison = $location->cout_livraison_client ?? 0;
        $remise = $location->remise ?? 0;
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
    </table>
@endsection
