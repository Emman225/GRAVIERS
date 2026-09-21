@extends('layout.main')
@section('title','Demande de paiement')

@php
    use Carbon\Carbon;
    $solde = (float) ($user->solde ?? 0);
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Demande de paiement
                </h2>
                <p class="dash-welcome-subtitle">
                    Soumettez une demande de retrait sur votre solde — profil <strong>{{ $profilLabel }}</strong>
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-account_balance_wallet"></i>
                    <span>{{ number_format($solde, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== FLASH MESSAGES ===== --}}
    @if (session('error'))
        <div class="alert alert-danger d-flex align-items-center" id="notify">
            <i class="material-icons md-error me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif
    @if (session('success'))
        <div class="alert alert-success d-flex align-items-center" id="notify">
            <i class="material-icons md-check_circle me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-monetization_on"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Solde disponible</div>
                    <div class="kpi-card-value">{{ number_format($solde, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">disponible au retrait</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-pending_actions"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">En attente</div>
                    <div class="kpi-card-value">{{ $totalEnAttente }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ number_format($montantEnAttente, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-payment"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Reçus</div>
                    <div class="kpi-card-value">{{ $totalPayees }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ number_format($montantRecu, 0, ',', ' ') }} FCFA reçus</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-receipt_long"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total paiements</div>
                    <div class="kpi-card-value">{{ $totalDemandes }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">historique cumulé</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== FORM + HISTORIQUE ===== --}}
    <div class="row g-3 mb-4">
        {{-- Form Card --}}
        <div class="col-lg-5">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-edit text-primary"></i>
                        Nouvelle demande
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" id="form" action="{{ route('show.demandeDepaie') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="material-icons md-payments text-primary" style="font-size:18px;vertical-align:middle"></i>
                                Montant souhaité
                            </label>
                            <div class="input-group">
                                <input class="form-control form-control-lg" name="montant" required id="montant"
                                       placeholder="0" type="number" min="1" max="{{ (int) $solde }}" />
                                <span class="input-group-text bg-light fw-bold">FCFA</span>
                            </div>
                            <small class="text-muted">Montant maximum : {{ number_format($solde, 0, ',', ' ') }} FCFA</small>
                            <small class="text-danger d-block" id="error"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="material-icons md-credit_card text-primary" style="font-size:18px;vertical-align:middle"></i>
                                Numéro de compte
                            </label>
                            <input class="form-control" name="numero" required id="numeroCompte"
                                   placeholder="Ex : 0707XXXXXX ou IBAN" type="text" />
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                <i class="material-icons md-account_balance text-primary" style="font-size:18px;vertical-align:middle"></i>
                                Mode de paiement
                            </label>
                            <select class="form-select" name="modePaie" id="modePaie" required>
                                <option value="">— Sélectionner —</option>
                                @foreach ($modesPaie as $mode)
                                    <option value="{{ $mode->id }}">{{ $mode->libelle }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                            <i class="material-icons md-send"></i> Envoyer la demande
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Historique --}}
        <div class="col-lg-7">
            <div class="card dash-card h-100">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-history text-primary"></i>
                        Historique de mes paiements
                    </h5>
                </div>
                <div class="card-body p-0">
                    <x-export-buttons table-id="listeDemandesDePaie"
                                      filename="demandes-de-paie"
                                      title="Demandes de paie" />
                    <div class="table-responsive">
                        <table id="listeDemandesDePaie" class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Référence</th>
                                    <th>Date</th>
                                    <th>Origine</th>
                                    <th class="text-end">Montant</th>
                                    <th>Mode</th>
                                    <th class="text-center">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($mouvements as $m)
                                    @php
                                        $badge = match ($m->statut) {
                                            1       => 'bg-success',
                                            2       => 'bg-danger',
                                            default => 'bg-warning text-dark',
                                        };
                                        $label = match ($m->statut) {
                                            1       => 'PAYÉ',
                                            2       => 'REFUSÉ',
                                            default => 'EN ATTENTE',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong class="text-primary">{{ $m->reference }}</strong>
                                            <br><small class="text-muted">{{ $m->detail }}</small>
                                        </td>
                                        <td>{{ $m->date ? \Help::dateHeure($m->date) : '—' }}</td>
                                        <td>
                                            {{-- Qui a lancé le versement : vous, ou l'entreprise
                                                 de sa propre initiative. Les seconds n'étaient
                                                 visibles nulle part sur cet écran. --}}
                                            @if ($m->origine === 'Vous')
                                                <span class="badge bg-info">Votre demande</span>
                                            @else
                                                <span class="badge bg-secondary">L'entreprise</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-bold">{{ number_format($m->montant, 0, ',', ' ') }} FCFA</td>
                                        <td>{{ $m->mode ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $badge }}">{{ $label }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Aucun paiement pour le moment.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('jsParts')
    <script>
        (function () {
            var form    = document.getElementById('form');
            var montant = document.getElementById('montant');
            var error   = document.getElementById('error');
            if (!form || !montant) return;

            var solde = {{ (float) $solde }};

            form.addEventListener('submit', function (e) {
                error.textContent = '';
                var v = parseFloat(montant.value);
                if (!v || v < 1) {
                    e.preventDefault();
                    error.textContent = 'Veuillez entrer un montant supérieur à 0.';
                    return;
                }
                if (v > solde) {
                    e.preventDefault();
                    error.textContent = 'Le montant dépasse votre solde disponible.';
                    return;
                }
            });
        })();
    </script>
@endsection
