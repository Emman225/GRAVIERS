@php
    use Illuminate\Support\carbon;
@endphp
@extends('layout.main')
@section('title','Livraison et réapprovisionnement')
@section('contenu')

<div class="content-header">
    <h2 class="content-title"> - Etat de livraison et réapprovisionnnement - </h2>
</div>
<div class="card mb-4">
    <header class="card-header">
        <div class="row gx-3">
            <div class="col-md-12 me-auto d-flex">
                <div class="h3 me-5 fw-bold text-success">T. Qté enlevée : {{$enlevements->sum('qte')}} </div>
                <div class="h3 ms-5 fw-bold text-success">T. Qté livrée: {{$enlevements->sum('qte')}}</div>
            </div>
        </div>
    </header>
    <!-- card-header end// -->
    <div class="card-body">
        <div class="table-responsive">

            <table class="table table-striped" id="liste">
                <thead>
                    <tr>
                        <th class="text-center">Date</th>
                        <th class="text-center">N° commande</th>
                        <th class="text-center">N° BE</th>
                        <th class="text-center">Client</th>
                        <th class="text-center">N° Compte client</th>
                        <th class="text-center">Livreur</th>
                        <th class="text-center">N° compte livreur</th>
                        <th class="text-center">Fournisseur</th>
                        <th class="text-center">N° compte fournisseur</th>
                        <th class="text-center">Qté enlevée</th>
                        <th class="text-center">Qté livrée</th>
                        {{-- <th class="text-end">Action</th> --}}
                    </tr>
                </thead>

                <tbody>
                    @foreach ($enlevements as $enlevement )
                        <tr>
                            <td class="text-center">
                                <div class="info pl-3">
                                    <h6 class="mb-0 title">{{ Carbon::parse($enlevement->created_at)->format('d-m-Y'); }}</h6>
                                    {{-- <small class="text-muted">Login du fournisseur: {{$frs->user->login}} </small> --}}
                                </div>
                            </td>
                            <td class="text-center"><span>{{$enlevement->livraison->detailCommande->commande?->numero}}</span></td>
                            <td class="text-center">{{$enlevement->code_enleve}}</td>
                            <td class="text-center">
                                {{$enlevement->livraison->client->nom.' '.$enlevement->livraison->client->prenom}}
                            </td>
                            <td class="text-center">{{$enlevement->livraison->client->user_id}}</td>
                            <td class="text-center">{{$enlevement->livreur?->user->nom_prenoms}}</td>
                            <td class="text-center">{{$enlevement->livreur?->user->id}}</td>
                            <td class="text-center">{{$enlevement->fournisseur?->nom_prenoms}}</td>
                            <td class="text-center">{{$enlevement->fournisseur?->user->id}}</td>
                            <td class="text-center"> {{$enlevement->qte}} </td>
                            <td class="text-center">{{$enlevement->qte_servi}}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="container">
                <div class="row">


                </div>
            </div>
            <!-- table-responsive.// -->
        </div>
    </div>
    <!-- card-body end// -->
</div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
            });
        });
    </script>
@endsection
