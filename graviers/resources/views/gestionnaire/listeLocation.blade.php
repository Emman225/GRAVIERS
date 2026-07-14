@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des demandes de location')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des locations en attente</h2>

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
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Paiement</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $location)

                                    <tr>
                                        <td class="text-center" > <a href="">{{$location->numero}}</a> </td>
                                        <td class="text-center"><b> {{$location->client->nom.' '.$location->client->prenom}} </b></td>
                                        <td class="text-center"> {{$location->client->type_client}} </td>
                                        <td class="text-center">
                                            {{-- Afficher le prix avant la reduction --}}
                                            <span class="vieux-prix"></span> <br>
                                            {{-- afficher le montant courant --}}
                                            {{number_format($location->montant_total,'0','',' ')}} fcfa

                                        </td>
                                        <td class="text-center">
                                            @php $etat = $location->etatLibelle(); @endphp
                                            <span class="badge rounded-pill {{ $etat === 'EN ATTENTE' ? 'bg-warning text-dark' : ($etat === 'EN COURS' ? 'bg-info text-dark' : 'bg-success') }}">{{ $etat }}</span>
                                        </td>
                                        <td class="text-center"> {{Carbon::parse($location->created_at)->format('d-m-Y')}} </td>

                                        <td class="text-center">
                                            @if ($location->statut == 1)
                                            <span class="badge rounded-pill alert-success text-danger">Aucun
                                                paiement effectué</span>
                                        @elseif($location->statut == 2)
                                            <span class="badge rounded-pill alert-success text-warning">paiement
                                                en cours...</span>
                                        @elseif($location->statut == 3)
                                            <span class="badge rounded-pill alert-success text-success">Paiement
                                                soldé</span>
                                        @endif
                                        </td>

                                        <td class="text-end">
                                            @if ($location->etatLibelle() === 'EN ATTENTE')
                                                <a href="{{ route('show.validerLocationPage', $location) }}" class="btn btn-sm btn-primary rounded font-sm">Valider &amp; affecter</a>
                                            @elseif ($location->etatLibelle() === 'EN COURS')
                                                <a href="{{ route('show.retourLocationPage', $location) }}" class="btn btn-sm btn-success rounded font-sm">Retour matériel</a>
                                            @endif
                                            {{-- "Faire un paiement" seulement si la location n'est pas déjà soldée (statut 3). --}}
                                            @if ($location->statut != 3)
                                                <a  href="{{route('paye.paiementLocation',$location)}}" class="btn btn-sm rounded font-sm">Faire un paiement</a>
                                            @endif
                                            {{-- Facture FNE : générer (une fois) puis consulter. --}}
                                            @if ($location->factureFne)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $location->factureFne->id, 'action' => 'voir']) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded font-sm">Voir facture</a>
                                            @else
                                                <form action="{{ route('orders.genererFactureLocation', $location) }}" method="post" style="display:inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded font-sm"
                                                        onclick="return confirm('Générer la facture FNE de cette location ?');">Générer facture</button>
                                                </form>
                                            @endif
                                            {{-- Supprimer une location "fantôme" : EN ATTENTE ET non payée (statut != 3). --}}
                                            @if ($location->statut != 3)
                                                <form action="{{ route('show.supprimerLocation', $location) }}" method="post" style="display:inline-block"
                                                      onsubmit="return confirm('Supprimer cette location non payée ? Elle sera archivée.');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded font-sm">Supprimer</button>
                                                </form>
                                            @endif
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
