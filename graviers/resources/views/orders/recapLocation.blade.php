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
{{-- « Proforma location » tant que rien n'est payé en ligne, « Facture de location »
     ensuite (lot 85, 15/09/2026 ; DocumentDAffaire::titreLocation). --}}
@section('type_document', $typeDocument ?? (isset($location) ? 'Facture de location' : 'Proforma location'))
{{-- Le vendeur : le point de vente de l'entreprise (lot 87, 15/09/2026). --}}
@section('vendeur', \Help::nomDuVendeur(null))

@if(isset($mode) && $mode)
    @section('mode_paiement', is_string($mode) ? $mode : ($mode->libelle ?? ''))
@endif

@if($fne_adresse)
    @section('adresse_livraison', \Help::phrase($fne_adresse))
@endif

@section('articles')
    @if(isset($location))
        @if (empty($pourPdf))
            <div style="background:#d4edda; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#155724;">Location validée</div>
        @endif
        @if (!empty($messageAvance))
            {{-- Avance du client imputée sur la location (10/09/2026). --}}
            <div class="js-avance-imputee" style="background:#fff3cd; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#856404;">{{ $messageAvance }}</div>
        @endif
        <div style="margin-bottom:10px;">
            <p style="font-size:9pt;"><strong>Date de location :</strong> {{ ucfirst($location->created_at->dayName) . ' ' . $location->created_at->isoFormat('LL') }} à {{ Carbon::parse($location->created_at)->format('H:i:s') }}</p>
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
            {{-- Le numéro de bon de commande interne devant chaque désignation (09/09/2026) :
                 défini AVANT les lignes qui l'utilisent. --}}
            @php $refBon = \Help::referenceBonDeCommande(isset($location) ? ($location->numero_bon_commande ?? null) : session('numero_bon_commande')); @endphp
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
                        <td class="col-ref">{{ \Help::referenceLigne($index, $refBon) }}</td>
                        <td class="col-designation">{{ \Help::phrase($detail->produit?->nom) }} (location {{ $jours }} j)</td>
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
                        <td class="col-ref">{{ \Help::referenceLigne($index, $refBon) }}</td>
                        <td class="col-designation">{{ \Help::phrase($produit->model->nom) }}</td>
                        <td class="col-qte">{{ $produit->qty }}</td>
                        <td class="col-pu">{{ number_format($produit->price, 0, '', ' ') }}</td>
                        <td class="col-unite">Du {{ Carbon::parse(session('debuts')[$i])->format('d-m-Y') }} au {{ Carbon::parse(session('fins')[$i])->format('d-m-Y') }}</td>
                        <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                    </tr>
                    @php $i++; @endphp
                @endforeach
            @endif

            {{-- LE TRANSPORT EST UNE LIGNE DU TABLEAU (09/09/2026), avec la mention
                 de TVA quand le paramétrage l'a taxé ; sa TVA entrait dans le
                 total sans ligne pour l'expliquer. --}}
            @php
                $livraison    = isset($location) ? (float) ($location->cout_livraison_client ?? 0) : (float) (session('0')['cout_livraison'] ?? 0);
                $tvaTransport = isset($location) ? (float) ($location->tva_transport ?? 0) : (float) (session('0')['tva_transport'] ?? 0);
            @endphp
            @if($livraison > 0 && $tvaTransport > 0)
                <tr>
                    <td class="col-ref"></td>
                    <td class="col-designation">Coût de livraison — TVA ({{ $config->tva ?? 0 }}%)</td>
                    <td class="col-qte">1</td>
                    <td class="col-pu">{{ number_format($livraison, 0, '', ' ') }}</td>
                    <td class="col-unite">Forfait</td>
                    <td class="col-montant">{{ number_format($livraison, 0, '', ' ') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTVA = session('0') && isset(session('0')['tva']) ? session('0')['tva'] : 0;
        if(isset($location)) {
            $totalTVA = $location->tvaLocation->montant ?? $totalTVA;
            // Remise déduite (cohérent avec le montant réellement payé) : HT - remise + TVA + livraison.
            $remise = $location->remise ?? 0;
            $airsi = (float) ($location->airsi ?? 0);
            $totalAPayer = max(0, $location->montant_total - $remise) + $totalTVA + $livraison + $tvaTransport + $airsi;
        } else {
            // session('remise') porte DÉJÀ la remise totale (code promo + valeur des points).
            // On ne recompte donc PAS $Promo/$point séparément, sinon la remise serait
            // déduite deux fois (bug : proforma affichait 150 au lieu de 165).
            $remise = session('remise') ?? 0;
            $Promo = 0; $point = 0;
            // Source de vérité = session('0')['montantTTC'] (identique au mode-paiement).
            $totalTVA = session('0')['tva'] ?? $totalTVA;
            // AIRSI (10/09/2026) : sur le HT net de remise + TVA.
            $airsi = \Help::airsiPour(Auth::user()?->client, max(0, $totalHT - $remise) + $totalTVA);
            $totalAPayer = (session('0')['montantTTC'] ?? ($totalHT - $remise + $totalTVA + $livraison)) + $airsi;
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
        @php $htAffiche = $totalHT + ($tvaTransport > 0 ? $livraison : 0); @endphp
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($htAffiche, 0, '', ' ') }}</td></tr>
        @if($remise > 0)
        <tr><td class="label">Remise</td><td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td></tr>
        <tr><td class="label">TOTAL HT NET</td><td class="valeur">{{ number_format(max(0, $htAffiche - $remise), 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA + $tvaTransport, 0, '', ' ') }}</td></tr>
        {{-- Transport NON taxé : présentation d'avant, sous les totaux. --}}
        @if($livraison > 0 && $tvaTransport <= 0)
        <tr><td class="label">Coût livraison</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        @include('document.partials._ligne_airsi', ['airsi' => $airsi ?? 0])
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
                <td class="text-right">{{ number_format(max(0, $totalHT - ($remise ?? 0)) + ($tvaTransport > 0 ? $livraison : 0), 0, '', ' ') }}</td>
                <td class="text-center">{{ $config->tva ?? 0 }}%</td>
                <td class="text-right">{{ number_format($totalTVA + $tvaTransport, 0, '', ' ') }}</td>
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
        @if(!empty($pourPdf))
            {{-- Le PDF de Mon compte : sans bouton. --}}
        @elseif(isset($location))
            <a href="{{ route('client.documentLocationPdf', $location) }}" style="display:inline-block; padding:12px 30px; background-color:#6c757d; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold; margin-right:8px;">Télécharger ({{ \App\Services\DocumentDAffaire::titreLocation($location) }})</a>
            <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
        @else
            <a href="{{ route('client.enregistrementLocation') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Valider la location</a>
        @endif
    </div>
@endsection
