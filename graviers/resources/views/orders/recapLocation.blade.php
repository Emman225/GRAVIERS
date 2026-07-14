@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $fne_numero = isset($location) ? $location->numero : 'LOC-' . date('YmdHis');
    $fne_adresse = isset($location) ? ($location->adresseLivraison?->affichage ?? '') : '';
    $clientObj = isset($location) ? $location->client : ($client ?? null);
@endphp
@include('document.layouts._fne_init', ['client' => $clientObj, 'fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', 'Récapitulatif location')
@section('type_document', isset($location) ? 'Facture de location' : 'Proforma location')

@if(isset($mode) && $mode)
    @section('mode_paiement', is_string($mode) ? $mode : ($mode->libelle ?? ''))
@endif

@if($fne_adresse)
    @section('adresse_livraison', ucwords($fne_adresse))
@endif

@section('articles')
    @if(isset($location))
        <div style="background:#d4edda; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#155724;">Location Validée</div>
        <div style="margin-bottom:10px;">
            <p style="font-size:9pt;"><strong>Date de location :</strong> {{ ucfirst($location->created_at->dayName) . ' ' . $location->created_at->isoFormat('LL') }} à {{ Carbon::parse($location->created_at)->format('H:i') }}</p>
            <p style="font-size:9pt;"><strong>Numéro :</strong> {{ $location->numero }}</p>
        </div>
    @endif

    <table class="fne-articles">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-designation">Désignation</th>
                <th class="col-qte">Qté</th>
                <th class="col-pu">Prix/jour</th>
                <th class="col-unite">Délai</th>
                <th class="col-montant">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @php $totalHT = 0; $index = 0; $i = 0; $livraison = 0; $remise = 0; $Promo = 0; $point = 0; @endphp

            @if(isset($location))
                @foreach($location->detailLocation as $detail)
                    @php
                        $montant = ($detail->prix ?? $detail->produit->prix_moyen) * $detail->qte * $detail->nombre_jour;
                        $totalHT += $montant; $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($detail->produit->nom) }}</td>
                        <td class="col-qte">{{ $detail->qte }}</td>
                        <td class="col-pu">{{ number_format($detail->prix ?? $detail->produit->prix_moyen, 0, '', ' ') }}</td>
                        <td class="col-unite">Du {{ $detail->debut }} au {{ $detail->fin }}</td>
                        <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                    </tr>
                @endforeach
            @elseif(Cart::content()->count() > 0)
                @foreach(Cart::content() as $produit)
                    @php
                        $montant = $produit->price * $produit->qty * session('nbre_jour')[$i];
                        $totalHT += $montant; $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($produit->model->nom) }}</td>
                        <td class="col-qte">{{ $produit->qty }}</td>
                        <td class="col-pu">{{ number_format($produit->price, 0, '', ' ') }}</td>
                        <td class="col-unite">Du {{ Carbon::parse(session('debuts')[$i])->format('d-m-Y') }} au {{ Carbon::parse(session('fins')[$i])->format('d-m-Y') }}</td>
                        <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                    </tr>
                    @php $i++; @endphp
                @endforeach
            @endif
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTVA = session('0') && isset(session('0')['tva']) ? session('0')['tva'] : 0;
        if(isset($location)) {
            $totalTVA = $location->tvaLocation->montant ?? $totalTVA;
            $livraison = $location->cout_livraison_client ?? 0;
            // Remise déduite (cohérent avec le montant réellement payé) : HT - remise + TVA + livraison.
            $remise = $location->remise ?? 0;
            $totalAPayer = max(0, $location->montant_total - $remise) + $totalTVA + $livraison;
        } else {
            if(session('0') && isset(session('0')['cout_livraison'])) $livraison = session('0')['cout_livraison'];
            // session('remise') porte DÉJÀ la remise totale (code promo + valeur des points).
            // On ne recompte donc PAS $Promo/$point séparément, sinon la remise serait
            // déduite deux fois (bug : proforma affichait 150 au lieu de 165).
            $remise = session('remise') ?? 0;
            $Promo = 0; $point = 0;
            // Source de vérité = session('0')['montantTTC'] (identique au mode-paiement).
            $totalTVA = session('0')['tva'] ?? $totalTVA;
            $totalAPayer = session('0')['montantTTC'] ?? ($totalHT - $remise + $totalTVA + $livraison);
        }
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td></tr>
        @if($livraison > 0)
        <tr><td class="label">Coût livraison</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        @if($Promo > 0)
        <tr><td class="label">Réduction code promo</td><td class="valeur">-{{ number_format($Promo, 0, '', ' ') }}</td></tr>
        @endif
        @if($point > 0)
        <tr><td class="label">Réduction par point</td><td class="valeur">-{{ number_format($point, 0, '', ' ') }}</td></tr>
        @endif
        @if($remise > 0)
        <tr><td class="label">Remise</td><td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td></tr>
        @endif
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
        @if(isset($location))
            <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
        @else
            <a href="{{ route('client.enregistrementLocation') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la location</a>
        @endif
    </div>
@endsection
