<x-mail::message>

<div style="text-align:center; margin-bottom: 20px;">
    <img src="{{ asset('frontend/assets/imgs/logo/dalakoun-blanc.png') }}" alt="Logo DALAKOUN" width="180" style="max-width:180px; height:auto;">
</div>

Bonjour M./Mme {{ $client->prenom }}, votre location est validée en **retrait sur place**. Présentez le code ci-dessous au fournisseur pour retirer votre matériel. <br>

<strong>Location N° {{ $location->numero }}</strong> <br>
<strong>Validée le {{ Illuminate\Support\Carbon::parse($location->updated_at)->format('d-m-Y') }} à {{ Illuminate\Support\Carbon::parse($location->updated_at)->format('H:i') }}.</strong>

<table style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
    <thead>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Matériel</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Quantité</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Code de retrait</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Fournisseur</th>
            <th style="border: 1px solid #ddd; padding: 12px; text-align:center">Contact</th>
    </thead>
    <tbody>
        <tr style="background-color: #f2f2f2;">
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $produit?->nom ?? 'Matériel' }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $enlevement->qte }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center"><strong>{{ $enlevement->code_enleve }}</strong></td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $enlevement->fournisseur?->user?->nom_prenoms ?? '—' }}</td>
            <td style="border: 1px solid #ddd; padding: 8px; text-align:center">{{ $enlevement->fournisseur?->user?->contact ?? '—' }}</td>
        </tr>
    </tbody>
</table>

Ce code est votre preuve : sans lui, le fournisseur ne peut pas vous remettre le matériel. Conservez-le jusqu'au retrait.

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
