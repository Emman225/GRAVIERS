@extends('layout.main')
@section('title','Liste des bons en attente')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Les bons d'enlèvement en attente</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">
                @if (!$enlevements->isEmpty())
                    <!-- card-header end// -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Code du bon</th>
                                        <th>Client</th>
                                        <th>Nom du Fournisseur</th>
                                        <th>livreur</th>
                                        <th>Produit</th>
                                        <th class="text-center">Quantité</th>
                                        <th class="text-center">Agent ayant créé l'enlèvement</th>
                                        <th>Date</th>

                                        {{-- <th class="text-end">Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @foreach ($enlevements as $enlevement)
                                        <tr>
                                            <td><input type="radio" value="{{$enlevement->id}}" class="form-control"></td>
                                            <td> {{ $enlevement->code_enleve }} </td>
                                            <td> {{ $enlevement->livraison->client->nom.' '.$enlevement->livraison->client->prenom }} </td>
                                            <td><b> {{ $enlevement->fournisseur->nom.' '.$enlevement->fournisseur->prenom }} </b></td>
                                            <td><b> {{ $enlevement->livreur->nom.' '.$enlevement->livreur->prenom }} </b></td>
                                            <td>{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->livraison->user?->nom_prenoms }}</td>
                                            <td>{{ $enlevement->created_at->format('d-m-Y')}} @if($enlevement->fournisseur_validation != null) <span class="text-success"> (Fournisseur) @elseif($enlevement->livreur_validation != null) <span class="text-success"> (Livreur) </span> @endif</span> </td>
                                            {{-- <td class="text-end">
                                        <a href="" class="btn btn-md rounded font-sm">Detail</a>
                                    </td> --}}
                                        </tr>
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>
                    <!-- card-body end// -->
                @else
                    <h1>Aucun bon pour l'instant</h1>
                @endif
            </div>
            <!-- card end// -->
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order:[],
            });
        });
    </script>
@endsection
