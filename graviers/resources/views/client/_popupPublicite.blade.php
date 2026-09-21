{{--
    LE POPUP DE PUBLICITÉ DE LA PAGE D'ACCUEIL.

    Son visuel n'est PAS écrit dans le code : il vient d'une bannière de type
    « POPUP », créée depuis Gestionnaire → Bannières. L'affiche du mois se change
    donc sans intervention technique, comme les autres visuels du site.

    Sans bannière de ce type, rien ne s'affiche : mieux vaut aucun popup qu'un
    cadre vide, et cela laisse le choix de ne pas en avoir.

    Le visiteur qui ferme n'est pas harcelé — le popup ne revient pas de la
    visite — mais une pastille reste en bas à gauche pour le rouvrir : fermer
    par réflexe ne doit pas priver définitivement de l'offre.
--}}

@php
    $popup = \App\Models\Banniere::liste('POPUP')->first();
@endphp

@if ($popup)
    <div class="pub-overlay" id="pubOverlay" hidden>
        <div class="pub-boite" role="dialog" aria-modal="true" aria-labelledby="pubTitre">
            <button type="button" class="pub-fermer" id="pubFermer" aria-label="Fermer">&times;</button>

            {{-- LE CHEMIN DES BANNIERES PASSE PAR « storage/ ».
                 Le formulaire depose l'image sur le disque public, et tous les
                 ecrans du back-office la servent ainsi. La lire a la racine
                 donnait une image cassee — visible seulement a l'oeil, aucun
                 test ne verifie qu'un fichier existe derriere une adresse. --}}
            @if ($popup->image)
                <div class="pub-visuel">
                    <img src="{{ asset('storage/' . ltrim($popup->image, '/')) }}"
                         alt="{{ $popup->titre }}">
                </div>
            @endif

            <div class="pub-contenu">
                <h2 class="pub-titre" id="pubTitre">{{ $popup->titre ?: 'Bienvenue sur Mon Gravier' }}</h2>

                @if ($popup->sous_titre)
                    <p class="pub-sous-titre">{{ $popup->sous_titre }}</p>
                @endif

                <p class="pub-texte">
                    Abonnez-vous à notre lettre d'information et recevez nos offres,
                    nos nouveautés et nos prix en avant-première.
                </p>

                {{-- Le message de retour de l'inscription. Clé propre : Flasher
                     capte success/error et les rejoue en bulle éphémère, qui
                     disparaîtrait avant que le visiteur l'ait lue. --}}
                @if (session('newsletter_message'))
                    <div class="pub-message">{{ session('newsletter_message') }}</div>
                @endif

                <form action="{{ route('newsletter.store') }}" method="POST" class="pub-formulaire" id="pubFormulaire">
                    @csrf
                    <input type="hidden" name="origine" value="Popup de la page d'accueil">

                    <label class="pub-consentement">
                        <input type="checkbox" name="consentement" id="pubConsentement" required>
                        <span>
                            J'accepte de recevoir la lettre d'information de DALAKOUN et je
                            comprends que je peux me désabonner à tout moment.
                        </span>
                    </label>

                    <div class="pub-champ">
                        <input type="email" name="email" placeholder="Entrez votre adresse e-mail"
                               required aria-label="Adresse e-mail">
                    </div>

                    @error('email')
                        <div class="pub-erreur">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="pub-bouton" id="pubBouton" disabled>S'abonner</button>
                </form>

                <p class="pub-mention">
                    Vous pouvez vous désabonner à tout moment. Vos données ne sont utilisées
                    que pour vous adresser nos informations commerciales.
                </p>
            </div>
        </div>
    </div>

    {{-- La pastille de rappel : le popup fermé reste accessible. --}}
    <button type="button" class="pub-pastille" id="pubPastille" hidden>
        <span class="pub-pastille-texte">{{ $popup->titre ?: 'Nos offres' }}</span>
        <span class="pub-pastille-fermer" id="pubPastilleFermer" aria-label="Masquer">&times;</span>
    </button>

    <style>
        .pub-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:2000;
            display:flex;align-items:center;justify-content:center;padding:16px;}
        .pub-overlay[hidden]{display:none;}
        .pub-boite{position:relative;background:#fff;border-radius:10px;overflow:hidden;
            width:min(900px,100%);max-height:92vh;overflow-y:auto;display:flex;flex-wrap:wrap;
            box-shadow:0 20px 60px rgba(0,0,0,.35);}
        .pub-visuel{flex:1 1 320px;min-width:0;background:#f6f7f9;display:flex;
            align-items:center;justify-content:center;}
        .pub-visuel img{width:100%;height:100%;object-fit:cover;display:block;}
        .pub-contenu{flex:1 1 340px;min-width:0;padding:28px 26px;}
        .pub-titre{font-size:24px;font-weight:700;margin:0 0 6px;color:#1c1c1c;}
        .pub-sous-titre{font-weight:600;margin:0 0 10px;color:#1c57a3;}
        .pub-texte{font-size:14px;color:#555;margin:0 0 14px;}
        .pub-message{background:#e8f5e9;color:#1e7e34;border-radius:6px;padding:9px 12px;
            font-size:13px;margin-bottom:12px;}
        .pub-erreur{color:#c62828;font-size:13px;margin:-4px 0 10px;}
        .pub-consentement{display:flex;gap:9px;align-items:flex-start;font-size:12.5px;
            color:#555;margin-bottom:12px;cursor:pointer;}
        .pub-consentement input{margin-top:3px;flex:0 0 auto;}
        .pub-champ input{width:100%;padding:11px 13px;border:1px solid #d6d6d6;border-radius:6px;
            font-size:14px;}
        .pub-bouton{width:100%;margin-top:10px;padding:12px;border:0;border-radius:6px;
            background:#1c57a3;color:#fff;font-weight:600;font-size:15px;cursor:pointer;}
        .pub-bouton:disabled{background:#c9c9c9;cursor:not-allowed;}
        .pub-mention{font-size:11.5px;color:#8a8a8a;margin:12px 0 0;}
        .pub-fermer{position:absolute;top:8px;right:12px;border:0;background:transparent;
            font-size:30px;line-height:1;color:#555;cursor:pointer;z-index:2;}
        .pub-pastille{position:fixed;left:16px;bottom:16px;z-index:1900;display:flex;
            align-items:center;gap:10px;border:0;border-radius:30px;padding:11px 16px;
            background:#1c57a3;color:#fff;font-weight:600;font-size:13px;cursor:pointer;
            box-shadow:0 8px 24px rgba(0,0,0,.25);}
        .pub-pastille[hidden]{display:none;}
        .pub-pastille-fermer{font-size:18px;line-height:1;opacity:.8;}
        @media (max-width:640px){
            .pub-visuel{max-height:170px;}
            .pub-contenu{padding:20px 18px;}
            .pub-titre{font-size:20px;}
        }
    </style>

    <script>
        (function () {
            // La mémoire du visiteur. Une bannière remplacée doit pouvoir se
            // montrer de nouveau, meme a qui avait ferme la precedente : la clé
            // porte donc l'identifiant de la bannière.
            var CLE = 'pub-popup-{{ $popup->id }}';
            var CLE_PASTILLE = CLE + '-pastille';

            var overlay = document.getElementById('pubOverlay');
            var pastille = document.getElementById('pubPastille');
            var formulaire = document.getElementById('pubFormulaire');
            var consentement = document.getElementById('pubConsentement');
            var bouton = document.getElementById('pubBouton');

            function lire(cle) {
                try { return window.localStorage.getItem(cle); } catch (e) { return null; }
            }

            function ecrire(cle, valeur) {
                try { window.localStorage.setItem(cle, valeur); } catch (e) { /* navigation privée */ }
            }

            function ouvrir() {
                overlay.hidden = false;
                pastille.hidden = true;
                document.body.style.overflow = 'hidden';
            }

            function fermer() {
                overlay.hidden = true;
                document.body.style.overflow = '';
                ecrire(CLE, 'vu');

                if (lire(CLE_PASTILLE) !== 'masquee') {
                    pastille.hidden = false;
                }
            }

            // Le bouton ne s'active qu'une fois le consentement donné : recueillir
            // une adresse sans accord explicite n'aurait aucune valeur.
            consentement.addEventListener('change', function () {
                bouton.disabled = !consentement.checked;
            });

            document.getElementById('pubFermer').addEventListener('click', fermer);

            overlay.addEventListener('click', function (evenement) {
                if (evenement.target === overlay) fermer();
            });

            document.addEventListener('keydown', function (evenement) {
                if (evenement.key === 'Escape' && !overlay.hidden) fermer();
            });

            pastille.addEventListener('click', function (evenement) {
                if (evenement.target.id === 'pubPastilleFermer') {
                    ecrire(CLE_PASTILLE, 'masquee');
                    pastille.hidden = true;
                    return;
                }
                ouvrir();
            });

            // L'inscription vaut acceptation : le popup ne revient pas.
            formulaire.addEventListener('submit', function () {
                ecrire(CLE, 'vu');
                ecrire(CLE_PASTILLE, 'masquee');
            });

            /**
             * LE CHOIX SUR LES COOKIES A-T-IL DEJA ETE FAIT ?
             *
             * On lit le cookie SOI-MEME plutot que d'attendre un indicateur pose
             * par l'autre script : ce popup est rendu dans le contenu de la page,
             * donc AVANT le pied de page ou vit le bandeau des cookies. Un
             * indicateur pose plus tard n'existerait pas encore ici, et le
             * visiteur qui a deja choisi attendrait un evenement qui ne viendrait
             * jamais — le popup ne s'ouvrirait plus jamais pour lui.
             */
            function consentementDejaDonne() {
                return ('; ' + document.cookie).indexOf('; consentement_cookies=') !== -1;
            }

            function ouvrirApresDelai() {
                // Un court délai : surgir avant que la page ne soit lisible donne
                // l'impression d'une fenêtre intruse.
                window.setTimeout(ouvrir, 900);
            }

            if (lire(CLE) === 'vu') {
                if (lire(CLE_PASTILLE) !== 'masquee') pastille.hidden = false;
            } else if (consentementDejaDonne()) {
                // Le visiteur a deja repondu sur les cookies : rien a attendre.
                ouvrirApresDelai();
            } else {
                // APRES le consentement, pas avant. Deux fenetres simultanees se
                // recouvrent sur un ecran court, et l'offre commerciale n'a pas a
                // passer devant la question du consentement.
                document.addEventListener('cookies-choisis', ouvrirApresDelai, { once: true });
            }
        })();
    </script>
@endif
