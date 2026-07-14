@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des commandes')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des commandes </h2>

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
                <div class="card-header">
                    <a href="{{route('grandLivre.clientOrdinaireDetail', $client)}}" class="btn btn-md rounded">Commandes</a>
                    <a href="{{route('grandLivre.clientOrdinairePaiements',$client)}}" class="btn btn-md rounded bg-warning">Paiement</a>
                    <a href="{{route('grandLivre.clientOrdinaireFactures',$client)}}" class="btn btn-md rounded bg-info">Factures</a>
                </div>

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered tablee">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <!-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Réduction</th> -->
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Enlevement</th>
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($commandes as $commande)
                               
                                        <tr>
                                            <td class="texte-center" > {{ $commande->numero }} </td>
                                            <td class="texte-center"><b><a href=""></a>{{ $commande->client->nom }}  {{ $commande->client->prenom }}</b></td>
                                            <td class="text-center"> {{$commande->client->type_client}} </td>
                                            <td class="texte-center">
                                                {{-- Afficher le prix avant la reduction --}}
                                                <span class="vieux-prix">{{ $commande->remise > 0 ? number_format($commande->remise+ $commande->montant_total,'0','',' ').' fcfa' : '' }}</span> <br>
                                                {{ number_format($commande->montant_total,'0','',' ') }} fcfa
                                            </td>
                                            <td><span
                                                    class="badge rounded-pill text-warning">{{ $commande->etat_commande }}</span>
                                            </td>
                                            <td class="texte-center">{{ Carbon::parse($commande->created_at)->format('d-m-Y à H:i') }}</td>
                                         
                                            <!-- <td class="text-end">
                                                <a  href="{{ route('orders.reduction', $commande) }}"  class="btn btn-md rounded font-sm">Faire une réduction</a>
                                            </td> -->
                                            <td class="text-end">
                                                <a  href="{{ route('orders.BECommande', $commande->numero) }}"  class="btn btn-md rounded font-sm">Les enlevements</a>
                                            </td>
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
        <div class="col-md-3">

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
            var $table = $('.tablee').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

@endsection
