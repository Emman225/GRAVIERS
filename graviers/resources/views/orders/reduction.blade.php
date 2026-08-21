@extends('layout.main')
@section('title','Réduction')

@section('contenu')

<div class="screen-overlay"></div>

<div class="row">
    <div class="col-9">
        <div class="content-header">
            <h3 class="content-title">
              Appliquer une réduction
            </h3>
            <div>
                {{-- <button class="btn btn-light rounded font-sm mr-5 text-body hover-up">Save to draft</button>
                <button class="btn btn-md rounded font-sm hover-up">Publich</button> --}}
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4"> 
            <div class="card-header">
                <h4>Commande N°{{$commande->numero}} </h4> <br>
                <h4>Montant HT actuel : <b class="fw-bold"> {{number_format($commande->montantHT(),'0','',' ')}}fcfa </b></h4>
            </div>

            @if(Auth::user()->id == $conf->gestionnaire2_id)
                <div class="container">
                    {{-- La réduction est cherchée par le contrôleur : elle se rattache
                         désormais à la COMMANDE, et plus seulement au devis, qui
                         n'existe pas sur une vente comptant ni sur une commande venue
                         de l'application. --}}
                    @if ($reduction)
                        @php
                            $montantRemise = $commande->montantHT() * $reduction->taux_reduction / 100;
                            $nouveauMontant = $commande->montantHT() - $montantRemise;
                        @endphp
                        <form action="{{route('orders.confirmationReductionTraitement', $commande)}}" method="post">
                            @csrf
                            <h2>Demandeur de réduction: <i class="text-success"> {{ $reduction->user?->nom_prenoms ?: '—' }} </i></h2>

                            <h2>Pourcentage de réduction: <i class="text-success"> {{ $reduction->taux_reduction }}%  </i></h2>
                            {{-- « Nouveau montant » affichait le MONTANT DE LA REMISE :
                                 sur 796 205 fcfa à 10 %, le gestionnaire lisait
                                 « Nouveau montant : 79 620 fcfa » et croyait valider une
                                 commande ramenée à ce prix-là. Les deux lignes sont
                                 désormais distinctes. --}}
                            <h2>Montant de la remise: <i class="text-success"> {{ number_format($montantRemise,0,'',' ') }}fcfa </i></h2>
                            <h2>Nouveau montant HT: <i class="text-success"> {{ number_format($nouveauMontant,0,'',' ') }}fcfa </i></h2>
                            <hr>
                            <button type="submit"  class="btn btn-success mb-3"> Je confirme la réduction </button>
                            <span></span>
                        </form>
                    @else
                        <h2>Pas de demande de réduction</h2>
                    @endif
                </div>
            @elseif ($reduction)
                {{-- Une demande existe déjà. Rien ne le disait : la page réaffichait
                     le formulaire vierge, si bien qu'on ignorait qu'une réduction
                     avait été saisie, qu'elle attendait une seconde signature — et
                     qu'on pouvait en empiler une deuxième par mégarde. --}}
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading mb-2">Réduction de {{ $reduction->taux_reduction }}% en attente de validation</h5>
                        <p class="mb-1">
                            Demandée par <b>{{ $reduction->user?->nom_prenoms ?: 'un administrateur' }}</b>
                            le {{ \Carbon\Carbon::parse($reduction->created_at)->format('d/m/Y à H:i') }}.
                        </p>
                        <p class="mb-1">
                            Montant de la remise :
                            <b>{{ number_format($commande->montantHT() * $reduction->taux_reduction / 100, 0, '', ' ') }} fcfa</b>
                            — nouveau montant HT :
                            <b>{{ number_format($commande->montantHT() - $commande->montantHT() * $reduction->taux_reduction / 100, 0, '', ' ') }} fcfa</b>.
                        </p>
                        <hr>
                        @if ($conf?->gestionnaire2_id)
                            <p class="mb-0">
                                Elle ne s'appliquera à la commande <b>qu'après validation</b> par le trésorier
                                <b>{{ $conf->gestionnaire2?->nom_prenoms ?: 'désigné' }}</b>, qui la retrouvera
                                dans « Commandes en attente ». Le montant dû par le client reste inchangé jusque-là.
                            </p>
                        @else
                            {{-- Sans second gestionnaire configuré, PERSONNE ne peut valider :
                                 la demande resterait en attente indéfiniment, sans que rien
                                 ne le signale. --}}
                            <p class="mb-0 text-danger">
                                <b>Aucun trésorier n'est configuré</b> (second gestionnaire).
                                Tant que ce n'est pas fait dans les paramètres, cette réduction
                                ne pourra être validée par personne.
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <form  method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                            @if(session('succes'))
                                <div class="alert alert-success text-center">
                                    {{session('succes')}}
                                </div>
                            @endif

                            @unless ($conf?->gestionnaire2_id)
                                <div class="alert alert-danger">
                                    <b>Aucun trésorier n'est configuré</b> (second gestionnaire).
                                    Une réduction saisie maintenant ne pourra être validée par
                                    personne, et ne s'appliquera donc jamais.
                                </div>
                            @endunless

                            <div class="alert alert-info">
                                La réduction n'est pas appliquée immédiatement : elle part en
                                validation chez le trésorier{{ $conf?->gestionnaire2?->nom_prenoms ? ' (' . $conf->gestionnaire2->nom_prenoms . ')' : '' }},
                                et le montant dû par le client ne change qu'à ce moment-là.
                            </div>

                            <div class="mb-4">
                                <label for=""> en %</label>
                                <input type="number" min="1" max="100"  class="form-control" placeholder="Veuillez entrer le pourcentage de réduction" name="remise">
                            </div>

                            <div class="mb-4 d-flex align-center">
                                <button  type="submit" class=" d-flex w-50 btn btn-primary bg-success" style="margin:auto">
                                    Initialiser la réduction
                                </button>
                            </div>
                        </div>
                </form>
            @endif
        </div>
        <!-- card end// -->

    </div>


</div>

@endsection
