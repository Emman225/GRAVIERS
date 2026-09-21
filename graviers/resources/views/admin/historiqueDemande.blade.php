
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
                            {{-- Case a cocher retiree : sans nom, sans valeur et sans script,
                                 c etait un « tout selectionner » herite du gabarit qui n a
                                 jamais rien selectionne. --}}

                            <div class="col-md-2 col-6">
                                <input type="date" value="02.05.2021" class="form-control" />
                            </div>
                        </div>
                    </header>
                    <div class="card-body">
                        <x-export-buttons table-id="liste"
                                          filename="historique-des-demandes"
                                          title="Historique des demandes de paiement" />
                        <div class="table-responsive">
                            <table id="liste" class="table table-striped">
                                <thead>

                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Nom prénom</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Fonction</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Montant demandé</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de demande</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Date de traitement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Agent ayant validé le paiement</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">3e administrateur (preuve / finalisation)</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white;">Statut</th>
                                        <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Preuve / Action</th>

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
                                                <td class="text-center"> {{$demande->user?->type_user?->nom}} </td>
                                                <td class="text-center"> {{number_format($demande->montant,'0','',' ')}} fcfa </td>
                                                <td class="text-center"> {{Carbon::parse($demande->created_at)->format('d/m/Y à H:i:s')}} </td>
                                                <td class="text-center"> {{Carbon::parse($demande->updated_at)->format('d/m/Y à H:i:s')}} </td>
                                                <td class="text-center"> {{$demande->userValide?->nom_prenoms}} </td>
                                                <td class="text-center small">{{ $demande->agentEffectuee?->nom_prenoms ?? $demande->agentPreuve?->nom_prenoms ?? '-' }}</td>
                                                <td class="text-center">
                                                    {{-- Point 20 : validée → « À payer » → preuve jointe → « Effectuée ». --}}
                                                    @if ((int) $demande->paye === 2)
                                                        <span class="badge bg-danger">Refusée</span>
                                                    @elseif ($demande->estEffectuee())
                                                        <span class="badge bg-success">Effectuée</span>
                                                        <br><small class="text-muted">{{ $demande->date_effectuee ? Carbon::parse($demande->date_effectuee)->format('d/m/Y H:i:s') : '' }}</small>
                                                    @elseif ($demande->etat_reglement === \App\Models\DemandePaiement::PREUVE_JOINTE)
                                                        <span class="badge bg-info text-dark">À payer — preuve jointe</span>
                                                    @elseif ($demande->etat_reglement === \App\Models\DemandePaiement::A_PAYER)
                                                        <span class="badge bg-warning text-dark">À payer</span>
                                                    @else
                                                        <span class="badge bg-success">Payée</span>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap text-center">
                                                    @if ((int) $demande->paye === 1)
                                                        @if ($demande->preuve_paiement)
                                                            <a href="{{ route('show.demandePaiement.voirPreuve', $demande) }}" target="_blank"
                                                               class="btn btn-sm font-sm rounded btn-light" title="Voir la preuve">
                                                                <i class="material-icons md-visibility"></i>
                                                            </a>
                                                        @endif
                                                        @if (($demande->etat_reglement === \App\Models\DemandePaiement::A_PAYER
                                                             || $demande->etat_reglement === \App\Models\DemandePaiement::PREUVE_JOINTE)
                                                             && $demande->troisiemeAdministrateur(auth()->user()))
                                                            <button type="button" class="btn btn-sm font-sm rounded btn-primary js-joindre-preuve"
                                                                    data-url="{{ route('show.demandePaiement.preuve', $demande) }}"
                                                                    data-libelle="{{ $demande->user?->nom_prenoms }} — {{ number_format($demande->montant, 0, '', ' ') }} fcfa"
                                                                    title="{{ $demande->preuve_paiement ? 'Remplacer la preuve de paiement' : 'Joindre la preuve de paiement' }}">
                                                                <i class="material-icons md-cloud_upload"></i>
                                                            </button>
                                                        @endif
                                                        @if ($demande->etat_reglement === \App\Models\DemandePaiement::PREUVE_JOINTE
                                                             && $demande->troisiemeAdministrateur(auth()->user()))
                                                            <form method="POST" action="{{ route('show.demandePaiement.effectuer', $demande) }}" class="d-inline js-delete-form"
                                                                  data-confirm-mode="confirm"
                                                                  data-confirm-title="Finaliser le paiement ?"
                                                                  data-confirm-text="Déclarer ce paiement EFFECTUÉ ? Le bénéficiaire le verra dans son espace et son application."
                                                                  data-confirm-button="Oui, finaliser">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm font-sm rounded btn-success" title="Finaliser : paiement effectué">
                                                                    <i class="material-icons md-task_alt"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        @if (in_array($demande->etat_reglement, [\App\Models\DemandePaiement::A_PAYER, \App\Models\DemandePaiement::PREUVE_JOINTE], true)
                                                             && !$demande->troisiemeAdministrateur(auth()->user()))
                                                            {{-- Sécurité (09/09/2026) : un troisième administrateur téléverse et finalise. --}}
                                                            <span class="text-muted small d-block"><em>Preuve et finalisation : un troisième administrateur</em></span>
                                                        @endif
                                                    @else
                                                        —
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


    {{-- Preuve de paiement (point 20) : un seul formulaire, pointé sur la demande choisie. --}}
    <div class="modal fade" id="modalPreuve" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" id="formPreuve" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="material-icons md-cloud_upload"></i> Joindre la preuve de paiement</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2"><strong id="preuveLibelle"></strong></p>
                        <label class="form-label" for="preuveFichier">Preuve (PDF, JPG ou PNG — 5 Mo maximum) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="preuveFichier" name="preuve" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Reçu de virement, capture du mobile money, bordereau… Une fois jointe, le paiement peut être finalisé.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer la preuve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('click', function (e) {
            var b = e.target.closest('.js-joindre-preuve');
            if (!b) return;
            document.getElementById('formPreuve').setAttribute('action', b.getAttribute('data-url'));
            document.getElementById('preuveLibelle').textContent = b.getAttribute('data-libelle') || '';
            new bootstrap.Modal(document.getElementById('modalPreuve')).show();
        });
    </script>
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
