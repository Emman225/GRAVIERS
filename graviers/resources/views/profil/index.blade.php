@extends('layout.main')
@section('title','Mon profil')

@php
    use Carbon\Carbon;

    $displayName  = $user->nom_prenoms ?? $user->login ?? 'Utilisateur';
    $initials     = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $displayName) ?: 'U', 0, 1));
    $palette      = ['#1c57a3', '#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#ef4444'];
    $avatarColor  = $palette[crc32($displayName) % count($palette)];

    // Résolution robuste du chemin photo (gère imageUser/, profils/ ou nom seul)
    $photoPath = null;
    if ($user->photo) {
        $candidate = str_contains($user->photo, '/')
            ? $user->photo
            : 'imageUser/' . $user->photo;
        if (file_exists(public_path('storage/' . $candidate))) {
            $photoPath = $candidate;
        }
    }
    $photoExists = $photoPath !== null;
    $photoUrl    = $photoExists ? asset('storage/' . $photoPath) : null;
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Mon <span class="dash-welcome-name">Profil</span> 👤
                </h2>
                <p class="dash-welcome-subtitle">
                    Consultez et mettez à jour vos informations personnelles
                    — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
            <div class="dash-welcome-actions d-none d-md-flex">
                <div class="dash-time-pill">
                    <i class="material-icons md-verified_user"></i>
                    <span>{{ $typeLabel }}</span>
                </div>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    {{-- ===== MESSAGES ===== --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class="material-icons md-check_circle align-middle"></i>
            {{ session('success') }}
        </div>
    @endif
    @if(session('emailExiste'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('emailExiste') }}
        </div>
    @endif
    @if(session('loginExiste'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('loginExiste') }}
        </div>
    @endif
    @if(session('errorPassword'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('errorPassword') }}
        </div>
    @endif
    @if(session('passDifferent'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('passDifferent') }}
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">

            {{-- ===== CARD APERÇU UTILISATEUR ===== --}}
            <div class="card dash-card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        @if($photoExists)
                            <img src="{{ $photoUrl }}" alt="{{ $displayName }}"
                                 style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e5e7eb;" />
                        @else
                            <div style="width:80px;height:80px;border-radius:50%;background:{{ $avatarColor }};color:white;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:bold;">
                                {{ $initials }}
                            </div>
                        @endif
                        <div class="flex-grow-1">
                            <h4 class="mb-1">{{ $displayName }}</h4>
                            <p class="text-muted mb-1" style="font-size:0.9rem;">
                                <i class="material-icons md-mail" style="font-size:14px;vertical-align:middle;"></i>
                                {{ $user->email ?: '—' }}
                            </p>
                            <span class="badge bg-primary text-white" style="font-size:0.75rem;color:#ffffff !important;">{{ $typeLabel }}</span>
                            <span class="badge bg-light text-dark" style="font-size:0.75rem;">
                                <i class="material-icons md-perm_identity" style="font-size:12px;vertical-align:middle;"></i>
                                {{ $user->login }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== FORMULAIRE ===== --}}
            <div class="card dash-card mb-4">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-account_circle text-primary"></i>
                        Modifier mes informations
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('show.monProfilUpdate') }}"
                          enctype="multipart/form-data" id="formMonProfil">
                        @csrf

                        {{-- Nom et prénoms --}}
                        <div>
                            <label class="form-label fw-bold">
                                <i class="material-icons md-person" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Nom et prénoms <span class="text-danger">*</span>
                            </label>
                            <input class="form-control" name="nom_prenoms" type="text" required
                                   value="{{ old('nom_prenoms', $user->nom_prenoms) }}" />
                            @error('nom_prenoms')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        {{-- Email + Contact --}}
                        <div class="row g-3 mt-1">
                            <div class="col-md-7">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-mail" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Email <span class="text-danger">*</span>
                                </label>
                                <input class="form-control" name="email" type="email" required
                                       value="{{ old('email', $user->email) }}" />
                                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold">
                                    <i class="material-icons md-phone" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                    Téléphone
                                </label>
                                <input class="form-control" name="contact" type="tel"
                                       value="{{ old('contact', $user->contact) }}"
                                       placeholder="07 XX XX XX XX" maxlength="20" />
                                @error('contact')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Login (non modifiable) --}}
                        <div class="mt-3">
                            <label class="form-label fw-bold">
                                <i class="material-icons md-account_circle" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Identifiant (login)
                            </label>
                            <input class="form-control" type="text"
                                   value="{{ $user->login }}"
                                   readonly disabled
                                   style="background-color:#f1f5f9;cursor:not-allowed;" />
                            <small class="text-muted">
                                <i class="material-icons" style="font-size:12px;vertical-align:middle;">lock</i>
                                L'identifiant de connexion ne peut pas être modifié. Contactez un administrateur si nécessaire.
                            </small>
                        </div>

                        {{-- Adresse --}}
                        <div class="mt-3">
                            <label class="form-label fw-bold">
                                <i class="material-icons md-place" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Adresse
                            </label>
                            <input class="form-control" name="adresse" type="text"
                                   value="{{ old('adresse', $user->adresse) }}"
                                   placeholder="Ville, quartier, rue..." />
                        </div>

                        {{-- Photo de profil --}}
                        <div class="mt-3">
                            <label class="form-label fw-bold">
                                <i class="material-icons md-photo_camera" style="font-size:14px;vertical-align:middle;color:#1c57a3;"></i>
                                Photo de profil <small class="text-muted">(facultatif)</small>
                            </label>
                            <input class="form-control" type="file" name="photo" id="photoInput"
                                   accept="image/jpeg,image/png,image/webp"
                                   onchange="previsualiserPhoto(this)" />
                            <small class="text-muted">JPG, PNG ou WEBP — max 2 Mo.</small>
                            <div id="photoPreview" class="mt-2" style="display:none;">
                                <img id="photoPreviewImg" src="" alt="Aperçu"
                                     style="max-width:120px;max-height:120px;border-radius:8px;border:2px solid #e5e7eb;object-fit:cover;" />
                            </div>
                            @error('photo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <hr class="my-4">

                        {{-- Section mot de passe --}}
                        <h6 class="fw-bold text-muted mb-3">
                            <i class="material-icons md-lock" style="font-size:16px;vertical-align:middle;"></i>
                            Changer le mot de passe <small class="text-muted">(facultatif)</small>
                        </h6>

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Ancien mot de passe</label>
                                <input class="form-control" name="oldPassWord" type="password" autocomplete="current-password" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nouveau mot de passe</label>
                                <input class="form-control" name="newPassWord" type="password" autocomplete="new-password" />
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmer le mot de passe</label>
                                <input class="form-control" name="confirmPassWord" type="password" autocomplete="new-password" />
                            </div>
                        </div>

                        {{-- Boutons --}}
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="material-icons md-save align-middle"></i>
                                Enregistrer les modifications
                            </button>
                            <a href="{{ url()->previous() }}" class="btn btn-light">
                                <i class="material-icons md-close align-middle"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <style>
        #formMonProfil .form-control {
            border-radius: 8px;
            padding: 10px 12px;
            border: 1.5px solid #e5e7eb;
            transition: all 0.2s;
        }
        #formMonProfil .form-control:focus {
            border-color: #1c57a3;
            box-shadow: 0 0 0 3px rgba(28, 87, 163, 0.1);
        }
    </style>

    <script>
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
    </script>
@endsection
