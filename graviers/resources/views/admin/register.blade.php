@extends('layout.main')
@section('title', 'Création de compte admin')

@php
    use Carbon\Carbon;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Création de compte <span class="dash-welcome-name">Administrateur</span> 🛡️
                </h2>
                <p class="dash-welcome-subtitle">
                    Créer un nouveau compte avec privilèges administrateur complets
                    — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <a href="{{ route('show.listeGestionnaire') }}" class="dash-time-pill" style="text-decoration:none;">
                    <i class="material-icons md-list_alt"></i>
                    <span>Voir gestionnaires</span>
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('ok'))
        <div class="alert alert-success">
            <i class="material-icons md-check_circle align-middle"></i>
            {{ session('ok') }}
        </div>
    @endif
    @if (session('modified'))
        <div class="alert alert-success">
            <i class="material-icons md-check_circle align-middle"></i>
            {{ session('modified') }}
        </div>
    @endif
    @if (session('errorEmail'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('errorEmail') }}
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-7">

            {{-- ===== AVERTISSEMENT PRIVILÈGES ===== --}}
            <div class="alert alert-info mb-4" style="border-radius:14px;border:none;border-left:4px solid #3b82f6;background:#eff6ff;color:#1e3a8a;">
                <div class="d-flex align-items-start gap-3">
                    <i class="material-icons md-admin_panel_settings" style="font-size:28px;color:#3b82f6;"></i>
                    <div class="flex-grow-1">
                        <strong>Privilèges administrateur complets</strong>
                        <p class="mb-0 mt-1" style="font-size:0.9rem;">
                            Ce compte aura accès à toutes les fonctionnalités : validation des paiements,
                            gestion des utilisateurs, configuration, suppression d'enregistrements,
                            et création d'autres administrateurs. Vérifiez bien l'identité de la personne avant.
                        </p>
                    </div>
                </div>
            </div>

            {{-- ===== FORMULAIRE ===== --}}
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-account_circle text-primary"></i>
                        Informations du nouveau compte
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('show.registerAdminn') }}"
                          enctype="multipart/form-data" id="formRegisterAdmin">
                        @csrf

                        {{-- Nom + Prénom --}}
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-person" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Nom <span class="text-danger">*</span>
                                </label>
                                <input class="form-control" name="nom" placeholder="Ex: Konan" type="text"
                                       required value="{{ old('nom') }}" autocomplete="family-name" />
                                @error('nom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-person" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Prénom <span class="text-danger">*</span>
                                </label>
                                <input class="form-control" name="prenom" placeholder="Ex: Jean" type="text"
                                       required value="{{ old('prenom') }}" autocomplete="given-name" />
                                @error('prenom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Email + Téléphone --}}
                        <div class="row g-3 mt-1">
                            <div class="col-md-7">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-email" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Email professionnel <span class="text-danger">*</span>
                                </label>
                                <input class="form-control" name="email" placeholder="admin@gravierci.com" type="email"
                                       required value="{{ old('email') }}" autocomplete="email" />
                                <small class="text-muted">Récupération de mot de passe et notifications.</small>
                                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-phone" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Téléphone <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <select class="form-control" name="indicatif" style="max-width:90px;">
                                        <option value="+225" {{ old('indicatif', '+225') === '+225' ? 'selected' : '' }}>+225</option>
                                        <option value="+226" {{ old('indicatif') === '+226' ? 'selected' : '' }}>+226</option>
                                        <option value="+228" {{ old('indicatif') === '+228' ? 'selected' : '' }}>+228</option>
                                        <option value="+229" {{ old('indicatif') === '+229' ? 'selected' : '' }}>+229</option>
                                        <option value="+233" {{ old('indicatif') === '+233' ? 'selected' : '' }}>+233</option>
                                    </select>
                                    <input class="form-control" name="contact" placeholder="0700000000" type="tel"
                                           required value="{{ old('contact') }}" autocomplete="tel" maxlength="15" />
                                </div>
                                <small class="text-muted">Format local (sans indicatif).</small>
                                @error('contact')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Login --}}
                        <div class="mt-3">
                            <label class="form-label fw-bold">
                                <i class="material-icons md-account_circle" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Identifiant de connexion (login) <span class="text-danger">*</span>
                            </label>
                            <input class="form-control" name="login" placeholder="Ex: admin2" type="text"
                                   required value="{{ old('login') }}" autocomplete="username" />
                            <small class="text-muted">Utilisé pour se connecter à l'espace admin (au lieu de l'email).</small>
                            @error('login')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Champ mot de passe supprimé : généré automatiquement et envoyé
                             au nouvel admin par email (le créateur ne doit pas le connaître). --}}
                        <div class="mt-3">
                            <div class="alert alert-info py-2 mb-0">
                                <i class="material-icons md-info" style="font-size:14px;vertical-align:middle;"></i>
                                Le mot de passe sera <strong>généré automatiquement</strong> et envoyé au nouvel administrateur par email.
                            </div>
                        </div>

                        {{-- Photo de profil (optionnelle) --}}
                        <div class="mt-3">
                            <label class="form-label fw-bold">
                                <i class="material-icons md-photo_camera" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Photo de profil <small class="text-muted">(facultatif)</small>
                            </label>
                            <input class="form-control" type="file" name="photo" id="photoInput"
                                   accept="image/jpeg,image/png"
                                   onchange="previsualiserPhoto(this)" />
                            <small class="text-muted">Formats acceptés : JPG, PNG (max 2 Mo)</small>
                            <div id="photoPreview" class="mt-2" style="display:none;">
                                <img id="photoPreviewImg" src="" alt="Aperçu"
                                     style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #e5e7eb;object-fit:cover;" />
                            </div>
                            @error('photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Boutons --}}
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="material-icons md-check_circle align-middle"></i>
                                Créer le compte admin
                            </button>
                            <a href="{{ route('show.listeGestionnaire') }}" class="btn btn-light">
                                <i class="material-icons md-arrow_back align-middle"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ===== INFOS SÉCURITÉ ===== --}}
            <div class="card dash-card mt-3" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="card-body py-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-1 text-center">
                            <i class="material-icons md-verified_user" style="font-size:32px;color:#10b981;"></i>
                        </div>
                        <div class="col-md-11">
                            <strong style="color:#1e293b;">Sécurité</strong>
                            <p class="text-muted small mb-0">
                                Le mot de passe est chiffré (bcrypt) avant stockage en base. Le nouvel admin pourra
                                se connecter immédiatement avec son login et son mot de passe sur
                                <code>/login-account</code>. La double validation des paiements en agence s'applique
                                automatiquement : il ne pourra pas valider ses propres opérations.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <style>
        /* Renforcer le style des inputs sur cette page */
        #formRegisterAdmin .form-control {
            border-radius: 8px;
            padding: 10px 12px;
            border: 1.5px solid #e5e7eb;
            transition: all 0.2s;
        }
        #formRegisterAdmin .form-control:focus {
            border-color: #1c57a3;
            box-shadow: 0 0 0 3px rgba(28, 87, 163, 0.1);
        }
        #formRegisterAdmin .input-group .btn {
            border-radius: 0 8px 8px 0;
        }
        #formRegisterAdmin .input-group .form-control {
            border-radius: 8px 0 0 8px;
        }
    </style>

    <script>
        function togglePasswordReveal() {
            var input = document.getElementById('passwordInput');
            var eye = document.getElementById('passwordEye');
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.remove('md-visibility');
                eye.classList.add('md-visibility_off');
            } else {
                input.type = 'password';
                eye.classList.remove('md-visibility_off');
                eye.classList.add('md-visibility');
            }
        }

        function previsualiserPhoto(input) {
            var preview = document.getElementById('photoPreview');
            var img = document.getElementById('photoPreviewImg');
            if (input.files && input.files[0]) {
                var file = input.files[0];
                if (file.type.indexOf('image/') === 0) {
                    var reader = new FileReader();
                    reader.onload = function (e) {
                        img.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                }
            } else {
                preview.style.display = 'none';
            }
        }

        function evaluerForcePassword(pwd) {
            var bar = document.getElementById('passwordStrengthBar');
            var fill = document.getElementById('passwordStrengthFill');
            var label = document.getElementById('passwordStrengthLabel');

            if (!pwd) {
                bar.style.display = 'none';
                return;
            }
            bar.style.display = 'block';

            var score = 0;
            if (pwd.length >= 8)  score++;
            if (pwd.length >= 12) score++;
            if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) score++;
            if (/\d/.test(pwd))   score++;
            if (/[^a-zA-Z\d]/.test(pwd)) score++;

            var pct, color, txt;
            if (score <= 1)      { pct = 20;  color = '#ef4444'; txt = 'Très faible'; }
            else if (score == 2) { pct = 40;  color = '#f97316'; txt = 'Faible'; }
            else if (score == 3) { pct = 60;  color = '#f59e0b'; txt = 'Moyen'; }
            else if (score == 4) { pct = 80;  color = '#10b981'; txt = 'Fort'; }
            else                 { pct = 100; color = '#059669'; txt = 'Très fort'; }

            fill.style.width = pct + '%';
            fill.style.backgroundColor = color;
            label.textContent = 'Force : ' + txt;
            label.style.color = color;
        }
    </script>
@endsection
