<x-mail::message>

<div style="text-align:center; margin-bottom: 20px;">
    <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Logo Granite" width="180" style="max-width:180px; height:auto;">
</div>

Bonjour M./Mme {{$client->prenom}}, veuillez utiliser ce code pour permettre au livreur de valider sa livraison. <br>

<strong>Commande N° {{$commande->numero}} </strong> <br>
<strong>Commande passée le {{Illuminate\Support\Carbon::parse($commande->created_at)->format('d-m-Y')}} à {{Illuminate\Support\Carbon::parse($commande->created_at)->format('H:i')}}.</strong>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Produit</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Quantité</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Code de livraison</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Livreur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Matricule véhicule</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Date de livraison</th>
    </thead>
    <tbody>
        <tr style="background-color: #f2f2f2;">
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$produit->nom}}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$livraison->qte}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$livraison->numero}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$livraison->livreur->user->nom_prenoms}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$livraison->vehicule->immatriculation}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$livraison->date_livraison}} </td>
        </tr>
    </tbody>
</table>
Merci,<br>
{{ config('app.name') }}
</x-mail::message>
