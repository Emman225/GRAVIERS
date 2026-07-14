@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Historique des livraisons')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Historique des livraisons</h2>

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

                                    <th class="text-center" style="background-color: #1c57a3; color: white">Client </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Fournisseur </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Livreur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Lieu de livraison</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Produit</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Quantité</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Véhicule</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Immatriculation</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Capacité</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Accepte</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Statut</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date de livraison</th>
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white;" >Nouveau livreur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px" >Nouveau vehicule</th> --}}
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px" >Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($livraisons as $key => $livraison)
                                    {{-- @if($livraison->etat_livraison != 'LIVREE' && $livraison->statut != 3) --}}
                                    <tr>
                                            <form action="{{route('show.restaureLivraison',$livraison)}}" method="POST">
                                                @csrf
                                            <td class="texte-center"><b> {{$livraison->client?->nom.' '. $livraison->client?->prenom ?? ''}} </b></td>
                                            <td class="texte-center"><b> {{$livraison->enlevement?->fournisseur?->user?->nom_prenoms ?? ''}} </b></td>
                                            <td class="texte-center"><b> {{ $livraison->livre_par == 1 ? $livraison->livreur?->user?->nom_prenoms : $livraison->clientLivreur?->nom }} </b></td>
                                            <td class="texte-center"> {{$livraison->AdresseLivraison?->affichage ?? 'Pas de livraison'}}</td>
                                            @if ($livraison->provenance == 'COMMANDE')
                                                <td>{{$livraison->detailCommande?->produit?->nom ?? '-'}}</td>
                                                <td class="texte-center">{{$livraison->qte}}</td>
                                            @elseif ($livraison->provenance == 'LOCATION')
                                                {{-- Livraison de LOCATION : detail_commande_id pointe un detail_location. --}}
                                                @php $dl = \App\Models\DetailLocation::find($livraison->detail_commande_id); @endphp
                                                <td>{{$dl?->produit?->nom ?? '-'}}</td>
                                                <td class="texte-center">{{$dl?->qte ?? $livraison->qte}}</td>
                                            @else
                                                <td> {{$livraison->detailLivraison?->nom_produit ?? '-'}} </td>
                                                <td class="texte-center">{{$livraison->detailLivraison?->qte}}</td>
                                            @endif
                                            @if ($livraison->vehicule != null)
                                                <td class="text-center">{{$livraison->vehicule->marque ?? '' }}</td>
                                                <td class="text-center">{{$livraison->vehicule->immatriculation ?? '' }}</td>
                                                <td class="text-center">{{$livraison->vehicule->capacite ?? '' }}t</td>
                                            @else
                                                <td class="texte-center"><b> Pas de livraison </b></td>
                                                <td class="texte-center"><b> Pas de livraison </b></td>
                                                <td class="texte-center"><b> Pas de livraison </b></td>
                                            @endif
                                            <td class="text-center">
                                                @switch($livraison->accepte)
                                                    @case(1)
                                                    <span class="badge bg-success">Accepté</span>
                                                    @break
                                                    @case(3)
                                                    <span class="badge bg-danger">Refusé</span>
                                                    @break

                                                    @case(2)
                                                    <span class="badge bg-secondary">En attente</span>
                                                    @break
                                                    @default
                                                @endswitch
                                            </td>
                                            <td>
                                                @switch($livraison->etat_livraison)
                                                    @case("EN ATTENTE")
                                                        <span class="badge bg-secondary"> {{ $livraison->etat_livraison }} </span>
                                                        @break
                                                    @case("EN TRAITEMENT")
                                                        <span class="badge bg-info"> {{ $livraison->etat_livraison }} </span>
                                                        @break
                                                    @case("EN COURS LIVRAISON")
                                                        <span class="badge bg-warning text-dark"> {{ $livraison->etat_livraison }} </span>
                                                        @break
                                                    @case("LIVREE")
                                                        <span class="badge bg-success"> {{ $livraison->etat_livraison }} </span>
                                                        @break

                                                    @default

                                                @endswitch
                                            </td>
                                            <td class="texte-center"> {{$livraison->date_livraison}} </td>

                                            {{-- <td class="texte-center">
                                                @if ($livraison->accepte == 3)
                                                    <select class="form-control select-livreur" name="livreur"  data-key="{{ $key }}">
                                                        <option value="">Choisir un nouveau livreur</option>
                                                        @foreach($livreurs as $livreur)
                                                            <option value="{{$livreur->id}}"> {{ $livreur->user->nom_prenoms }} </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </td>
                                            <td class="texte-center">
                                                @if ($livraison->accepte == 3)
                                                <select id="vehicules_{{ $key }}" name="vehicule" class="form-control" id="">
                                                    <option value="">Choisir un nouveau véhicule</option>
                                                </select>
                                                @endif
                                            </td> --}}

                                            {{-- <td>
                                                @if ($livraison->accepte == 3)
                                                    <button class="btn btn-success" type="submit">Valider</button>
                                                @endif
                                            </td> --}}
                                        </form>
                                        </tr>
                                    {{-- @endif --}}

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

        $('.select-livreur').on('change', function () {
            var livreurId = $(this).val();
            var key = $(this).data('key');
            afficherVehicules(livreurId, key);
        });

        function afficherVehicules(id, key){
            console.log(id, key);
            $.ajax({
                url : '/afficher-vehicule-livreur-'+id,
                dataType: 'json',
                method: 'GET',
                success: function(data){
                    console.log(data)
                    $('#vehicules_'+ key ).html('<option value=""> Choisir un nouveau véhicule</option>')
                    data.forEach(vehicule => {
                        $('#vehicules_'+ key ).append(`<option value="${vehicule.id}">${vehicule.marque} | Capacité : ${vehicule.capacite} t</option>`)
                    })
                    // $('#livreur_'+key).html('')
                    // $('#livreur_'+key).append(data.livreur)
                }
            })

        }
    </script>

@endsection
