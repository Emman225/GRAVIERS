@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title','Liste des bons')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Bons d'enlèvement en attente</h2>
        </div>
    </div>
    <div class="row " >
        <div class="col-md-9">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <x-export-buttons table-id="table"
                                      filename="bons-d-enlevement"
                                      title="Bons d enlèvement" />
                    <div class="table-responsive">
                        <table class="table table-striped" id="table">
                            <thead>
                                <tr>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">N°</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Nom du livreur</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Produit</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Quantité</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Date</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Date de livraison</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1;
                                    $env = collect();
                                @endphp

                                @foreach ($enlevements as $enlevement)
                                    @php
                                        $quiLivreur = $enlevement->livraison?->livre_par == 1 ? $enlevement->livraison?->livreur : $enlevement->livraison?->clientLivreur;
                                    @endphp

                                    @if ($enlevement->qte_servi == null && $enlevement->livraison?->accepte == 1 && $quiLivreur != null)
                                        @php
                                        $env->put($i,$enlevement);
                                        @endphp

                                        <tr>
                                            <td class="text-center"> <p>{{ $i }}</p> </td>
                                            <td class="text-center">
                                                <b>
                                                    @if($enlevement->livraison?->livre_par == 1)
                                                        {{ $enlevement->livraison?->livreur?->user?->nom_prenoms }}
                                                    @else

                                                        {{$enlevement->livraison?->clientLivreur?->nom}}

                                                    @endif
                                                </b>
                                            </td>
                                            <td class="text-center">{{ $enlevement->produit?->nom }}  </td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->created_at }}</td>
                                            {{-- La date à laquelle le livreur doit livrer : c'est
                                                 elle qui dit au fournisseur QUAND préparer le bon. --}}
                                            <td class="text-center" data-order="{{ $enlevement->livraison?->date_livraison }}">
                                                {{ $enlevement->livraison?->date_livraison ? \Help::dateHeure($enlevement->livraison->date_livraison) : '-' }}
                                            </td>
                                        </tr>
                                        @php
                                            $i++
                                        @endphp
                                    @endif
                                @endforeach

                                @php
                                    $statProduits = $env->groupBy('produit_id')->map(function ($items) {
                                        return [
                                            'produit' => $items->first()->produit?->nom,
                                            'nbre_env' => $items->count(),
                                            'qte_total' => $items->sum('qte')
                                        ];
                                    });
                                @endphp


                            </tbody>
                        </table>
                    </div>
                    <!-- table-responsive //end -->
                </div>

            </div>
            <!-- card-body end// -->
        </div>
        <!-- card end// -->
        <div class="col-lg-3 bloquerTopRem4 mb-5">
            <form action="" method="post" class="text-center">
                @csrf
                @if (session('fail'))
                <div class="alert alert-danger text-center">  {{session('fail')}} </div>
                @endif
                <div class="box shadow-sm bg-light text-center">
                    <h6 class="mb-15">Entrez le Code du bon d'enlèvement</h6>
                    <p>
                        <input type="text" name="code" placeholder="Code" class="form-control text-center border">
                    </p>
                    <button class="mt-3 btn btn-success rounded font-sm" type="submit">Vérifier le code</button>
                </div>
            </form>
            <!-- col// -->
        </div>
    </div>

    {{-- LE RÉCAPITULATIF PAR PRODUITS SOUS LE TABLEAU DES BONS (09/09/2026), sur
         toute la largeur : il vivait dans la colonne de droite, sous le
         formulaire de vérification du code, et se lisait mal. --}}
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Récap des enlèvements en attente par produits</h4>
                </div>
                <article class="card-body">
                    <x-export-buttons table-id="recapEnlevements"
                                      filename="recap-enlevements-en-attente"
                                      title="Récap des enlèvements en attente par produit" />
                    <div class="table-responsive">
                        <table class="table table-striped" id="recapEnlevements">
                            <thead>
                                <tr>
                                    <th style="background-color: #1c57a3; color: white">Produits</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Nbre de bons</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Qté totale</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($statProduits as $stat)
                                <tr>
                                    <td> {{ $stat['produit'] }} </td>
                                    <td class="text-center"> {{$stat['nbre_env']}} </td>
                                    <td class="text-center"> {{$stat['qte_total']}} </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">Aucun enlèvement en attente.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </div>
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
                },
                ordering: [],
            });
        });
    </script>
@endsection
