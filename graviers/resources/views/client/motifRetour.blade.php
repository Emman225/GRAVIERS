@php
    use Illuminate\Support\Carbon;

    $produit  = $detail->produit;
    $commande = $detail->commande;
    $image    = $produit?->image?->first()?->image;
    // Une demande refusée ne bloque pas : le client peut en redéposer une.
    // Seules celles en cours d'examen ou déjà approuvées ferment le formulaire.
    $demandeEnCours = $detail->retour && (int) $detail->retour->statut !== 3;
    $refusPrecedent = $detail->retour && (int) $detail->retour->statut === 3 ? $detail->retour : null;
@endphp

@extends('client.main')
@section('title', 'Retour de produit')
@section('content')
    <main class="main retour-main">

        {{-- ===== HERO ===== --}}
        <section class="retour-hero">
            <div class="retour-hero__inner">
                <span class="retour-hero__chip"><i class="fi-rs-refresh"></i> Espace client</span>
                <h1 class="retour-hero__title">Retour de produit</h1>
                <p class="retour-hero__subtitle">
                    Expliquez-nous ce qui ne va pas : notre service vous répondra après examen de votre demande.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">

            {{-- Fil d'Ariane : il annonçait « Boutique / Adresse », deux pages
                 sans rapport avec un retour de produit. --}}
            <nav class="retour-fil">
                <a href="{{ route('client.index') }}"><i class="fi-rs-home"></i> Accueil</a>
                <span>/</span>
                <a href="{{ route('client.retourProduitPage') }}">Retour de produits</a>
                <span>/</span>
                <strong>{{ $produit?->nom ?? 'Produit' }}</strong>
            </nav>

            @if (session('success'))
                <div class="retour-alerte retour-alerte--succes" id="notify">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="retour-alerte retour-alerte--erreur" id="notify">{{ session('error') }}</div>
            @endif

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    {{-- ===== LE PRODUIT CONCERNÉ =====
                         La page ne disait NULLE PART ce qui allait être retourné :
                         le client saisissait un motif sans avoir sous les yeux le
                         produit, la quantité ni la commande d'origine. --}}
                    <div class="retour-carte retour-carte--produit">
                        <div class="retour-produit">
                            <div class="retour-produit__image">
                                @if ($image)
                                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $produit?->nom }}" loading="lazy">
                                @else
                                    <i class="fi-rs-box-alt"></i>
                                @endif
                            </div>
                            <div class="retour-produit__infos">
                                <h2 class="retour-produit__nom">{{ $produit?->nom ?? 'Produit' }}</h2>
                                <div class="retour-produit__meta">
                                    <span><strong>{{ rtrim(rtrim(number_format($detail->qte, 2, ',', ' '), '0'), ',') }}</strong>
                                        {{ $produit?->uniteProduit?->libelle ?? '' }}</span>
                                    <span class="retour-sep"></span>
                                    <span>{{ number_format($detail->prix, 0, ',', ' ') }} FCFA l'unité</span>
                                </div>
                                @if ($commande)
                                    <div class="retour-produit__commande">
                                        Commande <strong>{{ $commande->numero }}</strong>
                                        du {{ \Help::dateHeure($commande->created_at) }}
                                    </div>
                                @endif
                            </div>
                            <div class="retour-produit__total">
                                <span class="retour-produit__total-label">Montant de la ligne</span>
                                <span class="retour-produit__total-valeur">
                                    {{ number_format($detail->prix * $detail->qte, 0, ',', ' ') }} <small>FCFA</small>
                                </span>
                            </div>
                        </div>
                    </div>

                    @if ($demandeEnCours)
                        {{-- Une demande vivante existe déjà pour cette ligne : le
                             formulaire en créait une seconde à chaque envoi, sans
                             rien signaler. --}}
                        <div class="retour-carte retour-carte--info">
                            <i class="fi-rs-check"></i>
                            <div>
                                <strong>Une demande de retour est déjà enregistrée pour ce produit.</strong>
                                <p>
                                    @if ((int) $detail->retour->statut === 2)
                                        Elle a été approuvée par notre service.
                                    @else
                                        Elle est en cours d'examen par notre service. Vous serez informé de la suite par courriel.
                                        Inutile d'en envoyer une seconde.
                                    @endif
                                </p>
                                <a href="{{ route('client.retourProduitPage') }}" class="retour-btn retour-btn--secondaire">
                                    Revenir à mes retours
                                </a>
                            </div>
                        </div>
                    @else
                        @if ($refusPrecedent)
                            {{-- Rappel du refus précédent : sans lui, le client
                                 redéposerait la même demande à l'identique, et
                                 obtiendrait le même refus. --}}
                            <div class="retour-carte retour-carte--refus">
                                <i class="fi-rs-cross-circle"></i>
                                <div>
                                    <strong>Votre demande précédente a été refusée.</strong>
                                    @if ($refusPrecedent->observation_reception)
                                        <p>Motif du refus : {{ $refusPrecedent->observation_reception }}</p>
                                    @else
                                        <p>Aucun motif n'a été précisé par notre service.</p>
                                    @endif
                                    <p class="retour-refus__conseil">
                                        Vous pouvez en déposer une nouvelle ci-dessous. Apportez si possible un
                                        élément que vous n'aviez pas mentionné la première fois.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- ===== LE MOTIF ===== --}}
                        <div class="retour-carte">
                            <div class="retour-carte__entete">
                                <h3 class="retour-carte__titre"><i class="fi-rs-comment-alt"></i> Motif du retour</h3>
                            </div>

                            <form method="post" action="{{ route('client.motif', $detail) }}" class="retour-form">
                                @csrf

                                <label for="motif" class="retour-label">
                                    Que reprochez-vous à ce produit ?
                                    <span class="retour-obligatoire">obligatoire</span>
                                </label>
                                <p class="retour-aide">
                                    Décrivez le problème avec vos mots : quantité incorrecte, qualité,
                                    produit endommagé, erreur de référence… Plus votre description est
                                    précise, plus vite nous pourrons traiter votre demande.
                                </p>

                                <textarea id="motif" name="motif" rows="8" minlength="15" maxlength="1000" required
                                          class="retour-textarea @error('motif') retour-textarea--erreur @enderror"
                                          placeholder="Exemple : la livraison contenait 3 sacs au lieu de 5, et deux d'entre eux étaient déchirés.">{{ old('motif') }}</textarea>

                                <div class="retour-compteur">
                                    <span id="compteur">0</span> / 1000 caractères
                                    <span class="retour-compteur__min">— 15 caractères minimum</span>
                                </div>

                                @error('motif')
                                    <div class="retour-erreur"><i class="fi-rs-cross-circle"></i> {{ $message }}</div>
                                @enderror

                                <div class="retour-actions">
                                    <a href="{{ route('client.retourProduitPage') }}" class="retour-btn retour-btn--secondaire">
                                        Annuler
                                    </a>
                                    <button type="submit" class="retour-btn retour-btn--principal">
                                        <i class="fi-rs-paper-plane"></i> Envoyer la demande
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </main>

    <style>
        .retour-main { background: #f7f8fa; }

        /* Hero */
        .retour-hero {
            background: linear-gradient(135deg, #1c57a3 0%, #2d7dd2 100%);
            padding: 46px 20px 54px;
            text-align: center;
            color: #fff;
        }
        .retour-hero__inner { max-width: 720px; margin: 0 auto; }
        .retour-hero__chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 999px; padding: 5px 14px;
            font-size: .78rem; letter-spacing: .04em; text-transform: uppercase;
        }
        .retour-hero__title { color: #fff; font-size: 2rem; font-weight: 700; margin: 14px 0 8px; }
        .retour-hero__subtitle { color: rgba(255,255,255,.9); margin: 0; font-size: .95rem; }

        /* Fil d'Ariane */
        .retour-fil {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            font-size: .85rem; color: #77808c; margin: 22px 0 18px;
        }
        .retour-fil a { color: #1c57a3; text-decoration: none; }
        .retour-fil a:hover { text-decoration: underline; }
        .retour-fil strong { color: #2b3038; font-weight: 600; }

        /* Alertes */
        .retour-alerte {
            border-radius: 12px; padding: 14px 18px; margin-bottom: 18px;
            font-size: .92rem; border: 1px solid transparent;
        }
        .retour-alerte--succes { background: #e8f7ef; border-color: #b7e4cd; color: #14663f; }
        .retour-alerte--erreur { background: #fdecec; border-color: #f5c2c2; color: #922; }

        /* Cartes */
        .retour-carte {
            background: #fff; border: 1px solid #ebedf1; border-radius: 16px;
            box-shadow: 0 2px 14px rgba(20,30,60,.05);
            padding: 24px; margin-bottom: 22px;
        }
        .retour-carte__entete { border-bottom: 1px solid #f0f1f4; padding-bottom: 14px; margin-bottom: 20px; }
        .retour-carte__titre {
            display: flex; align-items: center; gap: 9px;
            font-size: 1.05rem; font-weight: 700; color: #2b3038; margin: 0;
        }
        .retour-carte__titre i { color: #1c57a3; }
        .retour-carte--produit { border-left: 4px solid #1c57a3; }
        .retour-carte--info { display: flex; gap: 16px; align-items: flex-start; border-left: 4px solid #16a06a; }
        .retour-carte--info > i { color: #16a06a; font-size: 1.4rem; margin-top: 2px; }
        .retour-carte--info p { margin: 6px 0 14px; color: #5b636e; font-size: .92rem; }
        .retour-carte--refus { display: flex; gap: 16px; align-items: flex-start; border-left: 4px solid #d9534f; background: #fffafa; }
        .retour-carte--refus > i { color: #d9534f; font-size: 1.4rem; margin-top: 2px; }
        .retour-carte--refus p { margin: 6px 0 0; color: #5b636e; font-size: .92rem; }
        .retour-refus__conseil { color: #8b929c !important; font-size: .86rem !important; }

        /* Produit */
        .retour-produit { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .retour-produit__image {
            width: 88px; height: 88px; flex: 0 0 88px;
            border-radius: 12px; background: #f2f4f7;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; color: #a8b0bb; font-size: 1.8rem;
        }
        .retour-produit__image img { width: 100%; height: 100%; object-fit: cover; }
        .retour-produit__infos { flex: 1 1 220px; min-width: 0; }
        .retour-produit__nom { font-size: 1.15rem; font-weight: 700; color: #2b3038; margin: 0 0 6px; }
        .retour-produit__meta {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            color: #5b636e; font-size: .9rem;
        }
        .retour-sep { width: 4px; height: 4px; border-radius: 50%; background: #c9ced6; }
        .retour-produit__commande { margin-top: 8px; font-size: .85rem; color: #77808c; }
        .retour-produit__total { text-align: right; margin-left: auto; }
        .retour-produit__total-label { display: block; font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: #8b929c; }
        .retour-produit__total-valeur { font-size: 1.25rem; font-weight: 700; color: #1c57a3; }
        .retour-produit__total-valeur small { font-size: .8rem; font-weight: 600; }

        /* Formulaire */
        .retour-label { display: block; font-weight: 600; color: #2b3038; margin-bottom: 6px; }
        .retour-obligatoire {
            display: inline-block; margin-left: 8px; padding: 2px 8px;
            background: #fdecec; color: #c0392b; border-radius: 999px;
            font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
        }
        .retour-aide { color: #77808c; font-size: .88rem; margin-bottom: 14px; }
        .retour-textarea {
            width: 100%; border: 1px solid #dcdfe4; border-radius: 12px;
            padding: 14px 16px; font-size: .95rem; color: #2b3038;
            resize: vertical; transition: border-color .15s, box-shadow .15s;
        }
        .retour-textarea:focus {
            outline: none; border-color: #1c57a3;
            box-shadow: 0 0 0 3px rgba(28,87,163,.12);
        }
        .retour-textarea--erreur { border-color: #d9534f; }
        .retour-compteur { margin-top: 8px; font-size: .8rem; color: #8b929c; text-align: right; }
        .retour-compteur__min { color: #a8b0bb; }
        .retour-erreur {
            display: flex; align-items: center; gap: 7px;
            margin-top: 10px; color: #c0392b; font-size: .88rem;
        }

        /* Boutons */
        .retour-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 22px; flex-wrap: wrap; }
        .retour-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; border-radius: 10px; border: 1px solid transparent;
            font-size: .92rem; font-weight: 600; cursor: pointer; text-decoration: none;
            transition: background .15s, color .15s, border-color .15s;
        }
        .retour-btn--principal { background: #1c57a3; color: #fff !important; }
        .retour-btn--principal:hover { background: #16447f; }
        .retour-btn--secondaire { background: #fff; color: #5b636e !important; border-color: #dcdfe4; }
        .retour-btn--secondaire:hover { border-color: #b9bfc8; color: #2b3038 !important; }

        @media (max-width: 575px) {
            .retour-hero { padding: 32px 16px 38px; }
            .retour-hero__title { font-size: 1.5rem; }
            .retour-carte { padding: 18px; }
            .retour-produit__total { margin-left: 0; text-align: left; width: 100%; }
            .retour-actions { flex-direction: column-reverse; }
            .retour-btn { width: 100%; }
        }
    </style>

    <script>
        // Compteur de caractères. Placé ici et non dans une section « jspart » :
        // le gabarit client.main ne réserve aucun emplacement de ce nom, si bien
        // que le script précédent n'était jamais rendu.
        //
        // Et surtout : ne jamais écrire le nom d'une directive Blade dans un
        // commentaire de cette page. Blade compile le fichier entier, commentaires
        // JavaScript compris — le mot suffit à provoquer une erreur 500.
        (function () {
            var champ = document.getElementById('motif');
            var compteur = document.getElementById('compteur');
            if (!champ || !compteur) { return; }

            var majuscule = function () { compteur.textContent = champ.value.length; };
            champ.addEventListener('input', majuscule);
            majuscule();
        })();
    </script>
@endsection
