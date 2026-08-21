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
                        // detail_location.prix contient DÉJÀ le total de la ligne :
                        // il est écrit « qte × prix unitaire × nombre de jours » à la
                        // création (ClientController). On le remultipliait ici par la
                        // quantité ET par le nombre de jours : sur 2 engins loués
                        // 3 jours, la ligne était comptée SIX fois — 5 400 000 affichés
                        // pour 900 000 réellement dus. La facture, elle, le traitait
                        // déjà correctement (document/factureLocation).
                        $qte     = (float) ($detail->qte ?: 1);
                        $jours   = (int) ($detail->nombre_jour ?: 1);
                        $montant = (float) ($detail->prix ?? 0);

                        // Repli quand le prix n'a pas été figé sur la ligne.
                        if ($montant <= 0) {
                            $montant = (float) ($detail->produit?->prix_moyen ?? 0) * $qte * $jours;
                        }

                        // Prix unitaire par jour, pour que la ligne se relise :
                        // P.U. × quantité × jours = montant.
                        $prixUnitaire = ($qte > 0 && $jours > 0) ? $montant / ($qte * $jours) : $montant;

                        $totalHT += $montant; $index++;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="col-designation">{{ ucwords($detail->produit?->nom) }} (location {{ $jours }} j)</td>
                        <td class="col-qte">{{ $detail->qte }}</td>
                        <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
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

    {{-- Ordre des lignes : la REMISE se déduit AVANT la TVA, et la TVA porte sur
         le HT net de remise. C'est déjà ainsi qu'elle est calculée
         (ClientController : $htNet = total − remise, puis tva = htNet × taux),
         mais la remise était affichée APRÈS la TVA, tout en bas : la lecture
         laissait croire que la taxe s'appliquait au HT brut, et le document
         devenait invérifiable. Même présentation que le récapitulatif des ventes
         (recapPanierVersCommande).

         Les lignes « Réduction code promo » et « Réduction par point » ont été
         retirées : $Promo et $point valent toujours zéro — session('remise')
         porte DÉJÀ le total des deux — donc elles ne s'affichaient jamais. --}}
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT, 0, '', ' ') }}</td></tr>
        @if($remise > 0)
        <tr><td class="label">Remise</td><td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TOTAL HT NET</td><td class="valeur">{{ number_format(max(0, $totalHT - $remise), 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA, 0, '', ' ') }}</td></tr>
        @if($livraison > 0)
        <tr><td class="label">Coût livraison</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label" style="font-size:10pt;">TOTAL A PAYER</td><td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td></tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <div class="fne-resume-titre">RESUME DE LA FACTURE</div>
    <table class="fne-resume">
        <thead><tr><th>CATEGORIE</th><th>SOUS-TOTAL</th><th>TAUX (%)</th><th>TOTAL TAXES</th></tr></thead>
        <tbody>
            {{-- Base d'imposition = HT NET de remise, celle sur laquelle la TVA est
                 réellement calculée. La colonne SOUS-TOTAL affichait le HT BRUT :
                 sur 450 000 remisés de 50 000 à 18 %, la ligne annonçait
                 « 450 000 × 18 % = 72 000 », alors que 450 000 × 18 % font 81 000.
                 Le résumé fiscal ne tombait donc pas juste dès qu'il y avait une
                 remise. --}}
            <tr>
                <td>TVA {{ $config->tva ?? 0 }}% sur HT{{ ($remise ?? 0) > 0 ? ' net de remise' : '' }}</td>
                <td class="text-right">{{ number_format(max(0, $totalHT - ($remise ?? 0)), 0, '', ' ') }}</td>
                <td class="text-center">{{ $config->tva ?? 0 }}%</td>
                <td class="text-right">{{ number_format($totalTVA, 0, '', ' ') }}</td>
            </tr>
        </tbody>
    </table>
    <br>
    @if(!empty($echecPaiementEnLigne))
        {{-- La passerelle n'a pas pu s'ouvrir. La location existe mais n'est pas
             payée : le dire est le minimum, le taire laissait croire l'inverse. --}}
        <div style="margin:20px auto; max-width:640px; padding:14px 18px; border:1px solid #a94442; background:#f9e7e6; border-radius:5px; font-size:12px; color:#7d2b28;">
            <strong>Le paiement en ligne n'a pas pu démarrer.</strong><br>
            Votre location est bien enregistrée, mais elle n'est <strong>pas encore payée</strong>.
            Rendez-vous dans « Mon compte » pour relancer le paiement, ou réglez en agence.<br>
            <span style="opacity:.8">Motif communiqué par la plateforme de paiement : {{ $echecPaiementEnLigne }}</span>
        </div>
    @endif

    <div style="text-align:center; margin-top:20px;">
        @if(isset($location))
            <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
        @else
            <a href="{{ route('client.enregistrementLocation') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la location</a>
        @endif
    </div>
@endsection
