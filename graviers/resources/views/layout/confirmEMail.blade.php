<x-mail::message>
    {{-- <x-mail::header>
        ddd
    </x-mail::header> --}}
# Confirmation d'Email

Bonjour <b>{{strtoupper($nom)}}</b> !! <br>

Merci d'avoir créé votre compte. <br>
Vous êtes enregistré en tant qu'un APPORTEUR d'affaire. <br>

Veuillez cliquer sur le bouton ci-dessous pour confirmer votre email.

<x-mail::button  :url="route('apporteur.confirmePage',$token)" color="colorGranite">
Confirmez votre email
</x-mail::button>

Merci,<br>
{{ config('app.name') }}

{{-- <img src="{{asset('backend/assets/imgs/theme/logoVide.png')}}" class="logo" alt="{{config('app.name')}}"> --}}
</x-mail::message>
