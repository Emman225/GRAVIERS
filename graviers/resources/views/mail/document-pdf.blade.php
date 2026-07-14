<x-mail::message>

Bonjour **{{ $clientNom }}**,

Veuillez trouver ci-joint votre **{{ $typeDocument }}** N° **{{ $numero }}**.

<x-mail::panel>
Ce document est genere automatiquement par la plateforme IMLOD.
Pour toute question, n'hesitez pas a nous contacter.
</x-mail::panel>

Merci pour votre confiance,<br>
{{ config('app.name') }}
</x-mail::message>
