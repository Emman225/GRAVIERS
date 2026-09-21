@php
    use Illuminate\Support\carbon;
@endphp

{{-- {{var_dump($livraisons)}}
{{die}} --}}
@extends('layout.main')
@section('title','Demande de livraison en attente')

@section('contenu')
    <div class="mt-5 content-header">
        <h2 class="content-title">Demandes de livraison en attente </h2>
        @if (session('success'))
        <div class="alert alert-success sup" id="notify">
            {{session('success')}}
        </div>

        @endif

        {{-- Clé propre, et non « error » : le projet utilise Flasher avec
             flash_bag activé, qui capte error/success/warning/info avant la vue
             et les rejoue en bulle flottante. Ce message-ci est long et
             conditionne le travail du gestionnaire : il doit rester affiché. --}}
        @if (session('blocage_reglement'))
            <div class="alert alert-danger">
                <i class="material-icons md-lock align-middle"></i>
                {{ session('blocage_reglement') }}
            </div>
        @endif
    </div>
    <div class="card mb-4">
        <header class="card-header">

        </header>

        <div class="card-body">
            <x-export-buttons table-id="liste"
                              filename="demandes-de-livraison"
                              title="Demandes de livraison" />
            <div class="table-responsive">
                <table class="table table-striped" id="liste">

                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Produit</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Quantité</th> {{--  --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; ">Description</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Lieu de prise en charge</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Lieu de destination</th>
                            {{-- <th class="text-center" style="background-color: #1c57a3; color: white; "> Poids de vehicule souhaité</th> --}}
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Statut </th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; "> Date de commande </th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th> {{--  --}}

                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($livraisons as $livraison )
                            @if ($livraison->etat_commande != "TERMINEE")
                                <tr>
                                    <td class="text-center" > {{$livraison->client?->display_name}} </td>
                                    <td class="text-center">
                                        @foreach ($livraison->detailLivraison as $detail )
                                            {{$detail->nom_produit}} <br>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        {{-- {{$livraison->detailLivraison->qte.' '.$livraison->detailLivraison->unite}} --}}
                                        @foreach ($livraison->detailLivraison as $detail )
                                            {{$detail->qte.' '.($detail->uniteProduit?->libelle ?? '')}} <br>
                                        @endforeach

                                    </td>
                                    <td class="text-center">
                                        @foreach ($livraison->detailLivraison as $detail )
                                            {{$detail->description}} <br>
                                        @endforeach
                                    </td>
                                    {{-- Les deux adresses sont NULLABLES en base et l'API mobile
                                         recopie leurs identifiants depuis la requête sans les
                                         contrôler. La prise en charge était déjà protégée — le
                                         @dd laissé en commentaire ci-dessous garde la trace du
                                         jour où le cas s'est produit ; la destination, elle, ne
                                         l'était pas et faisait tomber la page entière. --}}
                                    <td class="text-center">
                                        {{-- @if ($livraison->priseEnCharge == null)
                                            @dd($livraison->id)
                                        @else --}}
                                            {{$livraison->priseEnCharge?->affichage ?: '—'}}
                                        {{-- @endif --}}
                                    </td>
                                    <td class="text-center"> {{$livraison->destination?->affichage ?: '—'}} </td>
                                    <td class="text-center">
                                        @switch($livraison->etat_commande)
                                            @case("EN ATTENTE")
                                                <span class="badge bg-secondary"> {{$livraison->etat_commande}} </span>
                                                @break
                                            @case("EN TRAITEMENT")
                                            <span class=" badge bg-warning"> {{$livraison->etat_commande}} </span>

                                                @break
                                            {{-- « TERMINEE » avec deux E : c'est la valeur exacte de
                                                 l'ENUM en base. Écrit « TERMINE », ce cas ne pouvait
                                                 jamais correspondre. --}}
                                            @case("TERMINEE")
                                            <span class="badge bg-success"> {{$livraison->etat_commande}} </span>

                                                @break
                                            @default

                                        @endswitch

                                        {{-- Un livreur qui refuse sa course laissait la demande
                                             inchangee : elle restait « en attente » sans que rien
                                             ne dise qu'il fallait la confier a quelqu'un d'autre. --}}
                                        @if ($livraison->attendUneReaffectation())
                                            <br>
                                            <span class="badge bg-danger mt-1">Refus livreur - a reaffecter</span>
                                        @endif

                                        {{-- L'avancement, ligne par ligne. « EN TRAITEMENT »
                                             ne disait pas si la marchandise etait partie :
                                             une demande livree a moitie et une demande dont
                                             rien n'a bouge portaient le meme mot. --}}
                                        @foreach ($livraison->detailLivraison as $uneLigne)
                                            @if ($uneLigne->qteLivree() > 0)
                                                <br>
                                                <small class="{{ $uneLigne->estEntierementLivree() ? 'text-success' : 'text-muted' }}">
                                                    {{ ucfirst($uneLigne->nom_produit) }} :
                                                    {{ $uneLigne->qteLivree() }}/{{ $uneLigne->qte }} livré
                                                </small>
                                            @endif
                                        @endforeach
                                    </td>
                                    {{-- <td class="text-center"> {{$livraison->detailLivraison->poids_vehicule_souhaite}}t </td> --}}
                                    <td class="text-center fw_bold"> {{Carbon::parse($livraison->created_at)->format('d/m/Y à H:i:s')}} </td>
                                    <td class="text-nowrap text-center"> <a href="{{route('show.traitelivraisonPage',$livraison)}}" class="btn btn-primary" title="traiter la demande"><i class="material-icons md-play_arrow"></i></a> </td>
                                </tr>
                            @endif
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
                order: [],
            });
        });
    </script>
@endsection
