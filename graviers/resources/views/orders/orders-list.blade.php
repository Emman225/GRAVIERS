@extends('layout.main')
{{-- @notifyCss --}}
@section('title', 'Liste des commandes')
<x-notify::notify />
@section('contenu')

    <x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des commandes en attente de traitement</h2>

        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success text-center">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <x-export-buttons table-id="tableCommandes" filename="commandes-en-attente" title="Commandes en attente de traitement" />
                    <div class="table-responsive">
                        <table id="tableCommandes" class="table table-hover table-bordered tablee">
                            <thead>
                                <tr>
                                    <th class="text-center"
                                        style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Client à terme
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Cout livraison</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Livrable</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($commandes as $commande)
                                    <tr>
                                        {{-- @dd($commande) --}}
                                        <td class="texte-center"> {{ $commande->numero }} </td>
                                        <td class="texte-center"><b>{{ $commande->infos_client }}</b></td>
                                        <td class="text-center"> {{ $commande->type_client }} </td>
                                        <td class="text-center"> {{ $commande->client_a_terme == 1 ? 'OUI' : 'NON' }} </td>
                                        <td class="texte-center">
                                            {{-- Afficher le prix avant la reduction (montants depuis les
                                                 lignes : cf. Commande::montantAPayer/montantHT) --}}
                                            <span
                                                class="vieux-prix">{{ $commande->remise > 0 ? Help::formatNombre($commande->montantAPayer() + $commande->remise, true) : '' }}</span>
                                            {{ Help::formatNombre($commande->montantAPayer(), true) }}
                                        </td>
                                        <td>
                                            {{Help::formatNombre($commande->cout_livraison_client, true)}}
                                        </td>
                                        <td><span
                                                class="badge rounded-pill text-warning">{{ $commande->etat_commande }} </span>
                                        </td>
                                        <td><span class="fw-bold"> {{$commande->est_livrable == 1 ? 'OUI' : 'NON'}} </span></td>
                                        <td class="texte-center">{{ $commande->created_at->format('d-m-Y à H:i') }}</td>

                                        <td>
                                            <div class="dropdown show">
                                                <a class="btn btn-secondary" href="#" role="button"
                                                    id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                    Actions
                                                </a>
                                                <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                                    <a href="{{ route('orders.details', $commande->numero) }}"
                                                        class="dropdown-item rounded font-sm">Détails de la commande</a>
                                                        @if($commande->devis && $commande->devis->reduction)
                                                            @if($commande->devis->reduction->est_utilise == false)
                                                                @if ($gest == 1)
                                                                    <p class=" dropdown-item rounded font-sm text-muted"> Demande de réduction en attente </p>
                                                                @else
                                                                    <a href="{{ route('orders.reduction', $commande) }}" class="dropdown-item rounded font-sm">Faire une réduction <span><h2 class="text-danger">•</h2></span></a>
                                                                @endif
                                                            @else

                                                            @endif
                                                        @else
                                                            <a href="{{ route('orders.reduction', $commande) }}" class="dropdown-item rounded font-sm">Faire une réduction <span></span></a>
                                                        @endif



                                                        @if ($commande->client_a_terme == 0 && $commande->montant_restant > 0)
                                                            <a href="{{ route('paye.effectuerPaiement', $commande->client_id) }}"
                                                                class="dropdown-item rounded font-sm">Solder la commande</a>
                                                        @else
                                                            <a href="{{ route('orders.traitement'.($commande->est_livrable == 1 ? '' : '.sansLivraison'), $commande) }}"
                                                                class="dropdown-item rounded font-sm">Traiter la commande</a>
                                                        @endif


                                                    <a href="{{ route('orders.BECommande', $commande->numero) }}"
                                                        class="dropdown-item rounded font-sm">Les enlevements</a>
                                                    <a href="{{ route('orders.BECommande.pdf', $commande->numero) }}"
                                                       class="dropdown-item rounded font-sm">Télécharger le bon</a>
                                                    @if ($commande->blClient && $commande->blClient->fichier)
                                                        <a href="{{ route('orders.fichierBlClient', ['bl' => $commande->blClient->id, 'mode' => 'inline']) }}" target="_blank" rel="noopener"
                                                            class="dropdown-item rounded font-sm">Voir fichier client</a>
                                                        <a href="{{ route('orders.fichierBlClient', ['bl' => $commande->blClient->id, 'mode' => 'download']) }}"
                                                            class="dropdown-item rounded font-sm">Télécharger fichier client</a>
                                                    @endif
                                                    @if ($commande->preuve)
                                                        <a href="{{ route('show.preuve',$commande) }}"
                                                            class="dropdown-item rounded font-sm">Afficher les informations</a>
                                                    @endif

                                                </div>
                                            </div>
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
        <div class="col-md-3">

        </div>
    </div>
    <div class="pagination-area mt-15 mb-50">

    </div>

@endsection
@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('.tablee').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

@endsection
