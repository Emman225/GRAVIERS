@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Tickets SAV')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Tickets SAV </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter Nouveau</a>
        </div> --}}
    </div>
    @if(session('success'))
        <div class="alert alert-success">
            {{session('success')}}
        </div>
    @endif
    @if(session('no'))
        <div class="alert alert-info">
            {{session('no')}}
        </div>
    @endif
    <div class="card mb-4">
        <header class="card-header">
            {{-- <div class="row gx-3">
                <div style="width:100%" class="col-lg-4 col-md-6 me-auto">
                    <p class="d-flex justify-content-between" >
                        <span class="text-success h4">montant de la fature : 0 fcfa</span>
                        <span class="text-success h4">Montant reglé : 0 fcfa</span>
                        <span class="text-success h4">SOLDE : 0 fcfa</span>
                    </p>
                </div>
            </div> --}}
        </header>
        <!-- card-header end// -->
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">N° Commande</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Produit</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Date de retour</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Motif</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> {{--  --}}
                            {{-- <th class="text-center">Montant reglé</th>
                            <th class="text-center">SOLDE </th>
                            <th class="text-center">Date Ech </th>
                            <th class="text-center">Date EXO </th>
                            <th class="text-center">Échéance </th>
                            <th class="text-center">Age </th>
                            <th class="text-center">Ageing1 </th>
                            <th class="text-center">Ageing2 </th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket )
                            <tr>
                                <td class="text-center" > {{$ticket->client?->nom}} </td>

                                <td class="text-center"> {{$ticket->detailCommande?->commande?->numero ?? '-'}} </td>

                                <td class="text-center"> {{$ticket->detailCommande?->produit?->nom ?? '-'}} </td>

                                <td class="text-center fw_bold"> {{Carbon::parse($ticket->created_at)->format('d-m-Y à H:i')}} </td>
                                <td class="text-center"> {{$ticket->message}} </td>
                                <td class="text-center">

                                    @switch($ticket->statut)
                                        @case(1)
                                            <a href="{{route('show.ticketSAVTraitement',$ticket)}}" class="btn btn-primary"> traiter le retour</a>
                                            @break
                                        @case(2)
                                        <span class="badge badge-warning bg-warning ">En traitement</span>
                                            @break
                                        @case(3)
                                        <span class="badge badge-warning bg-second text-white">Traité</span>
                                            @break

                                            @endswitch
                                </td>
                                {{-- <td class="text-center">

                                </td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td>
                                <td class="text-center"></td> --}}
                            </tr>
                        @endforeach


                    </tbody>
                </table>
                <!-- table-responsive.// -->
            </div>
        </div>
        <!-- card-body end// -->
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
            var $table = $('#liste').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
