@props(['route' => null, 'label' => 'Retour à la liste'])
@php
    $href = $route ?: (url()->previous() ?: url('/'));
@endphp
<div class="mb-3">
    <a href="{{ $href }}" class="btn btn-outline-secondary btn-sm">
        <i class="material-icons md-arrow_back" style="vertical-align:middle;"></i>
        <span style="vertical-align:middle;">{{ $label }}</span>
    </a>
</div>
