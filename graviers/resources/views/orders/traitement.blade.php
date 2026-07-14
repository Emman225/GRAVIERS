@php
    $qte = null;
    // dd($commande);
@endphp

@extends('layout.main')
@section('title', 'traitement de commande')
@section('contenu')

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Détails de la commande {{ $commande->numero }}</h2>
            {{-- <p>Details for Order ID: </p> --}}
        </div>
    </div>
    <div class="card">
        {{-- <form id="okko" action="{{route('orders.traitement.post', $commande)}}" method="post">

            @csrf --}}
        <header class="card-header">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6 mb-lg-0 mb-15">
                    <span> <i class="material-icons md-calendar_today"></i> <b>{{ $commande->date_commande }}</b> </span>
                    <br />
                    <small class="text-muted">ID commande: {{ $commande->numero }}</small>
                </div>
                <div class="col-lg-6 col-md-6 ms-auto text-md-end">
                    {{-- <select class="form-select d-inline-block mb-lg-0 mr-5 mw-200">
                            <option>Changer l'état</option>
                            <option>En attente de paiement</option>
                            <option>Confirmé</option>
                            <option>Expédié</option>
                            <option>Livré</option>
                        </select> --}}
                    {{-- <a class="btn btn-primary" href="#">Enregistrer</a>
                        <a class="btn btn-secondary print ms-2" href="#"><i
                                class="icon material-icons md-print"></i></a> --}}
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
                            <p class="mb-1">
                                Mode de paiement: {{ $commande->modePaiement?->libelle }} <br />
                            </p>
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
                                Ville: {{ ucfirst($commande->adresseLivraison?->ville?->nom) }}
                                <br />{{ ucfirst($commande->adresseLivraison?->complement_adresse) }} <br />

                            </p>
                            {{-- <a href="#">View profile</a> --}}
                        </div>
                    </article>
                </div>
                <!-- col// -->
            </div>
            <!-- row // -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="table-responsive">
                        <table class="table ">

                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Fournisseur</th>
                                    <th>Stock</th>
                                    <th>Prix fournisseur</th>
                                    <th>Quantite</th>
                                    <th>Livreur</th>
                                    <th>Vehicule</th>
                                    <th>Date de Livraison</th>
                                    <th></th>
                                    {{-- <th>Matricule Vehicule</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($commande->produits as $key => $produit)

                                    @if ($produit->pivot->statut != 2)

                                        @php
                                            $totalEnlev = 0;
                                            $totalEnlev = HELP::totatEnlevementUnProduit($commande->id, $produit->id);
                                            // dd($totalEnlev);
                                            $qteRestant = $produit->pivot->qte - $totalEnlev;

                                            $qteFournisseur = 0;
                                            foreach ($produit->fournisseurs as $frs) {
                                                $qteFournisseur += $frs->pivot->qte;
                                            }

                                           
                                        @endphp

                                        @if ($qteFournisseur > 0)
                                            @if ($qteRestant > 0)
                                                <tr>

                                                    <form id="ok"
                                                        action="{{ route('orders.traitementItem', ['commande' => $commande, 'produit' => $produit]) }}"
                                                        method="post">
                                                        @csrf
                                                        <td>
                                                            <div class="info"> <span class="fw-bold">{{ $produit->nom }}
                                                                </span> <br> Qté à Enlever : {{ $produit->pivot->qte }} |
                                                                @if ($totalEnlev < 0.1)
                                                                    Aucune Qté enlevée
                                                                @else
                                                                    Qté déjà Enlevée : {{ $totalEnlev }}
                                                                @endif | Qté restant :
                                                                {{ $qteRestant }} </div>
                                                            <input required type="hidden" name="produit"
                                                                value="{{ $produit->id }}">
                                                        </td>
   
                                                        <td>
                                                            <select required class="form-control" name="fournisseur"
                                                                id="fournisseurSelected{{ $produit->id }}"
                                                                onchange="afficherStock({{ $produit->id }})">

                                                                @foreach ($produit->fournisseurs as $fr)
                                                                    @if ($fr->pivot->qte != 0)
                                                                        @php
                                                                            $qte =
                                                                                $qte == null ? $fr->pivot->qte : $qte;
                                                                        @endphp
                                                                        <option class="test{{ $fr->id }}"
                                                                            value="{{ $fr->id }}">
                                                                            {{ $fr->nom_prenoms }}
                                                                            ({{ $fr->contact1 }})
                                                                            <!-- || {{ $fr->pivot->qte }} -->
                                                                        </option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </td>

                                                        <td class="fw-bold text-success h5" id="stock_{{ $produit->id }}">
                                                            {{ $qte }} </td>

                                                        <td> <input required class="form-control"
                                                                value="{{ $produit->prix_fournisseur }}" type="number"
                                                                name="prix_fournisseur" min="1">
                                                        </td>

                                                        <td> <input required class="form-control"
                                                                value="{{ $totalEnlev > 0 ? $qteRestant : $produit->pivot->qte }}"
                                                                type="number" name="qte" placeholder="Quantité"
                                                                min="0.1"
                                                                max="{{ $totalEnlev > 0 ? $qteRestant : $produit->pivot->qte }}"
                                                                step="any">
                                                        </td>

                                                        <td class="text-end">
                                                            <select onchange="afficherVehicules({{ $produit->id }})"
                                                                id="livreurSelectionne{{ $produit->id }}"
                                                                class="form-control" required name="livreur">
                                                                <option value="">Selectionnez un livreur</option>
                                                                @foreach ($livreurs as $livreur)
                                                                    <option value="{{ $livreur->id }}">
                                                                        {{ $livreur->user?->nom_prenoms . ' | ' . $livreur->user?->contact }}
                                                                    </option>
                                                                @endforeach

                                                            </select>
                                                        </td>
                                                        <td class="text-end">
                                                            <select class="form-control" id="vehicules_{{ $produit->id }}"
                                                                required name="vehicule">
                                                            </select>
                                                        </td>

                                                        <td>
                                                            <input class="form-control" required type="date"
                                                                value="{{ substr($commande->date_livraison, 0, 10) }}"
                                                                name="date">
                                                        </td>

                                                        <td>
                                                            <button class="btn btn-success" type="submit">Valider</button>
                                                        </td>

                                                    </form>
                                                </tr>
                                            @endif
                                        @else
                                            <tr>
                                                <td> <span class="text-danger text-center">Pas de fournisseur disponible
                                                        pour le produit : {{ $produit->nom }}</span> </td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>

                                            </tr>
                                        @endif
                                    @endif
                                    @php
                                        $qte = null;
                                    @endphp
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive// -->
                </div>
                <!-- col// -->

                <div class="col-lg-3 p-5">
                    {{-- <div class="box shadow-sm bg-light">
                            <h6 class="mb-15">Info du paiement</h6>
                            <p>
                                <img src="assets/imgs/card-brands/2.png" class="border" height="20" /> Master Card ****
                                **** 4768 <br />
                                Réference du paiement: <span class="fw-bold">{{ $lignePaiement?->reference }}</span> <br />
                                Numéro du paiement: <span class="fw-bold">{{ $commande->client->contact1 }}</span>
                            </p>
                        </div> --}}
                    <div class="h-25 pt-4">
                        {{-- <div class="mb-3">
                                        <label>Notes</label>
                                        <textarea class="form-control" name="notes" id="notes" placeholder="Type some note"></textarea>
                                    </div> --}}
                        {{-- <a href="#" class="btn btn-primary">Affecter un fournisseur</a>
                                    <a href="#" class="btn btn-primary">Affecter un livreur</a>
                                </div> --}}
                    </div>
                    <!-- col// -->
                </div>
            </div>
            <!-- card-body end// -->

            {{-- </form> --}}
        </div>
        <!-- card end// -->
    @endsection

    @section('cssParts')
        <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    @endsection
    @section('jsParts')
        <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
        <script type="text/javascript">
            $(function() {
                var $table = $('.tabl').DataTable({
                    language: {
                        url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    },
                });
            });
        </script>

        <script type="text/javascript">
            function afficherStock(idProduit) {

                let idFour = document.getElementById('fournisseurSelected' + idProduit).value
                console.log(idFour)

                fetch('/afficherStock/' + idProduit + '/' + idFour, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },

                    })
                    .then(response => response.json())
                    .then(data => {
                        // Afficher des messages dans la console si nécessaire ou traiter les données
                        console.log(data);
                        $('#stock_' + idProduit).html(data.qte)
                    });
                // *********************************

            }

            function afficherVehicules(key) {

                let id = document.getElementById('livreurSelectionne' + key).value
                console.log(id)

                $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: '/afficher-vehicule-livreur-' + id,
                    dataType: 'json',
                    method: 'GET',
                    success: function(data) {
                        $('#vehicules_' + key).empty();
                        if (data.length > 0) {
                            $('#vehicules_' + key).html('<option value=""> Selectionnnez un vehicule</option>')
                            data.forEach(vehicule => {
                                $('#vehicules_' + key).append(
                                    `<option value="${vehicule.id}">${vehicule.marque} | Capacité : ${vehicule.capacite} t</option>`
                                    )
                            })
                            // $('#livreur_'+key).html('')
                            // $('#livreur_'+key).append(data.livreur)
                        }
                    }
                })


                // fetch('/afficher-vehicule-livreur-'+id)
                // .then(response => json())
                // .then(data => {
                //     console.log(data)
                // })
            }
        </script>


    @endsection
