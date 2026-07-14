@include('layout.head')
@section('title', 'Connexion Administration')

<main class="auth-page-wrap">
    <div class="auth-bg-shape auth-bg-shape-1"></div>
    <div class="auth-bg-shape auth-bg-shape-2"></div>
    <div class="auth-bg-shape auth-bg-shape-3"></div>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-xl-9 col-lg-11">
                <div class="auth-card">
                    <div class="row g-0">
                        <div class="col-lg-6 d-none d-lg-flex auth-side-visual">
                            <div class="auth-side-content">
                                <div class="auth-brand">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM" class="auth-brand-logo">
                                </div>
                                <h2 class="auth-side-title">
                                    Espace<br>
                                    <span class="auth-side-highlight">Administration</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Plateforme de gestion centralisée de GRAVIER.COM. Pilotez vos commandes, livraisons, clients et finances en toute sécurité.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-dashboard"></i></span>
                                        <div><h6>Tableau de bord</h6><p>Vue 360° de l'activité</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-analytics"></i></span>
                                        <div><h6>Rapports détaillés</h6><p>Analyses & synthèses</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-security"></i></span>
                                        <div><h6>Sécurité renforcée</h6><p>Accès protégé</p></div>
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
                                    <h1 class="auth-form-title">Connexion 🔐</h1>
                                    <p class="auth-form-subtitle">
                                        Connectez-vous pour accéder à votre espace de gestion.
                                    </p>
                                </div>

                                @if (session('modified'))
                                    <div class="alert alert-success auth-alert">{{ session('modified') }}</div>
                                @endif
                                @if (session('ok'))
                                    <div class="alert alert-success auth-alert">{{ session('ok') }}</div>
                                @endif
                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif

                                <form method="post" action="" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="login">Identifiant</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-person"></i>
                                            <input type="text" required name="login" id="login"
                                                   class="auth-form-control" placeholder="Votre login" autofocus />
                                        </div>
                                    </div>

                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="password">Mot de passe</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-lock"></i>
                                            <input type="password" required id="password" name="password"
                                                   class="auth-form-control" placeholder="••••••••" />
                                            <span class="auth-input-toggle" id="oeil" onclick="togglePassword()">
                                                <i class="fa-solid fa-eye-slash"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="auth-form-extra">
                                        <a class="auth-form-link" href="{{ route('demandeEmail', ['from' => 'admin']) }}">
                                            Mot de passe oublié ?
                                        </a>
                                    </div>

                                    <button type="submit" class="auth-btn-submit">
                                        <i class="material-icons md-login"></i>
                                        Se connecter
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function togglePassword() {
        var input = document.getElementById('password');
        var icon = document.querySelector('#oeil i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fa-solid fa-eye';
        } else {
            input.type = 'password';
            icon.className = 'fa-solid fa-eye-slash';
        }
    }
</script>

@include('layout.footer')
