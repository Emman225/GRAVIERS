{{-- @dd($commande) --}}

@extends('layout.main')
@section('contenu')
@section('title', 'Paiement')


<div class="screen-overlay"></div>
<div class="content-header">
    <div>
        <h2 class="content-title card-title">Paiement de la commande N°{{ $commande->numero }}</h2>
    </div>
</div>
<div class="card">
    <header class="card-header">
        <div class="row align-items-center">
            <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                <span> <i class="material-icons md-calendar_today"></i> <b>{{ $commande->date_commande }}</b> </span>
                <br />
                <small class="text-muted">ID commande: {{ $commande->numero }}</small>
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
                            {{ $commande->client->nom }} {{ $commande->client->prenom }} <br />
                            {{-- {{$commande->client->user->email}} <br /> --}}
                            {{ $commande->client->contact1 }} <br>
                            {{ $commande->client->contact2 }} 
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
                            @if($commande->adresseLivraison && $commande->adresseLivraison->ville)
                                Ville: {{ ucfirst($commande->adresseLivraison->ville->nom) }}
                                <br />{{ ucfirst($commande->adresseLivraison->complement_adresse ?? '') }}
                            @else
                                <span class="text-muted">Pas de livraison</span>
                            @endif
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
                            @foreach ($commande->produits as $produit)
                                <tr>
                                    <td>
                                        <a class="itemside">
                                            <div class="info">{{ $produit->nom }}</div>
                                        </a>
                                    </td>
                                    <td>{{ number_format($produit->pivot->prix,'0','',' ') }} fcfa</td>
                                    <td> {{ $produit->pivot->qte }} </td>
                                    <td class="text-end">{{ number_format($produit->pivot->prix * $produit->pivot->qte,'0','',' ') }} fcfa <br>
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
                                            <dd><b class="h5"> {{ number_format($commande->montant_total,'0','',' ') }} fcfa </b></dd>
                                        </dl>
                                        <dl class="dlist">
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
                                        </dl>
                                    </article>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                {{-- @dd($devis->paiements) --}}
                @if(!$devis->lignes->isEmpty())
                    <div class="table-responsive">
                        <h3>Historique des paiements</h3>
                        <table class="table" id="table">
                            <thead>
                                <tr>
                                    <th width="40%">Date Paiement</th>
                                    <th width="20%">Montant Paiement</th>
                                    <th width="20%">Mode Paiement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $total = 0 @endphp

                                @foreach ($devis->lignes as $ligne)
                                    <tr>
                                        <td>
                                            <a class="itemside">
                                                <div class="info">
                                                    {{ $ligne->created_at->format('d-m-Y') }}</div>
                                            </a>
                                        </td>
                                        <td>{{ number_format($ligne->montant,'0','',' ') }} fcfa</td>
                                        <td> {{ $ligne->modePaiement->description }} </td>
                                        @php $total = $total+$ligne->montant @endphp
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
                                    <td></td>
                                    <td></td>
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


                    @if($commande->statut != 3)
                        <h4 class="card-title mb-4 text-center ">
                            Paiement de la commande N°{{ $commande->numero }} <br>
                            @if ($commande->remise >0)
                                <h5 class="text-center bg-danger text-white p-2">Remise de <small class="text-mutted"> {{ number_format($commande->remise,'0','',' ') }} fcfa effectuée sur le montant initial </small> </h5><br>
                            @endif
                            <h3 class="text-center ">
                                montant restant:
                                <span class="text-danger">
                                    {{-- @dd($devis->paiements) --}}
                                    @if ($commande->devis->paiements->isEmpty())
                                        {{ number_format($commande->montant_total,0,'',' ') }}
                                    @else
                                        {{number_format($montant_restant,'0','',' ')}}
                                    @endif
                                    fcfa </h3> <br>


                                </span>
                            </h4>
                            @if (session('succes'))
                                <div class="alert alert-success" id="notify">
                                    {{ session('succes') }}
                                </div>
                            @endif

                        </h4>
                        @if (session('fail'))
                            <div class="alert alert-danger" id="notify">
                                {{ session('fail') }}
                            </div>
                        @endif

                        <form method="post" id="form" action="">
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
                            <div class="mb-4 d-flex justify-content-between">
                                <a href="{{ route('orders.list') }}">Revenir à la liste des commandes</a>

                            </div>
                            <!-- form-group// -->
                        </form>
                    @else

                    <div class="container" style="display: flex; flex-direction: column; justify-content: center; align-items: center; margin: 0 auto">
                        <a class="btn btn-primary" href="{{ route('orders.traitement', $commande) }}">Traiter la commande</a> <br><br>
                        <a href="{{ route('orders.list') }}" class="mt-3">Revenir à la liste des commandes</a>
                    </div>


                    @endif
                    </div>

                </div>

            </div>
            <!-- col// -->

        </div>
        <!-- card-body end// -->
    </div>
</div>



@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')

<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>

<script type="text/javascript">
    $(function() {
        var $table = $('#table').DataTable({
            columnDefs: [{ targets: '_all', defaultContent: '-' }],
            language: {
                url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
            },

        });
    });
</script>

<script>
    function reload(){
        setTimeout(() => {
            location.reload()
        }, 1000);
    }


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
