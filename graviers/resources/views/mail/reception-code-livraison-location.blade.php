<x-mail::message>

<div style="text-align:center; margin-bottom: 20px;">
    <img src="https://graviers.fneconnect.net/backend/assets/imgs/theme/logoAvecFond.jpg" alt="Logo Granite" width="180" style="max-width:180px; height:auto;">
</div>

Bonjour M./Mme {{ $client->prenom }}, veuillez utiliser ce code pour permettre au livreur de valider la livraison de votre location. <br>

<strong>Location N° {{ $location->numero }} </strong> <br>
<strong>Validée le {{ Illuminate\Support\Carbon::parse($location->updated_at)->format('d-m-Y') }} à {{ Illuminate\Support\Carbon::parse($location->updated_at)->format('H:i') }}.</strong>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Matériel</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Quantité</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Code de validation</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Livreur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Matricule véhicule</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">Date de livraison</th>
    </thead>
    <tbody>
        <tr style="background-color: #f2f2f2;">
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $produit?->nom ?? 'Matériel' }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->qte }} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center"><strong>{{ $livraison->numero }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->livreur?->user?->nom_prenoms }} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->vehicule?->immatriculation }} </td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->date_livraison }} </td>
        </tr>
    </tbody>
</table>
Merci,<br>
{{ config('app.name') }}
</x-mail::message>
