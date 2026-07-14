@extends('client.main')
@section('title','Création de compte')

@section('content')
@include('client.navMobile')

<main class="hero-register">
    {{-- Couche image de fond + overlay sombre --}}
    <div class="hero-register__bg" aria-hidden="true"></div>
    <div class="hero-register__overlay" aria-hidden="true"></div>

    {{-- Décor flottant --}}
    <div class="hero-register__shape hero-register__shape--1" aria-hidden="true"></div>
    <div class="hero-register__shape hero-register__shape--2" aria-hidden="true"></div>

    {{-- Contenu centré --}}
    <div class="hero-register__content">
        <div class="hero-register__brand-line">
            <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM" class="hero-register__logo-mini">
            <span>GRAVIER.COM</span>
        </div>

        <h1 class="hero-register__hero-title">
            Rejoignez <span class="hero-register__accent">la communauté</span>
        </h1>
        <p class="hero-register__hero-subtitle">
            Créez votre compte en quelques secondes — commandez sable, gravier, ciment&hellip;
        </p>

        {{-- Carte d'inscription --}}
        <div class="hero-register__card">
            <div class="hero-register__card-header">
                <h2>Créer mon compte</h2>
                <p>
                    Vous avez déjà un compte ?
                    <a href="{{ route('client.login') }}" class="hero-register__link hero-register__link--strong">Se connecter</a>
                </p>
            </div>

            {{-- Alertes --}}
            @if(session('existEmail'))
                <div class="hero-register__alert hero-register__alert--danger">{{ session('existEmail') }}</div>
            @endif
            @if(session('failCode'))
                <div class="hero-register__alert hero-register__alert--danger">{{ session('failCode') }}</div>
            @endif

            <form method="post" action="{{ route('client.registerClient') }}" enctype="multipart/form-data" class="hero-register__form">
                @csrf

                {{-- Type de compte (cards radio) --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Qui êtes-vous ?</span>
                    <div class="hero-register__type-grid">
                        <label class="hero-register__type-card" data-type="1">
                            <input type="radio" name="type" value="1" {{ old('type', '1') == '1' ? 'checked' : '' }}>
                            <span class="hero-register__type-icon"><i class="material-icons md-person"></i></span>
                            <span class="hero-register__type-body">
                                <span class="hero-register__type-title">Particulier</span>
                                <span class="hero-register__type-desc">Pour usage personnel</span>
                            </span>
                            <span class="hero-register__type-check"><i class="material-icons md-check_circle"></i></span>
                        </label>
                        <label class="hero-register__type-card" data-type="2">
                            <input type="radio" name="type" value="2" {{ old('type') == '2' ? 'checked' : '' }}>
                            <span class="hero-register__type-icon hero-register__type-icon--alt"><i class="material-icons md-business"></i></span>
                            <span class="hero-register__type-body">
                                <span class="hero-register__type-title">Entreprise</span>
                                <span class="hero-register__type-desc">Pour usage professionnel</span>
                            </span>
                            <span class="hero-register__type-check"><i class="material-icons md-check_circle"></i></span>
                        </label>
                    </div>
                </div>

                {{-- Section IDENTITÉ --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Identité</span>

                    {{-- Nom / Prénom (particulier) --}}
                    <div class="row gx-3" id="nomPrenomRow">
                        @if(old('type', '1') == '1')
                            <div class="col-md-6 mb-3" id="nom">
                                <label class="hero-register__field-label">Nom <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-account_box"></i>
                                    <input type="text" name="nom" value="{{ old('nom') }}" placeholder="Votre nom" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('nom') }}</span>
                            </div>
                            <div class="col-md-6 mb-3" id="prenom">
                                <label class="hero-register__field-label">Prénom <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-person"></i>
                                    <input type="text" name="prenom" value="{{ old('prenom') }}" placeholder="Votre prénom" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('prenom') }}</span>
                            </div>
                        @else
                            <div class="col-md-6 mb-3" id="nom"></div>
                            <div class="col-md-6 mb-3" id="prenom"></div>
                        @endif
                    </div>

                    {{-- Raison sociale (entreprise) --}}
                    <div class="mb-3" id="raisonSociale">
                        @if(old('type') == '2')
                            <label class="hero-register__field-label">Raison Sociale <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-business"></i>
                                <input type="text" name="raisonSociale" value="{{ old('raisonSociale') }}" placeholder="Nom de l'entreprise" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('raisonSociale') }}</span>
                        @endif
                    </div>

                    <div class="mb-0">
                        <label class="hero-register__field-label">Email <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-email"></i>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="exemple@email.com" autocomplete="email" required />
                        </span>
                        <span class="hero-register__field-error">{{ $errors->first('email') }}</span>
                    </div>
                </div>

                {{-- Section LOCALISATION --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Localisation</span>
                    <div class="row gx-3">
                        <div class="col-md-4 mb-3">
                            <label class="hero-register__field-label">Pays <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-public"></i>
                                <select name="pays" required>
                                    <option value="">Sélectionner</option>
                                    @foreach ($pays as $p)
                                        <option {{ old('pays') ? (old('pays') == $p->id ? 'selected' : '') : (stripos($p->nom, 'ivoire') !== false ? 'selected' : '') }} value="{{ $p->id }}">{{ $p->nom }}</option>
                                    @endforeach
                                </select>
                            </span>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="hero-register__field-label">Ville <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-place"></i>
                                <select name="ville" required>
                                    <option value="">Sélectionner une ville</option>
                                    @foreach ($villes as $ville)
                                        <option {{ old('ville') == $ville->id ? 'selected' : '' }} value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                    @endforeach
                                </select>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('ville') }}</span>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="hero-register__field-label">Adresse <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-home"></i>
                            <input type="text" name="adresse" value="{{ old('adresse') }}" placeholder="Votre adresse complète" required />
                        </span>
                        <span class="hero-register__field-error">{{ $errors->first('adresse') }}</span>
                    </div>
                </div>

                {{-- Section CONTACTS --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Contacts</span>
                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Contact principal <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-phone"></i>
                                <input type="tel" name="contact1" value="{{ old('contact1') }}" placeholder="Ex : 0701020304" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('contact1') }}</span>
                        </div>
                        <div class="col-md-6 mb-0" id="contact2">
                            @if(old('type') == '2')
                                <label class="hero-register__field-label">Téléphone & personne à contacter <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-contact_phone"></i>
                                    <input type="text" name="contact2" value="{{ old('contact2') }}" placeholder="Contact + nom du responsable" required />
                                </span>
                            @else
                                <label class="hero-register__field-label">Contact secondaire</label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-phone_iphone"></i>
                                    <input type="tel" name="contact2" value="{{ old('contact2') }}" placeholder="Ex : 0507080910" />
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Section ENTREPRISE (RCCM, NCC, fichiers) --}}
                <div class="hero-register__section hero-register__section--commerce" id="pourCommerceWrap" style="{{ old('type') == '2' ? '' : 'display:none' }}">
                    <span class="hero-register__section-label">Documents légaux</span>
                    <div class="row gx-3" id="pourCommerce">
                        @if(old('type') == '2')
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">Registre de commerce <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-assignment"></i>
                                    <input type="text" id="rccm" name="rccm" value="{{ old('rccm') }}" placeholder="N° RCCM" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('rccm') }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">N° Compte contribuable <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-receipt"></i>
                                    <input type="text" id="ncc" name="ncc" value="{{ old('ncc') }}" placeholder="N° NCC" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('ncc') }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">DFE (PDF / Image) <span class="req">*</span></label>
                                <span class="hero-register__field-wrap hero-register__field-wrap--file">
                                    <i class="material-icons md-cloud_upload"></i>
                                    <input type="file" name="dfe" accept=".pdf,.jpg,.jpeg,.png" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('dfe') }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">Registre de commerce (PDF / Image) <span class="req">*</span></label>
                                <span class="hero-register__field-wrap hero-register__field-wrap--file">
                                    <i class="material-icons md-cloud_upload"></i>
                                    <input type="file" name="registre_commerce" accept=".pdf,.jpg,.jpeg,.png" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('registre_commerce') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Section SÉCURITÉ --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Sécurité</span>
                    <div class="row gx-3">
                        <div class="col-md-7 mb-3">
                            <label class="hero-register__field-label">Mot de passe <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-lock"></i>
                                <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="new-password" required />
                                <button type="button" class="hero-register__toggle" id="oeil" onclick="togglePassword()" aria-label="Afficher / masquer le mot de passe">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('password') }}</span>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label class="hero-register__field-label">Code parrain <span class="hero-register__field-optional">(optionnel)</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-loyalty"></i>
                                <input type="text" name="code_promo" value="{{ old('code_promo') }}" placeholder="Code de parrainage" />
                            </span>
                        </div>
                    </div>
                </div>

                {{-- CGU --}}
                <label class="hero-register__cgu">
                    <input type="checkbox" required name="condition" value="1" />
                    <span>J'accepte les <a href="{{ route('termesConditions') }}" target="_blank" rel="noopener">termes et conditions</a>.</span>
                </label>
                @error('condition')<div class="hero-register__field-error">{{ $message }}</div>@enderror

                <button type="submit" name="login" class="hero-register__submit">
                    <i class="material-icons md-person_add"></i>
                    Créer mon compte
                </button>

                <div class="hero-register__divider"><span>ou</span></div>

                <p class="hero-register__signup">
                    Déjà inscrit ?
                    <a href="{{ route('client.login') }}" class="hero-register__link hero-register__link--strong">Se connecter</a>
                </p>
            </form>
        </div>

        {{-- Trust badges --}}
        <ul class="hero-register__trust">
            <li><i class="material-icons md-verified_user"></i> Données sécurisées</li>
            <li><i class="material-icons md-bolt"></i> Inscription rapide</li>
            <li><i class="material-icons md-support_agent"></i> Support 7j/7</li>
        </ul>
    </div>
</main>

<style>
    /* ===================================================================
       HERO REGISTER — Pendant premium du hero-login
       =================================================================== */
    .hero-register {
        position: relative;
        min-height: calc(100vh - 60px);
        display: flex;
        align-items: flex-start;
        justify-content: center;
        overflow: hidden;
        padding: 50px 16px;
        isolation: isolate;
        background: linear-gradient(135deg, #0a2540 0%, #134380 50%, #c2410c 100%);
    }
    .hero-register__bg {
        position: absolute; inset: 0; z-index: -3;
        background-image: url("{{ asset('frontend/assets/imgs/banner/hero-gravier.png') }}");
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        transform: scale(1.05);
        filter: saturate(1.1) contrast(1.05) brightness(0.92);
    }
    .hero-register__overlay {
        position: absolute; inset: 0; z-index: -2;
        background:
            linear-gradient(180deg, rgba(10, 37, 64, 0.62) 0%, rgba(10, 37, 64, 0.85) 100%),
            radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.28), transparent 55%),
            radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.42), transparent 50%);
    }
    .hero-register__shape {
        position: absolute; z-index: -1;
        border-radius: 50%;
        filter: blur(80px);
        pointer-events: none;
    }
    .hero-register__shape--1 { width: 480px; height: 480px; top: -100px; right: -120px;
        background: radial-gradient(circle, rgba(251, 146, 60, 0.4), transparent 70%); }
    .hero-register__shape--2 { width: 420px; height: 420px; bottom: -140px; left: -120px;
        background: radial-gradient(circle, rgba(96, 165, 250, 0.35), transparent 70%); }

    .hero-register__content {
        position: relative; z-index: 1;
        width: 100%;
        max-width: 720px;
        color: #fff;
        text-align: center;
    }
    .hero-register__brand-line {
        display: inline-flex; align-items: center; gap: 10px;
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 16px;
        border-radius: 999px;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        color: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        letter-spacing: 0.05em;
        margin-bottom: 22px;
    }
    .hero-register__logo-mini {
        height: 28px;
        background: rgba(255, 255, 255, 0.95);
        padding: 4px 6px;
        border-radius: 6px;
    }
    .hero-register__hero-title,
    h1.hero-register__hero-title {
        font-size: 2.1rem;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -0.02em;
        margin: 0 0 10px;
        color: #ffffff !important;
        text-shadow: 0 2px 20px rgba(0, 0, 0, 0.35);
    }
    .hero-register__accent {
        background: linear-gradient(90deg, #fbbf24, #fb923c, #f87171);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        color: transparent;
    }
    .hero-register__hero-subtitle {
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.88);
        margin: 0 0 28px;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    /* === CARTE === */
    .hero-register__card {
        background: rgba(255, 255, 255, 0.97);
        border-radius: 22px;
        padding: 34px 30px 26px;
        box-shadow: 0 30px 70px rgba(0, 0, 0, 0.40), 0 0 0 1px rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        text-align: left;
        color: #1f2937;
    }
    .hero-register__card-header { text-align: center; margin-bottom: 22px; }
    .hero-register__card-header h2 {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0a2540;
        margin: 0 0 6px;
        letter-spacing: -0.01em;
    }
    .hero-register__card-header p { color: #6b7280; font-size: 0.9rem; margin: 0; }

    /* === SECTIONS === */
    .hero-register__section {
        padding: 16px 0 8px;
        border-top: 1px solid #f1f5f9;
    }
    .hero-register__section:first-of-type { padding-top: 4px; border-top: 0; }
    .hero-register__section-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7280;
        margin-bottom: 14px;
    }

    /* === Cards "Type de compte" === */
    .hero-register__type-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .hero-register__type-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: #ffffff;
        border: 2px solid #e5e7eb;
        border-radius: 14px;
        cursor: pointer;
        transition: all 0.18s ease;
    }
    .hero-register__type-card:hover { border-color: #cbd5e1; background: #f9fafb; }
    .hero-register__type-card input { display: none; }
    .hero-register__type-icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, #1c57a3, #134380);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .hero-register__type-icon .material-icons { font-size: 22px; }
    .hero-register__type-icon--alt { background: linear-gradient(135deg, #fb923c, #c2410c); }
    .hero-register__type-body { display: flex; flex-direction: column; min-width: 0; }
    .hero-register__type-title { font-weight: 600; color: #111827; font-size: 0.95rem; }
    .hero-register__type-desc { color: #6b7280; font-size: 0.78rem; }
    .hero-register__type-check {
        margin-left: auto;
        opacity: 0;
        transform: scale(0.7);
        transition: all 0.2s ease;
        color: #10b981;
    }
    .hero-register__type-check .material-icons { font-size: 22px; }
    /* État actif piloté UNIQUEMENT par le radio réellement coché (:has).
       Comme un seul radio peut être coché à la fois, une seule carte est active.
       On n'utilise plus la classe .is-active (qui pouvait rester collée). */
    .hero-register__type-card:has(input[type="radio"]:checked) {
        border-color: #1c57a3;
        background: #eff6ff;
        box-shadow: 0 4px 12px rgba(28, 87, 163, 0.10);
    }
    .hero-register__type-card:has(input[type="radio"]:checked) .hero-register__type-check { opacity: 1; transform: scale(1); }

    /* === Champs === */
    .hero-register__field-label {
        display: block;
        font-size: 0.78rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        letter-spacing: 0.02em;
    }
    .req { color: #ef4444; }
    .hero-register__field-optional { color: #9ca3af; font-weight: 400; }
    .hero-register__field-error { display: block; color: #ef4444; font-size: 0.78rem; margin-top: 4px; }

    .hero-register__field-wrap {
        display: flex !important;
        align-items: stretch !important;
        background: #f9fafb !important;
        border: 1.5px solid #e5e7eb !important;
        border-radius: 12px !important;
        overflow: hidden !important;
        transition: all 0.2s ease;
    }
    .hero-register__field-wrap:focus-within {
        background: #ffffff !important;
        border-color: #f97316 !important;
        box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.13) !important;
    }
    .hero-register__field-wrap > i.material-icons {
        flex: 0 0 46px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 46px !important;
        color: #9ca3af !important;
        background: #ffffff !important;
        border-right: 1px solid #e5e7eb !important;
        font-size: 19px !important;
        line-height: 1 !important;
        position: static !important;
        margin: 0 !important;
        padding: 0 !important;
        pointer-events: none;
        box-sizing: border-box;
    }
    .hero-register__field-wrap:focus-within > i { color: #ea580c !important; }

    .hero-register__field-wrap input,
    .hero-register__field-wrap select {
        flex: 1 1 auto !important;
        min-width: 0 !important;
        width: 100% !important;
        padding: 11px 14px !important;
        border: 0 !important;
        border-radius: 0 !important;
        background: transparent !important;
        color: #111827 !important;
        font-size: 0.92rem !important;
        line-height: 1.4 !important;
        outline: none !important;
        height: auto !important;
        appearance: none;
    }
    .hero-register__field-wrap select {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16'%3E%3Cpath fill='%236b7280' d='M4 6l4 4 4-4z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 12px center !important;
        padding-right: 32px !important;
    }
    .hero-register__field-wrap--file > input[type="file"] {
        padding: 9px 12px !important;
        cursor: pointer;
    }

    .hero-register__toggle {
        flex: 0 0 44px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: transparent !important;
        border: 0 !important;
        border-left: 1px solid #e5e7eb !important;
        color: #6b7280 !important;
        cursor: pointer;
        transition: color 0.2s, background 0.2s;
    }
    .hero-register__toggle > i { font-size: 16px !important; line-height: 1 !important; }
    .hero-register__toggle:hover { color: #ea580c !important; background: rgba(249, 115, 22, 0.08) !important; }

    /* === CGU === */
    .hero-register__cgu {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 12px 0 18px;
        cursor: pointer;
        font-size: 0.88rem;
        color: #374151;
    }
    .hero-register__cgu input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: #ea580c;
        cursor: pointer;
    }
    .hero-register__cgu a { color: #ea580c; font-weight: 600; text-decoration: none; }
    .hero-register__cgu a:hover { text-decoration: underline; }

    /* === Bouton submit === */
    .hero-register__submit {
        width: 100%;
        padding: 14px 18px;
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
        color: #fff;
        border: 0;
        border-radius: 14px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 10px 24px rgba(234, 88, 12, 0.45);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        letter-spacing: 0.02em;
    }
    .hero-register__submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 30px rgba(234, 88, 12, 0.55);
        background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
    }
    .hero-register__submit:active { transform: translateY(0); }
    .hero-register__submit i { font-size: 20px; }

    .hero-register__divider {
        display: flex; align-items: center; gap: 12px;
        margin: 18px 0 12px;
        color: #9ca3af; font-size: 0.78rem;
        text-transform: uppercase; letter-spacing: 0.08em;
    }
    .hero-register__divider::before, .hero-register__divider::after {
        content: ""; flex: 1; height: 1px; background: #e5e7eb;
    }
    .hero-register__signup { text-align: center; color: #6b7280; font-size: 0.9rem; margin: 0; }
    .hero-register__link { color: #ea580c; text-decoration: none; font-weight: 600; }
    .hero-register__link:hover { color: #7c2d12; text-decoration: underline; }
    .hero-register__link--strong { font-weight: 700; }

    /* === Trust === */
    .hero-register__trust {
        display: flex; flex-wrap: wrap; gap: 18px;
        justify-content: center; list-style: none;
        padding: 0; margin: 24px 0 0;
    }
    .hero-register__trust li {
        color: rgba(255, 255, 255, 0.92);
        font-size: 0.85rem; font-weight: 500;
        display: inline-flex; align-items: center; gap: 6px;
        text-shadow: 0 1px 8px rgba(0, 0, 0, 0.3);
    }
    .hero-register__trust i { font-size: 18px; color: #fbbf24; }

    /* === Alertes === */
    .hero-register__alert {
        border-radius: 12px;
        border: none;
        padding: 11px 14px;
        font-size: 0.88rem;
        font-weight: 500;
        margin-bottom: 14px;
        border-left: 4px solid;
    }
    .hero-register__alert--danger  { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }

    /* === Responsive === */
    @media (max-width: 575px) {
        .hero-register { padding: 30px 12px; }
        .hero-register__hero-title { font-size: 1.7rem; }
        .hero-register__card { padding: 26px 18px 20px; border-radius: 18px; }
        .hero-register__type-grid { grid-template-columns: 1fr; }
        .hero-register__trust { gap: 12px; }
        .hero-register__trust li { font-size: 0.78rem; }
    }
</style>

{{-- Bascule Particulier / Entreprise : JavaScript pur (sans jQuery), auto-suffisant,
     placé directement dans le contenu pour s'exécuter de façon fiable. --}}
<script>
    (function () {
        function setHTML(id, html) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = html;
        }

        // Reconstruit les champs en fonction du type sélectionné. L'état visuel des
        // cartes (carte active + ✅) est géré uniquement en CSS via :has(input:checked).
        function syncTypeUI() {
            var checked = document.querySelector('input[name="type"]:checked');
            var val = checked ? checked.value : '1';
            var pourCommerceWrap = document.getElementById('pourCommerceWrap');

            if (val === '2') {
                if (pourCommerceWrap) pourCommerceWrap.style.display = '';
                setHTML('nom', '');
                setHTML('prenom', '');
                setHTML('contact2', `
                    <label class="hero-register__field-label">Téléphone & personne à contacter <span class="req">*</span></label>
                    <span class="hero-register__field-wrap">
                        <i class="material-icons md-contact_phone"></i>
                        <input type="text" name="contact2" placeholder="Contact + nom du responsable" required />
                    </span>
                `);
                setHTML('pourCommerce', `
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">Registre de commerce <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-assignment"></i>
                            <input type="text" id="rccm" name="rccm" placeholder="N° RCCM" required />
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">N° Compte contribuable <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-receipt"></i>
                            <input type="text" id="ncc" name="ncc" placeholder="N° NCC" required />
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">DFE (PDF / Image) <span class="req">*</span></label>
                        <span class="hero-register__field-wrap hero-register__field-wrap--file">
                            <i class="material-icons md-cloud_upload"></i>
                            <input type="file" name="dfe" accept=".pdf,.jpg,.jpeg,.png" required />
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">Registre de commerce (PDF / Image) <span class="req">*</span></label>
                        <span class="hero-register__field-wrap hero-register__field-wrap--file">
                            <i class="material-icons md-cloud_upload"></i>
                            <input type="file" name="registre_commerce" accept=".pdf,.jpg,.jpeg,.png" required />
                        </span>
                    </div>
                `);
                setHTML('raisonSociale', `
                    <label class="hero-register__field-label">Raison Sociale <span class="req">*</span></label>
                    <span class="hero-register__field-wrap">
                        <i class="material-icons md-business"></i>
                        <input type="text" name="raisonSociale" placeholder="Nom de l'entreprise" required />
                    </span>
                `);
            } else {
                if (pourCommerceWrap) pourCommerceWrap.style.display = 'none';
                setHTML('pourCommerce', '');
                setHTML('raisonSociale', '');
                setHTML('nom', `
                    <label class="hero-register__field-label">Nom <span class="req">*</span></label>
                    <span class="hero-register__field-wrap">
                        <i class="material-icons md-account_box"></i>
                        <input type="text" name="nom" placeholder="Votre nom" required />
                    </span>
                `);
                setHTML('prenom', `
                    <label class="hero-register__field-label">Prénom <span class="req">*</span></label>
                    <span class="hero-register__field-wrap">
                        <i class="material-icons md-person"></i>
                        <input type="text" name="prenom" placeholder="Votre prénom" required />
                    </span>
                `);
                setHTML('contact2', `
                    <label class="hero-register__field-label">Contact secondaire</label>
                    <span class="hero-register__field-wrap">
                        <i class="material-icons md-phone_iphone"></i>
                        <input type="tel" name="contact2" placeholder="Ex : 0507080910" />
                    </span>
                `);
            }
        }

        function init() {
            // Clic sur la carte : coche le radio (sécurité si le label natif échoue)
            // puis reconstruit les champs.
            document.querySelectorAll('.hero-register__type-card').forEach(function (card) {
                card.addEventListener('click', function () {
                    var radio = card.querySelector('input[type="radio"]');
                    if (radio) radio.checked = true;
                    syncTypeUI();
                });
            });
            document.querySelectorAll('input[name="type"]').forEach(function (radio) {
                radio.addEventListener('change', syncTypeUI);
            });
            // Pas de synchronisation au chargement : le serveur (Blade) a déjà rendu les
            // bons champs selon old('type'), ce qui préserve les valeurs déjà saisies.
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
@endsection
