@extends('layout.main')
@section('title','Paiement des factures')
@section('contenu')


@php
// recuperation des produits associés au fournisseur
// $produitSelectionne = $fournisseur->produits()->pluck('produit_id')
// dd($client->Commande->first()->factures->first());



@endphp
@if (session('updated'))
<div class="alert alert-success text-center" id="notify">
    {{session('updated')}}
</div>
@endif
{{-- @dd($fournisseur->user) --}}
<div class=" mt-20 card mx-auto " >
    <div class="card-body">

        <h4 class="card-title mb-4">Les factures de {{ ucwords($client->nom.' '.$client->prenom) }}</h4>
        <form action="" method="post">
            @csrf
            @error('factures')
                <span class="alert alert-danger text-center"> {{$message}} </span>
            @enderror
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered tablee">
                        <thead>
                            <tr>
                                <th class="text-center"
                                    style="background-color: #1c57a3; color: white; border-top-left-radius:5px;"></th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;">Numero facture
                                </th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;"> Montant
                                    facture </th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;"> Montant
                                    restant </th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;"> Numero
                                    commande </th>
                                <th class="text-center"
                                    style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Date
                                    commande</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($client->Commande as $commande)

                                @foreach ($commande->factures as $facture)
                                    @if ($facture->statut == 2)
                                        <tr>
                                            <td class="texte-center">
                                                {{-- @if($client->client_a_terme == 1)  --}}
                                                    <input type="checkbox" name="factures[]" value="{{ $facture->id }}" id="">
                                                {{-- @endif  --}}
                                            </td>
                                            <td class="texte-center"><b>{{ $facture->numero }}</b></td>
                                            <td class="texte-center"><b>{{ number_format($facture->montant,'0','',' ') }} fcfa</b>
                                            </td>
                                            <td class="texte-center"><b>{{
                                                    number_format($facture->paiements?->sortBydesc('created_at')->first()?->montant_restant
                                                    > 0 ? $facture->paiements->sortBydesc('created_at')->first()?->montant_restant :
                                                    $facture->montant ,'0','',' ') }} fcfa</b></td>
                                            <td class="texte-center"><b>{{ $commande->numero }}</b></td>
                                            <td class="texte-center"><b>{{ $commande->created_at->format('d-m-Y') }}</b></td>
                                        </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    <div class="container border border-primary pt-3 pb-3">
                        <div class="row">
                            {{-- <div class="col-4">
                                <h4>Entrer le montant du paiement : </h4>
                            </div> --}}
                            <div class="col-4">

                                <input type="number" style="border: 1px solid #1c57a3" placeholder="Entrez le montant à payer" name="montant" class=" mb-20 form-control" id="">
                            </div>
                            <div class="col-4">

                                <select style="border: 1px solid #1c57a3" name="mode" class="form-control" id="">
                                    <option value="">Moyen de paiement</option>
                                    @foreach ($moyens as $moyen)
                                    <option value="{{ $moyen->id }}">{{ $moyen->libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-4">

                                <button type="submit" class="btn btn-primary ">Payer</button>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- table-responsive //end -->
            </div>





            {{-- @if($fournisseur->user)
            <input type="hidden" @if($fournisseur->user !==null) value="{{$fournisseur->id}}" @endif name="id">
            @endif --}}
            <!-- form-group// -->

            <!-- form-group  .// -->
            {{-- @if() --}}


            <!-- form-group// -->
        </form>

    </div>
</div>


@endsection
