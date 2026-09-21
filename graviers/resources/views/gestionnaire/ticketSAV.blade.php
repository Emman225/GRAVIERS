@php
    use Illuminate\Support\carbon;
@endphp


@extends('layout.main')
@section('title','Tickets SAV')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Tickets SAV </h2>
        {{-- <div>
            <a href="{{ route('sellers.register') }}" class="btn btn-primary"><i class="material-icons md-plus"></i> Ajouter nouveau</a>
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
            <x-export-buttons table-id="liste"
                              filename="tickets-sav"
                              title="Tickets SAV" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    {{-- @dd($founisseurs) --}}
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">N° Commande</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Produit</th> {{--  --}}
                            {{-- Ces deux intitulés étaient ceux de la liste des RETOURS, dont
                                 cette page est la copie : un ticket n'a ni « date de retour »
                                 ni « motif », il porte une demande et un message. --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Demandé le</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Message</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Confié à</th>
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

                                <td class="text-center fw_bold"> {{Carbon::parse($ticket->created_at)->format('d/m/Y à H:i:s')}} </td>
                                <td class="text-center"> {{$ticket->message}} </td>

                                {{-- À qui le ticket est confié : la liste ne le disait nulle
                                     part. Passé l'assignation, elle n'affichait qu'un badge
                                     « En traitement », et plus personne ne savait qui l'avait. --}}
                                <td class="text-center">
                                    @if ($ticket->user_id && $ticket->agent->nom_prenoms)
                                        {{ $ticket->agent->nom_prenoms }}
                                        @if ((int) $ticket->user_id === (int) Auth::id())
                                            <br><small class="text-success">c'est vous</small>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-nowrap text-center">
                                    {{-- La colonne s'intitule « Action » mais n'en proposait
                                         plus aucune dès le ticket assigné : un simple badge, et
                                         la personne qui l'avait en charge n'avait aucun moyen
                                         de l'ouvrir depuis cette page. Elle devait deviner
                                         qu'il fallait passer par « Mes tickets SAV ». --}}
                                    @switch($ticket->statut)
                                        @case(1)
                                            <a href="{{route('show.ticketSAVTraitement',$ticket)}}" class="btn btn-primary btn-sm" title="Confier le ticket"><i class="material-icons md-person_add"></i></a>
                                            @break
                                        @case(2)
                                            @if ((int) $ticket->user_id === (int) Auth::id())
                                                <a href="{{ route('show.traiterTicketSAVPage', $ticket) }}" class="btn btn-success btn-sm" title="Traiter"><i class="material-icons md-play_arrow"></i></a>
                                            @else
                                                <span class="badge badge-warning bg-warning d-block mb-1">En traitement</span>
                                                <a href="{{route('show.ticketSAVTraitement',$ticket)}}" class="btn btn-primary btn-sm" title="Confier à quelqu'un d'autre"><i class="material-icons md-person_add"></i></a>
                                            @endif
                                            @break
                                        @case(3)
                                            <span class="badge badge-warning bg-second text-white d-block">Traité</span>
                                            @if ($ticket->solution_trouvee)
                                                <small class="text-muted d-block mt-1">{{ \Illuminate\Support\Str::limit($ticket->solution_trouvee, 80) }}</small>
                                            @endif
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
