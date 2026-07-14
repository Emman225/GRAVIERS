@php
    use Illuminate\Support\Carbon;
    $config = App\Models\Configuration::first();
    $fne_numero = 'ETAT-LOC-' . date('YmdHis');
    $fne_client = ['nom' => 'Rapport', 'adresse' => '', 'ncc' => '', 'regime_imposition' => ''];
    $fne_qr_code = '';
    $fne_date = now()->format('d/m/Y H:i:s');
@endphp
@include('document.layouts._fne_init')

@extends('document.layouts.fne_base')

@section('titre', 'État des locations')
@section('type_document', 'État des locations')

@section('styles')
    @page { size: 297mm 210mm; }
@endsection

@section('articles')
    <table class="fne-articles">
        <thead>
            <tr>
                <th style="width:12%">N° Location</th>
                <th style="width:18%">Produits loués</th>
                <th style="width:12%">Début</th>
                <th style="width:12%">Fin</th>
                <th style="width:14%; text-align:right">Sous-total</th>
                <th style="width:14%">Date de commande</th>
                <th style="width:18%; text-align:right">Total location</th>
            </tr>
        </thead>
        <tbody>
            @foreach($locations as $location)
                @php $fautMettre = true; @endphp
                @foreach($location->detailLocation as $key => $detail)
                    <tr style="{{ $key == $location->detailLocation->count()-1 ? 'border-bottom: 2px solid black' : '' }}">
                        @if($fautMettre)
                            <td rowspan="{{ $location->detailLocation->count() }}">{{ $location->numero }}</td>
                        @endif
                        <td>{{ $detail->produit->nom }}</td>
                        <td>{{ Carbon::parse($detail->debut)->format('d-m-Y') }}</td>
                        <td>{{ Carbon::parse($detail->fin)->format('d-m-Y') }}</td>
                        <td style="text-align:right">{{ number_format($detail->prix, 0, '', ' ') }} fcfa</td>
                        @if($fautMettre)
                            <td rowspan="{{ $location->detailLocation->count() }}">{{ Carbon::parse($location->created_at)->format('d-m-Y') }}</td>
                            <td rowspan="{{ $location->detailLocation->count() }}" style="text-align:right">{{ number_format($location->montant_total, 0, '', ' ') }} fcfa</td>
                        @endif
                        @php $fautMettre = false; @endphp
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
@endsection

@section('totaux')
    <table class="fne-totaux-outer"><tr><td class="fne-totaux-spacer"></td><td class="fne-totaux-content"><table class="fne-totaux">
        <tr>
            <td class="label" style="font-size:10pt;">TOTAL DE TOUTES LES LOCATIONS</td>
            <td class="valeur" style="font-size:10pt; font-weight:bold;">{{ number_format($locations->sum('montant_total'), 0, '', ' ') }} fcfa</td>
        </tr>
    </table></td></tr></table>
@endsection

@section('resume_fiscal')
@endsection
