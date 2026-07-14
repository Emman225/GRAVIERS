@extends('document.layouts.fne_base')

@section('titre', 'Reçu de paiement')
@section('type_document', 'Reçu de paiement')

@section('vendeur', $ligne->userPaie?->nom_prenoms ?? 'Paiement en ligne')
@section('mode_paiement', $ligne->moyen_paiement ?? 'N/A')

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
                $serviceLabel = '';
                $serviceNumero = '';
                switch($ligne->paiement->service) {
                    case 'COMMANDE':
                        $serviceLabel = 'Paiement commande';
                        $serviceNumero = $ligne->paiement->commande?->numero ?? 'N/A';
                        break;
                    case 'LOCATION':
                        $serviceLabel = 'Paiement location';
                        $serviceNumero = $ligne->paiement->location?->numero ?? 'N/A';
                        break;
                    case 'LIVRAISON':
                        $serviceLabel = 'Paiement livraison';
                        $serviceNumero = $ligne->paiement->livraison?->numero ?? 'N/A';
                        break;
                    default:
                        $serviceLabel = 'Paiement';
                        $serviceNumero = $ligne->paiement->code;
                }
                $montant = $ligne->montant;
            @endphp
            <tr>
                <td class="col-ref">{{ $ligne->paiement->code }}</td>
                <td class="col-designation">{{ $serviceLabel }} n°{{ $serviceNumero }}<br><small>Référence : {{ $ligne->reference ?? 'N/A' }}</small></td>
                <td class="col-pu">{{ number_format($montant, 0, '', ' ') }}</td>
                <td class="col-qte">1</td>
                <td class="col-unite">Forfait</td>
                <td class="col-taxes">0</td>
                <td class="col-rem">0</td>
                <td class="col-montant">{{ number_format($montant, 0, '', ' ') }}</td>
            </tr>
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label">TOTAL HT</td>
            <td class="valeur">{{ number_format($ligne->montant, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">TVA</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label">TOTAL TTC</td>
            <td class="valeur">{{ number_format($ligne->montant, 0, '', ' ') }}</td>
        </tr>
        <tr>
            <td class="label">AUTRES TAXES</td>
            <td class="valeur">0</td>
        </tr>
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL A PAYER</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($ligne->montant, 0, '', ' ') }}</td>
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
            <tr>
                <td>TVA exo.lég - Pas de TVA sur HT 00,00% - D</td>
                <td class="text-right">{{ number_format($ligne->montant, 0, '', ' ') }}</td>
                <td class="text-center">0%</td>
                <td class="text-right">0</td>
            </tr>
        </tbody>
    </table></td></tr></table>
@endsection
