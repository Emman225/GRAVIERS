<x-mail::message>
Bonjour {{ $nom_prenoms }}

Votre compte a bien été créé. <br/><br>
Veuillez patienter jusqu'à validation de votre compte avant de vous connecter. <br>
Veuillez contacter le support technique pour plus d'informations  <br><br>

Utilisez les accès suivant pour vous connecter:<br/>
Login: <strong> {{ $email }}</strong><br/>
Mot de passe: <i>Le mot de passe que vous avez défini durant votre inscription</i>
<hr>

<x-mail::button :url="route('apporteur.login')" color="colorGranite">
    Cliquez ici pour vous connecter
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
