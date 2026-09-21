@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
{{-- @notifyCss --}}
@section('title','Liste des demandes de location')
<x-notify::notify />
@section('contenu')

    {{-- CLÉ PROPRE, ET NON « error » : Flasher capte success/error/warning/info
         et les rejoue en bulle éphémère. Un code non transmis doit rester à
         l'écran tant que le gestionnaire n'a pas prévenu son client. --}}
    @if (session('code_non_envoye'))
        <div class="alert alert-danger">
            <strong>Code de validation non transmis.</strong><br>
            {{ session('code_non_envoye') }}
        </div>
    @endif

<x-notify::notify />
    <div class="screen-overlay"></div>

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">Liste des locations en attente</h2>

        </div>
    </div>

        @if(session('success'))
            <div class="alert alert-success text-center">
                {{session('success')}}
            </div>
        @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <!-- card-header end// -->
                <div class="card-body">
                    <x-export-buttons table-id="listeLocationsEnAttente"
                                      filename="locations-en-attente"
                                      title="Locations en attente" />
                    <div class="table-responsive">
                        <table id="listeLocationsEnAttente" class="table table-hover table-bordered">
                            <thead>
                                <tr>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">N°</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Nom du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Type du client</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Montant</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Etat</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Date</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white">Paiement</th>
                                    <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($locations as $location)

                                    <tr>
                                        <td class="text-center" > <a href="">{{$location->numero}}</a> </td>
                                        <td class="text-center"><b> {{$location->client?->display_name}} </b></td>
                                        <td class="text-center"> {{$location->client?->type_client}} </td>
                                        <td class="text-center">
                                            {{-- Afficher le prix avant la reduction --}}
                                            <span class="vieux-prix"></span> <br>
                                            {{-- afficher le montant courant --}}
                                            {{number_format($location->montant_total,'0','',' ')}} fcfa

                                        </td>
                                        <td class="text-center">
                                            @php $etat = $location->etatLibelle(); @endphp
                                            <span class="badge rounded-pill {{ $etat === 'EN ATTENTE' ? 'bg-warning text-dark' : ($etat === 'EN COURS' ? 'bg-info text-dark' : 'bg-success') }}">{{ $etat }}</span>
                                        </td>
                                        <td class="text-center"> {{Carbon::parse($location->created_at)->format('d-m-Y')}} </td>

                                        <td class="text-center">
                                            {{-- CETTE PASTILLE LIT L ARGENT, PAS UN DRAPEAU.
                                                 Voir Location::etatPaiement(). --}}
                                            @php $etatPaiement = $location->etatPaiement(); @endphp
                                            @if ($etatPaiement === 'SOLDE')
                                            <span class="badge rounded-pill alert-success text-success">Paiement
                                                soldé</span>
                                        @elseif ($etatPaiement === 'PARTIEL')
                                            <span class="badge rounded-pill alert-success text-warning">paiement
                                                en cours...</span>
                                        @else
                                            <span class="badge rounded-pill alert-success text-danger">Aucun
                                                paiement effectué</span>
                                        @endif
                                        </td>

                                        <td class="text-nowrap text-end">
                                            @php
                                                // Paiement soldé (ou client à terme) exigé avant validation et facture FNE, comme pour les commandes.
                                                // L'ARGENT, PAS LE DRAPEAU : voir Location::estSoldee().
                                                $paiementOk = $location->estSoldee() || $location->client?->client_a_terme == 1;
                                            @endphp
                                            @if ($location->etatLibelle() === 'EN ATTENTE')
                                                @if ($paiementOk)
                                                    <a href="{{ route('show.validerLocationPage', $location) }}" class="btn btn-sm btn-primary rounded font-sm" title="Valider &amp; affecter"><i class="material-icons md-check_circle"></i></a>
                                                @endif
                                            @elseif ($location->etatLibelle() === 'EN COURS')
                                                <a href="{{ route('show.retourLocationPage', $location) }}" class="btn btn-sm btn-success rounded font-sm" title="Retour matériel"><i class="material-icons md-assignment_return"></i></a>
                                            @endif
                                            {{-- Paiement seulement si la location n'est pas déjà soldée (statut 3).
                                                 Le bouton mène au GUICHET des encaissements, avec agence, reçu et
                                                 seconde signature. L'écran de paiement historique, qui écrivait un
                                                 règlement validé d'un seul clic, a été retiré. --}}
                                            @if (!$location->estSoldee())
                                                <a href="{{ route('show.encaissements.locations', ['location' => $location->numero]) }}"
                                                   class="btn btn-sm btn-warning rounded font-sm" title="Encaisser le reste"><i class="material-icons md-payments"></i></a>
                                            @endif
                                            {{-- Facture FNE : générer (une fois, paiement soldé exigé) puis consulter. --}}
                                            @if ($location->factureFne)
                                                <a href="{{ route('orders.factureLocation', ['facture' => $location->factureFne->id, 'action' => 'voir']) }}" target="_blank" class="btn btn-sm btn-info rounded font-sm" title="Voir facture"><i class="material-icons md-visibility"></i></a>
                                            @elseif ($paiementOk)
                                                <form action="{{ route('orders.genererFactureLocation', $location) }}" method="post" style="display:inline-block">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-secondary rounded font-sm"
                                                        onclick="return confirm('Générer la facture FNE de cette location ?');" title="Générer facture"><i class="material-icons md-receipt_long"></i></button>
                                                </form>
                                            @endif
                                            {{-- Supprimer une location « fantôme » : EN ATTENTE et SANS AUCUN
                                                 paiement. Dès qu'un acompte est encaissé, la suppression
                                                 disparaît : on ne supprime pas une location sur laquelle il y a
                                                 de l'argent.

                                                 LA QUESTION SE POSE À L'ARGENT, PAS AU DRAPEAU. `statut` reste
                                                 à 1 quand un chemin de paiement oublie de le poser : une
                                                 location réellement encaissée proposait alors sa suppression.
                                                 Confirmation via SweetAlert2 (cf. jsParts). --}}
                                            @if ($location->etatPaiement() === 'AUCUN')
                                                <form action="{{ route('show.supprimerLocation', $location) }}" method="post" style="display:inline-block"
                                                      class="d-inline form-suppr-location" data-numero="{{ $location->numero }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-danger rounded font-sm" title="Supprimer"><i class="material-icons md-delete"></i></button>
                                                </form>
                                            @endif
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
            var $table = $('.table').DataTable({
                language: {
                    url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                },
                order: [],
            });
        });

        // Confirmation SweetAlert2 de la suppression. Écouteur délégué au document :
        // les lignes des autres pages DataTables sont hors DOM au chargement.
        $(document).on('submit', '.form-suppr-location', function (e) {
            var form = this;
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            if (typeof Swal === 'undefined') { form.dataset.confirmed = '1'; form.submit(); return; }
            Swal.fire({
                title: 'Supprimer cette location ?',
                html: 'Location <b>N° ' + form.dataset.numero + '</b> — aucune somme encaissée.<br>'
                    + 'Elle sera archivée et disparaîtra de la liste.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#d33',
            }).then(function (r) {
                if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
            });
        });
    </script>
    @notifyJs
@endsection
