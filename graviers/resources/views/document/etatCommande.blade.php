@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();
    $fne_numero = 'ETAT-CMD-' . date('YmdHis');
    $fne_client = ['nom' => 'Rapport', 'adresse' => '', 'ncc' => '', 'regime_imposition' => ''];
    $fne_qr_code = '';
    $fne_date = now()->format('d/m/Y H:i:s');
@endphp
@include('document.layouts._fne_init')

@extends('document.layouts.fne_base')

@section('titre', 'État des commandes')
@section('type_document', 'État des commandes')

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th style="width:15%">N° Commande</th>
                <th style="width:30%">Produits commandés</th>
                <th style="width:15%; text-align:right">Total commande</th>
                <th style="width:20%">Paiement</th>
                <th style="width:20%">Date de commande</th>
            </tr>
        </thead>
        <tbody>
            @foreach($commandes as $commande)
                <tr>
                    <td>{{ $commande->numero }}</td>
                    <td>
                        @foreach($commande->detailCommande as $detail)
                            - {{ ucfirst($detail->produit->nom) }}<br>
                        @endforeach
                    </td>
                    <td style="text-align:right">{{ number_format($commande->montant_total, 0, '', ' ') }} fcfa</td>
                    <td>
                        @if($commande->statut == 1)
                            <span style="color:red;">Aucun paiement effectué</span>
                        @elseif($commande->statut == 2)
                            <span style="color:orange;">Paiement en cours</span>
                        @elseif($commande->statut == 3 || $commande->statut == 4)
                            <span style="color:green;">Paiement soldé</span>
                        @endif
                    </td>
                    <td>{{ Carbon::parse($commande->created_at)->format('d-m-Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL DE TOUTES LES COMMANDES</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($commandes->sum('montant_total'), 0, '', ' ') }} fcfa</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
@endsection
