@extends('layout.main')
@section('title', 'Détails de commande')
@section('contenu')
    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Détails du devis {{ Help::formatNumeroFacture($devis->numero) }}</h2>
            @if(!empty($devis->libelle))
                <p class="mb-1"><strong>Libellé :</strong> {{ $devis->libelle }}</p>
            @endif
            <span class="badge bg-{{ $devis->statut == 1 ? 'secondary' : 'success' }}">{{ $devis->statut == 1 ? 'En attente' : 'Commandé' }} </span>
        </div>

    </div>
    <div class="card">
        <header class="card-header">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                    <span> <i class="material-icons md-calendar_today"></i> <b>{{ $devis->created_at->format('d-m-Y à H:i') }}</b> </span>
                    <br />
                    <small class="text-muted">ID commande: {{ $devis->numero }}</small>
                </div>
                {{-- <div class="col-lg-6 col-md-6 ms-auto text-md-end">
                    <select class="form-select d-inline-block mb-lg-0 mr-5 mw-200">
                        <option>Changer l'état</option>
                        <option>En attente de paiement</option>
                        <option>Confirmé</option>
                        <option>Expédié</option>
                        <option>Livré</option>
                    </select>
                    <a class="btn btn-primary" href="#">Enregistrer</a>
                    <a class="btn btn-secondary print ms-2" href="#"><i class="icon material-icons md-print"></i></a>
                </div> --}}
            </div>
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="row mb-50 mt-20 order-info-wrap">
                <div class="col-md-8">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-person"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Info client</h6>
                            <p class="mb-1">
                                {{ $devis->client?->nom }} {{ $devis->client?->prenom }} <br />
                                {{ $devis->client?->email }} <br />
                                {{ $devis->client?->contact1 }} <br>
                                {{ $devis->client?->contact2 }}
                            </p>
                            {{-- <a href="#">View profile</a> --}}
                        </div>
                    </article>
                </div>
                <!-- col// -->
                {{-- <div class="col-md-4">
                    <article class="icontext align-items-start">
                        <span class="icon icon-sm rounded-circle bg-primary-light">
                            <i class="text-primary material-icons md-local_shipping"></i>
                        </span>
                        <div class="text">
                            <h6 class="mb-1">Info commande</h6>
                            <p class="mb-1">
                                Mode de paiement: {{ $devis->modePaiement?->libelle }} <br />
                            </p>
                        </div>
                    </article>
                </div> --}}

                <!-- col// -->
                <div class="col-md-4">
                    <article class="icontext align-items-start">
                        @if ($devis->adresse_livraison_id)
                            <div class="text">
                                <span class="icon icon-sm rounded-circle bg-primary-light">
                                    <i class="text-primary material-icons md-place"></i>
                                </span>
                                    <h6 class="mb-1">Lieu de livraison</h6>
                                    <p class="mb-1">
                                        Pays: {{ ucfirst($devis->adresseLivraison?->pays?->nom ?? '') }}
                                        <br />Ville: {{ ucfirst($devis->adresseLivraison?->ville?->nom ?? '') }}
                                        <br />{{ ucfirst($devis->adresseLivraison?->complement_adresse ?? '') }} <br />

                                    </p>
                            </div>
                        @endif

                    </article>
                </div>
                <!-- col// -->
            </div>
            <!-- row // -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="table-responsive">
                        <table disabled class="table">
                            <thead>
                                <tr>
                                    <th width="40%">Produit</th>
                                    <th width="20%">Prix unitaire</th>
                                    <th width="20%">Quantité</th>
                                    <th width="20%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @dd($details->produit) --}}
                                @foreach ($devis->produits as $produit)
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">{{ $produit->nom }}</div>
                                            </a>
                                        </td>
                                        <td>{{ Help::formatNombre($produit->pivot->prix, true) }}</td>
                                        <td> {{ Help::formatNombre($produit->pivot->qte, false) }} </td>
                                        <td class="text-end">
                                            {{ Help::formatNombre($produit->pivot->prix * $produit->pivot->qte, true) }}
                                            <br>
                                            {{-- <small class="text-success"> {{ $produit->pivot->statut }} </small> --}}
                                        </td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="4">
                                        <article class="float-end">
                                            <dl class="dlist">
                                                <dt>Sous-total:</dt>
                                                <dd>{{ Help::formatNombre($devis->montant, true) }}</dd>
                                            </dl>
                                            @if ($devis->cout_livraison)
                                                <dl class="dlist">
                                                    <dt>Cout livraison</dt>
                                                    <dd>{{Help::formatNombre($devis->cout_livraison, true)}}</dd>
                                                </dl>
                                            @endif
                                            <dl class="dlist">
                                                <dt>TVA</dt>
                                                <dd>{{Help::formatNombre($devis->tva, true)}} </dd>
                                            </dl>
                                            <dl class="dlist">
                                                <dt>Total:</dt>
                                                <dd><b class="h5">
                                                        {{ Help::formatNombre($devis->montant + $devis->tva + $devis->cout_livraison, true) }} </b></dd>
                                            </dl>
                                            <dl class="dlist">

                                                <dd>

                                                </dd>
                                            </dl>
                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->
                </div>
            </div>
            <!-- card-body end// -->
        </div>
    </div>
    <!-- card end// -->
@endsection
