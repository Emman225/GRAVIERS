@php
    use Illuminate\Support\carbon;
@endphp

{{-- {{var_dump($livraisons)}}
{{die}} --}}
@extends('layout.main')
@section('title','Demande de livraison traitée')

@section('contenu')
    <div class=" mt-5 content-header">
        <h2 class="content-title">Demande de livraison traitée </h2>
        @if (session('success'))
        <div class="alert alert-success sup" id="notify">
            {{session('success')}}
        </div>

        @endif
    </div>
    <div class="card mb-4">
        <header class="card-header">
        </header>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" id="liste">

                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Produit</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Quantité</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Description</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Lieu de prise en charge</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Lieu de destination</th>
                            {{-- <th class="text-center" style="background-color: #1c57a3; color: white; "> Poids de vehicule souhaité</th> --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Date de commande </th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> {{--  --}}

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($livraisons as $livraison )

                            <tr>
                                <td class="text-center" > {{$livraison->numero}} </td>
                                <td class="text-center" > {{$livraison->client->nom. ' '. $livraison->client->prenom}} </td>
                                <td class="text-center">
                                    @foreach ($livraison->detailLivraison as $detail )
                                        {{$detail->nom_produit}} <br>
                                    @endforeach
                                </td>
                                <td class="text-center">
                                    {{-- {{$livraison->detailLivraison->qte.' '.$livraison->detailLivraison->unite}} --}}
                                    @foreach ($livraison->detailLivraison as $detail )
                                        {{$detail->qte.' '.$detail->uniteProduit->libelle}} <br>
                                    @endforeach

                                </td>
                                <td class="text-center"> {{$livraison->description}} </td>
                                <td class="text-center"> {{$livraison->priseEnCharge->affichage}} </td>
                                <td class="text-center"> {{$livraison->destination->affichage}} </td>
                                {{-- <td class="text-center"> {{$livraison->detailLivraison->poids_vehicule_souhaite}}t </td> --}}
                                <td class="text-center fw_bold"> {{Carbon::parse($livraison->created_at)->format('d-m-Y à H:i')}} </td>
                                <td class="text-center"> <a href="{{route('show.detailDemandeLivraison',$livraison)}}" class="btn btn-primary"> Détails</a> </td>
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
                ordering: false,
            });
        });
    </script>
@endsection
