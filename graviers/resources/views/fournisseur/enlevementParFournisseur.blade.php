@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title','Liste des bons')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Les bons d'enlèvement de M/Mme {{ucwords($fournisseur->nom_prenoms)}} </h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-head m-3"> <h3 class="text-success">Total montant : {{number_format($montantTotal,'0','',' ')}} fcfa </h3></div>

                <!-- card-header end// -->
                <div class="card-body">

                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">N°</th>
                                    <th class="text-center">Nom du livreur</th>
                                    <th class="text-center">Produit</th>
                                    <th class="text-center">Quantité demandé</th>
                                    <th class="text-center">Quantité servie</th>
                                    <th class="text-center">Date</th>
                                    {{-- <th class="text-end">Action</th> --}}
                                    {{-- <th class="text-end">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1
                                @endphp

                                @foreach ($enlevements as $enlevement)
                                    @if ($enlevement->qte_servi != null)
                                        <tr>
                                            <td class="text-center"> <p>{{ $enlevement->code_enleve }}</p> </td>
                                            @if ($enlevement->livraison->livre_par  == 1)
                                                <td class="text-center"><b> {{ $enlevement->livraison->livreur->user->nom_prenoms }} </b></td>
                                            @else
                                                <td class="text-center"><b> {{ $enlevement->livraison->clientLivreur->nom.' '.$enlevement->livraison->clientLivreur->prenom }} </b></td>
                                            @endif
                                            <td class="text-center">{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->qte_servi }}</td>
                                            <td class="text-center">{{ Carbon::parse($enlevement->created_at)->format('d-m-Y') }}</td>
                                        </tr>
                                        @php
                                            $i++
                                        @endphp
                                    @endif
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>

            </div>
            <!-- card-body end// -->
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
                order: [],
            });
        });
    </script>
@endsection
