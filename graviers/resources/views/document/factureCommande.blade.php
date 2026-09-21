@php
    use Illuminate\Support\Carbon;
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Facture de vente')
@section('type_document', 'Facture de vente')

@section('vendeur', \Help::nomDuVendeur($facture->user ?? null))
{{-- Le mode de paiement RÉEL : celui des règlements validés de la commande (lot 86, 15/09/2026). --}}
@section('mode_paiement', ($commande instanceof \App\Models\Commande)
    ? \Help::modePaiementDeLAffaire(\Help::$COMMANDE, (int) $commande->id, ($commande->modePaiement?->libelle ?: null) ?? $commande->devis?->modePaiement?->libelle)
    : 'N/A')

{{-- Le numéro de bon de commande du client (entreprise) est en colonne Réf
     de chaque ligne (13/09/2026) ; la mention d'en-tête a été retirée (15/09/2026). --}}
@php $bonDeCommandeClient = ($commande instanceof \App\Models\Commande) ? $commande->blClient : null; @endphp

@if($commande->adresseLivraison)
    @section('adresse_livraison', \Help::phrase($commande->adresseLivraison->affichage))
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
                $totalTVA = 0;
                $coutLivraison = 0;
                $tvaTransport = 0;
                $remise = $commande->remise ?? 0;
                // Le numéro de bon de commande interne en colonne Réf, « 01 - N° » (13/09/2026).
                $refBon = \Help::referenceBonDeCommande($bonDeCommandeClient?->numero);
                // Le taux imprimé est celui de l'AFFAIRE (15/09/2026) : 0 pour un
                // client dispensé de TVA, celui du paramétrage sinon.
                $tauxTva = \Help::tauxTvaAffaire($commande);
                // Nature de l'exonération du client pour le résumé fiscal (lot 82).
                $codeExoneration = ($commande instanceof \App\Models\Commande) ? $commande->client?->codeExonerationFne() : null;

                // LE TRANSPORT EST UNE LIGNE DU TABLEAU (09/09/2026) : il est
                // établi ici, avant les lignes, et non plus sous les totaux. Il
                // n'appartient qu'à la PREMIÈRE facture d'une commande (règle de
                // genererFacture) ; les factures partielles suivantes n'en portent pas.
                $commandeFacturee = $facture?->commande;
                $premiere = ($commandeFacturee && $facture)
                    ? $commandeFacturee->factures()->where('id', '<=', $facture->id)->count() <= 1
                    : true;
                if ($livraison == 1 && $premiere) {
                    $coutLivraison = (float) ($commandeFacturee?->cout_livraison_client ?? 0);
                    $tvaTransport  = \Help::arrondiFranc((float) ($commandeFacturee?->tva_transport ?? 0));
                }
            @endphp

            {{-- FACTURE SUR RÈGLEMENT : aucun enlèvement n'y est rattaché, la
                 marchandise n'étant pas encore retirée. On imprime alors les
                 lignes de la commande, au prorata de ce que couvre la facture
                 (cf. FacturationCommande::donneesDocument). Sans cela, le
                 tableau sortait vide et tous les totaux à 0. --}}
            @if ($enlevements->isEmpty() && !empty($lignesReglement))
                @foreach ($lignesReglement as $index => $ligneReglement)
                    @php
                        $montantLigne = $ligneReglement['qte'] * $ligneReglement['prix'];
                        $totalHT += $montantLigne;
                        $totalTVA += $montantLigne * ($tauxTva / 100);
                    @endphp
                    <tr>
                        <td class="col-ref">{{ \Help::referenceLigne($index + 1, $refBon) }}</td>
                        <td class="col-designation">{{ \Help::phrase($ligneReglement['produit']) }}</td>
                        <td class="col-pu">{{ number_format($ligneReglement['prix'], 0, '', ' ') }}</td>
                        <td class="col-qte">{{ rtrim(rtrim(number_format($ligneReglement['qte'], 2, ',', ' '), '0'), ',') }}</td>
                        <td class="col-unite">{{ $ligneReglement['unite'] }}</td>
                        <td class="col-taxes">TVA ({{ $tauxTva }}%)</td>
                        <td class="col-rem">0</td>
                        <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                    </tr>
                @endforeach
            @endif

            @foreach ($enlevements as $index => $env)
                @php
                    $prixUnitaire = $env->livraison?->detailCommande?->prix;

                    // La quantité FACTURÉE est celle que le fournisseur a
                    // réellement servie, la demandée tant que le bon n'est pas
                    // servi (Enlevement::quantiteAPayer). C'est déjà celle que
                    // retient le calcul du montant de la facture
                    // (OrdersController::creerFacturePourEnlevements) : la
                    // ligne affichait la quantité DEMANDÉE, si bien qu'un bon
                    // servi partiellement montrait un détail plus élevé que le
                    // total à payer inscrit juste en dessous.
                    $qteFacturee = $env->quantiteAPayer();
                    $montantLigne = $qteFacturee * $prixUnitaire;
                    $tvaLigne = $montantLigne * ($tauxTva / 100);
                    $totalHT += $montantLigne;
                    $totalTVA += $tvaLigne;
                @endphp
                <tr>
                    <td class="col-ref">{{ \Help::referenceLigne($index + 1, $refBon) }}</td>
                    <td class="col-designation">{{ \Help::phrase($env->produit?->nom) }}</td>
                    <td class="col-pu">{{ number_format($prixUnitaire, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ rtrim(rtrim(number_format($qteFacturee, 2, ',', ' '), '0'), ',') }}</td>
                    <td class="col-unite">{{ $env->produit?->uniteProduit->libelle ?? 'U' }}</td>
                    <td class="col-taxes">TVA ({{ $tauxTva }}%)</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                </tr>
            @endforeach

            @include('document.partials._ligne_transport', ['adresse' => $commande->adresseLivraison?->affichage ?? null])
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        // LE DOCUMENT REPRODUIT LA FORMULE DU MONTANT STOCKÉ.
        //
        // La TVA imprimée se calculait sur le HT BRUT — 18 % de 24 750 = 4 455 —
        // alors que le montant réclamé au client, lui, l'assied sur le HT REMISE
        // DÉDUITE : 21 027,5 x 18 % = 3 785. La facture U260000000001 annonçait
        // donc « TVA 4 455, TOTAL TTC 29 483 » sous un « TOTAL A PAYER 28 812 ».
        // Le client ne pouvait pas refaire l'addition, et taxer une remise
        // qu'on vient d'accorder n'a aucun sens.
        //
        // La remise et la livraison n'appartiennent QU'À LA PREMIÈRE facture
        // d'une commande — c'est la règle de genererFacture. Les réimprimer sur
        // chaque facture partielle les compterait autant de fois qu'il y a de
        // livraisons.
        if ($livraison == 1) {
            // $facture peut etre nul : le gabarit sert aussi a previsualiser un
            // document avant emission. Sans commande liee, on retombe sur le
            // comportement d une premiere facture, le plus courant.
            // $commandeFacturee, $premiere, $coutLivraison et $tvaTransport sont
            // établis en tête du tableau des articles, où le transport figure.
            // Toutes les lectures passent par l'operateur sur-null : une facture
            // peut etre rendue sans sa commande — un test le fait, et une
            // facture orpheline en base le ferait aussi. La page tombait alors
            // en « Attempt to read property on null ».
            $remise        = $premiere ? (float) ($commandeFacturee?->remise ?? 0) : 0.0;

            // La part de remise s'applique au HT DE CETTE FACTURE, comme au
            // calcul du montant : une commande livrée en plusieurs fois voit sa
            // remise répartie, jamais appliquée en entier sur chaque tranche.
            $htCommande = $commandeFacturee ? (float) $commandeFacturee->montantHT() : 0.0;
            $partRemise = $htCommande > 0
                ? min(1.0, (float) ($commandeFacturee?->remise ?? 0) / $htCommande)
                : 0.0;

            $totalTVA = \Help::arrondiFranc($totalHT * (1 - $partRemise) * ($tauxTva / 100));
        }

        // LE TOTAL S'ADDITIONNE À PARTIR DES MONTANTS IMPRIMÉS.
        //
        // Chaque poste s'affiche arrondi au franc ; si le total part des valeurs
        // BRUTES, le papier ne tombe pas juste. Ici : 24 750 - 3 723 + 3 785
        // + 4 000 = 28 812, alors que la remise réelle de 3 722,5 donnait un
        // total de 28 813. Un franc d'écart, mais un client qui refait
        // l'addition ne trouve pas son compte — et il a raison.
        $remise       = \Help::arrondiFranc($remise);
        $tvaTransport = \Help::arrondiFranc($tvaTransport ?? 0);
        $totalTTC     = max(0, $totalHT - $remise) + $totalTVA + $coutLivraison + $tvaTransport;

        // Le total à payer EST le TTC imprimé. Il reprenait facture.montant,
        // calculé ailleurs : les deux pouvaient — et ont — divergé de 671 F.
        // AIRSI porté par cette facture (10/09/2026) ; les anciennes factures n'en ont pas.
        $airsi        = \Help::arrondiFranc((float) ($facture->airsi_applique ?? 0));
        $totalAPayer = $totalTTC + $airsi;

        // Client dispensé de TVA : la TVA qui aurait été calculée (15/09/2026),
        // imprimée en en-tête et transmise à la DGI dans « Autres mentions ».
        $tvaNonFacturee = \Help::tvaNonFacturee(max(0, $totalHT - $remise), $tauxTva, $coutLivraison, $tvaTransport);
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        {{-- TOTAL HT = la somme du tableau, transport compris ; TVA = celle des
             articles plus celle du transport quand il est taxé (09/09/2026). --}}
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($totalHT + ($tvaTransport > 0 ? $coutLivraison : 0), 0, '', ' ') }}</td>
        </tr>
        @if($remise > 0)
        <tr>
            <td class="label">Remise</td>
            <td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">TVA ({{ $tauxTva }}%)</td>
            <td class="valeur">{{ number_format($totalTVA + $tvaTransport, 0, '', ' ') }}</td>
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

@if (($tvaNonFacturee ?? 0) > 0)
    @section('tva_non_facturee', \Help::montantTvaNonFacturee($tvaNonFacturee))
@endif

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
            @include('document.partials._resume_fiscal_ligne', ['baseArticles' => $totalHT, 'tvaArticles' => $totalTVA])
        </tbody>
    </table></td></tr></table>
@endsection
