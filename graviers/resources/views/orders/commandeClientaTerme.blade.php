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
            <h2 class="content-title card-title">Liste des commandes en cours de traitement</h2>

        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <!-- <th class="text-center" style="background-color: #1c57a3; color: white">Paiement</th> -->
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Detail</th>
                                    <!-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Paiment</th> -->
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Commande</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Enlevement</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Bons de commande client</th>
                                </tr>
                            </thead>
                            <tbody>

                                @foreach ($commandes as $commande)
                                    @if($commande->client->client_a_terme == 1)
                                        {{-- @if($commande->statut != 4) --}}
                                            <tr>
                                                <td class="texte-center" > {{ $commande->numero }} </td>
                                                <td class="texte-center"><b>{{ $commande->client->nom }}  {{ $commande->client->prenom }}</b></td>
                                                <td class="text-center"> {{$commande->client->type_client}} </td>
                                                <td class="texte-center">{{ number_format($commande->montant_total,'0','',' ') }} fcfa</td>
                                                <td><span class="badge rounded-pill text-warning">{{ $commande->etat_commande }}</span>
                                                </td>
                                                <td class="texte-center">{{ Carbon::parse($commande->created_at)->format('d-m-Y à H:i') }}</td>
                                                <!-- <td class="texte-center">


                                                    @if ($commande->statut == 1)
                                                        <p class="text-danger">Aucun paiement effectué</p>
                                                    @elseif ($commande->statut == 2)
                                                        <p class="text-warning">Paiement en cours</p>
                                                    @elseif ($commande->statut == 3 || $commande->statut == 4)
                                                        <p class="text-success">Paiement soldé</p>
                                                    @endif
                                                </td> -->
                                                <td class="text-end">
                                                    <a href="{{ route('orders.details', $commande->numero) }}" class="btn btn-md rounded font-sm">Detail</a>


                                                </td>
                                                <!-- <td>
                                                    @if($commande->statut == 1 || $commande->statut == 2)
                                                    <a href="{{ route('paye.create', $commande->id) }}" class="btn btn-md rounded font-sm">
                                                        Effectuer un paiement
                                                    </a>
                                                    @endif
                                                </td> -->
                                                <td>
                                                    <a href="{{ route('orders.traitement', $commande) }}" class="btn btn-md rounded font-sm">Traiter la commande</a>

                                                </td>
                                                <td class="text-end">
                                                    <a  href="{{ route('orders.BECommande', $commande->numero) }}"  class="btn btn-md rounded font-sm">Les enlevements</a>
                                                </td>
                                                <td class="text-end">
                                                    @if($commande->blClient)
                                                        <a download href="{{asset('storage/'.$commande->blClient->fichier)}}"  class="btn btn-md rounded font-sm">Télécharger</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        {{-- @endif --}}
                                    @endif
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
    @notifyJs
@endsection
