
@php
    use Illuminate\Support\carbon;
@endphp

@extends('layout.main')
@section('title','Liste des paiements')

@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title"> Etat des paiements </h2>
                        {{-- <p>Lorem ipsum dolor sit amet.</p> --}}
                    </div>

                </div>
                <div class="card mb-4">
                <div class="card-header">
                    <a href="{{route('grandLivre.clientOrdinaireDetail', $client)}}" class="btn btn-md rounded">Commandes</a>
                    <a href="{{route('grandLivre.clientOrdinairePaiements',$client)}}" class="btn btn-md rounded bg-warning">Paiement</a>
                    <a href="{{route('grandLivre.clientOrdinaireFactures',$client)}}" class="btn btn-md rounded bg-info">Factures</a>
                </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center">Date</th>

                                        <th class="text-center">Nom du client</th>
                                        <th class="text-center">N° compte client</th>
                                        <th class="text-center">N° reçu paiement</th>
                                        <th class="text-center">Moyen</th>
                                        <th class="text-center">Montant payé</th>
                                        <th class="text-center">Agent ayant reçu le paiement</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lignes as $ligne)
                                    <tr>
                                        <td class="text-center">
                                            {{-- <a class="itemside" href="{{route('paye.facture',$ligne->reference)}}"> --}}
                                                <div class="info">
                                                    <h6 class="mb-0">{{Carbon::parse($ligne->created_at)->format('d-m-Y');}}</h6>
                                                </div>
                                            {{-- </a> --}}
                                        </td>

                                        <td class="text-center">
                                            {{$ligne->paiement->client->nom.' '.$ligne->paiement->client->prenom}}
                                        </td>
                                        <td class="text-center">
                                            <span> {{$ligne->paiement->client->user_id}} </span>
                                        </td>
                                        <td class="text-center">
                                            <span> {{$ligne->paiement->code}} </span>
                                        </td>
                                        <td class="text-center">
                                            <span> {{$ligne->moyen_paiement}} </span>
                                        </td>
                                        <td class="text-center">
                                            <span> {{number_format($ligne->montant,'0','',' ')}}fcfa </span>
                                        </td>
                                        <td class="text-center">
                                            <span> {{$ligne->userPaie?->nom_prenoms}} </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{route('paye.facture',['reference' => $ligne->id, 'action' => 'telecharger'])}}" class="btn btn-sm font-sm rounded btn-brand"> <i class="material-icons md-edit"></i> Télécharger </a>
                                        </td>
                                    </tr>

                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- </div> --}}
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });
    </script>
@endsection
