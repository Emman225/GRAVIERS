<x-mail::message>


M./Mme {{$nom_prenom}} a initialisé une réduction de {{$remise}}% sur la commande N°{{$commande->numero}}

Detail de la commande


<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
        <tr style="background-color: #1c57a3; color: white;">
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Produit</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Qté</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Prix</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Sous total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($commande->detailCommande as $detail )
            <tr style="background-color: #f2f2f2;">
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$detail->produit->nom}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$detail->qte}}</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->produit->prix_moyen,'0','',' ')}}fcfa</td>
                <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{number_format($detail->qte * $detail->produit->prix_moyen,'0','',' ')}}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="4" style="border: 1px solid #ddd; padding: 8px; text-align:center">Montant initial: {{number_format($montant_initial,'0','',' ')}}fcfa</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 1px solid #ddd; padding: 8px; text-align:center">-{{$remise}}% de reduction</td>
        </tr>
        <tr>
            <td colspan="4" style="border: 1px solid #ddd; padding: 8px; text-align:center"> Montant final: {{number_format($commande->montant_total + $remise/100,'0','','  ')}}fcfa</td>
        </tr>
    </tbody>
</table>




  <br>
{{ config('app.name') }}
</x-mail::message>
