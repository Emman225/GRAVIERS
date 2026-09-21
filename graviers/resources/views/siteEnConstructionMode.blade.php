<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Site en construction — Mon Gravier</title>
    @include('layout._favicon')
    <style>
        /* Lot 114 (19/09/2026) — page autonome : aucune dépendance au thème, elle doit s'afficher
           même si une feuille du site manque. Palette de la charte : bleu nuit, bleu, jaune. */
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #fff;
            background: linear-gradient(135deg, #0A2540 0%, #1C57A3 100%); display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow-x: hidden; }
        body::before { content: ""; position: fixed; right: -140px; top: -140px; width: 460px; height: 460px; border-radius: 50%; background: rgba(255,179,0,.14); }
        body::after { content: ""; position: fixed; left: -160px; bottom: -180px; width: 420px; height: 420px; border-radius: 50%; background: rgba(255,255,255,.06); }
        .carte { position: relative; z-index: 1; width: 100%; max-width: 640px; text-align: center; background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.18); border-radius: 22px; padding: 40px 32px 32px; backdrop-filter: blur(6px); box-shadow: 0 24px 60px rgba(0,0,0,.28); }
        .logo { width: 132px; height: 132px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 10px 26px rgba(0,0,0,.35); }
        .sur { display: inline-block; margin: 22px 0 10px; background: rgba(255,179,0,.18); color: #FFB300; font-weight: 700; letter-spacing: .8px;
            text-transform: uppercase; font-size: 12.5px; padding: 7px 16px; border-radius: 20px; }
        h1 { font-size: 2.2rem; line-height: 1.2; margin: 0 0 12px; font-weight: 800; }
        h1 span { color: #FFB300; }
        p { color: #dce9f7; line-height: 1.7; margin: 0 auto 8px; max-width: 520px; font-size: 1.02rem; }
        .barre { height: 8px; border-radius: 6px; background: rgba(255,255,255,.16); overflow: hidden; margin: 26px auto 26px; max-width: 360px; }
        .barre i { display: block; height: 100%; width: 72%; border-radius: 6px; background: linear-gradient(90deg, #FFB300, #ffd566); }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-bottom: 22px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 13px 26px; border-radius: 30px; font-weight: 700; text-decoration: none; font-size: 15px; }
        .btn--plein { background: #FFB300; color: #0A2540; }
        .btn--plein:hover { background: #ffc333; }
        .btn--wa { background: #25D366; color: #fff; }
        .btn--wa:hover { background: #1ebe5a; }
        .pro { border-top: 1px solid rgba(255,255,255,.16); padding-top: 18px; font-size: 13.5px; color: #cddff0; }
        .pro a { color: #fff; text-decoration: underline; text-underline-offset: 3px; margin: 0 6px; white-space: nowrap; }
        .pied { margin-top: 18px; font-size: 12.5px; color: #a9c0da; }
        .alerte { background: rgba(255,255,255,.14); border-radius: 12px; padding: 10px 14px; margin-bottom: 16px; font-size: 14px; }
        @media (max-width: 480px) { h1 { font-size: 1.7rem; } .carte { padding: 30px 20px 24px; } .logo { width: 108px; height: 108px; } }
    </style>
</head>
<body>
    <main class="carte">
        <img class="logo" src="{{ asset(config('constantes.logo')) }}" alt="Mon Gravier">
        <div><span class="sur">Mon Gravier · une plateforme de DALAKOUN SARLU</span></div>
        <h1>Site en <span>construction</span></h1>
        <p>Nous préparons une nouvelle version de la plateforme : matériaux de construction, livraison sur chantier et location de matériel.</p>
        <p>L'accès est pour l'instant réservé aux personnes disposant d'un compte.</p>
        <div class="barre"><i></i></div>
        @if (session('fail') || session('error'))
            <div class="alerte">{{ session('fail') ?? session('error') }}</div>
        @endif
        <div class="actions">
            <a class="btn btn--plein" href="{{ route('client.login') }}">Se connecter</a>
            <a class="btn btn--wa" href="https://wa.me/2250700130798" target="_blank" rel="noopener">Nous écrire sur WhatsApp</a>
        </div>
        {{-- 19/09/2026 : seuls « Se connecter » et WhatsApp restent (demande du client). Les connexions
             de l'administration, des livreurs, fournisseurs et affiliés restent ouvertes à leurs
             adresses habituelles (/login-account, /livreur/login, /seller/login, /apporteur/login). --}}
        <div class="pied">&copy; {{ date('Y') }} Mon Gravier — DALAKOUN SARLU · (+225) 07 00 13 07 98</div>
    </main>
</body>
</html>
