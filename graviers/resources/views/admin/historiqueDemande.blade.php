
@php
    use Illuminate\Support\carbon;
@endphp

@extends('layout.main')
@section('title','Liste des demandes')

@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title"> Etat des paiements </h2>
                        {{-- <p>Lorem ipsum dolor sit amet.</p> --}}
                    </div>

                </div>
                <div class="card mb-4">
                    <header class="card-header">
                        <div class="row gx-3">
                            <div class="col col-check flex-grow-0">
                                <div class="form-check ms-2">
                                    <input class="form-check-input" type="checkbox" value="" />
                                </div>
                            </div>

                            <div class="col-md-2 col-6">
                                <input type="date" value="02.05.2021" class="form-control" />
                            </div>
                        </div>
                    </header>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>

                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Nom prénom</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Fonction</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Montant demandé</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de demande</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de traitement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Agent ayant validé le paiement</th>
                                        <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Statut</th>

                                </thead>
                                <tbody>
                                    @foreach ($demandes as $demande)
                                        @if($demande->paye == true || $demande->paye == 2)
                                            <tr>
                                                <td class="text-center">
                                                    {{-- Nom complet pris sur le user (toujours renseigné, quel que soit le type) :
                                                         Livreur/Fournisseur/Apporteur n'ont pas de colonnes nom/prenom. --}}
                                                    {{ $demande->user?->nom_prenoms }}
                                                </td>
                                                <td class="text-center"> {{$demande->user->type_user->nom}} </td>
                                                <td class="text-center"> {{number_format($demande->montant,'0','',' ')}} fcfa </td>
                                                <td class="text-center"> {{Carbon::parse($demande->created_at)->format('d-m-Y à H:i')}} </td>
                                                <td class="text-center"> {{Carbon::parse($demande->updated_at)->format('d-m-Y à H:i')}} </td>
                                                <td class="text-center"> {{$demande->userValide?->nom_prenoms}} </td>
                                                <td class="text-center">
                                                    @switch($demande->paye)
                                                        @case(1)
                                                            <span class="badge bg-success">Payée</span>
                                                            @break

                                                        @case(2)
                                                            <span class="badge bg-danger">Réfusée</span>
                                                            @break

                                                        @default

                                                    @endswitch
                                                </td>
                                            </tr>
                                        @endif
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
                },order:[],
            });
        });
    </script>
@endsection
