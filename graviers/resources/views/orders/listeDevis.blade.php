@extends('layout.main')
{{-- @notifyCss --}}
@section('title', 'Liste des dévis')
<x-notify::notify />
@section('contenu')

    <x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des devis </h2>

        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success text-center">
            {{ session('success') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <x-export-buttons table-id="tableDevis" filename="liste-devis" title="Liste des devis" />
                    <div class="table-responsive">
                        <table id="tableDevis" class="table table-hover table-bordered tablee">
                            <thead>
                                <tr>
                                    <th class="text-center"
                                        style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Libellé</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Contact
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Client à terme
                                    </th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Cout livraison</th>
                                    {{-- <th class="text-center" style="background-color: #1c57a3; color: white">Livrable</th> --}}
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($devis as $d)
                                    <tr>
                                        {{-- @dd($d) --}}
                                        <td class="texte-center"> {{ Help::formatNumeroFacture($d->numero) }} </td>
                                        <td class="texte-center"> {{ $d->libelle ?? '-' }} </td>
                                        <td class="texte-center"><b>{{ $d->client?->nom }}</b></td>
                                        <td class="texte-center"><b>{{ $d->client?->contact1 .' | '. $d->client?->contact2 }}</b></td>
                                        <td class="text-center"> {{ $d->client?->type_client }} </td>
                                        <td class="text-center"> {{ $d->client?->client_a_terme == 1 ? 'OUI' : 'NON' }} </td>
                                        <td class="texte-center">
                                            {{-- Afficher le prix avant la reduction --}}
                                            <span
                                                class="vieux-prix">{{ $d->remise > 0 ? Help::formatNombre($d->remise + $d->montant_total, true) : '' }}</span>
                                            {{ Help::formatNombre($d->montant + $d->tva + $d->cout_livraison, true) }}
                                        </td>
                                        <td>
                                             @if($d->cout_livraison > 0 ) {{number_format($d->cout_livraison, '0','', ' ')}} fcfa @else <span class="fw-bold"> Pas à livrer </span> @endif
                                        </td>
                                        {{-- <td><span class="fw-bold"> {{$d->cout_livraison > 0 ? 'OUI' : 'NON'}} </span></td> --}}
                                        <td class="texte-center">{{ $d->created_at->format('d-m-Y à H:i') }}</td>
                                        <td><span class="badge bg-{{ $d->statut == 1 ? 'secondary' : 'success' }}">{{ $d->statut == 1 ? 'En attente' : 'Commandé' }} </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('orders.detailDevis', $d) }}" class="btn btn-primary"> Détail </a>
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
