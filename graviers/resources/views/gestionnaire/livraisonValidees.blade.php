@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Livraisons validées')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Livraisons validées</h2>

        </div>
    </div>

        @if(session('success'))
            <div class="alert alert-success text-center">
                {{session('success')}}
            </div>
        @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Client </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Livreur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Lieu de livraison</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Produit</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Quantité</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Véhicule</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Immatriculation</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Capacité</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date prevu de livraison</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date effective de livraison</th>
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($livraisons as $livraison)

                                    <tr>
                                        <td class="texte-center" > {{ $livraison->numero }}</td>
                                        <td class="texte-center"><b> {{$livraison->client?->nom.' '.$livraison->client?->prenom}} </b></td>
                                        <td class="texte-center">
                                            <b> {{$livraison->livre_par == 1 ? $livraison->livreur?->user?->nom_prenoms : $livraison->client?->nom.' '.$livraison->client?->prenom }} </b>
                                        </td>
                                        <td class="texte-center"> {{$livraison->AdresseLivraison?->affichage}}</td>
                                        @if ($livraison->provenance == 'COMMANDE')
                                            <td> {{$livraison->detailCommande?->produit?->nom ?? '-'}} </td>
                                            <td class="texte-center">{{$livraison->detailCommande?->qte}}</td>
                                        @elseif ($livraison->provenance == 'LOCATION')
                                            {{-- Livraison de LOCATION : detail_commande_id pointe un detail_location. --}}
                                            @php $dl = \App\Models\DetailLocation::find($livraison->detail_commande_id); @endphp
                                            <td> {{$dl?->produit?->nom ?? '-'}} </td>
                                            <td class="texte-center">{{$dl?->qte}}</td>
                                        @else
                                            <td> {{$livraison->detailLivraison?->nom_produit ?? '-'}} </td>
                                            <td class="texte-center">{{$livraison->detailLivraison?->qte}}</td>
                                        @endif
                                        {{-- <td class="text-center">{{$livraison->qte }}</td> --}}
                                        <td class="text-center">{{$livraison->vehicule->marque ?? '' }}</td>
                                        <td class="text-center">{{$livraison->vehicule->immatriculation ?? '' }}</td>
                                        <td class="text-center">{{$livraison->vehicule->capacite ?? '' }}</td>
                                        <td class="texte-center"> {{$livraison->date_livraison}} </td>
                                        <td class="texte-center"> {{$livraison->updated_at}} </td>
                                    </tr>

                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>
                <!-- card-body end// -->
            </div>
            <!-- card end// -->
        </div>

    </div>
    <div class="pagination-area mt-15 mb-50">

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
