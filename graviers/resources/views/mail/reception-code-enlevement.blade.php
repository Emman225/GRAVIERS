<x-mail::message>

<div style="text-align:center; margin-bottom: 20px;">
    <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Logo Granite" width="180" style="max-width:180px; height:auto;">
</div>

Bonjour M./Mme {{$client->prenom}}, veuillez utiliser ce code pour récupérer votre commande chez le fournisseur. <br>

<strong>Commande N° {{$commande->numero}} </strong> <br>
<strong>Commande passée le {{Illuminate\Support\Carbon::parse($commande->created_at)->format('d-m-Y')}} à {{Illuminate\Support\Carbon::parse($commande->created_at)->format('H:i')}}.</strong>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Produit</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Quantité</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Code d'enlèvement</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Nom fournisseur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left; text-align:center">Adresse fournisseur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Date récupération</th>
    </thead>
    <tbody>
        <tr style="background-color: #f2f2f2;">
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$produit->nom}}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$enlevement->qte}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$enlevement->code_enleve}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$enlevement->fournisseur->user->nom_prenoms}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$enlevement->fournisseur->adresse_geo}} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{$enlevement->livraison->date_livraison}} </td>
        </tr>
    </tbody>
</table>

@component('mail::button', ['url' => $url, 'color' => 'colorGranite'])
Localisation du fournisseur
@endcomponent

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
