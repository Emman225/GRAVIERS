<x-mail::message>

<div style="text-align:center; margin-bottom: 20px;">
    <img src="https://graviers.fneconnect.net/frontend/assets/imgs/logo/dalakoun-blanc.png" alt="Logo Granite" width="180" style="max-width:180px; height:auto;">
</div>

Bonjour M./Mme {{ $client->prenom ?: $client->nom }}, votre demande de livraison a été prise en charge. Veuillez communiquer le code ci-dessous au livreur : il en a besoin pour valider la livraison une fois votre marchandise arrivée. <br>

<strong>Demande N° {{ $demande->numero }}</strong> <br>
<strong>Prise en charge le {{ Illuminate\Support\Carbon::parse($demande->updated_at)->format('d-m-Y') }} à {{ Illuminate\Support\Carbon::parse($demande->updated_at)->format('H:i') }}.</strong>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Marchandise</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Quantité</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Code de validation</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Livreur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Matricule véhicule</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Date de livraison</th>
    </thead>
    <tbody>
        <tr style="background-color: #f2f2f2;">
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $ligne?->nom_produit ?: 'Marchandise' }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->qte }} {{ $ligne?->unite }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center"><strong>{{ $livraison->numero }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->livreur?->user?->nom_prenoms }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->vehicule?->immatriculation }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $livraison->date_livraison }}</td>
        </tr>
    </tbody>
</table>

@if ($demande->priseEnCharge || $demande->destination)
<br>
<strong>Trajet :</strong> {{ $demande->priseEnCharge?->affichage ?: '—' }} &rarr; {{ $demande->destination?->affichage ?: '—' }}
@endif

<br><br>
Un code différent vous est envoyé pour chaque véhicule affecté : remettez au livreur celui qui porte son matricule.

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
