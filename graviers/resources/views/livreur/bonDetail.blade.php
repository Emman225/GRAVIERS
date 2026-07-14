@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title', 'Détails de bon')
@section('contenu')
    @if ($enlevement->livreur_validation != null)
        <div class="alert alert-success text-center">
            Le bon à déjà été traité
        </div>
    @endif
    <div class="card">
        <div class="card-header">
            <a href="javascript:history.back()"><i class="material-icons md-arrow_back"></i> </a>
            <h4>Information de la Commande/Bon d'enlevement N° {{ $enlevement->code_enleve }}</h4>

        </div>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="row">
                <div class="col-md-10" style="">
                    <h3>Commande/Bon d'enlevement N° {{ $enlevement->code_enleve }} </h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="text-center">Désignation</th>
                                    {{-- <th>Prix unitaire</th> --}}
                                    <th class="text-center">Quantité</th>
                                    {{-- <th class="text-end">Total</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <a class="itemside">
                                            <div class="h6 info bd-primary"> {{ $enlevement->produit->nom }} </div>
                                        </a>
                                    </td>
                                    {{-- <td class="fw-bold h5"> {{ $enlevement->produit->prix_moyen }} fcfa</td> --}}
                                    <td class="h6 text-center"> {{ $enlevement->qte_servi == null ? $enlevement->qte : $enlevement->qte_servi }} </td>
                                    {{-- <td class="text-end"><dd><b class="h5 text-success fw-bold "> {{ $enlevement->produit->prix_moyen * $enlevement->qte }} fcfa </b> <br> --}}

                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->

                </div>


                <div class="col-md-2" style="">
                    <td>

                        @if ($enlevement->livreur_validation == null)
                            <form action="{{ route('livreur.bon.validation', $enlevement) }}" method="post">
                                @csrf
                                <button class=" btn btn-success rounded font-sm" type="submit">Valider le bon</button>
                            </form>
                        @endif
                    </td>
                    <br><br>
                    <td>
                        <form action="{{ route('livreur.bon.imprime', $enlevement) }}" method="post">
                            @csrf
                            <button class=" btn btn-info rounded font-sm " type="submit">Imprimer le bon</button>
                        </form>
                    </td>
                </div>
                <!-- col// -->
            </div>
            <!-- card-body end// -->

            <div class="row">
                <div class="col-12">
                    <h3>Détail fournisseur </h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Adresse</th>
                                    <th>Contact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <a class="itemside">
                                            <div class="h6 info">
                                                {{ $enlevement->fournisseur->nom_prenoms }}
                                            </div>
                                        </a>
                                    </td>
                                    <td class="h6"> {{ $enlevement->fournisseur->adresse_geo }}</td>
                                    <td class="h6"> {{ $enlevement->fournisseur->contact1 }} </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <h3>Détail client</h3>
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
                                                <div class="h6 info ">
                                                    {{ $enlevement->livraison->client->nom . ' ' . $enlevement->livraison->client->prenom }}
                                                </div>
                                            </a>
                                        </td>
                                        <td class="h6">
                                            {{ $enlevement->livraison->client->contact1 }}</td>
                                        </td>
                                        <td class="h6">
                                            {{ $enlevement->livraison->adresseLivraison->affichage }}</td>
                                    </tr>

                                </tbody>
                            </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- card end// -->
    </div>
@endsection
