
{{-- @dd($location) --}}
@extends('layout.main')
@section('contenu')
@section('title', 'Paiement')


<div class="screen-overlay"></div>
<div class="content-header">
    <div>
        <h2 class="content-title card-title">Paiement de la location N°{{ $location->numero }}</h2>
    </div>
</div>
<div class="card">
    <header class="card-header">
        <div class="row align-items-center">
            <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                <span> <i class="material-icons md-calendar_today"></i> <b>{{ $location->created_at }}</b> </span>
                <br />
                {{-- <small class="text-muted">ID commande: {{ $commande->numero }}</small> --}}
            </div>

        </div>
    </header>
    <!-- card-header end// -->
    <div class="card-body">
        <div class="row mb-50 mt-20 order-info-wrap">
            <div class="col-md-4">
                <article class="icontext align-items-start">
                    <span class="icon icon-sm rounded-circle bg-primary-light">
                        <i class="text-primary material-icons md-person"></i>
                    </span>
                    <div class="text">
                        <h6 class="mb-1">Info client</h6>
                        <p class="mb-1">
                            {{ $location->client->nom.' '.$location->client->prenom}} <br />
                            {{-- {{$commande->client->user->email}} <br /> --}}
                            {{ $location->client->contact1 }} <br>
                            {{ $location->client->contact2 }}
                        </p>
                        <a href="#">View profile</a>
                    </div>
                </article>
            </div>
            <!-- col// -->
            <div class="col-md-4">
                <article class="icontext align-items-start">
                    <span class="icon icon-sm rounded-circle bg-primary-light">
                        <i class="text-primary material-icons md-local_shipping"></i>
                    </span>
                    <div class="text">
                        <h6 class="mb-1">Info commande</h6>

                        {{-- <a href="#">Download info</a> --}}
                    </div>
                </article>
            </div>
            <!-- col// -->
            <div class="col-md-4">
                <article class="icontext align-items-start">
                    <span class="icon icon-sm rounded-circle bg-primary-light">
                        <i class="text-primary material-icons md-place"></i>
                    </span>
                    <div class="text">
                        <h6 class="mb-1">Lieu de livraison</h6>
                        <p class="mb-1">
                            Ville: {{ ucfirst($location->adresseLivraison?->ville?->nom) }}
                            <br />{{ ucfirst($location->adresseLivraison?->complement_adresse) }} <br />

                        </p>
                        {{-- <a href="#">View profile</a> --}}
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
                                <th width="20%">Prix unitaire</th>
                                <th width="20%">Quantité</th>
                                <th width="20%" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- @dd($details->produit) --}}
                            @foreach ($location->detailLocation as $detail)
                                @php
                                    // Prix unitaire effectivement facturé sur la ligne (inclut le prix personnalisé du client si applicable).
                                    // detail_location.prix stocke le total = qte * pu * nbre_jour, donc on remonte au pu.
                                    $diviseur = max(1, ($detail->qte ?? 1) * ($detail->nombre_jour ?? 1));
                                    $puFacture = $detail->prix ? ($detail->prix / $diviseur) : ($detail->produit?->prix_moyen ?? 0);
                                @endphp
                                <tr>
                                    <td>
                                        <a class="itemside">
                                            <div class="info">{{ $detail->produit?->nom }}</div>
                                        </a>
                                    </td>
                                    <td>{{ number_format($puFacture,'0','',' ') }} fcfa</td>
                                    <td> {{ $detail->qte }} </td>
                                    <td class="text-end">{{ number_format($puFacture * $detail->qte,'0','',' ') }} fcfa <br>
                                        {{-- <small class="text-success"> {{ $produit->pivot->statut }} </small> --}}
                                    </td>
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
                                            <dd><b class="h5"> {{ number_format($location->montant_total,'0','',' ') }} fcfa </b></dd>
                                        </dl>
                                        <dl class="dlist">
                                            <dt class="text-muted">Statut:</dt>
                                            <dd>
                                                @if ($location->statut == 1)
                                                    <span class="badge rounded-pill alert-success text-danger">Aucun
                                                        paiement effectué</span>
                                                @elseif($location->statut == 2)
                                                    <span class="badge rounded-pill alert-success text-warning">paiement
                                                        en cours...</span>
                                                @elseif($location->statut == 3)
                                                    <span class="badge rounded-pill alert-success text-success">Paiement
                                                        soldé</span>
                                                @endif
                                            </dd>
                                        </dl>
                                    </article>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                {{-- @dd($devis->paiements) --}}
                @if(!$location->paiements->isEmpty())
                    <div class="table-responsive">
                        <h3>Historique des paiements</h3>
                        <table disabled class="table table-striped">
                            <thead>
                                <tr>
                                    <th width="40%">Date Paiement</th>
                                    <th width="20%">Montant Paiement</th>
                                    <th width="20%">Mode Paiement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $total = 0 @endphp

                                @foreach ($location->paiements as $paiement)
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">
                                                    {{ $paiement->ligne?->created_at?->format('d-m-Y') }}</div>
                                            </a>
                                        </td>
                                        <td>{{ number_format($paiement->ligne?->montant ?? 0,'0','',' ') }} fcfa</td>
                                        <td> {{ $paiement->ligne?->modePaiement?->description }} </td>
                                        @php $total = $total + ($paiement->ligne?->montant ?? 0) @endphp
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
                                                        </dl>  --}}
                                            <dl class="dlist">
                                                <dt>Total:</dt>
                                                <dd><b class="h4 text-success">{{number_format($total,0,'',' ')}} fcfa </b></dd>
                                            </dl>
                                            {{-- <dl class="dlist">
                                                <dt class="text-muted">Statut:</dt>
                                                <dd>
                                                    @if ($commande->statut == 1)
                                                        <span class="badge rounded-pill alert-success text-danger">Aucun
                                                            paiement effectué</span>
                                                    @elseif($commande->statut == 2)
                                                        <span class="badge rounded-pill alert-success text-warning">paiement
                                                            en cours...</span>
                                                    @elseif($commande->statut == 3)
                                                        <span class="badge rounded-pill alert-success text-success">Paiement
                                                            soldé</span>
                                                    @endif
                                                </dd>
                                            </dl>  --}}
                                        </article>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                @endif
                <!-- table-responsive// -->
            </div>

            <div class="col-lg-4">
                <div class="card " style="margin: auto; width: 30rem; height: 30rem">

                    <div class="card-body" style="">

                        {{-- @dd($commande->devis->paiements) --}}
                        <h4 class="card-title mb-4 text-center ">
                            Paiement de la commande N°{{ $location->numero }} <br>
                            @if ($location->remise >0)
                                <h5 class="text-center bg-danger text-white">Remise de <small class="text-mutted"> {{ ($location->remise) }}% effectuée sur le montant initial </small> </h5><br>
                            @endif
                            <h3 class="text-center ">
                                montant restant:
                                <span class="text-danger">{{number_format(Help::montantLocationRestant($location),'0','',' ')}}fcfa </h3> <br>


                                </span>
                            </h4>
                            @if (session('succes'))
                                <div class="alert alert-success text-center" id="notify">
                                    {{ session('succes') }}
                                </div>
                            @endif

                        </h4>
                        @if (session('fail'))
                            <div class="alert alert-danger text-center" id="notify">
                                {{ session('fail') }}
                            </div>
                        @endif

                        <form method="post" id="form" action="{{route('paye.paiementLocationTraitement',$location)}}">
                            @csrf

                            <div class="mb-3">
                                <input type="text" required name="libelle" placeholder="Libelle" class="form-control">
                            </div>
                            <div class="mb-3">
                                <input type="text" required name="moyen" placeholder="Moyen de paiement"
                                    class="form-control">
                            </div>
                            <div class="mb-3">
                                <select class="form-control" name="mode">
                                    @foreach ($modePaiement as $modepaiement)
                                        <option value="{{ $modepaiement->id }}">{{ $modepaiement->description }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <input type="number" id="montant" required name="montant" placeholder="Entrez le montant" class="form-control">
                                <span class="text-danger" id="erreur"></span>
                            </div>

                            <!-- form-group// -->
                            <!-- form-group form-check .// -->
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary w-100">Payer</button>
                            </div>
                            <div class="mb-4">
                                <a href="{{ route('orders.list') }}">Revenir à la liste des commandes</button>
                            </div>
                            <!-- form-group// -->
                        </form>


                    </div>
                </div>
            </div>
            <!-- col// -->

        </div>
        <!-- card-body end// -->
    </div>
</div>




@endsection
@section('jsParts')
<script>
    function reload(){
        setTimeout(() => {
            location.reload()
        }, 1000);
    }
    // setTimeOut(() => {},3000)


    let form = document.getElementById('form');
    form.addEventListener('submit', function(e) {
        let montant = document.getElementById('montant');
        let erreur = document.getElementById('erreur');
        // alert(montant.value)
        if (montant.value < 0) {

            erreur.innerHTML = "Les montant inférieur à 0 ne sont pas autorisés"
            e.preventDefault();
        }
    })
</script>
@endsection
