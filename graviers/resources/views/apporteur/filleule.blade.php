@extends('layout.main')
@section('title','Mes filleul(e)s')

@php
    use Carbon\Carbon;
    $totalClients = $clients->count();
    $nbInactifs   = $totalClients - $nbActifs;
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Mes filleul(e)s
                </h2>
                <p class="dash-welcome-subtitle">
                    Les clients que vous avez parrainés avec votre code <strong>{{ $apporteur->code }}</strong>
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-loyalty"></i>
                    <span>Code parrain :&nbsp;<strong>{{ $apporteur->code }}</strong></span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== KPI CARDS ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon">
                    <i class="material-icons md-group"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total filleul(e)s</div>
                    <div class="kpi-card-value">{{ $totalClients }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">parrainés à ce jour</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon">
                    <i class="material-icons md-verified_user"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Filleul(e)s actifs</div>
                    <div class="kpi-card-value">{{ $nbActifs }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">{{ $nbInactifs }} inactif{{ $nbInactifs > 1 ? 's' : '' }}</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-warning">
                <div class="kpi-card-icon">
                    <i class="material-icons md-business_center"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Clients à terme</div>
                    <div class="kpi-card-value">{{ $nbATerme }}</div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">comptes professionnels</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon">
                    <i class="material-icons md-monetization_on"></i>
                </div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Commissions générées</div>
                    <div class="kpi-card-value">{{ number_format($totalCommissionsFilleules, 0, ',', ' ') }}<span class="kpi-card-currency">FCFA</span></div>
                    <div class="kpi-card-meta">
                        <span class="kpi-card-meta-text">cumul par vos filleul(e)s</span>
                    </div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== LISTE DES FILLEULES ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-header dash-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="dash-card-title mb-0">
                <i class="material-icons md-people text-primary"></i>
                Liste des filleul(e)s
            </h5>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="width: 260px">
                    <span class="input-group-text bg-light"><i class="material-icons md-search" style="font-size:18px"></i></span>
                    <input type="text" id="filleuleSearch" class="form-control" placeholder="Rechercher un filleul(e)..." />
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" id="filleuleTable">
                    <thead class="table-light">
                        <tr>
                            <th>Filleul(e)</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Inscrit le</th>
                            <th class="text-center">Commandes</th>
                            <th class="text-end">Commissions</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            @php
                                $stats = $statsMap->get($client->id);
                                $nbCmd = $stats?->nb_commandes ?? 0;
                                $totalCom = (float) ($stats?->total_commission ?? 0);
                                $nomComplet = trim(($client->nom ?? '').' '.($client->prenom ?? '')) ?: 'Filleul(e) #'.$client->id;
                                $initials = strtoupper(mb_substr($client->nom ?? 'F', 0, 1).mb_substr($client->prenom ?? '', 0, 1));
                                $statutActif = (int) $client->statut === 1;
                                $aTerme = (int) $client->client_a_terme === 1;
                                // Couleur d'avatar déterministe basée sur le nom
                                $palette = ['#1c57a3', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#ef4444'];
                                $avatarColor = $palette[crc32($nomComplet) % count($palette)];
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="filleule-avatar me-3" style="background:{{ $avatarColor }}">
                                            {{ $initials ?: 'F' }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold">{{ $nomComplet }}</div>
                                            @if($client->email)
                                                <small class="text-muted">{{ $client->email }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($client->contact1)
                                        <div><i class="material-icons md-phone text-muted" style="font-size:14px;vertical-align:middle"></i> {{ $client->contact1 }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($aTerme)
                                        <span class="badge bg-info-light text-info">À terme</span>
                                    @else
                                        <span class="badge bg-light text-dark">Ordinaire</span>
                                    @endif
                                </td>
                                <td>{{ optional($client->created_at)->format('d/m/Y') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-primary-light text-primary">{{ $nbCmd }}</span>
                                </td>
                                <td class="text-end fw-bold {{ $totalCom > 0 ? 'text-success' : 'text-muted' }}">
                                    {{ number_format($totalCom, 0, ',', ' ') }} <small class="text-muted">FCFA</small>
                                </td>
                                <td class="text-center">
                                    @if($statutActif)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-secondary">Inactif</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="material-icons md-group_off" style="font-size:48px;opacity:0.3"></i>
                                    <p class="mb-0 mt-2">Aucun filleul(e) pour le moment.</p>
                                    <small>Partagez votre code <strong>{{ $apporteur->code }}</strong> pour commencer.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
<style>
    .filleule-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
</style>
@endsection

@section('jsParts')
<script>
    (function () {
        var input = document.getElementById('filleuleSearch');
        var rows  = document.querySelectorAll('#filleuleTable tbody tr');
        if (!input || !rows.length) return;
        input.addEventListener('input', function () {
            var q = this.value.toLowerCase().trim();
            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
            });
        });
    })();
</script>
@endsection
