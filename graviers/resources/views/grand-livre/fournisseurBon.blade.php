@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title','Bons validés')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Bons d'enlèvement en validés</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">
                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="table">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Code enlevement</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du livreur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Produit</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Quantité à récuperer</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Quantité servie</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date enlèvement</th>

                                </tr>
                            </thead>
                            <tbody>

                                @php
                                    $i = 1;
                                    $env = collect();
                                @endphp

                                @foreach ($enlevements as $enlevement)
                                    @if($enlevement->qte_servi != null)
                                            @php
                                                $env->put($i,$enlevement);
                                            @endphp
                                        <tr>
                                            <td class="text-center"> {{ $enlevement->code_enleve }} </td>
                                             @if ($enlevement->livraison->livre_par  == 1)
                                                <td class="text-center"><b> {{ $enlevement->livraison->livreur->nom.' '.$enlevement->livraison->livreur->prenom }} </b></td>
                                            @else
                                                <td class="text-center"><b> {{ $enlevement->livraison->clientLivreur->nom.' '.$enlevement->livraison->clientLivreur->prenom }} </b></td>
                                            @endif
                                            {{-- <td class="text-center"><b> {{ $enlevement->livraison->livreur->user->nom_prenoms}} </b></td> --}}
                                            <td class="text-center">{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->qte_servi }}</td>
                                            <td class="text-center">{{ Carbon::parse($enlevement->fournisseur_validation)->format('d-m-Y') }} à {{ Carbon::parse($enlevement->updated_at)->format('H:i') }}</td>

                                        </tr>
                                    @endif
                                        @php
                                            $i++;
                                        @endphp

                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 bloquerTopRem4 mb-5">

            <!-- col// -->

            <div class="card mt-30">
                <div class="card-header">
                    <h4>Recap des enlevements en attente par produits</h4>
                </div>
                <article class="card-body">
                    <div class="table-responsive ">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th style="background-color: #1c57a3; color: white">Produits</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Nbre de bons</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Qte total</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($fournisseur->produits as $produit)
                                @php
                                    $qte = $env->where('produit_id',$produit->id)->sum('qte');
                                    $bons = $env->where('produit_id',$produit->id)->count();
                                @endphp
                                @if ($qte > 0)

                                    <tr>
                                    <td class="fw-bold"> {{$produit->nom}} </td>
                                        <td class="fw-bold text-center"> {{$bons}} </td>
                                        <td class="fw-bold text-center"> {{$qte}} </td>
                                    </tr>
                                @endif
                            @endforeach

                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
            <!-- *********** -->
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
            var $table = $('#table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                    ordering: [],
                },
            });
        });
    </script>
@endsection
