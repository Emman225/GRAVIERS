@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Editer une bannière')

@section('contenu')
<div class="col-lg-6">
    <div class="card mb-4">
        @if (session('success'))
            <div class="alert alert-success" id="notify">
                {{session('success')}}
            </div>
        @endif
        <div class="card-header d-flex justify-content-between">
            <h4> Edition de bannière </h4>
            <a href="{{route('show.listeDesBannieres')}}" class="btn btn-primary float-end">Liste des Bannières</a>
        </div>
        @include('gestionnaire.formBanniere')
    </div>
    <!-- card end// -->

</div>

@endsection




