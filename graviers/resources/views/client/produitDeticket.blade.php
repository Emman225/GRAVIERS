@php
    use Illuminate\Support\Carbon;
@endphp

@extends('client.main')
@section('title', 'Ticket de produit')
@section('content')
@include('client.navMobile')
    <main class="main">
        @if (session('success'))
            <div class="alert alert-success text-center" id="notify">
                {{session('success')}}
            </div>
        @endif
        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{ route('client.index') }}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> Boutique
                    <span></span> Adresse
                </div>
            </div>
        </div>

        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Ticket de service après vente SAV</h1>
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('client.mesTicketsSAV') }}" class="btn btn-sm"><i class="fi-rs-time-past mr-5"></i> Voir mes tickets SAV &amp; leur suivi</a>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <div class="row mb-50">

                        <div class="col-lg-6">

                        </div>
                    </div>
                    <div class="row">
                        <div class="container">
                            {{-- <h4 class="text-danger">A savoir</h4> --}}
                            <h4> Vous avez un soucis avec l'un de nos produit ? </h4>
                            <p>Veuillez selectionner le produit en question pour générer un ticket SAV </p>
                            <p>Un de nos agent vous prendra en charge sous peu de temps</p>
                            <hr>

                        </div>
                            <div class="row shipping_calculator">
                                <div class="table-responsive">
                                    <table id="liste">
                                        <thead>
                                            <tr style="background-color: #1c57a3; color:white">
                                                <th class="text-center">N°</th>
                                                <th class="text-center" style="border-top-right-radius: 5px">Produit</th>
                                                <th class="text-center"style="border-top-right-radius: 5px">Quantité commandée</th>
                                                <th class="text-center">numero commande</th>
                                                <th class="text-center">Date de commande</th>
                                                <th class="text-center">Statut</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $i = 1;
                                            @endphp
                                            @foreach ($commandes as $commande )
                                                @foreach ($commande->detailCommande as $detail )
                                                @if ($detail->statut == 2)
                                                    <tr>
                                                        <td class="text-center"> {{$i}} </td>
                                                        <td class="text-center">{{$detail->produit->nom}}</td>
                                                        <td class="text-center">{{$detail->qte}}</td>
                                                        <td class="text-center">{{$commande->numero}}</td>
                                                        <td class="text-center">


                                                                {{Carbon::parse($detail->updated_at)->format('d-m-Y à H:i')}}


                                                        </td>
                                                        <td class="text-center">
                                                            @if($detail->ticket)
                                                                @switch($detail->ticket->statut)
                                                                    @case(1)
                                                                        <span class="badge bg-secondary"> En attente de traitement </span>
                                                                        @break
                                                                    @case(2)
                                                                        <span class="badge bg-success">Approuvé</span>
                                                                        @break
                                                                    @case(3)
                                                                        <span class="badge bg-danger"> Réfusé </span>
                                                                        @break
                                                                    @default
                                                                @endswitch
                                                            @else
                                                            <i>Pas de demande en cour</i>
                                                            @endif
                                                        </td>

                                                        <td class="text-center">
                                                            @if(!$detail->retour)
                                                                <a href="{{route('client.infoTicketSAV',$detail)}}" class="btn btn-sm" >Générer un ticket SAV</a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif
                                                @php $i++ @endphp
                                                @endforeach

                                            @endforeach

                                        </tbody>
                                    </table>
                                </div>

                                {{-- <input required type="text" name="objet" class="form-control mb-5"  placeholder="Objet">
                                <textarea required name="description" id="" cols="30" class="mt-5" placeholder="Décrivez en quelques mot vos raisons de vouloir être un client à terme" rows="10"></textarea> --}}

                                {{-- <button type="submit" class="btn btn-fill-out btn-block mt-30">Envoyer la demande</button> --}}
                                {{-- Ce bouton s'affiche si le client veut passer une commande --}}

                            </div>
                        {{-- </form> --}}
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
@section('jspart')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>



@endsection


