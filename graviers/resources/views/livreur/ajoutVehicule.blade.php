@extends('layout.main')
@section('contenu')
@section('title', 'Ajout de vehicule')

<x-back-to-list :route="route('livreur.listeVehicule')" />

<div class="card mx-auto "style=" width: 40rem; ">
    <div class="card-body">
        @if (session('succes'))
            <div class="alert alert-success">
                {{ session('succes') }}
            </div>
        @endif
        @include('livreur.formVehicule')
    </div>
</div>
@endsection

@section('jsParts')
<script type="text/javascript">
    $(function() {
        $('#produits').select();
    });
</script>
@endsection
