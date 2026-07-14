@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Modification de blog')

@section('contenu')
<div class="col-lg-6">
    <div class="card mb-4">
        @if (session('success'))
            <div class="alert alert-success" id="notify">
                {{session('success')}}
            </div>
        @endif
        <div class="card-header d-flex justify-content-between">
            <h4> Modification de Blog </h4>
            <a href="{{route('show.listeDesBannieres')}}" class="btn btn-primary float-end">Liste des bannieres</a>
        </div>
        @include('gestionnaire.formBanniere')
    </div>
    <!-- card end// -->

</div>

@endsection

