@include('layout.head')
@section('title', 'Nouveau mot de passe')

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
                                    Définir<br>
                                    <span class="auth-side-highlight">un nouveau MDP 🔒</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Dernière étape ! Choisissez un nouveau mot de passe sécurisé pour protéger votre compte.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-check_circle"></i></span>
                                        <div><h6>Min. 4 caractères</h6><p>Plus c'est long, mieux c'est</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-fingerprint"></i></span>
                                        <div><h6>Mélangez</h6><p>Lettres, chiffres, symboles</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-vpn_key"></i></span>
                                        <div><h6>Mémorisez</h6><p>Ne le partagez jamais</p></div>
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
                                    <h1 class="auth-form-title">Nouveau mot de passe 🔒</h1>
                                    <p class="auth-form-subtitle">
                                        Saisissez et confirmez votre nouveau mot de passe.
                                    </p>
                                </div>

                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif

                                <form method="post" action="" id="form" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="password">Nouveau mot de passe</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-lock"></i>
                                            <input type="password" required name="password" id="password"
                                                   class="auth-form-control" placeholder="••••••••" autofocus />
                                            <span class="auth-input-toggle" id="oeil" onclick="togglePassword()">
                                                <i class="fa-solid fa-eye-slash"></i>
                                            </span>
                                        </div>
                                        <span style="color: #ef4444; font-size: 0.85rem;" id="erreurCourt"></span>
                                    </div>

                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="password2">Confirmation</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-lock"></i>
                                            <input type="password" required name="password2" id="password2"
                                                   class="auth-form-control" placeholder="••••••••" />
                                            <span class="auth-input-toggle" id="oeil2" onclick="togglePassword(2)">
                                                <i class="fa-solid fa-eye-slash"></i>
                                            </span>
                                        </div>
                                        <span style="color: #ef4444; font-size: 0.85rem;" id="erreur"></span>
                                    </div>

                                    <input type="hidden" value="{{ session('email') }}" name="email" />

                                    <button type="submit" class="auth-btn-submit">
                                        <i class="material-icons md-update"></i>
                                        Mettre à jour le mot de passe
                                    </button>

                                    <div class="auth-form-divider"><span>ou</span></div>

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

<script>
    function togglePassword(n) {
        var idInput = n === 2 ? 'password2' : 'password';
        var idEye = n === 2 ? 'oeil2' : 'oeil';
        var input = document.getElementById(idInput);
        var icon = document.querySelector('#' + idEye + ' i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fa-solid fa-eye';
        } else {
            input.type = 'password';
            icon.className = 'fa-solid fa-eye-slash';
        }
    }

    document.getElementById('form').addEventListener('submit', function (e) {
        var password = document.getElementById('password');
        var password2 = document.getElementById('password2');
        var erreur = document.getElementById('erreur');
        var erreurCourt = document.getElementById('erreurCourt');
        erreur.innerHTML = '';
        erreurCourt.innerHTML = '';

        if (password.value.length < 4) {
            erreurCourt.innerHTML = 'Le mot de passe doit contenir au moins 4 caractères.';
            e.preventDefault();
        } else if (password.value.trim() !== password2.value.trim()) {
            erreur.innerHTML = 'Les mots de passe ne correspondent pas.';
            e.preventDefault();
        }
    });
</script>

@include('layout.footer')
