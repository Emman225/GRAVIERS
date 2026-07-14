@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@notifyCss
@section('title','Liste des commandes')
<x-notify::notify />
@section('contenu')

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des commandes déjà traitées</h2>

        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <x-export-buttons table-id="tableCommandesTraitees" filename="commandes-traitees" title="Commandes traitées" />
                    <div class="table-responsive">
                        <table id="tableCommandesTraitees" class="table table-striped" >
                            <thead>
                                <tr>
                                    <th style="background-color: #1c57a3; color: white"  >N°</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-center">Nom du client</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-center">Montant</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-center">cout livraison</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-center">Etat</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-center">Date</th>
                                    {{-- <th style="background-color: #1c57a3; color: white"  class="text-center">Paiements</th> --}}
                                    <th style="background-color: #1c57a3; color: white"  class="text-end" colspan="1">Details</th>
                                    <th style="background-color: #1c57a3; color: white"  class="text-end" colspan="1">Enlevements</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($commandes as $commande)

                                {{-- @if (Help::verificationDeCommandeTotalementTraitee($commande) == true) --}}
                                    <tr>
                                        <td  > {{ $commande->numero }} </td>

                                        <td  class="text-center"><b>{{ $commande->client?->nom }}
                                                {{ $commande->client?->prenom }}</b></td>


                                        <td  class="text-center">{{ Help::formatNombre($commande->montant_total + $commande->cout_livraison_client + $commande->TvaCommande->montant - $commande->remise, true)}} </td>
                                        <td  class="text-center">{{ Help::formatNombre($commande->cout_livraison_client, true)}} </td>


                                        <td  class="text-center"><span
                                                class="badge rounded-pill text-warning">{{ $commande->etat_commande }}</span>
                                            @php
                                                // Statut global de livraison de la commande
                                                $detailsCmd = $commande->detailCommande ?? collect();
                                                $totalQte = (float) $detailsCmd->sum('qte');
                                                $totalLivree = (float) $detailsCmd->sum('qte_livree');
                                                $resteACalcul = 0;
                                                foreach ($detailsCmd as $d) {
                                                    $resteACalcul += max(0, ((float) $d->qte) - ((float) ($d->qte_livree ?? 0))) * (float) $d->prix;
                                                }
                                            @endphp
                                            @if ($totalLivree > 0 && $totalLivree < $totalQte)
                                                <br><span class="badge bg-warning text-dark mt-1">
                                                    Livraison partielle — Reste {{ Help::formatNombre($resteACalcul, true) }}
                                                </span>
                                            @elseif ($totalLivree >= $totalQte && $totalQte > 0)
                                                <br><span class="badge bg-success mt-1">Livraison totale</span>
                                            @endif
                                        </td>


                                        <td  class="text-center">{{ Carbon::parse($commande->updated_at)->format('d-m-Y à H:i') }}</td>


                                        {{-- <td  class="text-center">
                                            @if ($commande->statut == 1)
                                                <p class="text-danger">Aucun paiement effectué</p>
                                            @elseif ($commande->statut == 2)
                                                <p class="text-warning">Paiement en cours</p>
                                            @elseif ($commande->statut == 3 || $commande->statut == 4)
                                                <p class="text-success">Paiement soldé</p>
                                            @endif
                                        </td> --}}


                                        <td  class="text-end">
                                            <a href="{{ route('orders.details', $commande->numero) }}" class="btn btn-md rounded font-sm">Detail</a>
                                        </td>


                                        <td class="text-end">
                                            <a href="{{ route('orders.BECommande', $commande->numero) }}" class="btn btn-md rounded font-sm">Les enlèvements</a>
                                        </td>

                                    </tr>
                                {{-- @endif --}}

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
            var $table = $('.table').DataTable({
                columnDefs: [{ targets: '_all', defaultContent: '-' }],
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [
                ],
            });
        });
    </script>
    @notifyJs
@endsection
