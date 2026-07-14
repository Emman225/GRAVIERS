@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();
    $fne_numero = 'ETAT-LIV-' . date('YmdHis');
    $fne_client = ['nom' => 'Rapport', 'adresse' => '', 'ncc' => '', 'regime_imposition' => ''];
    $fne_qr_code = '';
    $fne_date = now()->format('d/m/Y H:i:s');
@endphp
@include('document.layouts._fne_init')

@extends('document.layouts.fne_base')

@section('titre', 'État des livraisons')
@section('type_document', 'État des livraisons')

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th style="width:15%">N° Livraison</th>
                <th style="width:30%">Produits commandés</th>
                <th style="width:15%; text-align:right">Total commande</th>
                <th style="width:20%">État</th>
                <th style="width:20%">Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($livraisons as $livraison)
                <tr>
                    <td>{{ $livraison->numero }}</td>
                    <td>
                        @foreach($livraison->detailLivraison as $detail)
                            - {{ ucfirst($detail->nom_produit) }}<br>
                        @endforeach
                    </td>
                    <td style="text-align:right">{{ number_format($livraison->montantTotal, 0, '', ' ') }} fcfa</td>
                    <td>
                        @switch($livraison->etat_commande)
                            @case('EN ATTENTE')
                                <span style="color:gray;">{{ $livraison->etat_commande }}</span>
                                @break
                            @case('EN TRAITEMENT')
                                <span style="color:orange;">{{ $livraison->etat_commande }}</span>
                                @break
                            @case('TERMINEE')
                                <span style="color:green;">{{ $livraison->etat_commande }}</span>
                                @break
                        @endswitch
                    </td>
                    <td>{{ Carbon::parse($livraison->created_at)->format('d-m-Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL DE TOUTES LES LIVRAISONS</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($livraisons->sum('montantTotal'), 0, '', ' ') }} fcfa</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
@endsection
