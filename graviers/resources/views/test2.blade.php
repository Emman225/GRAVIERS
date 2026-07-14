@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title','Création de code promo')
@section('contenu')

<div class="container">
    <select class="form-multi-select form-control" id="ms1" multiple multiple data-coreui-search="global">
        <option value="0">Angular</option>
        @foreach ($produits as $p )
            <option value="{{ $p->id }}">{{$p->nom}}</option>
        @endforeach
    </select>
</div>

    @endsection
    @section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
@endsection
