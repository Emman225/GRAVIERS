@include('layout.head')
@section('title', 'Code de réinitialisation')

<main class="auth-page-wrap">
    <div class="auth-bg-shape auth-bg-shape-1"></div>
    <div class="auth-bg-shape auth-bg-shape-2"></div>
    <div class="auth-bg-shape auth-bg-shape-3"></div>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-xl-9 col-lg-11">
                <div class="auth-card">
                    <div class="row g-0">
                        <div class="col-lg-6 d-none d-lg-flex auth-side-visual variant-reset">
                            <div class="auth-side-content">
                                <div class="auth-brand">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM" class="auth-brand-logo">
                                </div>
                                <h2 class="auth-side-title">
                                    Vérification<br>
                                    <span class="auth-side-highlight">par code 🔐</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Un code à usage unique vient de vous être envoyé par email. Saisissez-le pour continuer.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-mark_email_read"></i></span>
                                        <div><h6>Email envoyé</h6><p>Vérifiez votre boîte de réception</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-timer"></i></span>
                                        <div><h6>Code temporaire</h6><p>Valable quelques minutes</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-shield"></i></span>
                                        <div><h6>Sécurité maximale</h6><p>Chiffrement bout en bout</p></div>
                                    </div>
                                </div>
                            </div>
                            <div class="auth-side-overlay"></div>
                        </div>

                        <div class="col-lg-6">
                            <div class="auth-form-wrap">
                                <div class="auth-mobile-brand d-lg-none mb-4 text-center">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM">
                                </div>

                                <div class="auth-form-header">
                                    <h1 class="auth-form-title">Saisir le code 🔢</h1>
                                    <p class="auth-form-subtitle">
                                        Nous avons envoyé un code de réinitialisation par email. Saisissez-le ci-dessous.
                                    </p>
                                </div>

                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif

                                <form method="post" action="" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="code">Code reçu par email</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-pin"></i>
                                            <input type="number" required name="code" id="code"
                                                   class="auth-form-control" placeholder="Entrez le code à 6 chiffres" autofocus
                                                   style="letter-spacing: 0.4em; font-size: 1.1rem; font-weight: 600;" />
                                        </div>
                                    </div>

                                    <button type="submit" class="auth-btn-submit">
                                        <i class="material-icons md-verified"></i>
                                        Vérifier le code
                                    </button>

                                    <div class="auth-form-divider"><span>ou</span></div>

                                    <p class="auth-form-footer text-center mb-1">
                                        <a href="{{ route('demandeEmail') }}" class="auth-form-link-strong">
                                            <i class="material-icons md-refresh" style="vertical-align: middle; font-size: 16px;"></i>
                                            Renvoyer un nouveau code
                                        </a>
                                    </p>
                                    <p class="auth-form-footer text-center">
                                        <a href="{{ \App\Http\Controllers\ResetProcessController::resetBackLogin() }}" class="auth-form-link">
                                            <i class="material-icons md-arrow_back" style="vertical-align: middle; font-size: 16px;"></i>
                                            Retour à la connexion
                                        </a>
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

@include('layout.footer')
