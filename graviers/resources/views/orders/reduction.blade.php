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
                <h4>Montant actuelle: <b class="fw-bold"> {{number_format($commande->montant_total,'0','',' ')}}fcfa </b></h4>
            </div>

            @if(Auth::user()->id == $conf->gestionnaire2_id)
                <div class="container">
                    @if ( $commande->devis->reduction && $commande->devis->reduction->est_utilise == false)
                        <form action="{{route('orders.confirmationReductionTraitement', $commande)}}" method="post">
                            @csrf
                            <h2>Demandeur de réduction: <i class="text-success"> {{ $commande->devis->reduction->user->nom_prenoms }} </i></h2>

                            <h2>Pourcentage de réduction: <i class="text-success"> {{ $commande->devis->reduction->taux_reduction }}%  </i></h2>
                            <h2>Nouveau montant: <i class="text-success"> {{ number_format($commande->montant_total * $commande->devis->reduction->taux_reduction/100,0,'',' ') }}fcfa </i></h2>
                            <hr>
                            <button type="submit"  class="btn btn-success mb-3"> Je confirme la réduction </a>
                            <span></span>
                        </form>
                    @else
                        <h2>Pas de demande de réduction</h2>
                    @endif
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

                            <div class="mb-4">
                                <label for=""> en %</label>
                                <input type="number" min="1" max="100"  class="form-control" placeholder="Veuillez entrer le pourcentage de réduction" name="remise">
                            </div>

                            <div class="mb-4 d-flex align-center">
                                <button  type="submit" class=" d-flex w-50 btn btn-primary bg-success" style="margin:auto">
                                    Initialialiser la réduction
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
