@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();
    $mode = $mode ?? App\Models\ModePaiement::find(session('mode'));

    $fne_numero = isset($commande) ? $commande->numero : 'CMD-' . date('YmdHis');
    $fne_adresse = isset($commande) ? ($commande->adresseLivraison->affichage ?? '') : ($lieu ?? '');

    $clientObj = isset($commande) ? $commande->client : ($client ?? null);
@endphp
@include('document.layouts._fne_init', ['client' => $clientObj, 'fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif commande')
@section('type_document', isset($commande) ? 'Facture de vente' : 'Proforma')

@if(isset($commande) && $commande->modePaiement)
    @section('mode_paiement', ucwords($commande->modePaiement->libelle))
@elseif(isset($mode) && $mode)
    @section('mode_paiement', ucwords($mode->libelle))
@endif

@if($fne_adresse)
    @section('adresse_livraison', ucwords($fne_adresse))
@endif

@section('articles')
    @if(isset($commande))
        <div style="margin-bottom:10px;">
            <p style="font-size:9pt;"><strong>Date de commande :</strong> {{ ucfirst($commande->created_at->dayName) . ' ' . $commande->created_at->isoFormat('LL') }} à {{ Carbon::parse($commande->created_at)->format('H:i') }}</p>
            <p style="font-size:9pt;"><strong>Numéro de commande :</strong> {{ $commande->numero }}</p>
        </div>
    @endif

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
            @php $totalHT = 0; $totalTVA = 0; $index = 0; @endphp

            @if(isset($commande))
                @foreach($commande->detailCommande as $detail)
                    @php
                        $pu = $detail->prix ?? $detail->produit->prix_moyen;
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
            @elseif(Cart::content()->count() > 0)
                @foreach(Cart::content() as $produit)
                    @php
                        $pu = $produit->price;
                        $montant = $pu * $produit->qty;
                        $totalHT += $montant;
                        $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($produit->model->nom) }}</td>
                        <td class="col-pu">{{ number_format($pu, 0, '', ' ') }}</td>
                        <td class="col-qte">{{ $produit->qty }}</td>
                        <td class="col-unite">{{ $produit->model->uniteProduit->abreviation ?? 'U' }}</td>
                        <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                        <td class="col-rem">0</td>
                        <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                    </tr>
                @endforeach
            @elseif(session('type') == 'devis' && isset($devis))
                @foreach($devis->detailDevis as $detail)
                    @php
                        $pu = $detail->prix ?? $detail->produit->prix_moyen;
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
            @endif
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        if(isset($commande)) {
            $totalTVA = $commande->TvaCommande->montant ?? 0;
            $totalAPayer = $commande->montant_total;
        } elseif(session('type') == 'devis' && isset($devis)) {
            $totalTVA = $devis->tva;
            $totalAPayer = $devis->montant + $devis->tva;
        } else {
            $totalTVA = ($totalHT * ($config->tva ?? 0)) / 100;
            $totalAPayer = $totalHT + $totalTVA;
        }
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td></tr>
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
        @if(isset($commande))
            <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
        @elseif(session('type') == 'devis')
            <a href="{{ route('client.devisCommande') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la commande</a>
        @elseif(Cart::content()->count() > 0)
            <a href="{{ route('client.panierCommande') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la commande</a>
        @endif
    </div>
@endsection
