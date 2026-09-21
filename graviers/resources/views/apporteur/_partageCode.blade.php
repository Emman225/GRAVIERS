{{--
    PARTAGER SON CODE PARRAIN.

    Le code s'affichait, et c'est tout : l'apporteur devait le recopier à la main
    dans WhatsApp, puis expliquer où le saisir. Chaque étape recopiée est une
    occasion de se tromper — et un filleul perdu est une commission perdue.

    Le lien partagé PORTE le code : la page d'inscription le pré-remplit, et le
    filleul n'a rien à retaper. C'est ce qui distingue un partage utile d'un
    simple copier-coller.

    Attend : $code (le code parrain).
--}}

@php
    $lienInscription = route('client.register', ['code_promo' => $code]);

    $messagePartage = "Bonjour ! Commandez sable, gravier, ciment et location d'engins "
        . "sur GRAVIER.COM.\n\n"
        . "Utilisez mon code parrain *{$code}* à l'inscription :\n"
        . $lienInscription;
@endphp

<div class="partage-code">
    <a class="partage-code__whatsapp"
       href="https://wa.me/?text={{ rawurlencode($messagePartage) }}"
       target="_blank" rel="noopener">
        <i class="fa-brands fa-whatsapp"></i>
        Partager sur WhatsApp
    </a>

    {{-- Copier reste utile : tout le monde ne passe pas par WhatsApp. --}}
    <button type="button" class="partage-code__copier"
            data-lien="{{ $lienInscription }}"
            aria-label="Copier le lien de parrainage">
        <i class="material-icons md-content_copy"></i>
        Copier le lien
    </button>

    <span class="partage-code__retour" hidden>Lien copié</span>
</div>

<style>
    .partage-code{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:10px;}
    .partage-code__whatsapp{
        display:inline-flex;align-items:center;gap:7px;padding:8px 14px;border-radius:8px;
        background:#25D366;color:#fff !important;font-size:.85rem;font-weight:600;
        text-decoration:none;transition:filter .18s ease;
    }
    .partage-code__whatsapp:hover{filter:brightness(.93);color:#fff !important;}
    .partage-code__whatsapp i{font-size:17px;}
    .partage-code__copier{
        display:inline-flex;align-items:center;gap:6px;padding:8px 13px;border-radius:8px;
        border:1px solid #d6dbe4;background:#fff;color:#374151;font-size:.85rem;
        font-weight:600;cursor:pointer;
    }
    .partage-code__copier:hover{background:#f8fafc;border-color:#1c57a3;color:#1c57a3;}
    .partage-code__copier i{font-size:17px;}
    .partage-code__retour{font-size:.8rem;color:#1e7e34;font-weight:600;}
</style>

<script>
    (function () {
        var bouton = document.currentScript.parentElement.querySelector('.partage-code__copier');
        var retour = document.currentScript.parentElement.querySelector('.partage-code__retour');

        if (!bouton) return;

        bouton.addEventListener('click', function () {
            var lien = bouton.getAttribute('data-lien');

            // navigator.clipboard n'existe qu'en HTTPS (ou en local) : sans repli,
            // le bouton ne ferait RIEN sur une connexion non securisee, et sans
            // le moindre message.
            function annoncer() {
                retour.hidden = false;
                window.setTimeout(function () { retour.hidden = true; }, 2500);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(lien).then(annoncer);
                return;
            }

            var zone = document.createElement('textarea');
            zone.value = lien;
            zone.style.position = 'fixed';
            zone.style.opacity = '0';
            document.body.appendChild(zone);
            zone.select();

            try { document.execCommand('copy'); annoncer(); } catch (e) { /* rien à faire */ }

            document.body.removeChild(zone);
        });
    })();
</script>
