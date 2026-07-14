@php
    use Carbon\Carbon;
    $totalAdmins = $admins->count();
    $totalActifs = $admins->where('statut', 1)->count();
@endphp

@extends('layout.main')
@section('title', 'Liste des administrateurs')

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Liste des <span class="dash-welcome-name">Administrateurs</span> 🛡️
                </h2>
                <p class="dash-welcome-subtitle">
                    Comptes avec privilèges complets — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.registerAdmin') }}" class="btn btn-primary">
                    <i class="material-icons md-plus"></i> Ajouter un nouveau administrateur
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success">
            <i class="material-icons md-check_circle align-middle"></i>
            {{ session('ok') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('error') }}
        </div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-admin_panel_settings"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total administrateurs</div>
                    <div class="kpi-card-value">{{ $totalAdmins }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-verified_user"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Comptes actifs</div>
                    <div class="kpi-card-value">{{ $totalActifs }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    {{-- ===== TABLEAU ===== --}}
    <div class="card dash-card mb-4">
        <div class="card-body">
            <x-export-buttons table-id="listeAdmins" filename="liste-administrateurs" title="Liste des administrateurs" />
            <div class="table-responsive">
                <table id="listeAdmins" class="table dash-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Administrateur</th>
                            <th>Email</th>
                            <th class="text-center">Téléphone</th>
                            <th class="text-center">Identifiant</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Enregistré le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                            <tr @if($admin->id === Auth::id()) style="background-color: #eff6ff;" @endif>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        @php
                                            $photoPath = $admin->photo
                                                ? 'storage/imageUser/' . $admin->photo
                                                : null;
                                            $photoExists = $photoPath
                                                && file_exists(public_path($photoPath));
                                        @endphp
                                        @if ($photoExists)
                                            <img src="{{ asset($photoPath) }}"
                                                 alt="{{ $admin->nom_prenoms }}"
                                                 style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;" />
                                        @else
                                            <div class="dash-counter-icon dash-counter-icon-primary"
                                                 style="width:42px;height:42px;font-size:18px;border-radius:50%;"
                                                 title="Aucune photo">
                                                <i class="material-icons md-admin_panel_settings"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <strong>{{ $admin->nom_prenoms }}</strong>
                                            @if ($admin->id === Auth::id())
                                                <span class="badge bg-info" style="font-size:0.7rem;">Vous</span>
                                            @endif
                                            <div class="text-muted small">ID #{{ $admin->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $admin->email ?? '-' }}</td>
                                <td class="text-center">{{ $admin->contact ?? '-' }}</td>
                                <td class="text-center">
                                    <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">{{ $admin->login }}</code>
                                </td>
                                <td class="text-center">
                                    @if ($admin->statut == 1)
                                        <span class="badge bg-success">Actif</span>
                                    @else
                                        <span class="badge bg-secondary">Inactif</span>
                                    @endif
                                </td>
                                <td class="text-center text-muted small">
                                    {{ $admin->created_at ? Carbon::parse($admin->created_at)->locale('fr')->isoFormat('D MMM YYYY') : '-' }}
                                </td>
                                <td class="text-end">
                                    @if ($admin->id === Auth::id())
                                        <span class="text-muted small"><em>Votre compte</em></span>
                                    @else
                                        @php
                                            // Le dernier admin actif ne peut pas être désactivé/supprimé
                                            $estDernierActif = ($totalActifs <= 1 && (int) $admin->statut === 1);
                                        @endphp

                                        <div class="dropdown d-inline">
                                            <a href="#" data-bs-toggle="dropdown" class="btn btn-light btn-sm rounded">
                                                <i class="material-icons md-more_horiz"></i> Actions
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-end">

                                                {{-- Désactiver / Réactiver --}}
                                                @if ($estDernierActif)
                                                    <span class="dropdown-item text-muted disabled" title="Dernier admin actif">
                                                        <i class="material-icons md-block"></i> Désactiver
                                                    </span>
                                                @else
                                                    <form action="{{ route('show.toggleAdminStatus', $admin->id) }}"
                                                          method="POST"
                                                          class="d-inline js-delete-form"
                                                          data-confirm-mode="confirm"
                                                          data-confirm-title="{{ (int) $admin->statut === 1 ? 'Désactiver' : 'Réactiver' }} le compte"
                                                          data-confirm-text="{{ (int) $admin->statut === 1
                                                              ? 'Confirmez-vous la désactivation de ' . $admin->nom_prenoms . ' ? Ce compte ne pourra plus se connecter tant qu\'il ne sera pas réactivé.'
                                                              : 'Confirmez-vous la réactivation de ' . $admin->nom_prenoms . ' ? Ce compte pourra à nouveau se connecter.' }}"
                                                          data-confirm-button="Oui, {{ (int) $admin->statut === 1 ? 'désactiver' : 'réactiver' }}">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item {{ (int) $admin->statut === 1 ? 'text-warning' : 'text-success' }}">
                                                            @if ((int) $admin->statut === 1)
                                                                <i class="material-icons md-block"></i> Désactiver
                                                            @else
                                                                <i class="material-icons md-check_circle"></i> Réactiver
                                                            @endif
                                                        </button>
                                                    </form>
                                                @endif

                                                {{-- Supprimer --}}
                                                @if ($estDernierActif)
                                                    <span class="dropdown-item text-muted disabled" title="Dernier admin actif">
                                                        <i class="material-icons md-delete"></i> Supprimer
                                                    </span>
                                                @else
                                                    <form action="{{ route('show.deleteAdmin', $admin->id) }}"
                                                          method="POST"
                                                          class="d-inline js-delete-form"
                                                          data-item-name="{{ $admin->nom_prenoms }}"
                                                          data-confirm-text="Cette action est irréversible. L'administrateur ne pourra plus se connecter et son compte sera marqué comme supprimé.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="material-icons md-delete"></i> Supprimer
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Aucun administrateur enregistré.
                                    <a href="{{ route('show.registerAdmin') }}">Créer le premier</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===== INFO SÉCURITÉ ===== --}}
    <div class="alert alert-info" style="border-radius:14px;border:none;border-left:4px solid #3b82f6;background:#eff6ff;color:#1e3a8a;">
        <div class="d-flex align-items-start gap-3">
            <i class="material-icons md-info" style="font-size:24px;color:#3b82f6;"></i>
            <div class="flex-grow-1">
                <strong>À propos des comptes administrateur</strong>
                <ul class="mb-0 mt-1" style="font-size:0.9rem;">
                    <li>Privilèges complets : validation des paiements, gestion utilisateurs, configuration</li>
                    <li>Double validation : un admin ne peut pas valider ses propres opérations — un 2e admin est nécessaire</li>
                    <li>Recommandé : maintenir au moins 2 comptes admin actifs pour ne jamais bloquer le workflow</li>
                </ul>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var $table = $('#listeAdmins');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[5, 'desc']],
                });
            }
        });
    </script>
@endsection
