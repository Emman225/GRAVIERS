@php
    use Illuminate\Support\carbon;
@endphp

{{-- {{var_dump($livraisons)}}
{{die}} --}}
@extends('layout.main')
@section('title','Client à termes')

@if(session('success'))
    <div class="alert alert-success" id="notify">
        {{session('success')}}
    </div>
@endif

@section('contenu')
    <div class="content-header">
        <h2 class="content-title"> Liste des vehicules </h2>

    </div>
    <div class="card mb-4">
        <header class="card-header">
            <div>
                <a href="{{route('livreur.ajoutVehiculePage')}}" class="btn btn-primary btn-sm rounded float-end">Enregistrer un nouveau véhicule</a>
            </div>
        </header>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">

                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Matricule</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Marque</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Modèle</th>

                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Capacité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Enregistré le</th>
                            {{-- <th class="text-center" style="background-color: #1c57a3; color: white; "> Poids de vehicule souhaité</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Date de commande </th> --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">disponibilité</th>

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehicules as $vehicule )
                            <tr>
                                <td class="text-center" >
                                    {{-- <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="flexSwitchCheckChecked" checked>
                                        <label class="form-check-label" for="flexSwitchCheckChecked">Disponible</label>
                                    </div><br> --}}
                                    <span class="fw-bold h5">{{$vehicule->immatriculation}}</span>
                                </td>

                                <td class="text-center"> {{$vehicule->marque}} </td>
                                <td class="text-center"> {{$vehicule->modele}} </td>

                                <td class="text-center"> {{$vehicule->capacite}}t </td>
                                {{-- <td class="text-center"> {{$livraison->destination->affichage}} </td>
                                <td class="text-center"> {{$livraison->detailLivraison->poids_vehicule_souhaite}}t </td> --}}
                                <td class="text-center fw_bold"> {{Carbon::parse($vehicule->created_at)->format('d-m-Y à H:i')}} </td>
                                <td class="text-center">
                                    <a href="{{route('livreur.modificationVehicule',$vehicule)}}" class="btn btn-primary"> Modifier</a>
                                    <a href="{{route('livreur.supressionVehicule',$vehicule)}}" class="btn btn-primary"> supprimer</a>
                                </td>
                                <td class="text-center">
                                    @if ($vehicule->disponible == 1)
                                        <a href="{{route('livreur.vehiculeDispo',$vehicule)}}" class="btn btn-success">Disponible</a>
                                    @else
                                        <a href="{{route('livreur.vehiculeDispo',$vehicule)}}" class="btn btn-danger">Indisponible</a>
                                    @endif
                                </td>
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
                order: [],
            });
        });
    </script>

    <script>

    </script>
@endsection
