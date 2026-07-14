@include('layout.head')
@section('title', 'Connexion Livreur')

<main class="auth-page-wrap">
    <div class="auth-bg-shape auth-bg-shape-1"></div>
    <div class="auth-bg-shape auth-bg-shape-2"></div>
    <div class="auth-bg-shape auth-bg-shape-3"></div>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-xl-9 col-lg-11">
                <div class="auth-card">
                    <div class="row g-0">
                        <div class="col-lg-6 d-none d-lg-flex auth-side-visual variant-livreur">
                            <div class="auth-side-content">
                                <div class="auth-brand">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM" class="auth-brand-logo">
                                </div>
                                <h2 class="auth-side-title">
                                    Espace<br>
                                    <span class="auth-side-highlight">Livreur 🚚</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Bienvenue sur votre espace livreur. Consultez vos missions, validez vos livraisons et suivez vos paiements en temps réel.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-local_shipping"></i></span>
                                        <div><h6>Mes missions</h6><p>Bons d'enlèvement & livraisons</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-payments"></i></span>
                                        <div><h6>Paiements</h6><p>Cycle hebdomadaire</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-map"></i></span>
                                        <div><h6>Itinéraires</h6><p>Géolocalisation intégrée</p></div>
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
                                    <h1 class="auth-form-title">Connexion 🚚</h1>
                                    <p class="auth-form-subtitle">
                                        Connectez-vous pour accéder à votre espace livreur.
                                    </p>
                                </div>

                                @if (session('modified'))
                                    <div class="alert alert-success auth-alert">{{ session('modified') }}</div>
                                @endif
                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif
                                @if (session('block'))
                                    <div class="alert alert-info auth-alert">{{ session('block') }}</div>
                                @endif

                                <form method="post" action="" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="login">Identifiant</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-person"></i>
                                            <input type="text" required name="login" id="login"
                                                   value="{{ old('login') }}"
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
                                        <a class="auth-form-link" href="{{ route('demandeEmail', ['from' => 'livreur']) }}">
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
