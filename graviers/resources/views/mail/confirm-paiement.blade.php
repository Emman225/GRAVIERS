<x-mail::message>
Bonjour {{ ucfirst($commande->client->prenoms) }},

Votre paiement a bien été effectué pour la commande N°{{$commande->numero}}

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
