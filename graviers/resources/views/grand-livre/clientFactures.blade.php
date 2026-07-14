@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title', 'Liste des commandes')
<x-notify::notify />
@section('contenu')

    <x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des factures du client {{ $client->nom . ' ' . $client->prenom }} </h2>

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
                <div class="card-header">
                    <a href="{{ route('grandLivre.clientOrdinaireDetail', $client) }}"
                        class="btn btn-md rounded">Commandes</a>
                    <a href="{{ route('grandLivre.clientOrdinairePaiements', $client) }}"
                        class="btn btn-md rounded bg-warning">Paiement</a>
                    <a href="{{ route('grandLivre.clientOrdinaireFactures', $client) }}"
                        class="btn btn-md rounded bg-info">Factures</a>
                </div>

                <!-- card-header end// -->
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead class="thead-dark">

                                <tr>
                                    <th style="background-color: #1c57a3; color: white;" width="">Numero facture</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Date facture</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Montant</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Voir</th>
                                    <th style="background-color: #1c57a3; color: white;" width="">Télécharger</th>

                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $livraison = 1 ;
                                    $supplement = 0;
                                @endphp
                                @foreach ($factures as $key => $facture)
                                    @php
                                        $supplement = $facture->commande->cout_livraison_client + $facture->commande->TvaCommande->montant - $facture->commande->remise;
                                        // dd($supplement, $facture->montant);
                                    @endphp

                                    @if($key > 0)
                                       @php
                                            $livraison = 0;
                                            $supplement = 0;
                                       @endphp
                                    @endif
                                    <tr>
                                        <td> {{ $facture->numero }} </td>
                                        <td>{{ carbon::parse($facture->created_at)->format('d-m-Y') }}</td>
                                        <td>{{ number_format($facture->montant, '0', '', ' ') }} fcfa</td>
                                        <td> <a style="text-decoration: none"
                                                href="{{ route('show.actionFacture', ['commande' => $facture->service_id, 'facture' => $facture, 'action' => 'voir', 'livraison' => $livraison]) }}"
                                                class="text-white btn btn-primary">Voir</a> </td>
                                        <td> <a style="text-decoration: none"
                                                href="{{ route('show.actionFacture', ['commande' => $facture->service_id, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => $livraison]) }}"
                                                class="text-white btn btn-primary">Telecharger</a> </td>
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>

@endsection
