<x-mail::message>
# Confirmez votre compte

Utilisez le code suivant pour confirmer votre compte.
<hr>

<h1 style="text-align:center;"><b> {{$token}} </b></h1>
<hr>

<x-mail::button :url="route('client.pageToken',['email' => $email])" color="colorGranite">
    Confirmer mon compte
    </x-mail::button>



Merci,<br>
{{ config('mail.from.name') }}
</x-mail::message>
