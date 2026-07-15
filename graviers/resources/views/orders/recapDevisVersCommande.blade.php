@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $mode = $mode ?? App\Models\ModePaiement::find(session('mode'));
    $fne_numero = isset($devis) ? $devis->numero : 'DEV-CMD-' . date('YmdHis');
    $fne_adresse = '';
@endphp
@include('document.layouts._fne_init', ['fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif devis vers commande')
@section('type_document', 'Proforma')

@if(isset($mode) && $mode)
    @section('mode_paiement', ucwords($mode->libelle))
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
            @php $totalHT = 0; $index = 0; @endphp
            @if(session('type') == 'devis' && isset($devis))
                @foreach($devis->detailDevis as $detail)
                    @php
                        $pu = $detail->prix ?? $detail->produit->prix_moyen; $montant = $pu * $detail->qte; $totalHT += $montant; $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($detail->produit->nom) }}</td>
                        <td class="col-pu">{{ number_format($pu, 0, '', ' ') }}</td>
                        <td class="col-qte">{{ $detail->qte }}</td>
                        <td class="col-unite">{{ $detail->produit->uniteProduit->abreviation ?? 'U' }}</td>
                        <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                        <td class="col-rem">0</td>
                        <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTVA = $devis->tva ?? 0;
        $livraison = $devis->cout_livraison ?? 0;
        // Remise = code promo / points appliqués sur la page de paiement (passée par
        // le contrôleur). Déduite du total à payer, comme le fait Commande::montantAPayer().
        $remise = $remise ?? 0;
        $totalAPayer = max(0, $totalHT + $totalTVA + $livraison - $remise);
    @endphp
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td></tr>
        @if($livraison > 0)
        <tr><td class="label">Coût livraison</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TOTAL TTC</td><td class="valeur">{{ number_format($totalHT + $totalTVA, 0, '', ' ') }}</td></tr>
        @if($remise > 0)
        <tr><td class="label">Remise (code promo / points)</td><td class="valeur">- {{ number_format($remise, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">AUTRES TAXES</td><td class="valeur">0</td></tr>
        <tr><td class="label" style="font-size:10pt;">TOTAL A PAYER</td><td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td></tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <div class="fne-resume-titre">RESUME DE LA FACTURE</div>
    <table class="fne-resume">
        <thead><tr><th>CATEGORIE</th><th>SOUS-TOTAL</th><th>TAUX (%)</th><th>TOTAL TAXES</th></tr></thead>
        <tbody>
            <tr>
                <td>TVA {{ $config->tva ?? 0 }}% sur HT</td>
                <td class="text-right">{{ number_format($totalHT, 0, '', ' ') }}</td>
                <td class="text-center">{{ $config->tva ?? 0 }}%</td>
                <td class="text-right">{{ number_format($totalTVA, 0, '', ' ') }}</td>
            </tr>
        </tbody>
    </table>
    <br>
    <div style="text-align:center; margin-top:20px;">
        @if(session('type') == 'devis' && isset($devis))
            <a href="{{ route('client.panierCommande', $devis) }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la commande</a>
        @endif
    </div>
@endsection
