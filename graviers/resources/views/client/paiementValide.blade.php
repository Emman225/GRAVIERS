@extends('client.main')
@section('title','Paiement validé')

@section('content')
@include('client.navMobile')

<main class="main paiement-valide-main">
    <section class="paiement-valide-hero">
        <div class="paiement-valide-hero__bg" aria-hidden="true"></div>
        <div class="paiement-valide-hero__inner">

            <div class="paiement-valide-icon">
                <svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="40" cy="40" r="38" stroke="#10b981" stroke-width="3" fill="rgba(16,185,129,0.10)"/>
                    <path class="check-mark" d="M24 41 L36 53 L57 30" stroke="#10b981" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                </svg>
            </div>

            <h1 class="paiement-valide-title">Paiement validé 🎉</h1>
            <p class="paiement-valide-subtitle">Votre paiement a bien été enregistré. Merci pour votre commande !</p>

            <div class="paiement-valide-code">
                <span class="paiement-valide-code__label">Code de transaction</span>
                <span class="paiement-valide-code__value" id="codeTransaction">{{ $code }}</span>
                <button type="button" class="paiement-valide-code__copy" onclick="copierCode()" title="Copier le code">
                    <i class="fi-rs-copy-alt"></i>
                </button>
            </div>

            <div class="paiement-valide-info">
                <div class="paiement-valide-info__item">
                    <i class="fi-rs-mail"></i>
                    <div>
                        <strong>Email de confirmation</strong>
                        <small>Vous allez recevoir un email récapitulatif sous quelques minutes.</small>
                    </div>
                </div>
                <div class="paiement-valide-info__item">
                    <i class="fi-rs-shipping-fast"></i>
                    <div>
                        <strong>Suivi de livraison</strong>
                        <small>Vous serez notifié dès que votre commande sera prise en charge par un livreur.</small>
                    </div>
                </div>
                <div class="paiement-valide-info__item">
                    <i class="fi-rs-headset"></i>
                    <div>
                        <strong>Besoin d'aide ?</strong>
                        <small>Notre équipe est disponible 7j/7 sur WhatsApp et par email.</small>
                    </div>
                </div>
            </div>

            <div class="paiement-valide-actions">
                <a href="{{ route('client.commande') }}" class="paiement-valide-btn paiement-valide-btn--primary">
                    <i class="fi-rs-shopping-bag"></i> Voir mes commandes
                </a>
                <a href="{{ route('client.index') }}" class="paiement-valide-btn paiement-valide-btn--secondary">
                    <i class="fi-rs-arrow-right"></i> Continuer mes achats
                </a>
            </div>
        </div>
    </section>
</main>

<style>
    .paiement-valide-main { min-height: calc(100vh - 60px); }
    .paiement-valide-hero {
        position: relative;
        background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 60%);
        padding: 70px 20px 80px;
        text-align: center;
        overflow: hidden;
        isolation: isolate;
    }
    .paiement-valide-hero__bg {
        position: absolute;
        top: -100px;
        left: 50%;
        transform: translateX(-50%);
        width: 600px; height: 600px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(16,185,129,0.18), transparent 65%);
        z-index: -1;
        filter: blur(40px);
    }
    .paiement-valide-hero__inner { max-width: 640px; margin: 0 auto; }

    .paiement-valide-icon {
        margin: 0 auto 24px;
        width: 80px; height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .paiement-valide-icon svg .check-mark {
        stroke-dasharray: 60;
        stroke-dashoffset: 60;
        animation: paiement-check-stroke 0.6s 0.2s ease-out forwards;
    }
    @keyframes paiement-check-stroke {
        to { stroke-dashoffset: 0; }
    }

    .paiement-valide-title,
    h1.paiement-valide-title {
        font-size: 2rem;
        font-weight: 800;
        color: #0a2540 !important;
        margin: 0 0 8px;
        letter-spacing: -0.02em;
    }
    .paiement-valide-subtitle {
        color: #4b5563;
        font-size: 1rem;
        line-height: 1.6;
        margin: 0 0 30px;
    }

    .paiement-valide-code {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        padding: 14px 22px;
        background: #ffffff;
        border: 1.5px dashed #10b981;
        border-radius: 14px;
        margin-bottom: 36px;
        box-shadow: 0 8px 24px rgba(16,185,129,0.10);
    }
    .paiement-valide-code__label {
        font-size: 0.78rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 600;
    }
    .paiement-valide-code__value {
        font-family: 'Courier New', monospace;
        font-size: 1.1rem;
        font-weight: 700;
        color: #0a2540;
        letter-spacing: 0.04em;
    }
    .paiement-valide-code__copy {
        background: #ecfdf5;
        border: 0;
        color: #10b981;
        width: 36px; height: 36px;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.18s ease;
    }
    .paiement-valide-code__copy:hover {
        background: #10b981;
        color: #ffffff;
        transform: scale(1.05);
    }
    .paiement-valide-code__copy.is-copied {
        background: #10b981;
        color: #ffffff;
    }

    .paiement-valide-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 36px;
        text-align: left;
    }
    .paiement-valide-info__item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15,23,42,0.04);
    }
    .paiement-valide-info__item i {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #1c57a3, #134380);
        color: #ffffff;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .paiement-valide-info__item strong {
        display: block;
        color: #0a2540;
        font-size: 0.92rem;
        margin-bottom: 3px;
    }
    .paiement-valide-info__item small {
        color: #6b7280;
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .paiement-valide-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .paiement-valide-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 13px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 0.92rem;
        text-decoration: none;
        transition: all 0.18s ease;
        border: 1.5px solid transparent;
    }
    .paiement-valide-btn--primary {
        background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
        color: #ffffff !important;
        box-shadow: 0 10px 22px rgba(234,88,12,0.32);
    }
    .paiement-valide-btn--primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 28px rgba(234,88,12,0.42);
        color: #ffffff !important;
    }
    .paiement-valide-btn--secondary {
        background: #ffffff;
        color: #1c57a3 !important;
        border-color: #1c57a3;
    }
    .paiement-valide-btn--secondary:hover {
        background: #1c57a3;
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(28,87,163,0.30);
    }
    .paiement-valide-btn i { font-size: 16px; }

    @media (max-width: 575px) {
        .paiement-valide-hero { padding: 50px 16px 60px; }
        .paiement-valide-title { font-size: 1.5rem; }
        .paiement-valide-code { flex-direction: column; gap: 8px; padding: 14px 20px; }
        .paiement-valide-actions { flex-direction: column; }
        .paiement-valide-btn { width: 100%; justify-content: center; }
    }
</style>

<script>
    function copierCode() {
        var code = document.getElementById('codeTransaction').textContent.trim();
        var btn = document.querySelector('.paiement-valide-code__copy');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function () {
                btn.classList.add('is-copied');
                setTimeout(function () { btn.classList.remove('is-copied'); }, 1500);
            });
        }
    }
</script>
@endsection
