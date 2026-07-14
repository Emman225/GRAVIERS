@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $fne_numero = $livraison->numero ?? '';
    $fne_adresse = '';
@endphp
@include('document.layouts._fne_init', ['fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Demande de livraison validée')
@section('type_document', 'Bon de livraison')

@section('articles')
    <div style="background:#d4edda; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#155724;">Demande de livraison Validée</div>

    <div style="margin-bottom:10px;">
        <p style="font-size:9pt;"><strong>Date de demande :</strong> {{ ucfirst($livraison->created_at->dayName) . ' ' . $livraison->created_at->isoFormat('LL') }} à {{ Carbon::parse($livraison->created_at)->format('H:i') }}</p>
        <p style="font-size:9pt;"><strong>Numéro :</strong> {{ $livraison->numero }}</p>
    </div>

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
            @foreach($livraison->detailLivraison as $detail)
                @php $index++; @endphp
                <tr>
                    <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                    <td class="col-designation">{{ $detail->nom_produit }}</td>
                    <td class="col-pu">{{ $detail->description }}</td>
                    <td class="col-qte">{{ $detail->qte }}</td>
                    <td class="col-unite">{{ $detail->uniteProduit->libelle ?? 'U' }}</td>
                    <td class="col-montant">-</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">Prise en charge</td><td class="valeur">{{ $livraison->priseEnCharge->affichage ?? '' }}</td></tr>
        <tr><td class="label">Destination</td><td class="valeur">{{ $livraison->destination->affichage ?? '' }}</td></tr>
        <tr><td class="label">Type de livraison</td><td class="valeur">{{ $livraison->TypeLivraison->libelle ?? '' }}</td></tr>
        <tr><td class="label">Mode de paiement</td><td class="valeur">{{ $livraison->ModeDePaiement?->description ?? '' }}</td></tr>
        <tr><td class="label" style="font-size:10pt;">TOTAL A PAYER</td><td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($livraison->montantTotal, 0, '', ' ') }}</td></tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <br>
    <div style="text-align:center; margin-top:20px;">
        <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
    </div>
@endsection
