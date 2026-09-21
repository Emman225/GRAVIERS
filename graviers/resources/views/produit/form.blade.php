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
                                        {{-- Cle propre : Flasher capte error/success et les rejoue en
                                             toast, ou le refus doit rester lisible a l ecran. --}}
                                        @if (session('produit_erreur'))
                                            <div class="alert alert-danger">{{ session('produit_erreur') }}</div>
                                        @endif

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
                                            <label for="product_name" class="form-label">Prix de réduction</label>
                                            <input type="number"  name="reduction" value="{{$produit->prix_reduction}}" class="form-control" id="product_name" />
                                            <span class="text-danger">
                                                @error('reduction')
                                                    {{$message}}
                                                @enderror
                                            </span>
                                        </div>
                                        @php
                                            // UN PRIX D'ACHAT APPARTIENT À UN COUPLE produit × fournisseur.
                                            //
                                            // À la création, un seul fournisseur est choisi : un champ
                                            // unique suffit. Sur un produit déjà rattaché à plusieurs
                                            // fournisseurs, chacun a son tarif — et c'est le plus élevé
                                            // qui fait le prix de vente. On les montre donc tous, et on
                                            // les rend corrigeables : jusqu'ici seul le fournisseur
                                            // lui-même, connecté à son espace, pouvait modifier son prix.
                                            $lignesAchat = $produit->exists
                                                ? \App\Models\StockProduit::with('fournisseur')
                                                    ->where('produit_id', $produit->id)
                                                    ->whereNull('deleted_at')
                                                    ->orderByDesc('prix')
                                                    ->get()
                                                : collect();
                                        @endphp

                                        @if ($produit->exists)
                                            <div class="mb-4">
                                                <label class="form-label">Prix d'achat par fournisseur</label>

                                                @if ($lignesAchat->isEmpty())
                                                    <p class="text-muted mb-0">
                                                        Aucun fournisseur ne tarife encore ce produit : son prix de
                                                        vente ne peut pas être calculé.
                                                    </p>
                                                @else
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle mb-1">
                                                            <thead>
                                                                <tr>
                                                                    <th>Fournisseur</th>
                                                                    <th style="width: 12rem;">Prix d'achat</th>
                                                                    <th style="width: 7rem;" class="text-center">Retiré</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($lignesAchat as $ligne)
                                                                    @php $retire = (int) $ligne->statut !== (int) \Help::$STATUT_ACTIF; @endphp
                                                                    <tr @class(['text-muted' => $retire])>
                                                                        <td>{{ $ligne->fournisseur->nom_prenoms ?? '—' }}</td>
                                                                        <td>
                                                                            <input type="number" min="0" step="1"
                                                                                   class="form-control {{ $retire ? '' : 'prix-achat' }}"
                                                                                   name="prix_achat[{{ $ligne->id }}]"
                                                                                   value="{{ old('prix_achat.' . $ligne->id, $ligne->prix) }}" />
                                                                        </td>
                                                                        <td class="text-center">
                                                                            {{-- Décoché, le fournisseur revient : une erreur de clic
                                                                                 doit pouvoir se défaire depuis le même écran. --}}
                                                                            <input type="checkbox" class="form-check-input"
                                                                                   name="fournisseur_retire[{{ $ligne->id }}]" value="1"
                                                                                   @checked(old('fournisseur_retire.' . $ligne->id, $retire)) />
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <small class="text-muted">
                                                        Le prix de vente suit le fournisseur <strong>le plus cher</strong>.
                                                        Un fournisseur retiré ne compte plus dans le prix, disparaît du
                                                        réapprovisionnement et ne peut plus recevoir de bon d'enlèvement.
                                                    </small>
                                                @endif

                                                <span class="text-danger">
                                                    @error('prix_achat.*')
                                                        {{$message}}
                                                    @enderror
                                                </span>
                                            </div>
                                        @else
                                            <div class="mb-4">
                                                <label for="prixFournisseur" class="form-label">Prix de fournisseur</label>
                                                <input type="number" min="0" step="1" name="prix_fournisseur"
                                                       value="{{ old('prix_fournisseur', $produit->prix_fournisseur) }}"
                                                       class="form-control" id="prixFournisseur" />
                                                <small class="text-muted">Ce que vous payez au fournisseur choisi ci-dessus.</small>
                                                <span class="text-danger">
                                                    @error('prix_fournisseur')
                                                        {{$message}}
                                                    @enderror
                                                </span>
                                            </div>
                                        @endif

                                        {{-- LE PRIX DE VENTE NE SE SAISIT PLUS.
                                             Il se calcule : prix d'achat majoré du pourcentage
                                             DALAKOUN. On le saisissait à côté du prix d'achat, sans
                                             rapport avec lui — d'où une bétonnière annoncée 20 000 et
                                             facturée 100. --}}
                                        <div class="mb-4">
                                            @php
                                                // LE TAUX DE CE PRODUIT, pas celui du catalogue.
                                                //
                                                // Un produit sous dérogation ne suit pas le taux
                                                // général : afficher ce dernier ferait annoncer par
                                                // la fiche un prix que la boutique ne pratique pas.
                                                $derogation   = $produit->exists && $produit->pourcentage_dalakoun !== null;
                                                $tauxApplique = $produit->exists
                                                    ? $produit->tauxDalakoun()
                                                    : (float) ($tauxDalakoun ?? 0);
                                                $enPourcent   = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
                                            @endphp

                                            <label for="prixDalakoun" class="form-label">Prix DALAKOUN (calculé)</label>
                                            <input type="text" class="form-control" id="prixDalakoun" readonly
                                                   value="" data-taux="{{ $tauxApplique }}"
                                                   aria-describedby="aidePrixDalakoun" />
                                            <small class="text-muted" id="aidePrixDalakoun">
                                                @if ($derogation)
                                                    Prix d'achat majoré de <strong>{{ $enPourcent($tauxApplique) }} %</strong>.
                                                    <span class="badge bg-info text-dark">Dérogation</span>
                                                    Ce produit ne suit pas le taux général
                                                    @if (!empty($tauxDalakoun))({{ $enPourcent($tauxDalakoun) }} %)@endif.
                                                    La dérogation se pose et se retire dans
                                                    <a href="{{ route('product.pourcentage') }}">Produits → Pourcentage DALAKOUN</a>,
                                                    après validation par un second administrateur.
                                                @elseif (!empty($tauxApplique))
                                                    Prix d'achat majoré de {{ $enPourcent($tauxApplique) }} %.
                                                    Ce champ n'est pas modifiable : il suit le pourcentage en vigueur.
                                                @else
                                                    Aucun pourcentage DALAKOUN n'est en vigueur : le produit serait vendu
                                                    à son prix d'achat. Rendez-vous dans
                                                    <a href="{{ route('product.pourcentage') }}">Produits → Pourcentage DALAKOUN</a>,
                                                    où se pose aussi une dérogation propre à un produit.
                                                @endif
                                            </small>
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

                                        {{-- Ce partiel est inclus dans une section : ce qui
                                             suit son @endsection sort AVANT le formulaire.
                                             Le script vit donc ici, et attend le DOM. --}}
                                        <script>
                                        document.addEventListener('DOMContentLoaded', function () {
        // Le prix DALAKOUN se recalcule à mesure qu'on saisit le prix d'achat :
        // on voit tout de suite ce que le client paiera.
        (function () {
            var vente = document.getElementById('prixDalakoun');

            if (!vente) {
                return;
            }

            // À la création un seul champ, à la modification un par fournisseur :
            // le prix de vente suit le plus cher, dans les deux cas.
            var champs = document.querySelectorAll('#prixFournisseur, .prix-achat');

            if (!champs.length) {
                return;
            }

            var taux = parseFloat(vente.dataset.taux || '0');

            function calculer() {
                var plusCher = 0;

                Array.prototype.forEach.call(champs, function (champ) {
                    var montant = parseFloat(champ.value);

                    if (!isNaN(montant) && montant > plusCher) {
                        plusCher = montant;
                    }
                });

                if (plusCher <= 0) {
                    vente.value = '';
                    return;
                }

                var prix = Math.round(plusCher * (1 + taux / 100));
                vente.value = prix.toLocaleString('fr-FR') + ' fcfa';
            }

            Array.prototype.forEach.call(champs, function (champ) {
                champ.addEventListener('input', calculer);
            });

            calculer();
        })();
                                        });
                                        </script>

        @endsection
