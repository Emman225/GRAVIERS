
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
                        <x-export-buttons table-id="liste" filename="etat-des-paiements" title="Etat des paiements reçu" />
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
                                        <th class="text-center">Statut</th>
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
                                            {{$ligne->paiement->client?->nom.' '.$ligne->paiement->client?->prenom}}
                                        </td>
                                        <td class="text-center">
                                            <span> {{$ligne->paiement->client?->user_id}} </span>
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
                                        <td class="text-center">
                                            @if($ligne->statut == \Help::$STATUT_ACTIF)
                                                <span class="badge bg-success">Payé</span>
                                            @elseif($ligne->statut == 3)
                                                <span class="badge bg-danger">Annulé</span>
                                            @else
                                                <span class="badge bg-warning text-dark">En attente</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{route('paye.facture',['reference' => $ligne->id, 'action' => 'telecharger'])}}" class="btn btn-sm font-sm rounded btn-brand"> <i class="material-icons md-edit"></i> Télécharger </a>
                                            @if($ligne->statut != \Help::$STATUT_ACTIF && $ligne->statut != 3 && $ligne->code_paiement)
                                                <form action="{{ route('show.confirmerPaiementManuel') }}" method="post" class="d-inline js-confirm-paiement">
                                                    @csrf
                                                    <input type="hidden" name="codePaiement" value="{{ $ligne->code_paiement }}">
                                                    <input type="hidden" name="reference" value="{{ $ligne->reference }}">
                                                    <input type="hidden" name="moyen_paiement" value="{{ $ligne->moyen_paiement ?: 'Confirmation manuelle' }}">
                                                    <button type="submit" class="btn btn-sm font-sm rounded btn-success">
                                                        <i class="material-icons md-check"></i> Confirmer le paiement
                                                    </button>
                                                </form>
                                            @endif
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

        // Confirmation SweetAlert2 avant la confirmation manuelle d'un paiement.
        var MSG_CONFIRM_PAIEMENT = "Confirmer manuellement ce paiement ? À ne faire que si le client a bien été débité.";
        document.querySelectorAll('form.js-confirm-paiement').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === '1') return; // déjà confirmé : on laisse soumettre
                e.preventDefault();

                if (typeof Swal === 'undefined') { // repli si SweetAlert2 indisponible
                    if (confirm(MSG_CONFIRM_PAIEMENT)) { form.dataset.confirmed = '1'; form.submit(); }
                    return;
                }

                Swal.fire({
                    title: 'Confirmer le paiement ?',
                    text: MSG_CONFIRM_PAIEMENT,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, confirmer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                }).then(function (result) {
                    if (result.isConfirmed) {
                        form.dataset.confirmed = '1';
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
