@extends('layout.main')

@section('contenu')
@section('title','Ajout de fournisseur')

    <x-back-to-list :route="route('show.listSeller')" />

    @if (session('login'))
        <div class="alert alert-success text-center">
            <h3>Login : {{session('login')}}</h3>
            @php
                session()->forget('login');
            @endphp
        </div>
    @endif
    @include('fournisseur.form')
@endsection
