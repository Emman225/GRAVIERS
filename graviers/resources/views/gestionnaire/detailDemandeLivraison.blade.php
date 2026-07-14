@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', 'Détails de commande')
@section('contenu')
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Demande de livraison n° {{ $demande->numero }}</h2>
            {{-- <p>Details for Order ID: </p> --}}
        </div>
    </div>
    <div class="card">
        <header class="card-header">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                    <span class="h5"> Date de la demande:  <b class="fw-bold">{{ $demande->created_at }}</b> </span>
                    <br />

                </div>

            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="row mb-50 mt-20 order-info-wrap">
                <div class="col-md-6">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-person"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Info client</h6>
                            <p class="mb-1">
                                {{ $demande->client->nom }} {{ $demande->client->prenom }} <br />
                                {{-- {{$commande->client->user->email}} <br /> --}}
                                {{ $demande->client->contact1 }} <br>
                                {{ $demande->client->contact2 }}
                            </p>

                        </div>
                    </article>
                </div>


                <!-- col// -->
                <div class="col-md-6">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-place"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Prise en charge</h6>
                            <p class="mb-1">
                                Ville: {{ ucfirst($demande->priseEnCharge->ville->nom) }}
                                <br />{{ ucfirst($demande->priseEnCharge->affichage) }} <br />

                            </p>

                        </div>
                        <div class="ms-5 text">
                            <h6 class="mb-1">Destination</h6>
                            <p class="mb-1">
                                Ville: {{ ucfirst($demande->destination->ville->nom) }}
                                <br />{{ ucfirst($demande->destination->affichage) }} <br />

                            </p>

                        </div>
                    </article>
                </div>
                <!-- col// -->
            </div>
            <!-- row // -->
            <div class="row">
                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table disabled class="table">
                            <thead>
                                <tr>
                                    <th width="40%">Produit</th>
                                    <th width="20%">Quantité</th>
                                    <th width="20%">Description</th>

                                </tr>
                            </thead>
                            <tbody>
                                {{-- @dd($details->produit) --}}
                                @foreach ($demande->detailLivraison as $detail)
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">{{ $detail->nom_produit }}</div>
                                            </a>
                                        </td>
                                        <td> {{ $detail->qte }} {{$detail->uniteProduit->abreviation}}</td>
                                        <td>{{ $detail->description }}</td>

                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="4">
                                        <article class="float-end">
                                            {{-- <dl class="dlist">
                                                            <dt>Subtotal:</dt>
                                                            <dd>$973.35</dd>
                                                        </dl>
                                                        <dl class="dlist">
                                                            <dt>Shipping cost:</dt>
                                                            <dd>$10.00</dd>
                                                        </dl> --}}
                                            <dl class="dlist">
                                                <dt>Total:</dt>
                                                <dd><b class="h5"> {{ number_format($demande->montantTotal,'0','',' ') }} fcfa </b></dd>
                                            </dl>
                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->
                </div>
                <!-- col// -->


            </div>
            <!-- card-body end// -->

            @foreach ($demande->detailLivraison as $detail)
                <div class="col-lg-10">
                    <div class="table-responsive">
                        <h3>{{$detail->nom_produit}}</h3>
                        <table  class="table table-striped">
                            <thead class="thead-dark">

                                <tr>
                                    <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px" width="">Numéro livraison</th>

                                    <th style="background-color: #1c57a3; color: white;" width="">Quantité livrée</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Livreur</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Date livraison</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Traité par</th>
                                    <th style="background-color: #1c57a3; color: white; border-top-right-radius:5px" width="">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php

                                    $qteRestante = $detail->qte

                                @endphp
                                @foreach ($detail->livraisons as $livraison )


                                <tr>
                                    <td> {{$livraison->numero}} </td> {{-- numéro --}}

                                    <td> {{$livraison->qte}} {{$detail->uniteProduit->abreviation}} </td> {{-- qte --}}


                                    <td> {{$livraison->livreur->user->nom.' '.$livraison->livreur->user->prenom}} ({{$livraison->livreur->user->contact}}) </td> {{-- livreur --}}

                                    <td> {{$livraison->updated_at}}</td> {{-- date livreur--}}

                                    <td> {{$livraison->user->nom_prenoms}}</td> {{-- traité par--}}

                                    <td> {{$livraison->etat_livraison}} </td> {{-- statut --}}



                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
        <!-- card end// -->
    @endsection
