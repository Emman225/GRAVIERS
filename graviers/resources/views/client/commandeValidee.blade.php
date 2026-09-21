@php
    use Illuminate\Support\Carbon;
    $config = $config ?? App\Models\Configuration::first();
    $fne_numero = $commande->numero ?? '';
    $clientObj = $commande->client ?? null;
    $fne_adresse = $commande->adresseLivraison?->affichage ?? '';
@endphp
@include('document.layouts._fne_init', ['client' => $clientObj, 'fne_adresse' => $fne_adresse])

@extends('document.layouts.fne_base')

@section('titre', $typeDocument ?? 'Commande validée')
{{-- « Proforma » tant que rien n'est payé en ligne, « Facture de vente » dès
     qu'un règlement en ligne est validé (lot 81, 15/09/2026 ;
     DocumentDeCommande::titre). Le même gabarit sert à l'écran, au PDF du
     courriel et au bouton de Mon compte. --}}
@section('type_document', $typeDocument ?? 'Facture de vente')
{{-- Le vendeur : le point de vente de l'entreprise (lot 87, 15/09/2026). --}}
@section('vendeur', \Help::nomDuVendeur(null))

@if($commande->modePaiement)
    @section('mode_paiement', \Help::phrase($commande->modePaiement?->description))
@endif

@if($fne_adresse)
    @section('adresse_livraison', \Help::phrase($fne_adresse))
@endif

@section('articles')
    @if (empty($pourPdf))
        <div style="background:#d4edda; padding:8px; text-align:center; margin-bottom:10px; font-weight:bold; color:#155724;">Commande Validée</div>
    @endif

    <div style="margin-bottom:10px;">
        <p style="font-size:9pt;"><strong>Date de commande :</strong> {{ ucfirst($commande->created_at->dayName) . ' ' . $commande->created_at->isoFormat('LL') }} à {{ Carbon::parse($commande->created_at)->format('H:i:s') }}</p>
        <p style="font-size:9pt;"><strong>Numéro de commande :</strong> {{ $commande->numero }}</p>
        @if (!empty($commande->date_livraison))
            {{-- Le délai toléré (lot 81, 15/09/2026) : le client le lit sur son document. --}}
            <p style="font-size:9pt;"><strong>Date de livraison souhaitée :</strong> {{ \Carbon\Carbon::parse($commande->date_livraison)->format('d/m/Y') }}</p>
            <p style="font-size:8pt; color:#555;">{{ \Help::mentionDelaiLivraison() }}</p>
        @endif
    </div>

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
                $totalHT = 0; $index = 0;
                // Le transport est une ligne du tableau (09/09/2026), taxée quand
                // le paramétrage l'avait décidé au moment de la commande.
                $coutLivraison = (float) ($commande->cout_livraison_client ?? 0);
                $tvaTransport  = (float) ($commande->tva_transport ?? 0);
                // Le numéro de bon de commande interne en colonne Réf, « 01 - N° » (13/09/2026).
                $refBon = \Help::referenceBonDeCommande($commande->blClient?->numero);
            @endphp
            @foreach($commande->detailCommande as $detail)
                @php
                    // Le prix FIGÉ sur la ligne d'abord (le document ne doit pas bouger
                    // avec le catalogue) ; repli sur le prix personnalisé puis catalogue.
                    $pu = (float) ($detail->prix ?? 0) > 0
                        ? (float) $detail->prix
                        : (isset($prixPerso[$detail->produit?->id]) ? $prixPerso[$detail->produit?->id] : $detail->produit?->prix_moyen);
                    $montant = $pu * $detail->qte;
                    $totalHT += $montant;
                    $index++;
                @endphp
                <tr>
                    <td class="col-ref">{{ \Help::referenceLigne($index, $refBon) }}</td>
                    <td class="col-designation">{{ \Help::phrase($detail->produit?->nom) }}</td>
                    <td class="col-pu">{{ number_format($pu, 0, '', ' ') }}</td>
                    <td class="col-qte">{{ $detail->qte }}</td>
                    <td class="col-unite">{{ $detail->produit?->uniteProduit->abreviation ?? 'U' }}</td>
                    <td class="col-taxes">TVA ({{ $config->tva ?? 0 }}%)</td>
                    <td class="col-rem">0</td>
                    <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
                </tr>
            @endforeach

            @include('document.partials._ligne_transport', ['adresse' => $fne_adresse ?? null])
        </tbody>
    </table>
@endsection

@section('totaux')
    @php
        $totalTVA = $commande->TvaCommande?->montant ?? 0;
        $livraison = $commande->cout_livraison_client ?? 0;
        $tvaTransport = (float) ($commande->tva_transport ?? 0);
        $remise = $commande->remise ?? 0;
        $airsi = (float) ($commande->airsi ?? 0);
        $totalAPayer = $commande->montantAPayer();
    @endphp

    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        {{-- TOTAL HT = la somme du tableau, transport compris ; TVA = celle des
             articles plus celle du transport quand il est taxé (09/09/2026). --}}
        <tr><td class="label">TOTAL HT</td><td class="valeur">{{ number_format($totalHT + ($tvaTransport > 0 ? $livraison : 0), 0, '', ' ') }}</td></tr>
        @if($remise > 0)
        <tr><td class="label">Remise</td><td class="valeur">-{{ number_format($remise, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TVA ({{ $config->tva ?? 0 }}%)</td><td class="valeur">{{ number_format($totalTVA + $tvaTransport, 0, '', ' ') }}</td></tr>
        {{-- Transport NON taxé : présentation d'avant, sous les totaux. --}}
        @if($livraison > 0 && $tvaTransport <= 0)
        <tr><td class="label">Transport (HT)</td><td class="valeur">{{ number_format($livraison, 0, '', ' ') }}</td></tr>
        @endif
        <tr><td class="label">TOTAL TTC</td><td class="valeur">{{ number_format(max(0, $totalHT - $remise) + $totalTVA + $livraison + $tvaTransport, 0, '', ' ') }}</td></tr>
        @include('document.partials._ligne_airsi', ['airsi' => $airsi ?? 0])
        <tr><td class="label" style="font-size:10pt;">TOTAL A PAYER</td><td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($totalAPayer, 0, '', ' ') }}</td></tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
    <div class="fne-resume-titre">RESUME DE LA FACTURE</div>
    <table class="fne-resume">
        <thead><tr><th>CATEGORIE</th><th>SOUS-TOTAL</th><th>TAUX (%)</th><th>TOTAL TAXES</th></tr></thead>
        <tbody>
            @include('document.partials._resume_fiscal_ligne', ['baseArticles' => $totalHT, 'tvaArticles' => $totalTVA])
        </tbody>
    </table>

    @if (empty($pourPdf))
    <br>
    <div style="text-align:center; margin-top:20px;">
        <a href="{{ route('client.documentCommandePdf', $commande->numero) }}" style="display:inline-block; padding:12px 30px; background-color:#6c757d; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold; margin-right:8px;">Télécharger ({{ $typeDocument ?? 'PDF' }})</a>
        <a href="{{ route('client.index') }}" style="display:inline-block; padding:12px 40px; background-color:#1c57a3; color:#fff; text-decoration:none; border-radius:5px; font-size:14px; font-weight:bold;">Continuer vos achats</a>
    </div>
    @endif
@endsection
