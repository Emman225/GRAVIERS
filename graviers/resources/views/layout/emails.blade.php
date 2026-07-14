<x-mail::message>
# Reinitialisation de mot de passe

Votre code de réinitialisation est <b> {{$code}} </b>.

{{-- <x-mail::button :url="''">
Button Text
</x-mail::button> --}}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
