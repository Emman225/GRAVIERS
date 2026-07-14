<x-mail::message>


Cher(e) {{$user->client->nom.' '.$user->client->prenom}},<br>
Votre demande de location a bien été enregistrée<br>
<b> Location N° {{$location->numero}} </b>
{{-- {{$commande->detailCommande}} --}}
{{-- {{$commande}} --}}
{{-- <table class="table" >
    <thead>
        <th class="text-center">Produit</th>
        <th class="text-center">Quantité</th>
        <th class="text-center">Prix/jour</th>
        <th class="text-center">delai</th>
        <th></th>
    </thead>
    <tbody>
        @php $i =0 @endphp
        @foreach ($location->detailLocation as $detail)
            <tr>
                <td class="text-center"> {{$detail->produit->nom}} </td>
                <td class="text-center"> {{$detail->qte}} </td>
                <td class="text-center"> <b>{{$detail->produit->prix_moyen}}fcfa</b></td>
                @php $i+= $detail->produit->prix_moyen @endphp
            </tr>
        @endforeach
            <tr class="text-center">
                <td>Réduction par point</td>
                <td>-</td>
                <td> <b>{{$montantPoint}}fcfa</b> </td>
            </tr>
            <tr class="text-center">
                <td>Réduction par code Promo</td>
                <td>-</td>
                <td> <b>{{$pourcentage}}% de réduction</b> </td>
            </tr>
    </tbody>
    <tfoot>
        <hr>
        <b>Total: {{$total}} fcfa</b>
    </tfoot>
</table> --}}
@php
    $i = 0;
@endphp
<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
        <tr style="background-color: #1c57a3; color: white;">
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Produit</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Qté</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Prix/jours</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Delai</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Sous total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($location->detailLocation as $detail )
            <tr style="background-color: #f2f2f2;">
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$detail->produit->nom}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$detail->qte}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->produit->prix_moyen,'0','',' ')}}fcfa</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$nbreJour[$i]}} jour{{$nbreJour[$i]>1? 's': ''}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->qte * $detail->produit->prix_moyen * $nbreJour[$i],'0','',' ')}}</td>
            </tr>
            @php
                $i++;
            @endphp
        @endforeach
        <tr>
            <td colspan="5" style="border: 1px solid #ddd; padding: 8px; text-align:center">Montant initial: {{number_format($initial,'0','',' ')}}fcfa</td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #ddd; padding: 8px; text-align:center">-{{$pourcentage}}% de reduction</td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #ddd; padding: 8px; text-align:center">Reduction par point: - {{$montantPoint}}fcfa</td>
        </tr>
        <tr>
            <td colspan="5" style="border: 1px solid #ddd; padding: 8px; text-align:center">Montant final: {{number_format($location->montant_total,'0','','  ')}}fcfa</td>
        </tr>
      
    </tbody>
</table>
<x-mail::button :url="route('client.index')">
Revenir aux achat
</x-mail::button>
<small>
    Si vous n'êtes pas à l'origine de cette demande de location, Vous pouvez demander
    une annulation de commande <a href="{{route('enConstruction',$location->numero)}}">en cliquant ici</a>
</small>




Merci,<br>
{{ config('app.name') }}
</x-mail::message>
