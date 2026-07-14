@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $fne_numero = isset($devis) && $devis->id ? $devis->numero : 'DEV-' . date('YmdHis');
    $fne_adresse = '';
    if (session('0') && isset(session('0')['infoSup'])) $fne_adresse = session('0')['infoSup'];
    elseif (isset($devis) && $devis->adresse_livraison_id) $fne_adresse = $devis->adresseLivraison->affichage ?? '';
@endphp
@include('document.layouts._fne_init', ['fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif devis')
@section('type_document', 'Devis')

@if($fne_adresse)
    @section('adresse_livraison', ucwords($fne_adresse))
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
            @php $totalHT = 0; $index = 0; $livraison = 0; $remise = 0; @endphp

            @if(session('devisAModifier') || session('type') == 'commande')
                @foreach(Cart::content() as $produit)
                    @php
                        $pu = $produit->price; $montant = $pu * $produit->qty; $totalHT += $montant; $index++;
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
            @elseif(isset($devis) && $devis->id)
                @foreach($devis->detailDevis as $d)
                    @php
                        $pu = $d->prix ?? $d->produit->prix_moyen; $montant = $pu * $d->qte; $totalHT += $montant; $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($d->produit->nom) }}</td>
                        <td class="col-pu">{{ number_format($pu, 0, '', ' ') }}</td>
                        <td class="col-qte">{{ $d->qte }}</td>
                        <td class="col-unite">{{ $d->produit->uniteProduit->abreviation ?? 'U' }}</td>
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
        $totalTVA = 0;
        if (session('devisAModifier') || session('type') == 'commande') {
            $totalTVA = ($totalHT * ($config->tva ?? 0) / 100) * ($client->applique_tva ?? 0);
            if (session('0') && isset(session('0')['cout_livraison'])) $livraison = session('0')['cout_livraison'];
            if (session('niveauModifDevis') == 1 && isset($devis)) $livraison = $devis->cout_livraison ?? 0;
            $remise = session('remise') ?? 0;
        } else {
            $totalTVA = $devis->tva ?? 0;
            $livraison = $devis->cout_livraison ?? 0;
            $remise = $devis->cout_reduction ?? 0;
        }
        $totalAPayer = $totalHT + $totalTVA + $livraison - $remise;
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
        @if(session('type') == 'commande')
            <a href="{{ route('client.panierDevis') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Enregistrer le devis</a>
        @endif
        @if(isset($devis) && $devis->id)
            @if(session('niveauModifDevis') && session('niveauModifDevis') != 0)
                <a href="{{ route('devis.updateDevis', $devis) }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Enregistrer le devis</a>
            @else
                <a href="{{ route('client.panierCommande', $devis) }}" style="display:inline-block; padding:12px 40px; background-color:#28a745; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Passer en commande maintenant</a>
            @endif
        @endif
    </div>
@endsection
