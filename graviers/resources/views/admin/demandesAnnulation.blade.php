@php
    use Illuminate\Support\Carbon;
@endphp

@extends('layout.main')
@section('title', "Demandes d'annulation")

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Demandes d'annulation (ventes &amp; locations)</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="liste">
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Type</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">N°</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Motif du client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">État du service</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Payé</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Demandée le</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Décision</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($demandes as $demande)
                            <tr>
                                <td class="text-center">
                                    <span class="badge {{ $demande->type_affaire === 'LOCATION' ? 'bg-info text-dark' : 'bg-primary' }}">
                                        {{ $demande->type_affaire === 'LOCATION' ? 'LOCATION' : 'VENTE' }}
                                    </span>
                                </td>
                                <td class="text-center"><strong>{{ $demande->infos->numero }}</strong></td>
                                <td>
                                    <strong>{{ $demande->client->nom ?? '-' }} {{ $demande->client->prenom ?? '' }}</strong>
                                    @if($demande->client?->user)
                                        <br><small class="text-muted">{{ $demande->client->user->email }}</small>
                                    @endif
                                </td>
                                <td><small>{{ \Illuminate\Support\Str::limit($demande->motif, 120) }}</small></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $demande->infos->etat }}</span>
                                    @if($demande->infos->en_traitement && !$demande->est_traite)
                                        <br><small class="text-danger">Traitement démarré : annulation auto impossible</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($demande->infos->paye > 0)
                                        <span class="text-success fw-bold">{{ number_format($demande->infos->paye, 0, ',', ' ') }} FCFA</span>
                                        <br><small class="text-danger">Remboursement à prévoir si annulée</small>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ Carbon::parse($demande->created_at)->format('d-m-Y H:i') }}</td>
                                <td class="text-center">
                                    @if (!$demande->est_traite)
                                        <span class="badge bg-warning text-dark">En attente</span>
                                    @elseif ($demande->decision == 1)
                                        <span class="badge bg-success">Approuvée</span>
                                        <br><small class="text-muted">{{ $demande->decided_at ? Carbon::parse($demande->decided_at)->format('d-m-Y H:i') : '' }}</small>
                                    @else
                                        <span class="badge bg-danger">Refusée</span>
                                        @if($demande->note)
                                            <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($demande->note, 60) }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if (!$demande->est_traite)
                                        <button type="button" class="btn btn-sm btn-success rounded font-sm me-1 {{ $demande->infos->en_traitement ? 'disabled' : '' }}"
                                                data-bs-toggle="modal" data-bs-target="#approveAnnul-{{ $demande->id }}"
                                                {{ $demande->infos->en_traitement ? 'disabled title=Traitement déjà démarré' : '' }}>Approuver</button>
                                        <button type="button" class="btn btn-sm btn-danger rounded font-sm"
                                                data-bs-toggle="modal" data-bs-target="#refuseAnnul-{{ $demande->id }}">Refuser</button>
                                    @else
                                        <span class="text-muted small">Traitée</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    @foreach ($demandes as $demande)
        @if(!$demande->est_traite)
        {{-- APPROUVER --}}
        <div class="modal fade" id="approveAnnul-{{ $demande->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('show.traiterDemandeAnnulation', $demande) }}">
                        @csrf
                        <input type="hidden" name="rep" value="1">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title text-white">Approuver l'annulation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Annuler définitivement la {{ $demande->type_affaire === 'LOCATION' ? 'location' : 'commande' }}
                                <strong>{{ $demande->infos->numero }}</strong> de
                                <strong>{{ $demande->client->nom ?? '' }} {{ $demande->client->prenom ?? '' }}</strong> ?</p>
                            @if ($demande->infos->paye > 0)
                                <div class="alert alert-warning py-2">
                                    <strong>{{ number_format($demande->infos->paye, 0, ',', ' ') }} FCFA déjà encaissés</strong> sur ce service :
                                    le remboursement devra être traité manuellement (le client en sera informé par email).
                                </div>
                            @endif
                            <div class="mb-2">
                                <label class="form-label">Commentaire pour le client (optionnel)</label>
                                <textarea name="note" class="form-control" rows="2" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-success btn-sm">Approuver et envoyer email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- REFUSER --}}
        <div class="modal fade" id="refuseAnnul-{{ $demande->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('show.traiterDemandeAnnulation', $demande) }}">
                        @csrf
                        <input type="hidden" name="rep" value="2">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title text-white">Refuser la demande d'annulation</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Refuser la demande d'annulation de <strong>{{ $demande->infos->numero }}</strong> ?
                                La {{ $demande->type_affaire === 'LOCATION' ? 'location' : 'commande' }} restera active.</p>
                            <div class="mb-2">
                                <label class="form-label fw-bold">Motif du refus <span class="text-danger">*</span></label>
                                <textarea name="note" class="form-control" rows="3" maxlength="1000" required
                                          placeholder="Ce motif sera envoyé au client par email."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-danger btn-sm">Refuser et envoyer email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    @endforeach
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection
@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#liste');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [],
                });
            }
        });
    </script>
@endsection
