<x-mail::message>


M/Mme <i>{{$nomPrenoms}},</i> <br>
Votre compte a bien été créé. Utilisez ces accès pour vous connecter à votre compte: <br> <br>

<strong>Accès</strong> <br>
Login : <strong> {{ $login }} </strong> <br>
Mot de passe : <strong> {{ $password }} </strong> <br> <br>

<x-mail::button :url="route($typeUser.'.login')" color="colorGranite">
    Cliquez ici pour vous connecter
</x-mail::button>

<small>Ne partagez ces accès à personne et rendez vous dans les paramètre de votre compte pour modifier vos accès</small>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
