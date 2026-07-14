@include('layout.head')
@section('title', 'Mot de passe oublié')

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
                                    Mot de passe<br>
                                    <span class="auth-side-highlight">oublié ?</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Pas de panique, ça arrive ! Saisissez votre adresse e-mail pour recevoir un code de réinitialisation sécurisé.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-mail"></i></span>
                                        <div><h6>1. Saisir votre email</h6><p>Adresse de votre compte</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-pin"></i></span>
                                        <div><h6>2. Recevoir un code</h6><p>Code à 6 chiffres par email</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-lock_reset"></i></span>
                                        <div><h6>3. Nouveau mot de passe</h6><p>Définir un nouveau MDP sécurisé</p></div>
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
                                    <h1 class="auth-form-title">Mot de passe oublié 🔑</h1>
                                    <p class="auth-form-subtitle">
                                        Saisissez votre adresse e-mail. Un code de réinitialisation vous sera envoyé.
                                    </p>
                                </div>

                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif

                                <form method="post" action="" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="email">Adresse e-mail</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-email"></i>
                                            <input type="email" required name="email" id="email"
                                                   class="auth-form-control" placeholder="exemple@gmail.com" autofocus />
                                        </div>
                                    </div>

                                    <button type="submit" class="auth-btn-submit">
                                        <i class="material-icons md-send"></i>
                                        Envoyer le code
                                    </button>

                                    <div class="auth-form-divider"><span>ou</span></div>

                                    <p class="auth-form-footer text-center">
                                        <a href="{{ \App\Http\Controllers\ResetProcessController::resetBackLogin() }}" class="auth-form-link-strong">
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
