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
            @error('paiements')
                <span class="alert alert-danger text-center"> {{$message}} </span>
            @enderror
            @if (session('fail'))
                <div class="alert alert-danger text-center" id="notify">
                    {{session('fail')}}
                </div>
            @endif

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered tablee">
                        <thead>
                            <tr>
                                <th class="text-center"
                                    style="background-color: #1c57a3; color: white; border-top-left-radius:5px;"></th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;">Numero Commande
                                </th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;"> Montant
                                    facture </th>
                                <th class="text-center" style="background-color: #1c57a3; color: white;"> Montant
                                    restant </th>
                                <th class="text-center"
                                    style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Date
                                    commande</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if($client->client_a_terme == 0)
                            {{-- dans le cas ou le client n'est pas à terme il paiement sur les commandes --}}
                                @foreach ($paiements as $cde)
                                    {{-- facture_id, commande_id, client_id, num_commande, date_commande, montant_a_payer, total_paye, montant_restant --}}
                                    <tr>
                                        <td class="texte-center">
                                            {{-- @if($client->client_a_terme == 1)  --}}
                                                <input type="checkbox" name="commande_id[]" value="{{ $cde->commande_id }}" id="">
                                            {{-- @endif  --}}
                                        </td>
                                        <td class="texte-center"><b>{{ $cde->num_commande }}</b></td>
                                        <td class="texte-center"><b>{{ number_format($cde->montant_a_payer,'0','',' ') }} fcfa</b></td>
                                        <td class="texte-center"><b> {{ number_format($cde->montant_restant,'0','',' ') }} fcfa </b></td>

                                        <td class="texte-center"><b>{{ \Carbon\Carbon::parse($cde->date_commande)->format('d-m-Y H:i:s') }}</b></td>
                                    </tr>
                                @endforeach
                            @else
                            {{-- dans le cas où le client est à terme il paie sur les factures --}}
                                @foreach ($paiements as $p)
                                    {{-- facture_id, commande_id, client_id, num_commande, date_commande, montant_a_payer, total_paye, montant_restant --}}
                                    <tr>
                                        <td class="texte-center">
                                            {{-- @if($client->client_a_terme == 1)  --}}
                                                <input type="checkbox" name="factures[]" value="{{ $p->facture_id }}" id="">
                                            {{-- @endif  --}}
                                        </td>
                                        <td class="texte-center"><b>{{ $p->num_commande }}</b></td>
                                        <td class="texte-center"><b>{{ number_format($p->montant_a_payer,'0','',' ') }} fcfa</b></td>
                                        <td class="texte-center"><b> {{ number_format($p->montant_restant,'0','',' ') }} fcfa </b></td>

                                        <td class="texte-center"><b>{{ \Carbon\Carbon::parse($p->date_commande)->format('d-m-Y') }}</b></td>
                                    </tr>
                                @endforeach
                            @endif
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

                                <select style="border: 1px solid #1c57a3" required name="mode" class="form-control" id="">
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
