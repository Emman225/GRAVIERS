@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Détails de livraison')
@section('contenu')
    @if ($enlevement->livreur_validation != null)
        <div class="alert alert-success text-center">
            Le bon à déjà été traité
        </div>
    @endif
    <div class="card">
        <div class="">
            <a href="javascript:history.back()"><i class="material-icons md-arrow_back"></i> </a>
            <h4>Infomation de la commande</h4>

        </div>
        <!-- card-header end// -->
        <div class="card-body">


            <div class="row " >
                <div class="col-lg-4 col-md-6 text-center" style="">
                    <h1>Commande : {{$enlevement->code_enleve}} </h1>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Désignation</th>
                                    <th>Prix unitaire</th>
                                    <th>Quantité</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <a class="itemside">
                                            <div class=" h4 info"> {{ $enlevement->produit->nom }} </div>
                                        </a>
                                    </td>
                                    <td class="fw-bold h5"> {{ $enlevement->produit->prix_moyen }} fcfa</td>
                                    <td class="h4"> {{ $enlevement->qte }} </td>
                                    <td class="text-end"><dd><b class="h5 text-success fw-bold "> {{ $enlevement->produit->prix_moyen * $enlevement->qte }} fcfa </b> <br>

                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="4">
                                        <article class="float-end">

                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->

                </div>



                <div class="col-lg-4 col-md-6 text-center" style="">
                            <h1>Détail client</h1>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="40%">Nom prénom</th>
                                            <th width="20%">Numéro </th>
                                            <th width="20%">Adresse</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        <tr>
                                            <td>
                                                <a>
                                                    <div class="h4 info ">{{ $enlevement->livraison->client->nom.' '.$enlevement->livraison->client->prenom }}</div>
                                                </a>
                                            </td>
                                            <td class="h4">
                                                {{ $enlevement->livraison->client->contact1 }}</td>
                                            </td>
                                            <td class="h4">
                                                {{ $enlevement->livraison->commande->adresseLivraison->complement_adresse}}</td>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>



                        <!-- col// -->



                    <!-- card-body end// -->
                </div>
                <div class="col-lg-1 col-md-6 text-center" style="">
                    <td>

                        @if ($enlevement->livreur_validation == null)
                            <form action="{{route('livreur.bon.validation',$enlevement)}}" method="post">
                                @csrf
                                <button class=" btn btn-success rounded font-sm" type="submit">Valider la livraison</button>
                            </form>
                        @endif
                    </td>
                    <br><br>
                    <td>
                        <form action="{{route('livreur.bon.imprime',$enlevement)}}" method="post">
                            @csrf
                            <button class=" btn btn-info rounded font-sm " type="submit">Imprimer le bon</button>
                        </form>
                    </td>
                </div>
                <!-- col// -->


            </div>
            <!-- card-body end// -->
        </div>
    </div>
    <div class="card">

        <!-- card-header end// -->
    </div>
    <!-- card end// -->
@endsection
