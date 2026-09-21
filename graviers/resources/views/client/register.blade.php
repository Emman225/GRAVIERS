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
            <img src="{{ asset(config('constantes.logo')) }}" alt="Mon Gravier" class="hero-register__logo-mini">
            <span>MON GRAVIER</span>
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
                            <label class="hero-register__field-label">Raison sociale <span class="req">*</span></label>
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
                                <label class="hero-register__field-label">Régime d’imposition <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-account_balance"></i>
                                    <select id="regime_imposition" name="regime_imposition" required>
                                        <option value="">— Choisir —</option>
                                        {{-- Valeur = code court, libellé = intitulé complet (App\Support\RegimeImposition). --}}
                                        @foreach (\App\Support\RegimeImposition::CODES as $codeRegime => $libelleRegime)
                                            <option value="{{ $codeRegime }}" @selected(\App\Support\RegimeImposition::code(old('regime_imposition')) === $codeRegime)>{{ $libelleRegime }}</option>
                                        @endforeach
                                    </select>
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('regime_imposition') }}</span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">Nature de l’organisation <span class="req">*</span></label>
                                <span class="hero-register__field-wrap">
                                    <i class="material-icons md-domain"></i>
                                    <select id="nature_fne" name="nature_fne" required>
                                        @foreach (\App\Models\Client::NATURES_FNE as $codeNature => $libelleNature)
                                            <option value="{{ $codeNature }}" @selected(old('nature_fne', 'B2B') === $codeNature)>{{ $libelleNature }}</option>
                                        @endforeach
                                    </select>
                                </span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="hero-register__field-label">DFE (PDF / Image) <span class="req">*</span></label>
                                <span class="hero-register__field-wrap hero-register__field-wrap--file">
                                    <i class="material-icons md-cloud_upload"></i>
                                    <input type="file" name="dfe" accept=".pdf,.jpg,.jpeg,.png" required />
                                </span>
                                <span class="hero-register__field-error">{{ $errors->first('dfe') }}</span>
                            </div>
                            <div class="col-md-12 mb-3">
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
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Mot de passe <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-lock"></i>
                                <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="new-password" required />
                                <button type="button" class="hero-register__toggle" id="oeil"
                                        onclick="basculerMotDePasse('password', 'oeil')"
                                        aria-label="Afficher / masquer le mot de passe">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('password') }}</span>
                        </div>

                        {{-- CONFIRMER LE MOT DE PASSE.
                             Il est saisi masque : une faute de frappe ne se voit pas, et
                             le client se retrouve avec un compte dont il ignore le mot de
                             passe — il faut alors passer par la reinitialisation avant
                             meme d'avoir commande une premiere fois.
                             Le nom « password_confirmation » est celui qu'attend la regle
                             « confirmed » de la validation ; le changer romprait le
                             rapprochement, sans erreur visible. --}}
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Confirmer le mot de passe <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-lock"></i>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       placeholder="••••••••" autocomplete="new-password" required />
                                <button type="button" class="hero-register__toggle" id="oeilConfirmation"
                                        onclick="basculerMotDePasse('password_confirmation', 'oeilConfirmation')"
                                        aria-label="Afficher / masquer la confirmation">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </span>
                            <span class="hero-register__field-error" id="erreurConfirmation">{{ $errors->first('password_confirmation') }}</span>
                        </div>
                    </div>

                    <div class="row gx-3">
                        {{-- Toute la largeur : c'est le dernier champ de la section, et
                             le laisser sur sept colonnes laissait un vide a sa droite. --}}
                        <div class="col-12 mb-3">
                            <label class="hero-register__field-label">Code parrain <span class="hero-register__field-optional">(optionnel)</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-loyalty"></i>
                                {{-- Le code arrive par le LIEN partage par l'apporteur : sans cette
                                     reprise, le filleul devrait le retaper, et chaque saisie
                                     manuelle est une occasion de se tromper — un code faux est
                                     d'ailleurs ignore en silence, sans que personne ne le sache. --}}
                                <input type="text" name="code_promo"
                                       value="{{ old('code_promo', request('code_promo')) }}"
                                       placeholder="Code de parrainage" />
                            </span>
                        </div>
                    </div>

                    <script>
                        // L'oeil du gabarit ne connait qu'un seul champ, « password », et
                        // qu'un seul bouton. Avec deux champs il faut lui dire lequel.
                        function basculerMotDePasse(idChamp, idBouton) {
                            var champ = document.getElementById(idChamp);
                            var bouton = document.getElementById(idBouton);

                            if (champ.type === 'password') {
                                champ.type = 'text';
                                bouton.innerHTML = '<i class="fa-solid fa-eye"></i>';
                            } else {
                                champ.type = 'password';
                                bouton.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
                            }
                        }

                        // L'ECART SE DIT AVANT L'ENVOI.
                        //
                        // Le serveur refuse deja deux mots de passe differents, mais la
                        // page revient alors vide de ses deux champs — le client doit tout
                        // ressaisir pour comprendre. Le navigateur le signale des la
                        // frappe ; le controle serveur reste, lui, la seule garantie.
                        (function () {
                            var mdp = document.getElementById('password');
                            var confirmation = document.getElementById('password_confirmation');

                            if (!mdp || !confirmation) return;

                            function verifier() {
                                confirmation.setCustomValidity(
                                    confirmation.value && confirmation.value !== mdp.value
                                        ? 'Les deux mots de passe ne correspondent pas.'
                                        : ''
                                );

                                var zone = document.getElementById('erreurConfirmation');
                                if (zone) {
                                    zone.textContent = (confirmation.value && confirmation.value !== mdp.value)
                                        ? 'Les deux mots de passe ne correspondent pas.'
                                        : '';
                                }
                            }

                            mdp.addEventListener('input', verifier);
                            confirmation.addEventListener('input', verifier);
                        })();
                    </script>
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
            <li><i class="material-icons md-flash_on"></i> Inscription rapide</li>
            <li><i class="material-icons md-support_agent"></i> Support 7j/7</li>
        </ul>
    </div>
</main>

{{-- Le dessin de cette page est partage avec l'inscription apporteur :
     il vit dans client/_styleHeroRegister. --}}
@include('client._styleHeroRegister')

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
                        <label class="hero-register__field-label">Régime d’imposition <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-account_balance"></i>
                            <select id="regime_imposition" name="regime_imposition" required>
                            <option value="">— Choisir —</option>
                            @foreach (\App\Support\RegimeImposition::CODES as $codeRegime => $libelleRegime)
                                <option value="{{ $codeRegime }}">{{ $libelleRegime }}</option>
                            @endforeach
                            </select>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">Nature de l’organisation <span class="req">*</span></label>
                        <span class="hero-register__field-wrap">
                            <i class="material-icons md-domain"></i>
                            <select id="nature_fne" name="nature_fne" required>
                            @foreach (\App\Models\Client::NATURES_FNE as $codeNature => $libelleNature)
                                <option value="{{ $codeNature }}">{{ $libelleNature }}</option>
                            @endforeach
                            </select>
                        </span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="hero-register__field-label">DFE (PDF / Image) <span class="req">*</span></label>
                        <span class="hero-register__field-wrap hero-register__field-wrap--file">
                            <i class="material-icons md-cloud_upload"></i>
                            <input type="file" name="dfe" accept=".pdf,.jpg,.jpeg,.png" required />
                        </span>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="hero-register__field-label">Registre de commerce (PDF / Image) <span class="req">*</span></label>
                        <span class="hero-register__field-wrap hero-register__field-wrap--file">
                            <i class="material-icons md-cloud_upload"></i>
                            <input type="file" name="registre_commerce" accept=".pdf,.jpg,.jpeg,.png" required />
                        </span>
                    </div>
                `);
                setHTML('raisonSociale', `
                    <label class="hero-register__field-label">Raison sociale <span class="req">*</span></label>
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
