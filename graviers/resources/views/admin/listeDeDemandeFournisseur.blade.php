
@php
    use Illuminate\Support\carbon;
    // Double validation (point 16) : tout admin peut valider, mais le 2e validateur
    // ne peut pas être le 1er.
    $currentUserId = Auth::id();
@endphp

@extends('layout.main')
@section('title','Liste des demandes de paiement - Fournisseurs')

@section('contenu')

                <div class="content-header">
                    <div>
                        <h2 class="content-title card-title"> Etat des paiements - Fournisseurs</h2>
                        {{-- <p>Lorem ipsum dolor sit amet.</p> --}}
                    </div>

                </div>
                <div class="card mb-4">
                    {{-- En-tete du gabarit retiree : elle ne portait qu'une case a
                         cocher sans nom ni script — un « tout selectionner » qui n'a
                         jamais rien selectionne — et un champ de date fige au
                         02.05.2021, qui ne filtrait rien.

                         Elle avait ete mise en commentaire, et un second commentaire
                         ecrit a l'interieur : un commentaire Blade NE S'IMBRIQUE PAS,
                         le premier se fermait donc trop tot et la fin du bloc
                         s'affichait en clair dans la page. --}}
                    <div class="card-body">
                        <x-export-buttons table-id="liste" filename="demandes-paiement-fournisseurs" title="Demandes de paiement - fournisseurs" />
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Nom prénom</th>

                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Montant demandé</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de demande</th>
                                        <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Accepter</th>
                                        <th class="text-end" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Refuser</th>
                                    </tr>
                                </thead>
                                <tbody>

                                        @foreach ($demandes as $demande)
                                            @if($demande->user_valide2_id  == null || $demande->user_valide_id == null )
                                                @php
                                                    $estInitiateur = (int) $demande->user_valide_id === (int) $currentUserId;
                                                    $estDejaFinalisee = $demande->user_valide_id && $demande->user_valide2_id;
                                                    $estEnAttente1 = is_null($demande->user_valide_id);
                                                    $estEnAttente2 = $demande->user_valide_id && !$demande->user_valide2_id;
                                                @endphp
                                                <tr>
                                                    <td class="text-center"> {{$loop->iteration}} </td>
                                                    <td class="text-center"> {{$demande->user?->nom_prenoms}} </td>
                                                    <td class="text-center"> {{number_format($demande->montant,'0','',' ')}} fcfa </td>
                                                    <td class="text-center"> {{Carbon::parse($demande->created_at)->format('d/m/Y à H:i:s')}} </td>
                                                    <td class="text-nowrap text-center">
                                                        @if($estDejaFinalisee)
                                                            <span class="badge bg-success">Validée</span>
                                                        @elseif($estEnAttente1)
                                                            <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'fournisseur','reponse' => 'accepter'])}}" class="btn btn-sm font-sm rounded btn-success" onclick="return confirm('Donner la 1re validation a cette demande ? Un SECOND administrateur devra ensuite accepter pour que ce fournisseur soit paye.'accepter pour que le fournisseur soit paye.');" title="1re validation"><i class="material-icons md-check"></i></a>
                                                        @elseif($estEnAttente2 && $estInitiateur)
                                                            <span class="text-muted" title="Vous êtes le 1er validateur">En attente d'un autre admin</span>
                                                        @elseif($estEnAttente2)
                                                            <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'fournisseur','reponse' => 'accepter'])}}" class="btn btn-sm font-sm rounded btn-success" onclick="return confirm('Accepter et PAYER cette demande ? Le versement sera enregistre et impute sur les pieces de ce fournisseur. Cette action est definitive.');" title="2e validation (accepter)"><i class="material-icons md-check"></i></a>
                                                        @endif
                                                    </td>
                                                    <td class="text-nowrap">
                                                        @if($estDejaFinalisee)
                                                            —
                                                        @elseif($estEnAttente2 && !$estInitiateur)
                                                            <a href="{{route('show.valideDemande',['id'=>$demande->id, 'type' => 'fournisseur','reponse' => 'refuser'])}}" class="btn btn-sm font-sm rounded btn-danger" onclick="return confirm('Refuser cette demande ? Le montant sera restitue au solde de ce fournisseur.');" title="Rejeter"><i class="material-icons md-block"></i></a>
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
                order: [],
            });
        });
    </script>
@endsection
