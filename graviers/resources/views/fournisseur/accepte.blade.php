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
                                    <th class="text-center" style="background-color: #1c57a3; color: white">ID</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du livreur</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Produit</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Quantité</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-end" style="background-color: #1c57a3; color: white">Action</th>
                                    {{-- <th class="text-end" style="background-color: #1c57a3; color: white">Action</th> --}}
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1;
                                    $env = collect();
                                @endphp

                                @foreach ($enlevements as $enlevement)
                                    @php
                                        $quiLivreur = $enlevement->livraison->livre_par == 1 ? $enlevement->livraison->livreur : $enlevement->livraison->clientLivreur;
                                    @endphp
                                    @if($enlevement->qte_servi != null && $enlevement->livraison->accepte == 1 && $quiLivreur != null)
                                            @php
                                                $env->put($i,$enlevement);
                                            @endphp
                                        <tr>
                                            <td class="text-center"> {{ $enlevement->code_enleve }} </td>
                                            <td class="text-center">
                                                <b>
                                                     @if($enlevement->livraison->livre_par == 1)
                                                        {{ $enlevement->livraison->livreur->user->nom_prenoms }}
                                                    @else
                                                        {{$enlevement->livraison->clientLivreur->nom}}
                                                    @endif
                                                </b>
                                            </td>
                                            <td class="text-center">{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte_servi }}</td>
                                            <td class="text-center">{{ Carbon::parse($enlevement->fournisseur_validation)->format('d-m-Y') }} à {{ Carbon::parse($enlevement->updated_at)->format('H:i') }}</td>
                                            <td class="text-end">
                                                <a href="{{route('sellers.bon.detail',$enlevement->code_enleve)}}" class="btn btn-sm font-sm rounded btn-brand"> <i class="material-icons md-list"></i> Détail </a>
                                            </td>
                                        </tr>
                                    @endif
                                        @php
                                            $i++;
                                        @endphp

                                @endforeach
                                @php
                                    $statProduits = $env->groupBy('produit_id')->map(function ($items) {
                                        return [
                                            'produit' => $items->first()->produit->nom,
                                            'nbre_env' => $items->count(),
                                            'qte_total' => $items->sum('qte')
                                        ];
                                    });
                                @endphp
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 bloquerTopRem4 mb-5">
            {{-- <form action="" method="post" class="text-center">
                @csrf
                @if (session('fail'))
                <div class="alert alert-danger text-center">  {{session('fail')}} </div>
                @endif
                <div class="box shadow-sm bg-light text-center">
                    <h6 class="mb-15">Entrez le Code du bon d'enlèvement</h6>
                    <p>
                        <input type="text" name="code" placeholder="Code" class="form-control text-center">
                    </p>
                    <button class="mt-3 btn btn-success rounded font-sm" type="submit">Vérifier le code</button>
                </div>
            </form> --}}
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
                            @foreach($statProduits as $stat)

                                <tr>
                                    <td> {{ $stat['produit'] }} </td>
                                    <td> {{$stat['nbre_env']}} </td>
                                    <td> {{$stat['qte_total']}} </td>
                                </tr>

                            @endforeach

                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
            <!-- *********** -->
        </div>

        <!-- <div class="col-md-3 bloquerTopRem4">
            <div class="card bloquerTopRem7 mt-30">
                <div class="card-header">
                    <h4>Recap des enlevements enlevés par produits</h4>
                </div>
                <article class="card-body">
                    @foreach($fournisseur->produits as $produit)
                    @php
                        $qte = $enlevements->where('produit_id',$produit->id)->count()
                    @endphp

                        @if ($qte > 0)
                            <h3 class="text-muted mb-3"> {{ $produit->nom }} :  <span class="text-danger fw-bold">{{ $qte }} bon{{ $qte > 1 ? 's' : '' }} </span></h3>
                        @endif
                    @endforeach

                </article>
            </div>
        </div> -->
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
