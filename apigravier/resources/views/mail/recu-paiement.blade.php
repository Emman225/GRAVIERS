<x-mail::message>
# Reçu de paiement N° {{ $recu['numeroRecu'] }}

Bonjour **{{ $recu['nomClient'] }}**,

Nous avons bien reçu votre paiement. En voici le reçu.

<x-mail::table>
| | |
|:--|--:|
| Date | {{ $recu['date'] }} |
| {{ $recu['libelleOperation'] }} | **{{ $recu['numeroOperation'] }}** |
@if (!empty($recu['bonCommande']))
| Bon de commande | {{ $recu['bonCommande'] }} |
@endif
| Mode de paiement | {{ $recu['mode'] }} |
| Référence transaction | {{ $recu['reference'] }} |
| **Montant encaissé** | **{{ $recu['montant'] }}** |
</x-mail::table>

Vous retrouvez ce reçu dans votre compte, rubrique « Mes paiements ».

Merci pour votre confiance,<br>
{{ config('app.name') }}
</x-mail::message>
