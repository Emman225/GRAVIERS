@php
    use Illuminate\Support\Carbon;

    // Libellés lisibles des clés de documents justificatifs (mêmes clés que les
    // formulaires web et mobile de demande de compte à terme).
    $docLibelles = [
        'rccm'     => 'RCCM / Registre de commerce',
        'bilan'    => 'Attestation de revenus / bilan',
        'piece_id' => "Pièce d'identité",
        'autre'    => 'Autre document',
    ];
@endphp


@extends('layout.main')
@section('title','Demandes Client à terme')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Demandes de compte Client à terme</h2>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Client</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Ouverture de compte</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Objet</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Documents</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">État</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($demandes as $demande)
                            @php $docs = is_array($demande->documents_path) ? $demande->documents_path : []; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $demande->client->nom }} {{ $demande->client->prenom }}</strong>
                                    @if($demande->client->user)
                                        <br><small class="text-muted">{{ $demande->client->user->email }}</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $demande->client->created_at ? Carbon::parse($demande->client->created_at)->format('d-m-Y') : '-' }}</td>
                                <td>
                                    <strong>{{ $demande->objet }}</strong>
                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($demande->description, 120) }}</small>
                                </td>
                                <td class="text-center">
                                    @if(empty($docs))
                                        <span class="text-muted small">Aucun</span>
                                    @else
                                        @foreach($docs as $key => $path)
                                            <div class="mb-1 text-nowrap">
                                                <a href="{{ route('show.demandeClientTermeDocument', ['demande' => $demande->id, 'key' => $key, 'mode' => 'inline']) }}"
                                                   target="_blank" rel="noopener" class="badge bg-info text-decoration-none"
                                                   title="Voir dans un nouvel onglet">
                                                    <i class="material-icons md-visibility" style="font-size:12px;vertical-align:middle;"></i>
                                                    {{ $docLibelles[$key] ?? $key }}
                                                </a>
                                                <a href="{{ route('show.demandeClientTermeDocument', ['demande' => $demande->id, 'key' => $key, 'mode' => 'download']) }}"
                                                   class="badge bg-secondary text-decoration-none" title="Télécharger">
                                                    <i class="material-icons md-download" style="font-size:12px;vertical-align:middle;"></i>
                                                </a>
                                            </div>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($demande->approuve == 0)
                                        <span class="badge bg-secondary">En attente</span>
                                    @elseif ($demande->approuve == 1)
                                        <span class="badge bg-success">Approuvée</span>
                                        @if($demande->plafond_credit)
                                            <br><small>Plafond : {{ number_format($demande->plafond_credit, 0, ',', ' ') }} FCFA</small>
                                        @endif
                                        @if($demande->delai_paiement)
                                            <br><small>Délai : {{ $demande->delai_paiement }} j</small>
                                        @endif
                                    @else
                                        <span class="badge bg-danger">Refusée</span>
                                        @if($demande->motif_refus)
                                            <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($demande->motif_refus, 60) }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($demande->approuve == 0)
                                        <button type="button" class="btn btn-sm btn-success rounded font-sm me-1"
                                                data-bs-toggle="modal" data-bs-target="#approveModal-{{ $demande->id }}"
                                                onclick="loadStats({{ $demande->id }})">Approuver</button>
                                        <button type="button" class="btn btn-sm btn-danger rounded font-sm"
                                                data-bs-toggle="modal" data-bs-target="#refuseModal-{{ $demande->id }}">Refuser</button>
                                    @else
                                        <span class="text-muted small">Décision le<br>{{ $demande->decided_at ? Carbon::parse($demande->decided_at)->format('d-m-Y H:i') : '-' }}</span>
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
        @if($demande->approuve == 0)
        {{-- Modal APPROUVER --}}
        <div class="modal fade" id="approveModal-{{ $demande->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="post" action="{{ route('show.validationDemande', ['demande' => $demande->id, 'rep' => 1]) }}">
                        @csrf
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title text-white">Approuver la demande de {{ $demande->client->nom }} {{ $demande->client->prenom }}</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            {{-- Vérifications automatiques --}}
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-dark"><strong>Vérifications automatiques</strong></div>
                                <div class="card-body" id="stats-{{ $demande->id }}">
                                    <p class="text-muted text-center mb-0">Chargement des statistiques…</p>
                                </div>
                            </div>

                            {{-- Documents justificatifs joints par le client : consultables
                                 directement pendant la validation --}}
                            @php $docsModal = is_array($demande->documents_path) ? $demande->documents_path : []; @endphp
                            <div class="card border-primary mb-3">
                                <div class="card-header" style="background-color:#1c57a3; color:#fff;"><strong>Documents justificatifs</strong></div>
                                <div class="card-body py-2">
                                    @if(empty($docsModal))
                                        <span class="text-muted small">Aucun document joint à cette demande.</span>
                                    @else
                                        @foreach($docsModal as $key => $path)
                                            <div class="d-flex align-items-center justify-content-between border-bottom py-1">
                                                <span>{{ $docLibelles[$key] ?? $key }}</span>
                                                <span class="text-nowrap">
                                                    <a href="{{ route('show.demandeClientTermeDocument', ['demande' => $demande->id, 'key' => $key, 'mode' => 'inline']) }}"
                                                       target="_blank" rel="noopener" class="btn btn-sm btn-info">
                                                        <i class="material-icons md-visibility" style="font-size:14px;vertical-align:middle;"></i> Voir
                                                    </a>
                                                    <a href="{{ route('show.demandeClientTermeDocument', ['demande' => $demande->id, 'key' => $key, 'mode' => 'download']) }}"
                                                       class="btn btn-sm btn-secondary">
                                                        <i class="material-icons md-download" style="font-size:14px;vertical-align:middle;"></i>
                                                    </a>
                                                </span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Plafond de crédit (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" min="0" step="1" name="plafond_credit" class="form-control" required placeholder="Ex: 5000000">
                                <small class="text-muted">Montant maximum que le client pourra avoir en encours.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Délai de paiement (en jours) <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="365" step="1" name="delai_paiement" class="form-control" required placeholder="Ex: 30">
                                <small class="text-muted">Nombre de jours dont disposera le client pour régler chaque facture.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Commentaire (optionnel)</label>
                                <textarea name="commentaire_admin" class="form-control" rows="3" maxlength="1000"
                                          placeholder="Notes internes ou conditions particulières communiquées au client par email."></textarea>
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

        {{-- Modal REFUSER --}}
        <div class="modal fade" id="refuseModal-{{ $demande->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('show.validationDemande', ['demande' => $demande->id, 'rep' => 2]) }}">
                        @csrf
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title text-white">Refuser la demande</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Refuser la demande de <strong>{{ $demande->client->nom }} {{ $demande->client->prenom }}</strong> ?</p>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Motif du refus <span class="text-danger">*</span></label>
                                <textarea name="motif_refus" class="form-control" rows="4" minlength="5" maxlength="1000" required
                                          placeholder="Expliquez les raisons du refus — ce message sera envoyé au client par email."></textarea>
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
        $(function() {
            $('#liste').DataTable({
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
            });
        });

        function loadStats(demandeId) {
            const container = document.getElementById('stats-' + demandeId);
            if (!container) return;
            container.innerHTML = '<p class="text-muted text-center mb-0">Chargement des statistiques…</p>';
            const url = "{{ url('demande-client-terme') }}/" + demandeId + "/stats";
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    if (d.error) { container.innerHTML = '<p class="text-danger mb-0">'+ d.error +'</p>'; return; }
                    const f = (n) => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));
                    container.innerHTML = `
                        <div class="row text-center">
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Ancienneté</div>
                                <strong>${f(d.anciennete_jours)} jours</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Nb commandes</div>
                                <strong>${f(d.nb_commandes)}</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Montant total commandé</div>
                                <strong>${f(d.montant_total)} FCFA</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Paiements validés</div>
                                <strong>${f(d.nb_paiements_valides)}</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Montant payé</div>
                                <strong>${f(d.montant_paye)} FCFA</strong>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="text-muted small">Taux de paiement</div>
                                <strong>${d.taux_paiement}%</strong>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="row small">
                            <div class="col-md-6"><strong>Email :</strong> ${d.client_email || '-'}</div>
                            <div class="col-md-6"><strong>Contact :</strong> ${d.client_contact || '-'}</div>
                        </div>
                    `;
                })
                .catch(err => {
                    container.innerHTML = '<p class="text-danger mb-0">Erreur de chargement des statistiques.</p>';
                    console.error(err);
                });
        }
    </script>
@endsection
