@php
// recuperation des produits associés au fournisseur
    $categorieSelectionne = $produit->categories()->pluck('categorie_id');
    $affaire = collect([$produit->type_affaire]);
    $uniteSelected = $unites->pluck('id');

    // dd($uniteSelected->contains($produit->unit));
@endphp

@extends('layout.main')

@section('contenu')
        <div class="screen-overlay"></div>

                <x-back-to-list :route="route('product.list')" />

                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10 mx-auto">
                        <div class="content-header">
                            <h2 class="content-title">
                                @if($produit->reference)
                                        Modification de {{$produit->nom}}
                                    @else
                                        Enregistrer un produit
                                    @endif
                            </h2>
                            <div>
                                {{-- <button class="btn btn-light rounded font-sm mr-5 text-body hover-up">Save to draft</button>
                                <button class="btn btn-md rounded font-sm hover-up">Publich</button> --}}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8 col-md-10 mx-auto">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>@if($produit->reference) Modifier le produit @else Informations du produit @endif</h4>
                            </div>
                            <form action="" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="card-body">
                                        @if(session('success'))
                                            <div class="alert alert-success text-center">
                                                {{-- <script>
                                                    Swal.fire({
                                                    title: 'Succès!',
                                                    text: 'Opération réussie',
                                                    icon: 'success',
                                                    confirmButtonText: 'OK'
                                                    });
                                                </script> --}}
                                            </div>
                                        @endif
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Reférence du produit</label>
                                            <input  type="text" class="form-control" name="reference" maxlength="10" value="{{$produit->reference}}"  id="product_name" />
                                            <span class="text-danger">
                                                @error('reference')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>




                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Nom du produit</label>
                                            <input  type="text"   name="nom" value="{{$produit->nom}}" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('nom')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Abreviation</label>
                                            <input  type="text"  value="abr" name="abreviation" value="{{$produit->abreviation}}" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('abreviation')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            {{-- <label for="product_name" class="form-label">Unité</label> --}}
                                            {{-- <input type="text"  name="unite" value="{{$produit->unite}}" class="form-control" id="product_name" /> --}}

                                            <select  class="form-control" name="unite" id="">
                                                <option value="">unité</option>
                                                @foreach ($unites as $unite)
                                                    <option @selected($unite->id == $produit->unite_produit_id) value="{{$unite->id}}"> {{$unite->libelle}} </option>
                                                @endforeach
                                            </select>
                                            <span class="text-danger">
                                                @error('unite')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Prix</label>
                                            <input  type="number"  name="prix_moyen" value="{{$produit->prix_moyen
                                            }}" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('prix')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>



                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Prix de réduction</label>
                                            <input type="number"  name="reduction" value="{{$produit->prix_reduction}}" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('reduction')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Prix de fournisseur</label>
                                            <input type="number"  name="prix_fournisseur" value="{{ old('prix_fournisseur', $produit->prix_fournisseur) }}" class="form-control" id="product_name" />
                                            <small class="text-muted">Prix d'achat auprès du fournisseur (sert de prix au catalogue).</small>
                                            <span class="text-danger">
                                                @error('prix_fournisseur')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Caution (location)</label>
                                            <input type="number" min="0" step="1" name="caution" value="{{ old('caution', $produit->caution ?? 0) }}" class="form-control" id="product_name" />
                                            <small class="text-muted">Caution unitaire demandée pour ce produit en location (0 si aucune). Pré-remplit la caution à la validation d'une location.</small>
                                        </div>

                                        @isset($fournisseurs)
                                        <div class="mb-4">
                                            <label class="form-label">Fournisseur</label>
                                            <select name="fournisseur" class="form-select">
                                                <option value="">-- Choisir un fournisseur --</option>
                                                @foreach ($fournisseurs as $f)
                                                    <option value="{{ $f->id }}" @selected(old('fournisseur') == $f->id)>{{ $f->nom_prenoms ?: (trim($f->nom.' '.$f->prenom) ?: 'Fournisseur #'.$f->id) }}</option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Le produit n'apparaît au catalogue qu'une fois rattaché à un fournisseur.</small>
                                            <span class="text-danger">
                                                @error('fournisseur')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Quantité en stock</label>
                                            <input type="number" min="0" name="qte" value="{{ old('qte') }}" class="form-control" placeholder="Ex : 500" />
                                            <span class="text-danger">
                                                @error('qte')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        @endisset


                                        <div class="mb-3">
                                            <select  style="height: 100px" name="categories[]" multiple class="form-select" id="produits">
                                                @foreach ($categories as $categorie)
                                                    <option @selected($categorieSelectionne->contains($categorie->id))  value="{{ $categorie->id }}">{{ $categorie->nom }}</option>
                                                @endforeach
                                            </select>
                                            <span class="text-danger">
                                                @error('categories')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-3">
                                            <label for="">Vente ou location</label>
                                            <select  name="type_affaire" class="form-select" id="produits">
                                                <option @selected($produit->type_affaire == "LOCATION") value="1">Location</option>
                                                <option @selected($produit->type_affaire == "VENTE") value="2">Vente</option>
                                                {{-- <option value="2">Pour location</option> --}}
                                            </select>
                                            <span class="text-danger">
                                                @error('type_affaire')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Description</label>
                                            <textarea   class="form-control" name="description"  rows="4">{{$produit->description}}</textarea>
                                            <span class="text-danger">
                                                @error('description')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label">Meilleur note /100</label>
                                            <input  type="number" name="meilleur_note" value="{{$produit->meilleur_note}}" placeholder="x/10" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('meilleur_note')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Choisissez une image</label>
                                            <input type="file"  class="form-control" name="image" id="product_name" />
                                            <span class="text-danger">
                                                @error('image')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        <div class="mb-4">
                                            <button type="submit" class="btn btn-primary w-100">
                                                @if($produit->categorie)
                                                    Modifier
                                                @else
                                                    Publier
                                                @endif
                                            </button>

                                        </div>
                                        {{-- <label class="form-check mb-4">
                                            <input class="form-check-input" type="checkbox" value="" />
                                            <span class="form-check-label"> Make a template </span>
                                        </label> --}}
                                    </div>
                            </form>
                        </div>
                        <!-- card end// -->

                    </div>


                </div>

        @endsection
