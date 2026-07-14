@extends('layout.main')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Bons d'enlèvement en attente</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom du livreur</th>
                                    <th>Produit</th>
                                    <th>Quantité</th>
                                    <th>Date</th>
                                    {{-- <th class="text-end">Action</th> --}}
                                    {{-- <th class="text-end">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                {{-- @php dd($enlevements) @endphp --}}
                                @foreach ($enlevements as $enlevement)
                                    <tr>
                                        <td> {{ $enlevement->id }} </td>
                                        <td><b> {{ $enlevement->livraison->livreur->user->nom_prenoms}} </b></td>
                                        <td>{{ $enlevement->produit->nom }}</td>
                                        <td>{{ $enlevement->qte }}</td>
                                        <td>{{ $enlevement->livraison->date_livraison }}</td>
                                        <td class="text-end">
                                        {{-- <a href="" class="btn btn-success rounded font-sm">Res</a> --}}
                                        {{-- <a href="" class="btn btn-danger rounded font-sm">Refuser</a> --}}
                                    </td>
                                    </tr>
                                    @endforeach

                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>
                <!-- card-body end// -->
            </div>
            <!-- card end// -->
        </div>

    </div>

@endsection
