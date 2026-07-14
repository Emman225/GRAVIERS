@php
// dd(null == 0);
    use Illuminate\Support\Carbon;

    // foreach ($livraisons as $livraison) {
    //     dd($livraison);
    // }
@endphp
@extends('layout.main')
@section('title','Livraison en attente')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Les livraisons en attente</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-9">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead dis>
                                <tr >

                                    <th style="background-color: #1c57a3; color: white; border-top-left-radius:5px" class="text-center">Client</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Produit</th>
                                    <th style="background-color: #1c57a3; color: white"class="text-center">Quantité</th>

                                    <th style="background-color: #1c57a3; color: white"class="text-center">Vehicule demande</th>
                                    <th style="background-color: #1c57a3; color: white"class="text-center">Immatriculation</th>
                                    <th style="background-color: #1c57a3; color: white" class="text-center">Capacité</th>

                                    <th style="background-color: #1c57a3; color: white"class="text-center">Adresse</th>
                                    <th style="background-color: #1c57a3; color: white"class="text-center">Date de reception</th>
                                    <th style="background-color: #1c57a3; color: white"class="text-center">Date de livraison prevu</th>
                                    <th style="background-color: #1c57a3; color: white; border-top-right-radius:5px" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>

                                {{-- @php dd($enlevements) @endphp --}}
                                @foreach ($livraisons as $livraison)
                                {{-- accepte : 1 = acceptée, 2 = en attente d'acceptation (valeur à la
                                     création), 3 = refusée. L'ancien filtre accepte == 1 masquait les
                                     livraisons fraîchement assignées (accepte = 2) alors que le mobile
                                     les montre dans "En Attente" -> page web vide à tort. --}}
                                @if ($livraison->etat_livraison != 'LIVREE' && in_array($livraison->accepte, [1, 2]) && $livraison->client != null)
                                    <tr>

                                        <td class="text-center"><b> {{ $livraison->client->nom.' '.$livraison->client->prenom}} </b></td>
                                        {{-- Produit selon la provenance : enlèvement (COMMANDE),
                                             detail_location (LOCATION : detail_commande_id pointe un
                                             detail_location), ou demande de livraison. --}}
                                        @php
                                            if ($livraison->enlevement) {
                                                $produitNom = $livraison->enlevement?->produit?->nom;
                                            } elseif ($livraison->provenance == 'LOCATION') {
                                                $produitNom = \App\Models\DetailLocation::find($livraison->detail_commande_id)?->produit?->nom;
                                            } else {
                                                $produitNom = $livraison->detailLivraison?->nom_produit;
                                            }
                                        @endphp
                                        <td class="text-center">{{ $produitNom ?? '-' }}</td>
                                        <td class="text-center">{{ $livraison->enlevement?->qte_servi == null ? $livraison->qte : $livraison->enlevement?->qte_servi }}</td>

                                        <td class="text-center">{{ $livraison->vehicule?->marque }}</td>
                                        <td class="text-center">{{ $livraison->vehicule?->immatriculation }}</td>
                                        <td class="text-center">{{ $livraison->vehicule?->capacite }}t</td>


                                        <td class="text-center">{{ $livraison->adresseLivraison?->affichage }}</td>
                                        <td class="text-center">{{ Carbon::parse($livraison->updated_at)->format('d-m-Y') }}</td>
                                        <td class="text-center">{{ Carbon::parse($livraison->date_livraison)->format('d-m-Y') }}</td>
                                        <td class="text-center">
                                            @if ($livraison->accepte == 2)
                                                {{-- Pas encore acceptée : le livreur accepte/refuse depuis
                                                     son application mobile. --}}
                                                <span class="badge bg-warning text-dark">À accepter (mobile)</span>
                                            @elseif ($livraison->provenance == "LIVRAISON")
                                                @if ($livraison->etat_livraison == "EN COURS DE LIVRAISON")
                                                    <span class="badge bg-warning text-dark">En route...</span>
                                                @else
                                                    <a href="{{route('livreur.enRoute',$livraison)}}" class="btn btn-primary">
                                                        En route
                                                    </a>

                                                @endif
                                            @endif
                                        </td>
                                    </tr>
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
        <div class="col-md-3">
            <form action="{{route('livreur.validationLivraison')}}" method="post" class="text-center">
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
@endsection
