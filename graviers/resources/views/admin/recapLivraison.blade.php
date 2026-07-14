@extends('layout.main')
@section('title','Recapitulatif des livraisons')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title"> - Récapitulatif des livraisons - </h2>
    </div>
    <div class="card mb-4">
        <form method="GET" action="" class="row">
            @csrf
            <div class="col-md-2 col-lg-2 col-xl-2">
                <input type="date" class="form-control" name="du" id="du" placeholder="Date debut" value="{{ Request::get('du') ?? date("Y-01-01") }}">
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2">
                <input type="date" class="form-control" name="au" id="au" placeholder="Date debut" value="{{ Request::get('au') ?? date("Y-m-d") }}">
            </div>
            <div class="col-md-2 col-lg-2 col-xl-2">
                <button type="submit" class="btn btn-primary ">Rechercher</button>
            </div>
        </form>
        <header class="card-header">
            <div class="row gx-3">
                <div class="col-lg-4 col-md-6 me-auto">
                    <h3 class="text-success">Total tonnage : {{$enlevements->sum('qte')}} </h3>
                </div>
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <x-export-buttons table-id="liste" filename="recap-livraison" title="Récapitulatif livraisons" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">

                    <thead>
                        <tr>
                            <th class="text-center">Date prévu</th>
                            <th class="text-center">Nom du client</th>
                            <th class="text-center">Produit</th> {{--  --}}
                            <th class="text-center">Tonnage</th> {{--  --}}
                            <th class="text-center">N° BE</th>
                            <th class="text-center">Lieu livraison</th>
                            <th class="text-center">Nom du fournisseur</th>
                            <th class="text-center">Nom du livreur</th>
                            <th class="text-center">Vehicule</th>
                            <th class="text-center">Etat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($enlevements as $enlevement )
                            <tr>
                                <td class="text-center"> {{ $enlevement->date_livraison }} </td>
                                <td class="text-center"> {{ $enlevement->le_client}} </td>
                                <td class="text-center"> {{ $enlevement->le_produit}} </td>
                                <td class="text-center"> {{ $enlevement->qte}} </td>
                                <td class="text-center"> {{ $enlevement->code_enleve}} </td>
                                <td class="text-center">
                                    <p>{{$enlevement->complement_adresse}}</p>
                                </td>
                                <td class="text-center"> {{$enlevement->le_fournisseur}} </td>
                                <td class="text-center"> {{$enlevement->le_livreur}} </td>
                                <td class="text-center"> {{$enlevement->le_vehicule}} </td>
                                <td class="text-center"> {{$enlevement->etat_livraison}} </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
    </div>
    <!-- card end// -->

@endsection


@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
