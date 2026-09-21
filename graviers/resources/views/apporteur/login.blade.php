@include('layout.head')
@section('title', 'Connexion apporteur')

<main class="auth-page-wrap">
    @include('layout._retourSitePublic')
    <div class="auth-bg-shape auth-bg-shape-1"></div>
    <div class="auth-bg-shape auth-bg-shape-2"></div>
    <div class="auth-bg-shape auth-bg-shape-3"></div>

    <div class="container py-5">
        <div class="row justify-content-center align-items-center">
            <div class="col-xl-9 col-lg-11">
                <div class="auth-card">
                    <div class="row g-0">
                        <div class="col-lg-6 d-none d-lg-flex auth-side-visual variant-apporteur">
                            <div class="auth-side-content">
                                <div class="auth-brand">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="Mon Gravier" class="auth-brand-logo">
                                </div>
                                <h2 class="auth-side-title">
                                    Espace<br>
                                    <span class="auth-side-highlight">Apporteur 🤝</span>
                                </h2>
                                <p class="auth-side-subtitle">
                                    Bienvenue sur votre espace apporteur d'affaires. Suivez vos filleuls, vos commissions et vos paiements.
                                </p>
                                <div class="auth-features">
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-people"></i></span>
                                        <div><h6>Filleuls</h6><p>Vos clients parrainés</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-pie_chart"></i></span>
                                        <div><h6>Commissions</h6><p>Calculées automatiquement</p></div>
                                    </div>
                                    <div class="auth-feature">
                                        <span class="auth-feature-icon"><i class="material-icons md-payments"></i></span>
                                        <div><h6>Paiements</h6><p>Versés à l'encaissement</p></div>
                                    </div>
                                </div>
                            </div>
                            <div class="auth-side-overlay"></div>
                        </div>

                        <div class="col-lg-6">
                            <div class="auth-form-wrap">
                                <div class="auth-mobile-brand d-lg-none mb-4 text-center">
                                    <img src="{{ asset(config('constantes.logo')) }}" alt="Mon Gravier">
                                </div>

                                <div class="auth-form-header">
                                    <h1 class="auth-form-title">Connexion 🤝</h1>
                                    <p class="auth-form-subtitle">
                                        Connectez-vous pour accéder à votre espace apporteur d'affaires.
                                    </p>
                                </div>

                                @if (session('succes'))
                                    <div class="alert alert-success auth-alert">{{ session('succes') }}</div>
                                @endif
                                @if (session('fail'))
                                    <div class="alert alert-danger auth-alert">{{ session('fail') }}</div>
                                @endif
                                @if (session('failToken'))
                                    <div class="alert alert-info auth-alert">{{ session('failToken') }}</div>
                                @endif
                                @if (session('failInfo'))
                                    <div class="alert alert-danger auth-alert">{{ session('failInfo') }}</div>
                                @endif
                                @if (session('block'))
                                    <div class="alert alert-info auth-alert">{{ session('block') }}</div>
                                @endif

                                <form method="post" action="" class="auth-form">
                                    @csrf
                                    <div class="auth-form-group">
                                        <label class="auth-form-label" for="email">Email</label>
                                        <div class="auth-input-icon">
                                            <i class="material-icons md-email"></i>
                                            <input type="email" required name="email" id="email"
                                                   class="auth-form-control" placeholder="exemple@gmail.com" autofocus />
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
                                        <a class="auth-form-link" href="{{ route('demandeEmail', ['from' => 'apporteur']) }}">
                                            Mot de passe oublié ?
                                        </a>
                                    </div>

                                    <button type="submit" class="auth-btn-submit">
                                        <i class="material-icons md-login"></i>
                                        Se connecter
                                    </button>

                                    {{-- LE CHEMIN VERS L'INSCRIPTION.
                                         La page d'inscription apporteur existait, mais
                                         aucune page n'y menait : il fallait connaitre
                                         l'adresse par coeur. Un apporteur pressenti qui
                                         arrivait ici ne pouvait que repartir.

                                         Meme motif que les ecrans de reinitialisation :
                                         separateur puis lien fort, pour ne pas concurrencer
                                         le bouton de connexion, qui reste l'action
                                         principale de cette page. --}}
                                    <div class="auth-form-divider"><span>ou</span></div>

                                    <p class="auth-form-footer text-center mb-0">
                                        Pas encore apporteur d'affaire ?
                                        <a href="{{ route('apporteur.register') }}" class="auth-form-link-strong">
                                            <i class="material-icons md-person_add" style="vertical-align: middle; font-size: 16px;"></i>
                                            Créer mon compte
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
