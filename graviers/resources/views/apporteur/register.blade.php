@extends('client.main')
@section('title','Devenir apporteur d\'affaire')

@section('content')
@include('client.navMobile')

{{--
    L'INSCRIPTION D'UN APPORTEUR D'AFFAIRE.

    Cette page s'appuyait sur le gabarit du BACK-OFFICE : fond blanc, carte nue,
    aucun rapport avec le reste du site public — alors qu'elle s'adresse à un
    visiteur, pas à un gestionnaire. Elle reprend désormais le dessin de
    l'inscription client, dont les règles sont partagées.

    LES CHAMPS SUIVENT CEUX DE L'APPLICATION MOBILE de l'apporteur, exactement :
    pays, ville, nom, téléphone, numéro de pièce, mode de paiement préféré,
    e-mail, mot de passe et sa confirmation, pièce recto et verso.

    Le web en demandait une partie seulement — sans le pays, la ville, la pièce
    ni le mode de paiement — et en réclamait deux que l'application ne demande
    pas : l'adresse et la zone d'intervention. Un même apporteur n'avait donc pas
    le même dossier selon l'endroit où il s'inscrivait. Ces deux champs ont été
    retirés : les colonnes `users.adresse` et `apporteur.zone_intervention`
    acceptent l'absence de valeur, et restent modifiables depuis la fiche.
--}}

<main class="hero-register">
    <div class="hero-register__bg" aria-hidden="true"></div>
    <div class="hero-register__overlay" aria-hidden="true"></div>

    <div class="hero-register__shape hero-register__shape--1" aria-hidden="true"></div>
    <div class="hero-register__shape hero-register__shape--2" aria-hidden="true"></div>

    <div class="hero-register__content">
        <div class="hero-register__brand-line">
            <img src="{{ asset(config('constantes.logo')) }}" alt="Mon Gravier" class="hero-register__logo-mini">
            <span>GRAVIER.COM</span>
        </div>

        <h1 class="hero-register__hero-title">
            Devenez <span class="hero-register__accent">apporteur d'affaire</span>
        </h1>
        <p class="hero-register__hero-subtitle">
            Présentez-nous des clients et touchez une commission sur chaque affaire conclue.
        </p>

        <div class="hero-register__card">
            <div class="hero-register__card-header">
                <h2>Créer mon compte apporteur</h2>
                <p>
                    Vous avez déjà un compte ?
                    <a href="{{ route('apporteur.login') }}" class="hero-register__link hero-register__link--strong">Se connecter</a>
                </p>
            </div>

            @if (session('succes'))
                <div class="hero-register__alert hero-register__alert--success">{{ session('succes') }}</div>
            @endif
            @if (session('existEmail'))
                <div class="hero-register__alert hero-register__alert--danger">{{ session('existEmail') }}</div>
            @endif

            <form method="post" action="{{ route('apporteur.store') }}" enctype="multipart/form-data" class="hero-register__form">
                @csrf

                {{-- Section IDENTITÉ --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Identité</span>

                    <div class="row gx-3">
                        <div class="col-12 mb-3">
                            <label class="hero-register__field-label">Nom &amp; prénoms ou raison sociale <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-person"></i>
                                <input type="text" name="nom_prenom" value="{{ old('nom_prenom') }}"
                                       placeholder="Votre nom complet ou votre raison sociale" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('nom_prenom') }}</span>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Pays <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-public"></i>
                                <select name="pays" required>
                                    <option value="">Choisir votre pays</option>
                                    @foreach ($pays as $unPays)
                                        <option value="{{ $unPays->id }}" @selected(old('pays') == $unPays->id)>{{ $unPays->libelle ?? $unPays->nom }}</option>
                                    @endforeach
                                </select>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('pays') }}</span>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Ville <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-location_city"></i>
                                <select name="ville" required>
                                    <option value="">Choisir votre ville</option>
                                    @foreach ($villes as $uneVille)
                                        <option value="{{ $uneVille->id }}" @selected(old('ville') == $uneVille->id)>{{ $uneVille->libelle ?? $uneVille->nom }}</option>
                                    @endforeach
                                </select>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('ville') }}</span>
                        </div>
                    </div>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Téléphone <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-phone"></i>
                                <input type="tel" name="contact" value="{{ old('contact') }}"
                                       placeholder="0700000000" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('contact') }}</span>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">E-mail <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-mail"></i>
                                <input type="email" name="email" value="{{ old('email') }}"
                                       placeholder="vous@exemple.com" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('email') }}</span>
                        </div>
                    </div>

                </div>

                {{-- Section ACTIVITÉ --}}
                <div class="hero-register__section">
                    <span class="hero-register__section-label">Votre activité</span>

                    <div class="row gx-3">
                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Numéro de CNI / pièce <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-assignment_ind"></i>
                                <input type="text" name="numero_piece" value="{{ old('numero_piece') }}"
                                       placeholder="Numéro de votre pièce" required />
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('numero_piece') }}</span>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="hero-register__field-label">Mode de paiement préféré <span class="req">*</span></label>
                            <span class="hero-register__field-wrap">
                                <i class="material-icons md-payments"></i>
                                <select name="mode_paiement" required>
                                    <option value="">Comment souhaitez-vous être payé ?</option>
                                    @foreach ($modesPaiement as $mode)
                                        <option value="{{ $mode->id }}" @selected(old('mode_paiement') == $mode->id)>{{ $mode->libelle }}</option>
                                    @endforeach
                                </select>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('mode_paiement') }}</span>
                        </div>
                    </div>


                    {{-- LES PIECES SE CHOISISSENT DEPUIS UNE VIGNETTE.
                         Sur le TELEPHONE, l'application propose de photographier la
                         piece. Sur le WEB, on selectionne un fichier deja sur
                         l'ordinateur : la vignette ouvre donc l'explorateur, et rien
                         ne force l'appareil photo.
                         L'apercu remplace l'icone une fois le fichier choisi. Un champ
                         de fichier nu ne dit pas ce qui a ete retenu — l'apporteur
                         devait rouvrir le selecteur pour verifier qu'il n'avait pas
                         envoye deux fois le meme cote. --}}
                    <p class="apporteur-pieces__consigne">
                        Cliquez sur l'image pour sélectionner le fichier — JPG, PNG ou PDF
                    </p>

                    <div class="row gx-3">
                        @foreach ([['recto', 'Pièce recto', 'md-person'], ['verso', 'Pièce verso', 'md-list']] as [$cote, $libelle, $icone])
                            <div class="col-6 mb-3">
                                <label class="apporteur-piece" for="piece-{{ $cote }}">
                                    <span class="apporteur-piece__cadre" id="apercu-{{ $cote }}">
                                        <i class="material-icons {{ $icone }}"></i>
                                    </span>
                                    <span class="apporteur-piece__nom">{{ $libelle }} <span class="req">*</span></span>
                                    <span class="apporteur-piece__fichier" id="nom-{{ $cote }}">Aucun fichier choisi</span>
                                </label>

                                <input type="file" id="piece-{{ $cote }}" name="{{ $cote }}"
                                       accept=".jpg,.jpeg,.png,.pdf"
                                       class="apporteur-piece__champ" required />

                                <span class="hero-register__field-error">{{ $errors->first($cote) }}</span>
                            </div>
                        @endforeach
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
                                <input type="password" id="password" name="password" placeholder="••••••••"
                                       autocomplete="new-password" required />
                                <button type="button" class="hero-register__toggle" id="oeil"
                                        onclick="basculerMotDePasse('password', 'oeil')"
                                        aria-label="Afficher / masquer le mot de passe">
                                    <i class="fa-solid fa-eye-slash"></i>
                                </button>
                            </span>
                            <span class="hero-register__field-error">{{ $errors->first('password') }}</span>
                        </div>

                        {{-- Confirmation : le mot de passe est saisi masque, une faute de
                             frappe ne se voit pas. Le nom « password_confirmation » est
                             celui qu'attend la regle « confirmed » de la validation. --}}
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
                </div>

                {{-- Meme libelle que l'application, a l'orthographe pres : elle
                     affiche « Je m'inscrit ». --}}
                <button type="submit" class="hero-register__submit">
                    <i class="material-icons md-how_to_reg"></i>
                    Je m'inscris
                </button>

                <p class="hero-register__legal">
                    En continuant, vous confirmez avoir accepté nos
                    <a href="{{ route('termesConditions') }}" target="_blank" rel="noopener">termes &amp; conditions</a>.
                    <br>
                    Votre compte est vérifié par nos équipes avant activation : vous recevrez un
                    code de confirmation par courriel.
                </p>
            </form>
        </div>
    </div>
</main>

@include('client._styleHeroRegister')

<style>
    .apporteur-pieces__consigne{
        margin: 2px 0 12px; text-align: center; font-size: .85rem; color: #6b7280;
    }
    .apporteur-piece{
        display: flex; flex-direction: column; align-items: center; gap: 6px;
        padding: 14px 10px; border: 1.5px dashed #cbd5e1; border-radius: 14px;
        cursor: pointer; transition: border-color .18s ease, background .18s ease;
        text-align: center; width: 100%;
    }
    .apporteur-piece:hover{ border-color: #1c57a3; background: #f8fafc; }
    .apporteur-piece__cadre{
        width: 84px; height: 84px; border-radius: 12px; overflow: hidden;
        display: flex; align-items: center; justify-content: center;
        background: #eef2f7; color: #1c3a6e;
    }
    .apporteur-piece__cadre i{ font-size: 40px; }
    .apporteur-piece__cadre img{ width: 100%; height: 100%; object-fit: cover; }
    .apporteur-piece__nom{ font-size: .9rem; font-weight: 600; color: #1f2937; }
    .apporteur-piece__fichier{
        font-size: .72rem; color: #9ca3af; max-width: 100%; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap;
    }
    /* Le champ reste dans la page — le masquer par « display:none » le
       retirerait de la validation du navigateur, qui ne saurait plus signaler
       une piece manquante. */
    .apporteur-piece__champ{
        position: absolute; width: 1px; height: 1px; opacity: 0;
        overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap;
    }
</style>

<script>
    // L'APERCU DE LA PIECE CHOISIE.
    //
    // Sans lui, rien ne distingue un champ rempli d'un champ vide : l'apporteur
    // devait rouvrir le selecteur pour verifier qu'il n'avait pas envoye deux
    // fois le meme cote. Un PDF n'a pas d'apercu : on affiche son nom.
    ['recto', 'verso'].forEach(function (cote) {
        var champ = document.getElementById('piece-' + cote);
        if (!champ) return;

        champ.addEventListener('change', function () {
            var fichier = champ.files && champ.files[0];
            var cadre = document.getElementById('apercu-' + cote);
            var nom = document.getElementById('nom-' + cote);

            if (!fichier) {
                nom.textContent = 'Aucun fichier choisi';
                return;
            }

            nom.textContent = fichier.name;

            if (!fichier.type.startsWith('image/')) return;

            var lecteur = new FileReader();
            lecteur.onload = function (e) {
                cadre.innerHTML = '<img src="' + e.target.result + '" alt="Aperçu ' + cote + '">';
            };
            lecteur.readAsDataURL(fichier);
        });
    });
</script>

<script>
    // L'oeil du gabarit ne connait qu'un seul champ : avec deux mots de passe,
    // il faut lui dire lequel.
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

    // L'ecart entre les deux saisies se dit AVANT l'envoi : le serveur le
    // refuse deja, mais la page revient alors vide de ses deux champs.
    (function () {
        var mdp = document.getElementById('password');
        var confirmation = document.getElementById('password_confirmation');

        if (!mdp || !confirmation) return;

        function verifier() {
            var different = confirmation.value && confirmation.value !== mdp.value;

            confirmation.setCustomValidity(different ? 'Les deux mots de passe ne correspondent pas.' : '');

            var zone = document.getElementById('erreurConfirmation');
            if (zone) {
                zone.textContent = different ? 'Les deux mots de passe ne correspondent pas.' : '';
            }
        }

        mdp.addEventListener('input', verifier);
        confirmation.addEventListener('input', verifier);
    })();
</script>
@endsection
