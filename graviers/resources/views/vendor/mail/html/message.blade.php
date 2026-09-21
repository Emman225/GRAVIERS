<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
    {{-- Adresse absolue en dur, comme dans les autres courriels : dans une boîte
         mail il n'y a pas de page d'origine, et asset() dépend d'APP_URL — mal
         renseigné, l'image ne s'afficherait nulle part. --}}
    <img src="{{ asset('frontend/assets/imgs/logo/dalakoun-blanc.png') }}" class="logo" alt="DALAKOUN">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{{ $slot }}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
