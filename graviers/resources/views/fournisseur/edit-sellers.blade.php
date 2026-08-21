@extends('layout.main')
@section('title','Modification de fournisseur')
@section('contenu')

<x-back-to-list :route="route('show.listSeller')" />

@php
// recuperation des produits associés au fournisseur
$produitSelectionne = $fournisseur->produits()->pluck('produit_id')



@endphp
@if (session('updated'))
    <div class="alert alert-success text-center" id="notify">
        {{session('updated')}}
    </div>
@endif
{{-- @dd($fournisseur->user) --}}
    <div class=" mt-20 card mx-auto "style=" width: 40rem; ">
        <div class="card-body">

                    <h4 class="card-title mb-4">Associez des produits à M/Mme {{ ucwords($fournisseur->user?->nom_prenoms) }}</h4>
            <form action="" method="post" >
                @csrf

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered tablee">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px;"></th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Produit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($produits as $produit)
                                    <tr>
                                        <td class="texte-center" > <input type="checkbox" @checked($produitSelectionne->contains($produit->id)) name="produits[]" value="{{ $produit->id }}" id=""> </td>
                                        <td class="texte-center"><b>{{ $produit->nom }}</b></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>

                <div class="mb-3 px-3">
                    <label class="form-label">Type de fournisseur</label>
                    @php $typeFrs = old('type_fournisseur', $fournisseur->type_fournisseur); @endphp
                    <select class="form-control" name="type_fournisseur">
                        <option value="">-- Sélectionner --</option>
                        @foreach (['Carrière','Producteur','Grossiste','Détaillant','Importateur','Autre'] as $tf)
                            <option value="{{ $tf }}" {{ $typeFrs === $tf ? 'selected' : '' }}>{{ $tf }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 px-3">
                    <label class="form-label">Produit principal</label>
                    @php $prodPrincipal = old('produit_principal', $fournisseur->produit_principal); @endphp
                    <select class="form-control" name="produit_principal">
                        <option value="">-- Sélectionner --</option>
                        @foreach ($produits as $pp)
                            <option value="{{ $pp->nom }}" {{ $prodPrincipal === $pp->nom ? 'selected' : '' }}>{{ $pp->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 px-3">
                    <label class="form-label">Régime de TVA</label>
                    @php $assujetti = (bool) old('assujetti_tva', $fournisseur->assujetti_tva); @endphp
                    {{-- Champ caché : une case décochée n'est pas postée, le décochage serait perdu. --}}
                    <input type="hidden" name="assujetti_tva" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" name="assujetti_tva"
                            id="assujettiTva" {{ $assujetti ? 'checked' : '' }}>
                        <label class="form-check-label" for="assujettiTva">
                            Fournisseur assujetti à la TVA
                        </label>
                    </div>
                    <small class="text-muted">
                        Décoché : on lui verse le seul coût du produit — la TVA payée par le client
                        revient à l'État, le transport à l'entreprise.
                        Coché : sa facture porte la TVA, elle s'ajoute donc à son règlement.
                    </small>
                </div>


                @if($fournisseur->user)
                    <input type="hidden" @if($fournisseur->user !==null) value="{{$fournisseur->id}}" @endif name="id">
                @endif
                <!-- form-group// -->

                <!-- form-group  .// -->
                {{-- @if() --}}

                <div class="mb-4">
                    <button type="submit" class="btn btn-primary" >Enregistrer</button>
                </div>

                <!-- form-group// -->
            </form>

        </div>
    </div>


@endsection
