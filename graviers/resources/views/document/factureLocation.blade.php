@php
    use Illuminate\Support\Carbon;
    // Le mode de paiement RÉEL : celui des règlements validés de la location (lot 86, 15/09/2026).
    $modeLibelle = \Help::modePaiementDeLAffaire(\Help::$LOCATION, (int) ($location->id ?? 0),
        optional(\App\Models\ModePaiement::find($location->mode_paiement_id))->libelle);
@endphp

@extends('document.layouts.fne_base')

@section('titre', 'Facture de location')
@section('type_document', 'Facture de location')

@section('vendeur', \Help::nomDuVendeur($facture->user ?? null))
@section('mode_paiement', $modeLibelle)

@if($location->adresseLivraison)
    @section('adresse_livraison', \Help::phrase($location->adresseLivraison->affichage))
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
                $remise = $location->remise ?? 0;
                // Le transport est une ligne du tableau (09/09/2026), taxée quand
                // le paramétrage l'avait décidé au moment de la location.
                $coutLivraison = (float) ($location->cout_livraison_client ?? 0);
                $tvaTransport  = \Help::arrondiFranc((float) ($location->tva_transport ?? 0));
                // Le taux imprimé est celui de l'AFFAIRE (15/09/2026) : 0 pour un
                // client dispensé de TVA, celui du paramétrage sinon.
                $tauxTva = \Help::tauxTvaAffaire($location);
                // Nature de l'exonération du client pour le résumé fiscal (lot 82).
                $codeExoneration = $location->client?->codeExonerationFne();
                // Le numéro de bon de commande interne en colonne Réf, « 01 - N° » (13/09/2026).
                $refBon = \Help::referenceBonDeCommande($location->numero_bon_commande ?? null);
            @endphp

            @foreach ($location->detailLocation as $index => $detail)
                @php
                    $qte         = (float) ($detail->qte ?: 1);
                    $jours       = (int) ($detail->nombre_jour ?: 1);
                    $montantLigne = (float) ($detail->prix ?? 0);          // total ligne = qte × pu/jour × jours
                    $puPeriode   = $qte > 0 ? $montantLigne / $qte : $montantLigne; // P.U HT (par unité, période complète)
                    $tvaLigne    = $montantLigne * ($tauxTva / 100);
                    $totalHT    += $montantLigne;
                    $totalTVA   += $tvaLigne;
                @endphp
                <tr>
                    <td class="col-ref">{{ \Help::referenceLigne($index + 1, $refBon) }}</td>
                    {{-- LA LIGNE DOIT SE LIRE SANS AMBIGUÏTÉ.
                         « Bétonnière (location 2 j) — P.U 22 000 — Qté 1 » invitait à
                         multiplier : 22 000 x 2 jours ? Le prix imprimé couvre déjà la
                         période entière (detail_location.prix = qté x tarif x jours).
                         On donne donc AUSSI le tarif journalier : le lecteur retrouve
                         la période complète de tête, et la question ne se pose plus. --}}
                    <td class="col-designation">
                        {{ \Help::phrase($detail->produit?->nom ?? 'Location') }}
                        @if ($jours > 1)
                            — {{ $jours }} jours à {{ number_format($puPeriode / $jours, 0, '', ' ') }}/jour
                        @else
                            — 1 jour
                        @endif
                    </td>
                    <td class="col-pu">{{ number_format($puPeriode, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ $detail->qte }}</td>
                    <td class="col-unite">{{ $detail->produit?->uniteProduit->libelle ?? 'U' }}</td>
                    <td class="col-taxes">TVA ({{ $tauxTva }}%)</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($montantLigne, 0, '', ' ') }}</td>
                </tr>
            @endforeach

            @include('document.partials._ligne_transport', ['adresse' => $location->adresseLivraison?->affichage ?? null])
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        // LE DOCUMENT S'ADDITIONNE — c'est la règle, et elle ne souffre pas
        // d'exception. Un client doit pouvoir refaire le calcul au stylo.
        //
        // La TVA se prenait sur la valeur STOCKÉE, qui pouvait contredire les
        // lignes : la facture U260000000002 annonçait 7 920 de TVA sur un HT de
        // 22 000, soit le double des 18 % affichés à la ligne.
        //
        // L'enquête a montré que la TVA stockée était JUSTE et la ligne fausse :
        // l'API mobile écrivait le tarif journalier (22 000) dans une colonne qui
        // porte le total de la ligne — 22 000 x 2 jours = 44 000, dont 7 920 de
        // TVA. Le défaut d'origine est corrigé côté API (LocationController).
        //
        // La TVA se calcule néanmoins sur le HT RÉELLEMENT IMPRIMÉ : c'est ce qui
        // rend le document vérifiable au stylo. Les deux valeurs coïncident
        // désormais ; si elles divergeaient encore, c'est la ligne qu'il faudrait
        // corriger, jamais le total qu'il faudrait maquiller.
        // $coutLivraison et $tvaTransport sont établis en tête du tableau.
        $remise        = $location->remise ?? 0;
        $totalTVA      = \Help::arrondiFranc(max(0, $totalHT - $remise) * ($tauxTva / 100));

        // LE TOTAL S'ADDITIONNE À PARTIR DES MONTANTS IMPRIMÉS.
        //
        // Chaque poste s'affiche arrondi au franc ; si le total part des valeurs
        // BRUTES, le papier ne tombe pas juste. Ici : 24 750 - 3 723 + 3 785
        // + 4 000 = 28 812, alors que la remise réelle de 3 722,5 donnait un
        // total de 28 813. Un franc d'écart, mais un client qui refait
        // l'addition ne trouve pas son compte — et il a raison.
        $remise        = \Help::arrondiFranc($remise);
        $totalTTC      = max(0, $totalHT - $remise) + $totalTVA + $coutLivraison + $tvaTransport;

        // Le total à payer EST le TTC. Il reprenait facture.montant, calculé
        // ailleurs et par une autre formule : 67 840 s'affichait sous des lignes
        // qui en totalisaient 29 960.
        $airsi         = \Help::arrondiFranc((float) ($location->airsi ?? 0));
        $totalAPayer = $totalTTC + $airsi;

        // Client dispensé de TVA : la TVA qui aurait été calculée (15/09/2026).
        $tvaNonFacturee = \Help::tvaNonFacturee(max(0, $totalHT - $remise), $tauxTva, $coutLivraison, $tvaTransport);
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        {{-- TOTAL HT = la somme du tableau, transport compris ; TVA = celle des
             articles plus celle du transport quand il est taxé (09/09/2026). La
             TVA du transport entrait dans le TTC sans ligne pour l'expliquer. --}}
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
            <td class="label">Coût livraison</td>
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
    </table>
@endsection
