<x-mail::message>

M/Mme {{$user->nom.' '.$user->prenom}} vient d'effectuer un paiement de <strong>{{number_format($montant,'0','',' ')}} fcfa</strong> sur la demande de location N°{{$location->numero}}

Detail de la demande de location

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
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->produit->prix_moyen,'0','',' ')}} fcfa</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$detail->nombre_jour}} jour{{($detail->nombre_jour > 1) ? 's': ''}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->prix,'0','',' ')}}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5" style="border: 1px solid #ddd; padding: 8px; text-align:center"> Montant Total: {{number_format($location->montant_total,'0','','  ')}}fcfa</td>
        </tr>
    </tbody>
</table>
Merci,<br>
{{ config('app.name') }}
</x-mail::message>
