

@php
    use Illuminate\Support\carbon;
@endphp
@extends('layout.main')
@section('title','Liste des bons')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des bons en attente</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">

                </div>
                @if (!$enlevements->isEmpty())
                    <!-- card-header end// -->
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">ID</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Nom du Fournisseur</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Produit</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Quantité</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Véhicule demandé</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Immatriculation</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Capacité</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Date de reception du bon</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Date livraison prévu</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white">Date livraison effective</th>


                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @foreach ($enlevements as $enlevement)
                                        @if($enlevement->qte_servi != null)
                                            @if($enlevement->livraison->etat_livraison == 'LIVREE')
                                                <tr>
                                                    <td class=" text-center"> <b> {{ $enlevement->livraison->numero }}</b> </td>
                                                    <td class=" text-center"><b> {{ $enlevement->fournisseur->user->nom_prenoms }} </b></td>
                                                    <td class=" text-center">{{ $enlevement->produit->nom }}</td>
                                                    <td class=" text-center">{{ $enlevement->qte }}</td>
                                                    @if ($enlevement->vehicule)
                                                        <td class=" text-center">{{ $enlevement->vehicule->marque }}</td>
                                                        <td class=" text-center">{{ $enlevement->vehicule->immatriculation }}</td>
                                                        <td class=" text-center">{{ $enlevement->vehicule->capacite }}t</td>
                                                    @else
                                                        <td class=" text-center">Pas de livraison</td>
                                                        <td class=" text-center">Pas de livraison</td>
                                                        <td class=" text-center">Pas de livraison</td>
                                                    @endif
                                                    <td class=" text-center">{{Carbon::parse($enlevement->created_at)->format('d-m-Y à H:i');  }}</td>
                                                    <td class=" text-center">{{Carbon::parse($enlevement->livraison->date_livraison)->format('d-m-Y');  }}</td>
                                                    <td class=" text-center">{{Carbon::parse($enlevement->livraison->updated_at)->format('d-m-Y');  }}</td>

                                                </tr>
                                            @endif
                                        @endif
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

        {{-- <div class="col-md-3">
            <form action="{{route('livreur.bonRecherche')}}" method="post" class="text-center">
                @csrf
                @if (session('fail'))
                <div class="alert alert-danger text-center">  {{session('fail')}} </div>
                @endif
                <div class="box shadow-sm bg-light text-center">
                    <h6 class="mb-15">Entrez le code de la livraison</h6>
                    <p>
                        <input type="text" name="code" placeholder="Code" class="form-control text-center">
                    </p>
                    <button class="mt-3 btn btn-success rounded font-sm" type="submit">Vérifier le code</button>
                </div>
            </form>

        </div> --}}


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
