@php
    use Illuminate\Support\Carbon;

    // dd($commande->paiements);
@endphp
@extends('client.main')
@section('title','Paiement de commande')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info conatiner.fluid text-center" id="notify">
            {{ session('ok') }}
        </div>
    @endif
    @if(session('errorQte'))
    <div class="alert alert-danger text-center" id="notify"> {{session('errorQte')}} </div>
@endif
    @if(session('livree'))
    <div class="alert alert-success text-center" id="notify"> {{session('livree')}} </div>
@endif
    <main class="main">
        @include('client.navMobile')
        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h3 class="heading-2 mb-10">Liste des paiements </h3>
                    <div class="d-flex justify-content-between">
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-lg-11">


                    <div class="card-body">

                        <div class="table-responsive">
                            @if ($client->client_a_terme == 0)
                            {{-- @dd('non') --}}

                                @if (!$paiements->isEmpty())
                                    <table class="table" id="liste">
                                        <thead>
                                            <tr>
                                                <th>Code de paiement</th>
                                                <th>Moyen de paiement</th>
                                                <th>Numero commande</th>
                                                <th>date commande</th>
                                                <th>Montant</th>
                                                {{-- <th>Agent de reception</th> --}}
                                                <th>Date de paiement</th>
                                                <th></th>
                                                <th></th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($paiements as $paiement)

                                            {{-- @dump($paiement->toSql()) --}}
                                            <tr>
                                                <td> <a href="">{{$paiement->code}} </a></td>
                                                <td> {{$paiement->lignePaiements->first()->moyen_paiement}} </td>
                                                <td> {{$paiement->commande->numero}} </td>
                                                <td> {{$paiement->commande->created_at}} </td>
                                                <td>
                                                    {{number_format($paiement->lignePaiements->first()->montant,0,'',' ')}} fcfa
                                                </td>

                                                <td>{{Carbon::parse($paiement->created_at)->format('d-m-Y à H:i:s')}}</td>

                                                <td>
                                                    <a href="{{route('paye.facture',['reference' => $paiement->lignePaiements->first()->reference, 'action' => 'voir'])}}" class="btn">Voir le reçu</a>
                                                </td>
                                                <td>
                                                    <a href="{{route('paye.facture',['reference' => $paiement->lignePaiements->first()->reference, 'action' => 'telecharger'])}}" class="btn">Télécharger le reçu</a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <h4>Pas de paiement pour l'instant</h4>
                                @endif
                            @else
                            {{-- @dd('oui') --}}

                                @if (!$commandes->isEmpty())
                                    <table class="table" id="liste">
                                        <thead>
                                            <tr>
                                                <th></th>
                                                <th>Numero facture</th>
                                                <th>Date facture</th>
                                                <th>Montant facture</th>
                                                <th>Numero commande</th>
                                                <th>Montant commande</th>
                                                <th>Date commande</th>


                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($commandes as $commande)
                                                @foreach ($commande->factures->where('statut',2) as $facture )
                                                    <tr>
                                                        <td> <input type="checkbox" onclick="payer({{$facture->id}})" value="{{$facture->id}}" class="form-check-input checkMontant"
                                                            name="factures[]" id="">
                                                        </td>
                                                        <td> <a href="">{{$facture->numero}} </a></td>
                                                        <td> {{$facture->created_at->format('d-m-Y')}} </td>
                                                        <td> {{number_format($facture->montant,'0','',' ')}} fcfa </td>
                                                        <td> {{$commande->numero}} </td>
                                                        <td> {{number_format($commande->montant_total,'0','',' ')}} fcfa </td>
                                                        <td>
                                                            {{$commande->created_at->format('d-m-Y')}}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <h4>Pas de paiement pour l'instant deh</h4>
                                @endif

                            @endif
                        </div>
                    </div>


                            <div class="divider-2 mb-30"></div>
                            <div class="cart-action d-flex justify-content-between">
                                <a class="btn" href="{{ route('client.monCompte') }}">Retour</a>
                            </div>
                            </form>
                        </div>
                        <div class="col-lg-4">

                </div>
            </div>
        </div>
    </main>
    @endsection

