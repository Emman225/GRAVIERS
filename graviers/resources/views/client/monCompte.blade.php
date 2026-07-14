{{-- @dd(Help::totalEnleveParClient(Auth::user()->client), Help::totalPaiementClient(Auth::user()->client)); --}}

@php
    use Illuminate\Support\Carbon;
@endphp
@extends('client.main')
@section('title','Mon compte')
@section('content')
<main class="main pages">
    @include('client.navMobile')

    @if(session('errorQte'))
        <div class="alert alert-danger text-center" id="notify"> {{session('errorQte')}} </div>
    @endif
    @if(session('livree'))
        <div class="alert alert-success text-center" id="notify"> {{session('livree')}} </div>
    @endif
    @if(session('delete'))
        <div class="alert alert-info text-center" id="notify"> {{session('delete')}} </div>
    @endif
    @if(session('removeDelete'))
        <div class="alert alert-success text-center" id="notify"> {{session('removeDelete')}} </div>
    @endif
    <div class="page-content pt-30 pb-150">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="dashboard-menu">
                                <ul class="nav flex-column" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" href="#dashboard" role="tab" aria-controls="dashboard" aria-selected="false"><i class="fi-rs-settings-sliders mr-10"></i>Votre tableau de bord</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="orders-tab" data-bs-toggle="tab" href="#orders" role="tab" aria-controls="orders" aria-selected="false"><i class="fi-rs-shopping-bag mr-10"></i>Mes commandes</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="devis-tab" data-bs-toggle="tab" href="#devis" role="tab" aria-controls="devis" aria-selected="false"><i class="fi-rs-shopping-bag mr-10"></i>Mes devis</a>
                                    </li>

                                    {{-- <li class="nav-item">
                                        <a class="nav-link" id="" href="{{route('client.gestionVehicule')}}" >Mes vehicules</a>
                                    </li> --}}

                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{route('client.retourProduitPage')}}" > Retour de produits</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{ route('client.ticketSAV') }}"><i class="fi-rs-headset mr-10"></i>Service après-vente (SAV)</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{ route('client.mesTicketsSAV') }}"><i class="fi-rs-time-past mr-10"></i>Mes tickets SAV (suivi)</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="delivery-tab" data-bs-toggle="tab" href="#delivery" role="tab" aria-controls="delivery" aria-selected="true"><i class="fi-rs-shopping-cart-check mr-10"></i>Demande de livraisons</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="location-tab" data-bs-toggle="tab" href="#location" role="tab" aria-controls="location" aria-selected="true"><i class="fi-rs-shopping-cart-check mr-10"></i>Demande de location</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="paiements-tab" href="{{ route('client.listePaiementCommande', 'en-attente') }}" > <i class="fi-rs-shopping-cart-check mr-10"></i>Mes paiements</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="account-detail-tab" data-bs-toggle="tab" href="#account-detail" role="tab" aria-controls="account-detail" aria-selected="true"><i class="fi-rs-user mr-10"></i>Détail du compte</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" id="" href="{{route('client.demandeClientATermePage')}}" > Devenir un client à terme</a>
                                    </li>

                                </ul>
                                {{-- <li class=""> --}}
                                    <form action="{{route('show.logout')}}" method="post">
                                        @csrf
                                        @method('delete')
                                        <button class="btn mt-5 d-flex align-items-center bg-secondary" style="height: 30px; background: indi">Déconnexion</button>
                                    </form>
                                    {{-- <a class="mt-5 btn bg-danger" id="" href="{{route('client.demandeClientATermePage')}}" > Deconnexion</a> --}}
                                {{-- </li> --}}
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="tab-content account dashboard-content pl-10">

                                {{-- TABLEAU DE BORD --}}
                                <div class="tab-pane fade active show" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0"> {{(now()->format('H')< 13 ) ? 'Bonjour' : 'Bonsoir'}} {{ $client->display_name }} ! </h3>
                                            <p class="fw-bold">Vous avez <span class="h4 text-primary fw-bold">{{$client->point}}</span> point{{$client->points >0 ? 's': ''}} </p>
                                        </div>
                                        <div class="card-body">
                                            <p>
                                                les points sont obtenu à partir de chaque commande terminée.<br />
                                                Utilisez les pour obtenir des réductions sur vos commandes.
                                            </p>
                                        </div>
                                        <div class="container">
                                            <h3 class="bg-primary px-3 py-1 text-white text-center" style="border-radius: 5px">Solde: {{Help::soldeClient($client, false)}} </h3> {{-- FALSE pour dire qu'on est pas sur l'interface d'un admin --}}
                                        </div>
                                        {{-- <span class="fw-bold h3"> Information du solde <a href="{{route('client.grandLivre')}}">Cliquez ici</a> </span> --}}
                                    </div>
                                </div>

                                {{-- COMMANDE --}}
                                <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Vos commandes</h3>
                                        </div>


                                        <div class="card-body">
                                            <a href="{{route('client.exportCommande')}}">Télécharger la liste des commandes</a>
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
                                                            <th>Numéro</th>
                                                            <th>Date commande</th>
                                                            <th>Statut</th>

                                                            <th>Total</th>
                                                            <th>Déjà enlevé</th>
                                                            <th>Reste à enlever</th>

                                                            <th></th>


                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($commandes as $commande)
                                                        <tr>
                                                            {{-- Voir --}}
                                                            <td>
                                                                <a href="{{route($commande->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit' ,$commande->id)}}">
                                                                    <span style="cursor:pointer;"><i class="fa-solid fa-eye"></i></span>
                                                                </a>
                                                            </td>

                                                            {{-- numero --}}
                                                            <td>
                                                                <a href="{{route($commande->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit' ,$commande->id)}}">{{$commande->numero}}<br>  <span class="text-muted"> ({{$commande->produits->count()}} produit{{($commande->produits->count() > 1) ? 's' :''}}) </span> </a>
                                                            </td>

                                                            {{-- date --}}
                                                            <td>{{$commande->created_at->isoFormat('LL') .' à '. $commande->created_at->format('H:i')}}</td>

                                                            {{-- statut --}}
                                                            <td>
                                                                @switch($commande->etat_commande)
                                                                    @case('EN ATTENTE')
                                                                    <span class="badge bg-secondary">{{$commande->etat_commande}}</span>
                                                                        @break
                                                                    @case('EN TRAITEMENT')
                                                                    <span class="badge bg-warning">{{$commande->etat_commande}}</span>
                                                                        @break
                                                                    @case('TERMINEE')
                                                                    <span class="badge bg-success">{{$commande->etat_commande}}</span>
                                                                        @break

                                                                    @default

                                                                @endswitch

                                                            </td>

                                                            {{-- total (net depuis les lignes : cf. Commande::montantAPayer) --}}
                                                            <td>
                                                                {{number_format($commande->montantAPayer(),0,'',' ')}} fcfa
                                                            </td>

                                                            {{-- total env --}}
                                                            <td>
                                                                @if (Help::totalEnleveSurCommande($commande) == 0)
                                                                    {{number_format(Help::totalEnleveSurCommande($commande),'0','',' ')}} fcfa
                                                                @else
                                                                    {{number_format(Help::totalEnleveSurCommande($commande),'0','',' ')}} fcfa
                                                                @endif

                                                            </td>

                                                            {{-- difference --}}
                                                            <td>
                                                                {{number_format($commande->montantAPayer() - Help::totalEnleveSurCommande($commande),'0','',' ')}} fcfa
                                                            </td>

                                                            <td>
                                                                <a href="{{route('client.listeFacture',$commande)}}" class="btn-small d-block">Facture</a>
                                                                @if ($commande->etat_commande !== 'ANNULEE' && $commande->etat_commande !== 'TERMINEE')
                                                                    <a href="{{ route('client.demandeAnnulationCommande', $commande->numero) }}"
                                                                       class="btn-small d-block text-danger">Annuler</a>
                                                                @elseif ($commande->etat_commande === 'ANNULEE')
                                                                    <span class="badge bg-danger">Annulée</span>
                                                                @endif
                                                            </td>


                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- DEVIS --}}
                                <div class="tab-pane fade" id="devis" role="tabpanel" aria-labelledby="devis-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Mes devis</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table" id="listeDevis">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Statut</th>
                                                            <th>Enregistré le</th>
                                                            <th>Total</th>
                                                            <th></th>
                                                            <th></th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($devis as $devis)
                                                        <tr>
                                                            <td>
                                                                {{$devis->numero}} <span class="text-muted"> ({{$devis->detailDevis->where('deleted_at',null)->count()}} produit{{($devis->detailDevis->where('deleted_at',null)->count() > 1) ? 's' :''}}) </span>
                                                                @if(!empty($devis->libelle))
                                                                    <br><small class="text-muted"><i class="fi-rs-label"></i> {{ $devis->libelle }}</small>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @switch($devis->statut)
                                                                    @case (1)
                                                                    <span class="badge bg-secondary">En attente</span>
                                                                        @break
                                                                    @case(2)
                                                                    <span class="badge bg-success">Passé en commande</span>
                                                                        @break
                                                                    @default

                                                                @endswitch

                                                            </td>
                                                            <td>
                                                                {{$devis->created_at->isoFormat('LL') .' à '.$devis->created_at->format('H:i')}}
                                                            </td>

                                                            <td>
                                                                {{number_format($devis->montant + $devis->tva + $devis->cout_livraison,0,'','.')}} fcfa
                                                            </td>

                                                            <td>
                                                                {{-- <a href="{{route('devis.recapDevis',$devis)}}" class="btn-small d-block"> {{($devis->statut == 1) ? 'Commander' : ''}} </a> --}}
                                                                <a href="{{route('devis.modePaiement',$devis)}}" class="btn-small d-block"> {{($devis->statut == 1) ? 'Commander' : ''}} </a>
                                                            </td>
                                                            <td>
                                                                <a href="{{route('client.factureDevis',$devis->numero)}}" class="btn-small d-block">Devis</a>
                                                            </td>
                                                            <td>
                                                                @if ($devis->statut == 1)
                                                                    <a href="{{route('devis.editDevis',$devis)}}" class="btn-small d-block">Modifier</a>
                                                                @endif
                                                            </td>

                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- VEHICULE --}}
                                {{-- <div class="tab-pane fade" id="vehicule" role="tabpanel" aria-labelledby="vehicule-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Détails du compte</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-success text-center" style="display: none" id="success"></div>
                                            <div class="alert alert-danger text-center" style="display: none" id="result"></div>
                                            <form method="post" id="formVehicule" action="{{route('client.update')}}">
                                                @csrf
                                                <div class="row">
                                                    <div class="form-group col-md-6">
                                                        <label>type <span class="required"></span></label>
                                                        <select  style="border: 1px solid black" class="form-control" name="type" value="" type="text">
                                                            <option value="">Selectionnez un type...</option>
                                                            @foreach ($types as $type )
                                                                <option value="{{$type->id}}"> {{$type->libelle}} </option>
                                                            @endforeach
                                                        </select>
                                                        <span class="text-danger"  id="type"></span>

                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Marque<span class="required"></span></label>
                                                        <input style="border: 1px solid black" value="" class="form-control" name="marque" />
                                                        <span class="text-danger" id="errorMarque"></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Immatriculation <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" value="" class="form-control" name="matricule" type="text" />
                                                        <span class="text-danger" id="matricule"></span>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Modèle <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" value=" " class="form-control" name="modele" type="text" />
                                                        <span class="text-danger" id="modele"></span>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Capacité <span class="required">*</span></label>
                                                        <input style="border: 1px solid black" class="form-control" value=" " name="capacite" type="number" />
                                                        <span class="text-danger" id="capacite"></span>
                                                    </div>

                                                    <button type="submit" class="btn btn-primary">Enregistrer</button>

                                                    <div class="col-md-12">

                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div> --}}



                                {{-- DEMANDE DE LIVRAISON --}}
                                <div class="tab-pane fade" id="delivery" role="tabpanel" aria-labelledby="delivery-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Vos demandes de livraison</h3>
                                        </div>
                                        <a href="{{route('client.exportDemandeDeLivraison')}}">Téléchargez la liste des demandes de livraison</a>
                                        <a class="btn btn-primary" href="{{route('client.demandeLivraison')}}">Demander une livraison</a>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Produits</th>
                                                            <th>Date de demande</th>
                                                            <th>Statut</th>
                                                            {{-- <th>Détail de la livraison</th> --}}
                                                            <th>montant</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($demandeLivraions as $demande)
                                                        {{-- @dd($demande) --}}

                                                        {{-- @dd($demande) --}}
                                                            {{-- @foreach ($demandes as $livraison ) --}}
                                                                <tr>
                                                                    <td> {{$demande->numero}} <span class="text-muted">  </span> </td>
                                                                    <td>
                                                                        @foreach ($demande->detailLivraison as $detail )
                                                                            {{$detail->nom_produit}} <br>
                                                                        @endforeach
                                                                    </td>
                                                                    <td>{{Carbon::parse($demande->created_at)->format('d-m-Y')}}</td>
                                                                    <td>
                                                                        @switch($demande->etat_commande)
                                                                            @case('EN ATTENTE')
                                                                            <span class="badge bg-secondary">{{$demande->etat_commande}}</span>
                                                                                @break
                                                                            @case('EN TRAITEMENT')
                                                                            <span class="badge bg-warning">{{$demande->etat_commande}}</span>
                                                                                @break
                                                                            @case('TERMINEE')
                                                                            <span class="badge bg-success">{{$demande->etat_commande}}</span>
                                                                                @break

                                                                            @default

                                                                        @endswitch

                                                                    </td>

                                                                    <td>{{number_format($demande->montantTotal,0,'','.')}} fcfa
                                                                        {{-- <small class="text-muted"> (Contient {{$commande->produits->count()}} produit{{($commande->produits->count()>1)? 's' : ''}})  </small> --}}
                                                                    </td>

                                                                    <td><a href="{{route('client.detaiDemandeDeLivraison',$demande)}}" class="btn-small d-block">Détails</a></td>
                                                                </tr>
                                                            {{-- @endforeach --}}
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- DEMANDE DE LOCATION --}}
                                <div class="tab-pane fade" id="location" role="tabpanel" aria-labelledby="location-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Mes demandes de location</h3>
                                            <a href="{{route('client.exportLocation')}}">Télécharger la liste des demandes de location</a>
                                        </div>
                                        @if ($locations->isEmpty())
                                            <H2>Vous n'avez pas de demande de location en cours</H2>
                                        @else
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table" id="liste">
                                                    <thead>
                                                        <tr>
                                                            <th>Numéro</th>
                                                            <th>Produits</th>
                                                            <th>Date de demande</th>
                                                            <th>Statut</th>
                                                            <th>montant</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($locations as $location)
                                                        {{-- @dd($demande) --}}

                                                        {{-- @dd($demande) --}}
                                                            {{-- @foreach ($demandes as $livraison ) --}}

                                                                <tr>
                                                                    <td> {{$location->numero}} <span class="text-muted">  </span> </td>
                                                                    <td>
                                                                        @foreach ($location->detailLocation as $detail )
                                                                            {{$detail->produit->nom}} <br>
                                                                        @endforeach
                                                                    </td>
                                                                    <td>{{Carbon::parse($location->created_at)->format('d-m-Y')}}</td>
                                                                    <td>
                                                                        @switch($location->etat_commande)
                                                                            @case('EN ATTENTE')
                                                                            <span class="badge bg-secondary">{{$location->etat_commande}}</span>
                                                                                @break
                                                                            @case('EN TRAITEMENT')
                                                                            <span class="badge bg-warning">{{$location->etat_commande}}</span>
                                                                                @break
                                                                            @case('TERMINEE')
                                                                            <span class="badge bg-success">{{$location->etat_commande}}</span>
                                                                                @break

                                                                            @default

                                                                        @endswitch

                                                                    </td>

                                                                    <td>{{number_format($location->montant_total,0,'',' ')}} fcfa
                                                                        {{-- <small class="text-muted"> (Contient {{$commande->produits->count()}} produit{{($commande->produits->count()>1)? 's' : ''}})  </small> --}}
                                                                    </td>

                                                                    <td><a href="{{route('client.detailDeLocation',$location)}}" class="btn-small d-block">Détails</a></td>
                                                                </tr>
                                                            {{-- @endforeach --}}
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>


                                {{-- MES PAIEMENTS --}}
                                {{-- <div class="tab-pane fade" id="paiements" role="tabpanel" aria-labelledby="paiements-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h3 class="mb-0">Mes paiements</h3>
                                            <a href="{{route('client.exportLocation')}}">Télécharger la liste de mes paiements</a>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4">
                                                <label for="paiement" class="btn btn-success"  id="button-payer" style="display: none">Payer <br><span class="fw-bold" id="span-payer"></span></label>
                                            </div>
                                            <div class="col-md-5  d-flex justify-content-around">
                                                <div class="form-check">
                                                    <input class="form-check-input" onclick="listePaiement({{2}})" type="radio" name="listeMontant" id="flexRadioDefault1" checked>
                                                    <label class="form-check-label" for="flexRadioDefault1">
                                                        Paiement non soldé
                                                    </label>
                                                </div>

                                                <div class="form-check">
                                                    <input class="form-check-input" onclick="listePaiement({{1}})" type="radio" name="listeMontant" id="flexRadioDefault2">
                                                    <label class="form-check-label" for="flexRadioDefault2">
                                                        Paiement soldé
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <form action="{{route('client.afficherMontant')}}" method="post">
                                            @csrf
                                            <button type="submit" id="paiement" style="display:none"></button>
                                            <div class="card-body">
                                                <div class="table-responsive">
                                                    <table class="table" id="listePaiements">
                                                        <thead>
                                                            <tr>
                                                                <th></th>
                                                                <th>code</th>
                                                                <th>libelle</th>
                                                                <th>Numero commande</th>
                                                                <th>Date commande</th>
                                                                <th>Numero facture</th>
                                                                <th>Date facture</th>
                                                                <th>montant</th>
                                                                <th>Créé le</th>
                                                                <!-- <th>Statut</th> -->
                                                            </tr>
                                                        </thead>
                                                        <tbody id="tbody">
                                                            @foreach($paiements as $paiement)
                                                                <tr>
                                                                    <td>
                                                                        <input type="checkbox" onclick="payer({{$paiement->id}})" value="{{$paiement->id}}" class="form-check-input checkMontant"
                                                                        name="paiements[]" id="{{$paiement->montant_total}}">
                                                                    </td>
                                                                    <td> {{$paiement->code}} </td>
                                                                    <td> {{$paiement->libelle}} </td>
                                                                    <td> {{$paiement->devis->numero}} </td>
                                                                    <td> {{$paiement->devis->created_at->format('d-m-Y à H:i')}} </td>
                                                                    <td></td>
                                                                    <td></td>
                                                                    <td> {{number_format($paiement->montant_total,'0','',' ')}} fcfa </td>
                                                                    <td> {{($paiement->created_at)->format('d-m-Y à H:i')}} </td>
                                                                    <!-- <td> {{($paiement->statut == 1) ? 'Payé' : 'En attente'}} </td> -->
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </form>

                                    </div>
                                </div> --}}


                                {{-- <div class="tab-pane fade" id="address" role="tabpanel" aria-labelledby="address-tab">
                                    <div class="row">
                                        <div class="col-lg-6">
                                            <div class="card mb-3 mb-lg-0">
                                                <div class="card-header">
                                                    <h3 class="mb-0">Billing Address</h3>
                                                </div>
                                                <div class="card-body">
                                                    <address>
                                                        3522 Interstate<br />
                                                        75 Business Spur,<br />
                                                        Sault Ste. <br />Marie, MI 49783
                                                    </address>
                                                    <p>New York</p>
                                                    <a href="#" class="btn-small">Edit</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="card">
                                                <div class="card-header">
                                                    <h5 class="mb-0">Shipping Address</h5>
                                                </div>
                                                <div class="card-body">
                                                    <address>
                                                        4299 Express Lane<br />
                                                        Sarasota, <br />FL 34249 USA <br />Phone: 1.941.227.4444
                                                    </address>
                                                    <p>Sarasota</p>
                                                    <a href="#" class="btn-small">Edit</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> --}}

                                {{-- INFORMATION DU CLIENT  --}}
                                <div class="tab-pane fade" id="account-detail" role="tabpanel" aria-labelledby="account-detail-tab">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5>Détails du compte</h5>
                                        </div>
                                        <div class="card-body">
                                            <form method="post" id="form" action="{{route('client.update')}}">
                                                @csrf
                                                <div class="row">
                                                    @if($client->type_client == "PARTICULIER")
                                                        <div class="form-group col-md-6">
                                                            <label>Nom <span class="required"></span></label>
                                                            <input class="form-control" name="nom" value="{{$client->nom}}" type="text" />
                                                            @error("nom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Prénom<span class="required"></span></label>
                                                            <input value="{{$client->prenom}}" class="form-control" name="prenom" />
                                                            @error("prenom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                    @else
                                                        <div class="form-group col-md-6">
                                                            <label>Raison sociale<span class="required"></span></label>
                                                            <input value="{{$client->nom}}" class="form-control" name="raisonSociale" required />
                                                            @error("prenom")
                                                            <span class="text-danger"> {{$message}}</span>
                                                            @enderror
                                                        </div>
                                                    @endif
                                                    <div class="form-group col-md-12">
                                                        <label>Conctact1 <span class="required">*</span></label>
                                                        <input value="{{$client->contact1}}" class="form-control" name="contact1" type="text" />
                                                        @error("contact1")
                                                           <span class="text-danger"> {{$message}}</span>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Conctact2 <span class="required">*</span></label>
                                                        <input value="{{$client->contact1}}" class="form-control" name="contact2" type="text" />
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Adresse Email  <span class="required">*</span></label>
                                                        <input class="form-control" disabled value="{{$client->user->email}}" name="email" type="email" />
                                                        @error("email")
                                                           <span class="text-danger"> {{$message}}</span>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Ville <span class="required"></span></label>
                                                        <select style="border: solid 1px grey" class="form-control " required name="ville">
                                                            <option value="">Selectionnez une ville...</option>
                                                            @foreach ($villes as $ville)
                                                                <option @selected($ville->id == $client->user->ville_id) value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Adresse   <span class="required">*</span></label>
                                                        <input class="form-control" value="{{$client->user->adresse}}" name="adresse" type="text" />
                                                    </div>
                                                    {{-- ********************* NCC et RCC ***************** --}}
                                                    @if($client->type_client == "ENTREPRISE")
                                                        <div class="form-group col-md-12">
                                                            <span>Registre de commerce</span>
                                                            <input style="border: solid 1px grey;" type="text" id="rccm" value="{{ $client->rccm_clt }}" name="rccm" required placeholder="RCCM" />
                                                        </div>
                                                        <div class="form-group col-md-12">
                                                            <span>N° Compte contribuable</span>
                                                            <input style="border: solid 1px grey;" type="text" id="ncc" value="{{ $client->ncc_clt }}" name="ncc" required placeholder="NCC" />
                                                        </div>
                                                    @endif
                                                    {{-- ********************* FIN ************************ --}}
                                                    <div class="form-group col-md-12">
                                                        <label>Code parrain <span class="required">*</span></label>
                                                        <input {{$client->code_parrain ? 'disabled' : ''}} class="form-control" value="{{$client->code_parrain}}"  name="code" type="text" />
                                                    </div>
                                                    <div class="form-group col-md-12 position-relative">
                                                        <label>Mot de passe actuel <span class="required">*</span></label>
                                                        <input class="form-control" name="password" id="password" type="password" />
                                                        <span class="position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;" id="oeil" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Nouveau mot de passe <span class="required">*</span></label>
                                                        <input  class="form-control" id="pass1" name="newPassword" type="password" />
                                                        <span class="text-danger" id="erreurCourt"></span>
                                                    </div>
                                                    <div class="form-group col-md-12">
                                                        <label>Confirmez le nouveau mot de passe <span class="required">*</span></label>
                                                        <input class="form-control" id="pass2" name="confirmPassword" type="password" />
                                                        <span class="text-danger" id="erreur"></span>
                                                    </div>
                                                    <div class="col-md-12">
                                                        <button type="submit" class="btn btn-fill-out submit font-weight-bold" id="submit" >Sauvegarder les modification</button>
                                                    </div>
                                                    <div class="col-md-12">
                                                        @if (Auth::user()->statut == 1)
                                                            <a class="text-danger" onclick="return confirm('Voulez-vous vraiment supprimer votre compte?')" href="{{route('client.supprimerCompte')}}" class="text-muted"><i class="fi-rs-sign-out mr-10 text-white"></i>Supprimer mon compte</a>
                                                        @else
                                                            <a class="text-danger" onclick="return confirm('Voulez-vous vraiment la demande de suppression ?')" href="{{route('client.supprimerCompte')}}" class="text-muted"><i class="fi-rs-sign-out mr-10 text-white"></i>Annuler la demande de suppression</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

{{-- =================================================================
     PREMIUM STYLES — Compte client (injection CSS sans modification HTML)
     ================================================================= --}}
<style>
    /* ===== PAGE BG ===== */
    main.pages { background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); }

    /* ===== SIDEBAR ===== */
    .dashboard-menu {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 14px 10px;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        position: sticky;
        top: 20px;
    }
    .dashboard-menu .nav { gap: 4px; }
    .dashboard-menu .nav-link {
        display: flex !important;
        align-items: center;
        gap: 10px;
        padding: 11px 14px !important;
        border-radius: 10px !important;
        color: #4b5563 !important;
        font-weight: 600 !important;
        font-size: 0.9rem !important;
        transition: all 0.15s ease !important;
        background: transparent !important;
        border: 0 !important;
        text-decoration: none !important;
    }
    .dashboard-menu .nav-link i {
        font-size: 16px !important;
        color: #9ca3af;
        transition: color 0.15s ease;
        flex-shrink: 0;
    }
    .dashboard-menu .nav-link:hover {
        background: #f3f4f6 !important;
        color: #1c57a3 !important;
    }
    .dashboard-menu .nav-link:hover i { color: #1c57a3; }
    .dashboard-menu .nav-link.active {
        background: linear-gradient(135deg, #1c57a3, #134380) !important;
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(28,87,163,0.25);
    }
    .dashboard-menu .nav-link.active i { color: #ffffff; }

    .dashboard-menu form .btn {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        width: 100% !important;
        padding: 11px 14px !important;
        margin-top: 10px !important;
        background: #fef2f2 !important;
        border: 1.5px solid #fecaca !important;
        color: #b91c1c !important;
        font-weight: 700 !important;
        font-size: 0.9rem !important;
        border-radius: 10px !important;
        transition: all 0.15s ease !important;
    }
    .dashboard-menu form .btn:hover {
        background: #ef4444 !important;
        border-color: #ef4444 !important;
        color: #ffffff !important;
        box-shadow: 0 6px 14px rgba(239,68,68,0.30);
    }
    .dashboard-menu form .btn::before {
        content: "↪";
        font-size: 1.1rem;
    }

    /* ===== CARDS DES ONGLETS ===== */
    .dashboard-content .card {
        background: #ffffff;
        border: 1px solid #e5e7eb !important;
        border-radius: 16px !important;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        margin-bottom: 24px;
    }
    .dashboard-content .card-header {
        padding: 18px 22px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        background: linear-gradient(to right, #f8fafc, #ffffff) !important;
    }
    .dashboard-content .card-header h3,
    .dashboard-content .card-header h4 {
        color: #0a2540 !important;
        font-weight: 700 !important;
        font-size: 1.15rem !important;
        margin: 0;
    }
    .dashboard-content .card-header h3 + p { color: #6b7280; font-size: 0.92rem; margin-top: 4px; }
    .dashboard-content .card-body { padding: 22px !important; }

    /* Welcome dashboard inner highlight */
    #dashboard .card .container h3.bg-primary {
        background: linear-gradient(135deg, #1c57a3, #134380) !important;
        padding: 14px 20px !important;
        border-radius: 12px !important;
        margin: 0 22px 18px !important;
        font-size: 1.1rem !important;
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(28,87,163,0.25);
    }

    /* ===== TABLES ===== */
    .dashboard-content table.table thead tr,
    .dashboard-content table.table thead th {
        background: #f9fafb !important;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.78rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1px solid #e5e7eb !important;
        border-top: 0 !important;
        padding: 12px 10px !important;
    }
    .dashboard-content table.table tbody td {
        padding: 14px 10px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
        font-size: 0.88rem;
    }
    .dashboard-content table.table tbody tr:hover { background: #fafbfc; }

    /* Liens de download/export */
    .dashboard-content .card-body > a[href*="export"] {
        display: inline-flex !important;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        background: #ecfdf5;
        border: 1px solid #d1fae5;
        color: #065f46 !important;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 10px;
        text-decoration: none;
        transition: all 0.15s ease;
        margin-bottom: 14px;
    }
    .dashboard-content .card-body > a[href*="export"]:hover {
        background: #10b981;
        color: #ffffff !important;
        border-color: #10b981;
    }
    .dashboard-content .card-body > a[href*="export"]::before {
        content: "⬇";
        font-size: 14px;
    }

    /* ===== INPUTS ===== */
    .dashboard-content input.form-control,
    .dashboard-content select.form-control,
    .dashboard-content textarea.form-control {
        padding: 11px 14px !important;
        border: 1.5px solid #e5e7eb !important;
        border-radius: 10px !important;
        background: #ffffff !important;
        font-size: 0.92rem !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        height: auto !important;
    }
    .dashboard-content input.form-control:focus,
    .dashboard-content select.form-control:focus,
    .dashboard-content textarea.form-control:focus {
        border-color: #ea580c !important;
        box-shadow: 0 0 0 3px rgba(234,88,12,0.12) !important;
        outline: none !important;
    }

    /* ===== BOUTONS principaux ===== */
    .dashboard-content button[type="submit"]:not(.dashboard-menu *),
    .dashboard-content .btn-primary {
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%) !important;
        border: 0 !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        padding: 11px 22px !important;
        border-radius: 10px !important;
        box-shadow: 0 8px 18px rgba(234,88,12,0.30) !important;
        transition: all 0.18s ease !important;
    }
    .dashboard-content button[type="submit"]:not(.dashboard-menu *):hover,
    .dashboard-content .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(234,88,12,0.42) !important;
    }

    /* Badges existants */
    .dashboard-content .badge.bg-secondary { background: #f3f4f6 !important; color: #4b5563 !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-warning { background: #fef3c7 !important; color: #92400e !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-primary { background: #dbeafe !important; color: #1e40af !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-success { background: #d1fae5 !important; color: #065f46 !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }
    .dashboard-content .badge.bg-danger { background: #fee2e2 !important; color: #991b1b !important; font-weight: 600; padding: 5px 12px; border-radius: 999px; }

    /* ===== POINTS HIGHLIGHT (dashboard) ===== */
    #dashboard p.fw-bold span.text-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        color: #ffffff !important;
        border-radius: 999px;
        font-weight: 800;
        margin: 0 4px;
        box-shadow: 0 4px 10px rgba(251,191,36,0.30);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 767px) {
        .dashboard-menu { position: static; }
    }
</style>

@section('jspart')
    <script>

        let form = document.getElementById('form');
        form.addEventListener('submit', function(e) {
            let pass1 = document.getElementById('pass1')
            let pass2 = document.getElementById('pass2')
            let erreur = document.getElementById('erreur')
            let erreurCourt = document.getElementById('erreurCourt')


            // alert(password.value.length)

            if(pass1>0){
                if (pass1.value.length < 4) {
                    // alert(pass1.value)
                    erreurCourt.innerHTML = "Le mot de passe doit être au moins 4 caractères";
                    e.preventDefault();
                } else if (pass1.value.trim() != pass2.value.trim()) {
                    erreurCourt.innerHTML = "";
                    erreur.innerHTML = "Les mots de passe ne correspondent pas";
                    e.preventDefault();
                }
            }
        })
    </script>





    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>


    <script type="text/javascript">
        $(function() {
            var $table = $('#listeDevis').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listeLivraison').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listePaiements').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script type="text/javascript">
        $(function() {
            var $table = $('#listeLocation').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

    <script>
        function listePaiement(paye) {
            fetch('/liste-des-paiements-'+paye, {
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

                $('#tbody').html('');
                let checkbox = '';
                data.forEach(function(item) {
                    if (item.statut == 2) {
                        checkbox = `
                            <input type="checkbox" onclick="payer(${item.id})" value="${item.id}" class="form-check-input checkMontant"
                            name="paiements[]" id="${item.montant_total}">
                        `;
                    }

                    $('#tbody').append(`
                    <tr>
                        <td>
                            ${checkbox}
                        </td>
                        <td> ${item.code} </td>
                        <td> ${item.libelle} </td>
                        <td> ${formatNumber(item.montant_total)} fcfa </td>
                        <td> ${formatDate(item.created_at)} </td>

                    </tr>
                    `);
                });

            })

            .catch(error => console.error('Erreur de mise à jour de la localisation:', error));

        }

        function formatNumber(number) {
            return number.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
        function formatDate(dateString) {
            // Créer un objet Date à partir de la chaîne de caractères
            const date = new Date(dateString);

            // Extraire les composants de la date
            const day = String(date.getDate()).padStart(2, '0'); // Jour (avec un zéro devant si nécessaire)
            const month = String(date.getMonth() + 1).padStart(2, '0'); // Mois (les mois commencent à 0, donc +1)
            const year = date.getFullYear(); // Année
            const hours = String(date.getHours()).padStart(2, '0'); // Heures
            const minutes = String(date.getMinutes()).padStart(2, '0'); // Minutes

            // Retourner la date formatée
            return `${day}-${month}-${year} à ${hours}:${minutes}`;
        }

        function payer(id) {


            let bouton = document.getElementById('button-payer');
            // let button = document.getElementById('bu-payer');

            console.log(id)

            let checkboxes = document.querySelectorAll('.checkMontant:checked');

            console.log(checkboxes)
            let total = 0;

            checkboxes.forEach(function(checkbox) {
                total += parseFloat(checkbox.id);
            });

            if(total <= 0 ){
            bouton.style.display = 'none';
            }else{
                bouton.style.display = 'block';
            }

            document.getElementById('span-payer').textContent = formatNumber(total)+' fcfa';
            // Afficher des messages dans la console si nécessaire ou traiter les données


        }
    </script>

@endsection

@endsection
