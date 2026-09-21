{{--
    LES CODES D'UNE LIVRAISON, POUR LE CLIENT SEUL.

    Le client remet le code de livraison au livreur (il valide la course) et
    le bon d'enlèvement au fournisseur (il libère la marchandise). Il ne les
    trouvait que dans ses courriels ; ils sont désormais sous ses yeux, avec un
    bouton pour copier et un autre pour envoyer par WhatsApp — à un chauffeur,
    un chef de chantier, un collègue.

    Attend : $livraison. Options : $queEnlevement (retrait sur place : seul le
    bon d'enlèvement a un sens), $numeroCommande.

    Depuis le 08/09/2026, le bon d'enlèvement n'est montré au client QUE s'il
    va lui-même chez le fournisseur ; quand un livreur vient, c'est lui qui
    porte le bon et le client n'a que le code de livraison à remettre.
--}}
@php
    $codesAPartager = [];
    if (empty($queEnlevement) && $livraison->numero) {
        $codesAPartager[] = ['libelle' => 'Code de livraison', 'valeur' => $livraison->numero];
    }
    if (!empty($queEnlevement) && $livraison->enlevement?->code_enleve) {
        $codesAPartager[] = ['libelle' => "Bon d'enlèvement", 'valeur' => $livraison->enlevement->code_enleve];
    }
    $refCommande = $numeroCommande ?? ($livraison->detailCommande?->commande?->numero ?? null);
    // « commande n° » par défaut ; « location n° » pour une location (10/09/2026).
    $motAffaire = $libelleAffaire ?? 'commande';
@endphp

@foreach ($codesAPartager as $unCode)
    @php
        $messageWhatsApp = $unCode['libelle'] . ' Mon Gravier'
            . ($refCommande ? ' (' . $motAffaire . ' n° ' . $refCommande . ')' : '')
            . ' : ' . $unCode['valeur'];
    @endphp
    <div class="code-livraison">
        <span class="code-livraison__libelle">{{ $unCode['libelle'] }}</span>
        <strong class="code-livraison__valeur">{{ $unCode['valeur'] }}</strong>
        <button type="button" class="code-livraison__copier" data-code="{{ $unCode['valeur'] }}"
                title="Copier le code" aria-label="Copier {{ $unCode['libelle'] }} {{ $unCode['valeur'] }}">
            <i class="fa-solid fa-copy"></i>
        </button>
        <a class="code-livraison__whatsapp" target="_blank" rel="noopener"
           href="https://wa.me/?text={{ rawurlencode($messageWhatsApp) }}"
           title="Partager par WhatsApp" aria-label="Partager {{ $unCode['libelle'] }} par WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    </div>
@endforeach

@once
    <style>
        .code-livraison{display:flex;align-items:center;gap:6px;margin:2px 0;white-space:nowrap;}
        .code-livraison__libelle{font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.02em;}
        .code-livraison__valeur{font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.06em;color:#c0392b;}
        .code-livraison__copier,.code-livraison__whatsapp{
            display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;
            border-radius:6px;border:1px solid #d6dbe4;background:#fff;color:#374151;cursor:pointer;
            text-decoration:none;font-size:.85rem;line-height:1;padding:0;
        }
        .code-livraison__copier:hover{border-color:#1c57a3;color:#1c57a3;}
        .code-livraison__whatsapp{background:#25D366;border-color:#25D366;color:#fff !important;}
        .code-livraison__whatsapp:hover{filter:brightness(.93);}
        .code-livraison__copier.est-copie{border-color:#1e7e34;color:#1e7e34;}
    </style>
    <script>
        document.addEventListener('click', function (e) {
            var bouton = e.target.closest('.code-livraison__copier');
            if (!bouton) return;
            e.preventDefault();
            e.stopPropagation();
            var code = bouton.getAttribute('data-code') || '';
            var fini = function () {
                bouton.classList.add('est-copie');
                bouton.setAttribute('title', 'Copié');
                setTimeout(function () { bouton.classList.remove('est-copie'); bouton.setAttribute('title', 'Copier le code'); }, 1500);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(fini, fini);
            } else {
                // Repli : sélection dans un champ temporaire (contexte non sécurisé).
                var z = document.createElement('textarea');
                z.value = code; z.setAttribute('readonly', ''); z.style.position = 'absolute'; z.style.left = '-9999px';
                document.body.appendChild(z); z.select();
                try { document.execCommand('copy'); } catch (err) {}
                document.body.removeChild(z);
                fini();
            }
        });
    </script>
@endonce
