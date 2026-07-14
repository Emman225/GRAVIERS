@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Publication de blog')

@section('contenu')
<div class="col-lg-6">
    <div class="card mb-4">
        @if (session('success'))
            <div class="alert alert-success" id="notify">
                {{session('success')}}
            </div>
        @endif
        <div class="card-header d-flex justify-content-between">
            <h4> Publiez un blog </h4>
            <a href="{{route('show.listeDesBlogs')}}" class="btn btn-primary float-end">Liste des blogs</a>
        </div>
        @include('gestionnaire.formBlog')
    </div>
    <!-- card end// -->

</div>

@endsection




