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
            <h2 class="content-title card-title">Liste des commandes en cours de traitement </h2>

        </div>
    </div>

    <div class="row">
        <div class="col-4 "><h3>Client: {{ucfirst($client->nom).' '.ucfirst($client->prenom)}} </h3></div>
        <div class="col-4"><h3>Type de client: {{ucfirst($client->type_client)}} </h3></div>
        <div class="col-4"><h3>Montant à payer: {{Help::soldeClient($client,$client->client_a_terme)}}  </h3></div>

    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N° commande</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <!-- <th class="text-center" style="background-color: #1c57a3; color: white">Paiement</th> -->
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Detail</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">paiements</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Traitements</th>
                                </tr>
                            </thead>
                            <tbody>


                                        @foreach ($commandes as $commande)
                                            @if($commande->statut != 4)
                                            {{-- {{ $commande['statut'] }} --}}
                                            <tr>
                                                <td > {{ $commande->numero }} </td>

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
                                                <td>
                                                    <a href="{{ route('paye.create', $commande->id) }}" class="btn btn-md rounded font-sm">Effectuer un paiement</a>
                                                </td>
                                                <td>
                                                    <a href="{{ route('orders.traitement', $commande) }}" class="btn btn-md rounded font-sm">Traiter la commande</a>
                                                </td>

                                            </tr>
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
                },order : [],
            });
        });
    </script>
    @notifyJs
@endsection
