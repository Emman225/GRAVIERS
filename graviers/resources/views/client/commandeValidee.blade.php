@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $fne_numero = $commande->numero ?? '';
    $clientObj = $commande->client ?? null;
    $fne_adresse = $commande->adresseLivraison?->affichage ?? '';
@endphp
@include('document.layouts._fne_init', ['client' => $clientObj, 'fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Commande validée')
@section('type_document', 'Facture de vente')

@if($commande->modePaiement)
    @section('mode_paiement', ucwords($commande->modePaiement->description))
@endif

@if($fne_adresse)
    @section('adresse_livraison', ucwords($fne_adresse))
@endif

@section('articles')
    <div style="background:#d4edda; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#155724;">Commande Validée</div>

    <div style="margin-bottom:10px;">
        <p style="font-size:9pt;"><strong>Date de commande :</strong> {{ ucfirst($commande->created_at->dayName) . ' ' . $commande->created_at->isoFormat('LL') }} à {{ Carbon::parse($commande->created_at)->format('H:i') }}</p>
        <p style="font-size:9pt;"><strong>Numéro de commande :</strong> {{ $commande->numero }}</p>
    </div>

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
            @foreach($commande->detailCommande as $detail)
                @php
                    $pu = isset($prixPerso[$detail->produit->id]) ? $prixPerso[$detail->produit->id] : $detail->produit->prix_moyen;
                    $montant = $pu * $detail->qte;
                    $totalHT += $montant;
                    $index++;
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
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTVA = $commande->TvaCommande->montant ?? 0;
        $livraison = $commande->cout_livraison_client ?? 0;
        $remise = $commande->remise ?? 0;
        $totalAPayer = ($commande->montant_total + $totalTVA + $livraison) - $remise;
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td></tr>
        @if($livraison > 0)
        <tr><td class="label">Coût livraison</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        @if($remise > 0)
        <tr><td class="label">Remise</td><td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TOTAL TTC</td><td class="valeur">{{ number_format($totalHT + $totalTVA, 0, '', ' ') }}</td></tr>
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
        <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
    </div>
@endsection
