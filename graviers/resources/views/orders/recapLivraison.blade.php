@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $mode = $mode ?? App\Models\ModePaiement::find(session('mode'));
    $fne_numero = 'LIV-' . date('YmdHis');
    $fne_adresse = session('affichageDest') ?? '';
@endphp
@include('document.layouts._fne_init', ['fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif demande de livraison')
@section('type_document', 'Bon de livraison')

@if(isset($mode) && $mode)
    @section('mode_paiement', $mode->libelle ?? '')
@endif

@if($fne_adresse)
    @section('adresse_livraison', ucwords($fne_adresse))
@endif

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Produit</th>
                <th class="col-pu">Description</th>
                <th class="col-qte">Qté</th>
                <th class="col-unite">Unité</th>
                <th class="col-montant">Montant</th>
            </tr>
        </thead>
        <tbody>
            @php $index = 0; @endphp
            @foreach(session('produits') as $produit)
                @php $index++; @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">{{ $produit['nom_produit'] }}</td>
                    <td class="col-pu">{{ $produit['desc'] }}</td>
                    <td class="col-qte">{{ $produit['qte'] }}</td>
                    <td class="col-unite">
                        @foreach($unites as $uneUnite)
                            @if($uneUnite->id == $produit['unite']) {{ $uneUnite->libelle }} @endif
                        @endforeach
                    </td>
                    <td class="col-montant">-</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $montantTva = session('montantTva') ?? 0;
        $montantTotal = session('montant_total') ?? 0;
        $totalAPayer = $montantTotal + $montantTva;
    @endphp
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">Prise en charge</td><td class="valeur">{{ session('affichagePec') }}</td></tr>
        <tr><td class="label">Destination</td><td class="valeur">{{ session('affichageDest') }}</td></tr>
        <tr><td class="label">Distance</td><td class="valeur">{{ session('km') }} km</td></tr>
        <tr><td class="label">Type de livraison</td><td class="valeur">{{ session('type_livraison') }}</td></tr>
        <tr><td class="label">TVA</td><td class="valeur">{{ number_format($montantTva, 0, '', ' ') }}</td></tr>
        <tr><td class="label" style="font-size:10pt;">TOTAL A PAYER</td><td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td></tr>
    </table>

    @if($totalAPayer > 2000000)
        <p style="color:red; font-size:9pt; margin-top:8px;">
            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
        </p>
    @endif
@endsection

@section('resume_fiscal')
    <br>
    <div style="text-align:center; margin-top:20px;">
        <a href="{{ route('client.valideDemandeLivraison') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la demande de livraison</a>
    </div>
@endsection
