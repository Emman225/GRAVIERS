@php
    $config = $config ?? App\Models\Configuration::first();
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Devis')
@section('type_document', 'Devis')

@if($devis->adresse_livraison_id && $devis->adresseLivraison)
    @section('adresse_livraison', $devis->adresseLivraison->affichage)
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
                // Le transport est une ligne du tableau (09/09/2026), taxée quand
                // le paramétrage l'avait décidé au moment du devis.
                $coutLivraison = \Help::arrondiFranc((float) ($devis->cout_livraison ?? 0));
                $tvaTransport  = \Help::arrondiFranc((float) ($devis->tva_transport ?? 0));
                // Le numéro de bon de commande interne en colonne Réf, « 01 - N° » (13/09/2026).
                $refBon = \Help::referenceBonDeCommande($devis->numero_bon_commande ?? null);
            @endphp

            @foreach ($devis->detailDevis as $index => $detail)
                @if($detail->deleted_at == null)
                    @php
                        $prixUnitaire = $detail->prix ?? $detail->produit?->prix_moyen;
                        $montantLigne = $prixUnitaire * $detail->qte;
                        $totalHT += $montantLigne;
                    @endphp
                    <tr>
                        <td class="col-ref">{{ \Help::referenceLigne($index + 1, $refBon) }}</td>
                        <td class="col-designation">{{ $detail->produit?->nom }}</td>
                        <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
                        <td class="col-qte">{{ $detail->qte }}</td>
                        <td class="col-unite">{{ $detail->produit?->uniteProduit->libelle ?? 'U' }}</td>
                        <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                        <td class="col-rem">0</td>
                        <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                    </tr>
                @endif
            @endforeach

            @include('document.partials._ligne_transport', ['adresse' => ($devis->adresse_livraison_id && $devis->adresseLivraison) ? $devis->adresseLivraison->affichage : null])
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        // LE DEVIS S'ADDITIONNE, TRANSPORT COMPRIS (points 5 et 7, 07/09/2026).
        //
        // Le transport figurait sous le TOTAL TTC, qui ne le comptait pas :
        // le client lisait un TTC puis un « total à payer » plus grand, sans
        // ligne pour expliquer l'écart. Il entre désormais dans le TTC, avec
        // sa TVA quand elle s'applique, et le total à payer EST le TTC moins
        // la remise — la même présentation que la facture.
        $montantTVA    = \Help::arrondiFranc((float) ($devis->tva ?? 0));
        // $coutLivraison et $tvaTransport sont établis en tête du tableau.
        $coutReduction = \Help::arrondiFranc((float) ($devis->cout_reduction ?? 0));
        $totalTTC      = max(0, $totalHT - $coutReduction) + $montantTVA + $coutLivraison + $tvaTransport;
        $airsi         = \Help::arrondiFranc((float) ($devis->airsi ?? 0));
        $totalAPayer   = $totalTTC + $airsi;
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        {{-- TOTAL HT = la somme du tableau, transport compris ; TVA = celle des
             articles plus celle du transport quand il est taxé (09/09/2026). --}}
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT + ($tvaTransport > 0 ? $coutLivraison : 0), 0, '', ' ') }}</td>
        </tr>
        @if($coutReduction > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($coutReduction, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">TVA ({{ $config->tva ?? 0 }}%)</td>
            <td class="valeur">{{ number_format($montantTVA + $tvaTransport, 0, '', ' ') }}</td>
        </tr>
        {{-- Transport NON taxé : présentation d'avant, sous les totaux. --}}
        @if($coutLivraison > 0 && $tvaTransport <= 0)
        <tr>
            <td class="label">Transport (HT)</td>
            <td class="valeur">{{ number_format($coutLivraison, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($totalTTC, 0, '', ' ') }}</td>
        </tr>
        @include('document.partials._ligne_airsi', ['airsi' => $airsi ?? 0])
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
            @include('document.partials._resume_fiscal_ligne', ['baseArticles' => $totalHT, 'tvaArticles' => $montantTVA])
        </tbody>
    </table></td></tr></table>
@endsection
