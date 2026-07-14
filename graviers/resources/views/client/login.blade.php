@extends('client.main')
@section('title','Page de connexion')
@section('content')

    @include('client.navMobile')

    <main class="hero-login">
        {{-- Couche image de fond + overlay sombre --}}
        <div class="hero-login__bg" aria-hidden="true"></div>
        <div class="hero-login__overlay" aria-hidden="true"></div>

        {{-- Décor flottant --}}
        <div class="hero-login__shape hero-login__shape--1" aria-hidden="true"></div>
        <div class="hero-login__shape hero-login__shape--2" aria-hidden="true"></div>

        {{-- Illustration : camion-benne chargé de gravier (SVG inline, sans dépendance) --}}
        <div class="hero-login__truck" aria-hidden="true">
            <svg viewBox="0 0 520 280" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="truckBody" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#1c57a3"/>
                        <stop offset="1" stop-color="#0a2540"/>
                    </linearGradient>
                    <linearGradient id="truckBenne" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#134380"/>
                        <stop offset="1" stop-color="#0a2540"/>
                    </linearGradient>
                    <linearGradient id="truckCabTop" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#3b82f6"/>
                        <stop offset="1" stop-color="#1c57a3"/>
                    </linearGradient>
                    <radialGradient id="gravelStone" cx="0.4" cy="0.3" r="0.7">
                        <stop offset="0" stop-color="#cbd5e1"/>
                        <stop offset="1" stop-color="#64748b"/>
                    </radialGradient>
                </defs>

                {{-- Lignes de vitesse / mouvement --}}
                <g stroke="rgba(255,255,255,0.55)" stroke-width="3" stroke-linecap="round" fill="none">
                    <line x1="20"  y1="100" x2="80"  y2="100"/>
                    <line x1="10"  y1="135" x2="90"  y2="135"/>
                    <line x1="25"  y1="170" x2="70"  y2="170"/>
                </g>

                {{-- Sol / poussière --}}
                <ellipse cx="290" cy="246" rx="220" ry="10" fill="rgba(0,0,0,0.25)"/>

                {{-- Châssis --}}
                <rect x="120" y="180" width="360" height="22" rx="4" fill="#0a2540"/>

                {{-- Benne (basculée légèrement) --}}
                <path d="M 245 70 L 470 70 L 480 180 L 235 180 Z" fill="url(#truckBenne)"/>
                {{-- Bordure de benne --}}
                <path d="M 245 70 L 470 70" stroke="#0a2540" stroke-width="3" fill="none"/>
                {{-- Reflets benne --}}
                <path d="M 250 80 L 465 80" stroke="rgba(255,255,255,0.18)" stroke-width="2"/>
                <path d="M 240 175 L 480 175" stroke="rgba(255,255,255,0.10)" stroke-width="2"/>

                {{-- Tas de gravier dans la benne (multiples cailloux) --}}
                <g>
                    {{-- Couche du fond --}}
                    <ellipse cx="358" cy="78" rx="115" ry="14" fill="#475569"/>
                    {{-- Cailloux dispersés --}}
                    <circle cx="270" cy="76" r="7" fill="url(#gravelStone)"/>
                    <circle cx="290" cy="64" r="9" fill="url(#gravelStone)"/>
                    <circle cx="312" cy="55" r="11" fill="url(#gravelStone)"/>
                    <circle cx="335" cy="48" r="10" fill="url(#gravelStone)"/>
                    <circle cx="358" cy="44" r="12" fill="url(#gravelStone)"/>
                    <circle cx="382" cy="48" r="11" fill="url(#gravelStone)"/>
                    <circle cx="404" cy="55" r="10" fill="url(#gravelStone)"/>
                    <circle cx="425" cy="64" r="9" fill="url(#gravelStone)"/>
                    <circle cx="445" cy="74" r="8" fill="url(#gravelStone)"/>
                    {{-- Cailloux additionnels (texture) --}}
                    <circle cx="305" cy="72" r="5" fill="#94a3b8"/>
                    <circle cx="328" cy="65" r="6" fill="#94a3b8"/>
                    <circle cx="350" cy="58" r="5" fill="#cbd5e1"/>
                    <circle cx="372" cy="62" r="6" fill="#94a3b8"/>
                    <circle cx="395" cy="68" r="5" fill="#cbd5e1"/>
                    <circle cx="418" cy="74" r="5" fill="#94a3b8"/>
                </g>

                {{-- Cabine --}}
                <path d="M 120 180 L 120 110 Q 120 100 130 100 L 200 100 L 220 130 L 245 130 L 245 180 Z"
                      fill="url(#truckBody)"/>
                {{-- Toit cabine --}}
                <path d="M 120 110 Q 120 100 130 100 L 200 100 L 220 130 L 120 130 Z"
                      fill="url(#truckCabTop)"/>
                {{-- Vitre --}}
                <path d="M 138 112 L 196 112 L 212 130 L 138 130 Z"
                      fill="rgba(186, 230, 253, 0.85)" stroke="rgba(255,255,255,0.5)" stroke-width="1.5"/>
                {{-- Reflet vitre --}}
                <path d="M 145 116 L 175 116 L 175 122 L 152 122 Z" fill="rgba(255,255,255,0.4)"/>

                {{-- Phare --}}
                <circle cx="128" cy="160" r="6" fill="#fbbf24" stroke="#0a2540" stroke-width="1.5"/>

                {{-- Poignée porte --}}
                <rect x="160" y="148" width="22" height="3" rx="1.5" fill="rgba(255,255,255,0.4)"/>

                {{-- Roues --}}
                <g>
                    {{-- Roue avant --}}
                    <circle cx="165" cy="210" r="28" fill="#1f2937"/>
                    <circle cx="165" cy="210" r="20" fill="#374151"/>
                    <circle cx="165" cy="210" r="10" fill="#9ca3af"/>
                    <circle cx="165" cy="210" r="4" fill="#1f2937"/>

                    {{-- Roues arrière (paire) --}}
                    <circle cx="385" cy="210" r="28" fill="#1f2937"/>
                    <circle cx="385" cy="210" r="20" fill="#374151"/>
                    <circle cx="385" cy="210" r="10" fill="#9ca3af"/>
                    <circle cx="385" cy="210" r="4" fill="#1f2937"/>

                    <circle cx="445" cy="210" r="28" fill="#1f2937"/>
                    <circle cx="445" cy="210" r="20" fill="#374151"/>
                    <circle cx="445" cy="210" r="10" fill="#9ca3af"/>
                    <circle cx="445" cy="210" r="4" fill="#1f2937"/>
                </g>

                {{-- Petit cailloux qui tombe --}}
                <circle cx="495" cy="150" r="4" fill="#cbd5e1" opacity="0.85"/>
                <circle cx="505" cy="175" r="3" fill="#94a3b8" opacity="0.7"/>
                <circle cx="492" cy="200" r="3" fill="#cbd5e1" opacity="0.5"/>
            </svg>
        </div>

        {{-- Contenu centré --}}
        <div class="hero-login__content">
            <div class="hero-login__brand-line">
                <img src="{{ asset(config('constantes.logo')) }}" alt="GRAVIER.COM" class="hero-login__logo-mini">
                <span>GRAVIER.COM</span>
            </div>

            <h1 class="hero-login__hero-title">
                Vos chantiers,<br>
                livrés <span class="hero-login__accent">au juste prix</span>
            </h1>
            <p class="hero-login__hero-subtitle">
                Sable, gravier, ciment, blocs… Commandez en quelques clics.
            </p>

            {{-- Carte de connexion translucide --}}
            <div class="hero-login__card">
                <div class="hero-login__card-header">
                    <h2>Connexion</h2>
                    <p>Connectez-vous pour suivre vos commandes et livraisons.</p>
                </div>

                {{-- Notifications --}}
                @if (session('failToken'))
                    <div class="hero-login__alert hero-login__alert--info">{{ session('failToken') }}</div>
                @endif
                @if (session('failInfo'))
                    <div class="hero-login__alert hero-login__alert--danger">{{ session('failInfo') }}</div>
                @endif
                @if (session('success'))
                    <div class="hero-login__alert hero-login__alert--success">{{ session('success') }}</div>
                @endif
                @if (session('block'))
                    <div class="hero-login__alert hero-login__alert--info">{{ session('block') }}</div>
                @endif
                @if (session('modified'))
                    <div class="hero-login__alert hero-login__alert--success">{{ session('modified') }}</div>
                @endif

                <form method="post" action="{{ route('client.loginClient') }}" class="hero-login__form">
                    @csrf

                    <label class="hero-login__field">
                        <span class="hero-login__field-label">Adresse e-mail</span>
                        <span class="hero-login__field-wrap">
                            <i class="material-icons md-email"></i>
                            <input type="email" required name="email"
                                   placeholder="exemple@gmail.com"
                                   value="{{ old('email') }}"
                                   autocomplete="email" />
                        </span>
                    </label>

                    <label class="hero-login__field">
                        <span class="hero-login__field-label">Mot de passe</span>
                        <span class="hero-login__field-wrap">
                            <i class="material-icons md-lock"></i>
                            <input type="password" required id="password" name="password"
                                   placeholder="••••••••"
                                   autocomplete="current-password" />
                            <button type="button" class="hero-login__toggle" id="oeil" onclick="togglePassword()" aria-label="Afficher / masquer le mot de passe">
                                <i class="fa-solid fa-eye-slash"></i>
                            </button>
                        </span>
                    </label>

                    <input type="hidden" name="attente" value="{{ session('attente') }}">

                    <div class="hero-login__row">
                        <a href="{{ route('demandeEmail', ['from' => 'client']) }}" class="hero-login__link">
                            Mot de passe oublié ?
                        </a>
                    </div>

                    <button type="submit" name="login" class="hero-login__submit">
                        <i class="material-icons md-login"></i>
                        Se connecter
                    </button>

                    <div class="hero-login__divider"><span>ou</span></div>

                    <p class="hero-login__signup">
                        Pas encore de compte ?
                        <a href="{{ route('client.register') }}" class="hero-login__link hero-login__link--strong">Créer un compte</a>
                    </p>
                </form>
            </div>

            {{-- Pictos de réassurance sous la carte (desktop) --}}
            <ul class="hero-login__trust">
                <li><i class="material-icons md-local_shipping"></i> Livraison rapide</li>
                <li><i class="material-icons md-verified"></i> Qualité garantie</li>
                <li><i class="material-icons md-payments"></i> Paiement flexible</li>
            </ul>
        </div>
    </main>

    <style>
        /* ============================================================
           HERO LOGIN — Style « grand public » plein écran
           ============================================================ */
        .hero-login {
            position: relative;
            min-height: calc(100vh - 60px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 60px 16px;
            isolation: isolate;
        }

        /* Image de fond : photo de gravier concassé (cœur de métier GRAVIER.COM).
           Pour changer la photo, modifier juste l'URL ci-dessous. */
        .hero-login__bg {
            position: absolute; inset: 0; z-index: -3;
            background-image: url("{{ asset('frontend/assets/imgs/banner/hero-gravier.png') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transform: scale(1.05);
            filter: saturate(1.1) contrast(1.05) brightness(0.92);
        }
        /* Fallback dégradé si l'image ne charge pas */
        .hero-login {
            background: linear-gradient(135deg, #0a2540 0%, #134380 50%, #c2410c 100%);
        }
        /* Overlay sombre dégradé pour lisibilité */
        .hero-login__overlay {
            position: absolute; inset: 0; z-index: -2;
            background:
                linear-gradient(180deg, rgba(10, 37, 64, 0.55) 0%, rgba(10, 37, 64, 0.78) 100%),
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.45), transparent 50%);
        }
        /* Formes décoratives */
        .hero-login__shape {
            position: absolute; z-index: -1;
            border-radius: 50%;
            filter: blur(70px);
            pointer-events: none;
        }
        .hero-login__shape--1 {
            width: 480px; height: 480px;
            top: -120px; right: -100px;
            background: radial-gradient(circle, rgba(251, 146, 60, 0.45), transparent 70%);
        }
        .hero-login__shape--2 {
            width: 380px; height: 380px;
            bottom: -100px; left: -120px;
            background: radial-gradient(circle, rgba(96, 165, 250, 0.35), transparent 70%);
        }

        /* Camion-benne illustré (SVG inline) */
        .hero-login__truck {
            position: absolute;
            bottom: 40px;
            right: -40px;
            width: 460px;
            max-width: 38vw;
            z-index: 0;
            pointer-events: none;
            filter: drop-shadow(0 18px 30px rgba(0, 0, 0, 0.45));
            animation: hero-truck-roll 3.2s ease-in-out infinite;
        }
        .hero-login__truck svg { width: 100%; height: auto; display: block; }
        @keyframes hero-truck-roll {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-4px); }
        }
        @media (max-width: 991px) {
            .hero-login__truck {
                position: relative;
                bottom: auto; right: auto;
                width: 280px; max-width: 80%;
                margin: 24px auto -10px;
                display: block;
                filter: drop-shadow(0 10px 20px rgba(0, 0, 0, 0.4));
            }
        }
        @media (max-width: 575px) {
            .hero-login__truck { width: 220px; margin: 18px auto -6px; }
        }

        /* Bloc contenu centré */
        .hero-login__content {
            position: relative; z-index: 1;
            width: 100%;
            max-width: 460px;
            color: #ffffff;
            text-align: center;
        }

        .hero-login__brand-line {
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
            margin-bottom: 28px;
        }
        .hero-login__logo-mini {
            height: 28px;
            background: rgba(255, 255, 255, 0.95);
            padding: 4px 6px;
            border-radius: 6px;
        }

        h1.hero-login__hero-title,
        .hero-login__hero-title {
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.02em;
            margin: 0 0 12px;
            color: #ffffff !important;
            text-shadow: 0 2px 20px rgba(0, 0, 0, 0.35), 0 0 1px rgba(0, 0, 0, 0.2);
        }
        .hero-login__accent {
            background: linear-gradient(90deg, #fbbf24, #fb923c, #f87171);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            color: transparent;
        }
        .hero-login__hero-subtitle {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.88);
            margin: 0 0 32px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* Carte de connexion translucide (verre dépoli) */
        .hero-login__card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 22px;
            padding: 34px 28px 28px;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            text-align: left;
            color: #1f2937;
        }

        .hero-login__card-header { text-align: center; margin-bottom: 22px; }
        .hero-login__card-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0a2540;
            margin: 0 0 6px;
            letter-spacing: -0.01em;
        }
        .hero-login__wave { display: inline-block; animation: hero-wave 2.4s ease-in-out infinite; }
        @keyframes hero-wave {
            0%, 60%, 100% { transform: rotate(0); }
            10%, 30% { transform: rotate(-15deg); }
            20% { transform: rotate(20deg); }
            40%, 50% { transform: rotate(10deg); }
        }
        .hero-login__card-header p {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Champs */
        .hero-login__form { display: flex; flex-direction: column; gap: 14px; }
        .hero-login__field { display: flex; flex-direction: column; gap: 6px; }
        .hero-login__field-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #374151;
            letter-spacing: 0.02em;
        }
        /* Wrapper flex : [ICON ║ INPUT ║ TOGGLE] cellules séparées */
        .hero-login__field-wrap {
            display: flex !important;
            align-items: stretch !important;
            background: #f9fafb !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            transition: all 0.2s ease;
        }
        .hero-login__field-wrap:focus-within {
            background: #ffffff !important;
            border-color: #f97316 !important;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
        }

        .hero-login__field-wrap > i.material-icons {
            flex: 0 0 50px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 50px !important;
            color: #9ca3af !important;
            background: #ffffff !important;
            border-right: 1px solid #e5e7eb !important;
            font-size: 20px !important;
            line-height: 1 !important;
            position: static !important;
            top: auto !important; left: auto !important;
            right: auto !important; bottom: auto !important;
            transform: none !important;
            margin: 0 !important;
            padding: 0 !important;
            pointer-events: none;
            box-sizing: border-box;
        }
        .hero-login__field-wrap:focus-within > i { color: #ea580c !important; }
        .hero-login__field-wrap > i.material-icons::before { display: none !important; }

        .hero-login__field-wrap input {
            flex: 1 1 auto !important;
            min-width: 0 !important;
            width: 100% !important;
            padding: 13px 16px !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: #111827 !important;
            font-size: 0.95rem !important;
            line-height: 1.4 !important;
            outline: none !important;
            height: auto !important;
        }
        .hero-login__field-wrap input:focus {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            outline: none !important;
        }

        .hero-login__toggle {
            flex: 0 0 44px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 44px !important;
            background: transparent !important;
            border: 0 !important;
            border-left: 1px solid #e5e7eb !important;
            color: #6b7280 !important;
            padding: 0 !important;
            margin: 0 !important;
            cursor: pointer;
            transition: color 0.2s, background 0.2s;
            position: static !important;
            top: auto !important; right: auto !important;
            transform: none !important;
        }
        .hero-login__toggle > i { font-size: 16px !important; line-height: 1 !important; }
        .hero-login__toggle:hover {
            color: #ea580c !important;
            background: rgba(249, 115, 22, 0.08) !important;
        }

        .hero-login__row { text-align: right; margin-top: -4px; }
        .hero-login__link {
            color: #ea580c;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }
        .hero-login__link:hover { color: #7c2d12; text-decoration: underline; }
        .hero-login__link--strong { font-weight: 700; }

        /* Bouton de soumission */
        .hero-login__submit {
            margin-top: 6px;
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
        .hero-login__submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(234, 88, 12, 0.55);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }
        .hero-login__submit:active { transform: translateY(0); }
        .hero-login__submit i { font-size: 20px; }

        .hero-login__divider {
            display: flex; align-items: center; gap: 12px;
            margin: 18px 0 12px;
            color: #9ca3af; font-size: 0.78rem;
            text-transform: uppercase; letter-spacing: 0.08em;
        }
        .hero-login__divider::before,
        .hero-login__divider::after { content: ""; flex: 1; height: 1px; background: #e5e7eb; }

        .hero-login__signup {
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
        }

        /* Pictos de réassurance sous la carte */
        .hero-login__trust {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            justify-content: center;
            list-style: none;
            padding: 0;
            margin: 24px 0 0;
        }
        .hero-login__trust li {
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-shadow: 0 1px 8px rgba(0, 0, 0, 0.3);
        }
        .hero-login__trust i { font-size: 18px; color: #fbbf24; }

        /* Alertes */
        .hero-login__alert {
            border-radius: 12px;
            border: none;
            padding: 11px 14px;
            font-size: 0.88rem;
            font-weight: 500;
            margin-bottom: 14px;
            border-left: 4px solid;
        }
        .hero-login__alert--success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .hero-login__alert--danger  { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        .hero-login__alert--info    { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; }

        /* Responsive */
        @media (max-width: 575px) {
            .hero-login { padding: 30px 14px; }
            .hero-login__hero-title { font-size: 1.85rem; }
            .hero-login__hero-subtitle { font-size: 0.92rem; margin-bottom: 22px; }
            .hero-login__card { padding: 26px 20px 22px; border-radius: 18px; }
            .hero-login__trust { gap: 12px; }
            .hero-login__trust li { font-size: 0.78rem; }
        }
    </style>

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
@endsection
