{{--
    LE DESSIN DES PAGES D'INSCRIPTION.

    Ces regles vivaient dans un bloc <style> de l'inscription CLIENT. La page
    d'inscription d'un apporteur d'affaire, elle, s'appuyait sur le gabarit du
    back-office : fond blanc, carte nue, aucun rapport avec le reste du site
    public.

    Elles sont desormais partagees : les deux pages ont la meme allure, et il
    n'y a qu'un endroit a reprendre le jour ou elle changera.
--}}

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
    /* L'inscription apporteur annonce aussi des reussites — le code de
       confirmation envoye par courriel. Sans cette variante, le message
       s'affichait avec l'habit d'une erreur. */
    .hero-register__alert--success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }

    /* Mention de bas de formulaire : ce qui se passe apres l'envoi. */
    .hero-register__legal {
        margin: 12px 0 0;
        font-size: 0.78rem;
        color: #9ca3af;
        text-align: center;
        line-height: 1.5;
    }

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
