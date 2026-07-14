<x-mail::message>
# Confirmez votre compte

Utilisez le code suivant pour confirmer votre compte.
<hr>
<h1 style="text-align:center;"><b> {{$token}} </b></h1>
<hr>

<x-mail::button :url="route('apporteur.pageCode',['email' => $email])" color="colorGranite">
    Cliquez ici pour confirmer
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>

