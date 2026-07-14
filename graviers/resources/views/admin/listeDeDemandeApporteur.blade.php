
@php
    use Illuminate\Support\carbon;
    // Double validation (point 16) : tout admin peut valider, mais le 2e validateur
    // ne peut pas être le 1er.
    $currentUserId = Auth::id();
@endphp

@extends('layout.main')
@section('title','Liste des demandes de paiement - Apporteurs')

@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title"> Etat des paiements - Apporteurs</h2>
                        {{-- <p>Lorem ipsum dolor sit amet.</p> --}}
                    </div>

                </div>
                <div class="card mb-4">
                    {{-- <header class="card-header">
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
                    </header> --}}
                    <div class="card-body">
                        <x-export-buttons table-id="liste" filename="demandes-paiement-apporteurs" title="Demandes de paiement - apporteurs" />
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Nom prénom</th>

                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Montant demandé</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de demande</th>
                                        <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Accepter</th>
                                        <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Refuser</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($demandes as $demande)
                                        @if($demande->user->type_user_id == 6 && $demande->paye == false)
                                            @php
                                                $estInitiateur = (int) $demande->user_valide_id === (int) $currentUserId;
                                                $estDejaFinalisee = $demande->user_valide_id && $demande->user_valide2_id;
                                                $estEnAttente1 = is_null($demande->user_valide_id);
                                                $estEnAttente2 = $demande->user_valide_id && !$demande->user_valide2_id;
                                            @endphp
                                            <tr>
                                                <td class="text-center"> {{$demande->user->nom_prenoms}} </td>
                                                <td class="text-center"> {{$demande->montant}} fcfa </td>
                                                <td class="text-center"> {{Carbon::parse($demande->created_at)->format('d-m-Y à H:i')}} </td>
                                                <td class="text-center">
                                                    @if($estDejaFinalisee)
                                                        <span class="badge bg-success">Validée</span>
                                                    @elseif($estEnAttente1)
                                                        <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'apporteur','reponse' => 'accepter'])}}" class="btn btn-sm font-sm rounded btn-success"><i class="material-icons md-check"></i> 1re validation</a>
                                                    @elseif($estEnAttente2 && $estInitiateur)
                                                        <span class="text-muted" title="Vous êtes le 1er validateur">En attente d'un autre admin</span>
                                                    @elseif($estEnAttente2)
                                                        <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'apporteur','reponse' => 'accepter'])}}" class="btn btn-sm font-sm rounded btn-success"><i class="material-icons md-check"></i> 2e validation (accepter)</a>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($estDejaFinalisee)
                                                        —
                                                    @elseif($estEnAttente2 && !$estInitiateur)
                                                        <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'apporteur','reponse' => 'refuser'])}}" class="btn btn-sm font-sm rounded btn-danger" onclick="return confirm('Refuser cette demande ?');"><i class="material-icons md-denied"></i> Rejeter</a>
                                                    @endif
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
                },
            });
        });
    </script>
@endsection
