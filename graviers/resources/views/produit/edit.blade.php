@extends('layout.main')

@section('contenu')
    <p>
        produit : {{$infoProduit->nom}} <br>
        prix: {{$infoProduit->unite}}
    </p>
@endsection
