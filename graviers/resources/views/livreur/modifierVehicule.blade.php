<div>
    <!-- Very little is needed to make a happy life. - Marcus Aurelius -->
</div>
@extends('layout.main')
@section('contenu')
@section('title', 'modifier vehicule')

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
