@extends('layout.main')
@section('title','Liste des bons validés')
@section('contenu')
    <div class="screen-overlay"></div>


    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Les bons d'enlèvement validés</h2>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                @if (!$enlevements->isEmpty())
                    <!-- card-header end// -->
                    <div class="card-body">
                        <x-export-buttons table-id="tableBonsValides" filename="bons-enlevement-valides" title="Bons d'enlèvement validés" />
                        <div class="table-responsive">
                            <table id="tableBonsValides" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code du bon</th>
                                        <th>Client</th>
                                        <th>Nom du Fournisseur</th>
                                        <th>livreur</th>
                                        <th>Produit</th>
                                        <th class="text-center">Quantité</th>
                                        <th class="text-center">Agent ayant créé l'enlèvement</th>
                                        <th>Date</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- @php dd($enlevements) @endphp --}}
                                    @foreach ($enlevements as $enlevement)
                                        <tr>
                                            <td> {{ $enlevement->code_enleve }} </td>
                                            <td> {{ $enlevement->livraison->client->nom.' '.$enlevement->livraison->client->prenom }} </td>
                                            <td>
                                                <b> {{ $enlevement->fournisseur->nom_prenoms }} </b>
                                            </td>
                                            <td>
                                                <b>
                                                    @if ($enlevement->livraison->livre_par == 1)
                                                        {{ $enlevement->livraison->livreur->user->nom_prenoms }}
                                                    @else
                                                        {{ $enlevement->livraison->clientLivreur->nom }}
                                                    @endif
                                                </b>
                                            </td>
                                            <td>{{ $enlevement->produit->nom }}</td>
                                            <td class="text-center">{{ $enlevement->qte }}</td>
                                            <td class="text-center">{{ $enlevement->gestionnaire?->nom_prenoms ?: '-' }}</td>
                                            <td>{{ $enlevement->created_at->format('d-m-Y')}}</td>
                                            <td class="text-center">
                                                <a href="{{ route('show.bonApercu', $enlevement) }}"
                                                   class="btn btn-sm btn-primary" target="_blank"
                                                   title="Aperçu du bon">
                                                    <i class="material-icons md-visibility"></i>
                                                </a>
                                                <a href="{{ route('show.bonTelecharger', $enlevement) }}"
                                                   class="btn btn-sm btn-info"
                                                   title="Télécharger le PDF">
                                                    <i class="material-icons md-cloud_download"></i>
                                                </a>
                                            </td>
                                        </tr>
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
                order:[],
            });
        });
    </script>
@endsection
