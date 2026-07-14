@extends('layout.main')
@section('title','Apporteur - Paramètre')

@php
    $userName  = $apporteur->user->nom_prenoms ?? 'Apporteur';
    $firstName = explode(' ', $userName)[0];
    $initials  = strtoupper(mb_substr($firstName, 0, 1));
@endphp

@section('contenu')
    <div class="screen-overlay"></div>

    {{-- ===== HEADER ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Mes paramètres
                </h2>
                <p class="dash-welcome-subtitle">
                    Mettez à jour vos informations personnelles et votre mot de passe
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

    {{-- ===== FLASH MESSAGES ===== --}}
    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center">
            <i class="material-icons md-check_circle me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    <div class="row g-4">
        {{-- === PROFILE CARD === --}}
        <div class="col-lg-4">
            <div class="premium-form-card">
                <div class="premium-form-body text-center">
                    <div class="parametre-avatar mx-auto mb-3">{{ $initials ?: 'A' }}</div>
                    <h5 class="mb-1 fw-bold">{{ $userName }}</h5>
                    <p class="text-muted mb-3" style="font-size:0.85rem">{{ $apporteur->user->email ?? '' }}</p>

                    <div class="parametre-info-grid">
                        <div class="parametre-info-row">
                            <i class="material-icons md-percent text-primary"></i>
                            <div class="text-start">
                                <div class="parametre-info-label">Pourcentage</div>
                                <div class="parametre-info-value">{{ $apporteur->pourcentage }}%</div>
                            </div>
                        </div>
                        <div class="parametre-info-row">
                            <i class="material-icons md-account_balance_wallet text-primary"></i>
                            <div class="text-start">
                                <div class="parametre-info-label">Solde</div>
                                <div class="parametre-info-value">{{ number_format((float) $apporteur->solde, 0, ',', ' ') }} FCFA</div>
                            </div>
                        </div>
                        <div class="parametre-info-row">
                            <i class="material-icons md-loyalty text-primary"></i>
                            <div class="text-start">
                                <div class="parametre-info-label">Code parrain</div>
                                <div class="parametre-info-value">{{ $apporteur->code }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- === FORM CARD === --}}
        <div class="col-lg-8">
            <form method="post" action="{{ route('apporteur.parametreApporteur') }}">
                @csrf

                {{-- Informations personnelles --}}
                <div class="premium-form-card mb-4">
                    <div class="premium-form-header">
                        <h5><i class="material-icons md-person"></i> Informations personnelles</h5>
                        <p>Vos coordonnées affichées sur votre profil et dans les factures.</p>
                    </div>
                    <div class="premium-form-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="premium-field-label" for="nom_prenom"><i class="material-icons md-account_box"></i> Nom complet</label>
                                <input id="nom_prenom" class="form-control" required type="text" name="nom_prenom"
                                       value="{{ $apporteur->user->nom_prenoms }}" placeholder="Prénom NOM" />
                            </div>

                            <div class="col-md-6">
                                <label class="premium-field-label" for="contact"><i class="material-icons md-phone"></i> Contact</label>
                                <input id="contact" class="form-control" required type="tel" name="contact"
                                       value="{{ $apporteur->user->contact }}" placeholder="07 XX XX XX XX" />
                            </div>

                            <div class="col-md-6">
                                <label class="premium-field-label" for="email"><i class="material-icons md-mail"></i> Email</label>
                                <input id="email" class="form-control {{ session('emailExiste') ? 'is-invalid' : '' }}"
                                       required name="email" type="email" value="{{ $apporteur->user->email }}"
                                       placeholder="vous@exemple.com" />
                                @if (session('emailExiste'))
                                    <div class="invalid-feedback d-block">{{ session('emailExiste') }}</div>
                                @endif
                            </div>

                            <div class="col-12">
                                <label class="premium-field-label" for="adresse"><i class="material-icons md-home"></i> Adresse</label>
                                <input id="adresse" class="form-control" required type="text" name="adresse"
                                       value="{{ $apporteur->user->adresse }}" placeholder="Ville, quartier, rue..." />
                                @if (session('loginExiste'))
                                    <div class="invalid-feedback d-block">{{ session('loginExiste') }}</div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="premium-field-label"><i class="material-icons md-percent"></i> Pourcentage de commission</label>
                                <div class="input-group">
                                    <input class="form-control fw-bold text-center" disabled type="text"
                                           value="{{ $apporteur->pourcentage }}" />
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="form-text">Défini par l'administrateur, non modifiable.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sécurité --}}
                <div class="premium-form-card mb-4">
                    <div class="premium-form-header">
                        <h5><i class="material-icons md-lock"></i> Sécurité</h5>
                        <p>Laissez vide si vous ne souhaitez pas changer votre mot de passe.</p>
                    </div>
                    <div class="premium-form-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="premium-field-label" for="oldPassWord"><i class="material-icons md-history"></i> Ancien mot de passe</label>
                                <div class="input-group password-toggle">
                                    <input id="oldPassWord" class="form-control {{ session('errorPassword') ? 'is-invalid' : '' }}"
                                           name="oldPassWord" type="password" autocomplete="current-password" />
                                    <button class="btn btn-light" type="button" data-toggle-password="#oldPassWord">
                                        <i class="material-icons md-visibility"></i>
                                    </button>
                                </div>
                                @if (session('errorPassword'))
                                    <div class="invalid-feedback d-block">{{ session('errorPassword') }}</div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label class="premium-field-label" for="newPassWord"><i class="material-icons md-lock"></i> Nouveau mot de passe</label>
                                <div class="input-group password-toggle">
                                    <input id="newPassWord" class="form-control" name="newPassWord" type="password"
                                           autocomplete="new-password" />
                                    <button class="btn btn-light" type="button" data-toggle-password="#newPassWord">
                                        <i class="material-icons md-visibility"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="premium-field-label" for="confirmPassWord"><i class="material-icons md-lock_open"></i> Confirmer le mot de passe</label>
                                <div class="input-group password-toggle">
                                    <input id="confirmPassWord" class="form-control {{ session('passDifferent') || session('avant') ? 'is-invalid' : '' }}"
                                           name="confirmPassWord" type="password" autocomplete="new-password" />
                                    <button class="btn btn-light" type="button" data-toggle-password="#confirmPassWord">
                                        <i class="material-icons md-visibility"></i>
                                    </button>
                                </div>
                                @if (session('passDifferent'))
                                    <div class="invalid-feedback d-block">{{ session('passDifferent') }}</div>
                                @endif
                                @if (session('avant'))
                                    <div class="invalid-feedback d-block">{{ session('avant') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('apporteur.home') }}" class="btn btn-light">
                        <i class="material-icons md-close"></i> Annuler
                    </a>
                    <button class="btn btn-primary" type="submit">
                        <i class="material-icons md-save"></i> Appliquer les changements
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('cssParts')
<style>
    .parametre-avatar {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background: linear-gradient(135deg, #1c57a3, #134380);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.4rem;
        font-weight: 700;
        box-shadow: 0 6px 18px rgba(28, 87, 163, 0.25);
    }
    .parametre-info-grid { margin-top: 18px; border-top: 1px solid #f1f5f9; padding-top: 16px; }
    .parametre-info-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f5f5f5;
    }
    .parametre-info-row:last-child { border-bottom: 0; }
    .parametre-info-label { color: #6b7280; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
    .parametre-info-value { color: #111827; font-weight: 600; font-size: 0.95rem; }
    .password-toggle .btn-light { border-left: 0; }
</style>
@endsection

@section('jsParts')
<script>
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.querySelector(this.getAttribute('data-toggle-password'));
            if (!input) return;
            var icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) icon.classList.replace('md-visibility', 'md-visibility_off');
            } else {
                input.type = 'password';
                if (icon) icon.classList.replace('md-visibility_off', 'md-visibility');
            }
        });
    });
</script>
@endsection
