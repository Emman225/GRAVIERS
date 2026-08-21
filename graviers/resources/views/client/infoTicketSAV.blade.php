@php
    use Illuminate\Support\Carbon;

    $produit  = $detail->produit;
    $commande = $detail->commande;
    $image    = $produit?->image?->first()?->image;
    $ticket   = $detail->ticket;
@endphp

@extends('client.main')
@section('title', 'Ouvrir un ticket de service après-vente')
@section('content')
    <main class="main ticket-main">

        {{-- ===== HERO ===== --}}
        <section class="ticket-hero">
            <div class="ticket-hero__inner">
                <span class="ticket-hero__chip"><i class="fi-rs-headset"></i> Service après-vente</span>
                <h1 class="ticket-hero__title">Ouvrir un ticket</h1>
                <p class="ticket-hero__subtitle">
                    Décrivez le problème rencontré avec ce produit. Un agent prend votre demande
                    en charge et vous suivez son avancement depuis votre espace.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">

            {{-- Le fil d'Ariane annonçait « Boutique / Adresse », et toute la page
                 parlait de « retour de produit » : elle était la copie du gabarit
                 des retours, jusqu'au titre de l'onglet « Produit à retourner ».
                 Un ticket de service après-vente n'est pas un retour — c'est même
                 la voie à suivre QUAND le produit a déjà été utilisé. --}}
            <nav class="ticket-fil">
                <a href="{{ route('client.index') }}"><i class="fi-rs-home"></i> Accueil</a>
                <span>/</span>
                <a href="{{ route('client.ticketSAV') }}">Service après-vente</a>
                <span>/</span>
                <strong>{{ $produit?->nom ?? 'Produit' }}</strong>
            </nav>

            @if (session('success'))
                <div class="ticket-alerte ticket-alerte--succes" id="notify">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="ticket-alerte ticket-alerte--erreur" id="notify">{{ session('error') }}</div>
            @endif

            <div class="row justify-content-center">
                <div class="col-lg-8">

                    {{-- ===== LE PRODUIT CONCERNÉ ===== --}}
                    <div class="ticket-carte ticket-carte--produit">
                        <div class="ticket-produit">
                            <div class="ticket-produit__image">
                                @if ($image)
                                    <img src="{{ asset('storage/' . $image) }}" alt="{{ $produit?->nom }}" loading="lazy">
                                @else
                                    <i class="fi-rs-box-alt"></i>
                                @endif
                            </div>
                            <div class="ticket-produit__infos">
                                <h2 class="ticket-produit__nom">{{ $produit?->nom ?? 'Produit' }}</h2>
                                <div class="ticket-produit__meta">
                                    <span><strong>{{ rtrim(rtrim(number_format($detail->qte, 2, ',', ' '), '0'), ',') }}</strong>
                                        {{ $produit?->uniteProduit?->libelle ?? '' }}</span>
                                    <span class="ticket-sep"></span>
                                    <span>{{ number_format($detail->prix, 0, ',', ' ') }} FCFA l'unité</span>
                                </div>
                                @if ($commande)
                                    <div class="ticket-produit__commande">
                                        Commande <strong>{{ $commande->numero }}</strong>
                                        du {{ Carbon::parse($commande->created_at)->format('d/m/Y') }}
                                        @if ($detail->etat_livraison === 'LIVREE')
                                            <span class="ticket-livre"><i class="fi-rs-check"></i> livré</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($ticket)
                        {{-- Un ticket existe déjà : le formulaire en créait un second
                             à chaque envoi, sans rien signaler au client. --}}
                        <div class="ticket-carte ticket-carte--info">
                            <i class="fi-rs-check"></i>
                            <div>
                                <strong>Un ticket est déjà ouvert pour ce produit.</strong>
                                <p>
                                    Objet : « {{ $ticket->objet }} », ouvert le
                                    {{ Carbon::parse($ticket->created_at)->format('d/m/Y') }}.
                                    Suivez son avancement depuis vos tickets ; inutile d'en ouvrir un second.
                                </p>
                                <a href="{{ route('client.mesTicketsSAV') }}" class="ticket-btn ticket-btn--principal">
                                    <i class="fi-rs-eye"></i> Suivre mon ticket
                                </a>
                            </div>
                        </div>
                    @else
                        {{-- ===== LE TICKET ===== --}}
                        <div class="ticket-carte">
                            <div class="ticket-carte__entete">
                                <h3 class="ticket-carte__titre"><i class="fi-rs-comment-alt"></i> Votre demande</h3>
                            </div>

                            <form method="post" action="{{ route('client.creationTicket', $detail) }}" class="ticket-form">
                                @csrf

                                <label for="objet" class="ticket-label">
                                    Objet de la demande
                                    <span class="ticket-obligatoire">obligatoire</span>
                                </label>
                                <p class="ticket-aide">En quelques mots, de quoi s'agit-il ?</p>
                                <input type="text" id="objet" name="objet" maxlength="255" required
                                       class="ticket-input @error('objet') ticket-input--erreur @enderror"
                                       value="{{ old('objet') }}"
                                       placeholder="Exemple : le produit livré ne correspond pas à la référence commandée">
                                @error('objet')
                                    <div class="ticket-erreur"><i class="fi-rs-cross-circle"></i> {{ $message }}</div>
                                @enderror

                                <label for="message" class="ticket-label mt-4">
                                    Description du problème
                                    <span class="ticket-obligatoire">obligatoire</span>
                                </label>
                                <p class="ticket-aide">
                                    Décrivez ce que vous constatez, et depuis quand. Précisez ce que vous
                                    attendez de nous : remplacement, réparation, conseil d'utilisation.
                                    Plus votre description est précise, plus vite l'agent pourra agir.
                                </p>
                                <textarea id="message" name="message" rows="8" minlength="15" maxlength="2000" required
                                          class="ticket-textarea @error('message') ticket-textarea--erreur @enderror"
                                          placeholder="Exemple : les sacs présentent des traces d'humidité depuis la livraison, et le ciment a durci par endroits.">{{ old('message') }}</textarea>

                                <div class="ticket-compteur">
                                    <span id="compteur">0</span> / 2000 caractères
                                    <span class="ticket-compteur__min">— 15 caractères minimum</span>
                                </div>

                                @error('message')
                                    <div class="ticket-erreur"><i class="fi-rs-cross-circle"></i> {{ $message }}</div>
                                @enderror

                                <div class="ticket-actions">
                                    <a href="{{ route('client.ticketSAV') }}" class="ticket-btn ticket-btn--secondaire">
                                        Annuler
                                    </a>
                                    <button type="submit" class="ticket-btn ticket-btn--principal">
                                        <i class="fi-rs-paper-plane"></i> Envoyer ma demande
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
        .ticket-main { background: #f7f8fa; }

        /* Hero */
        .ticket-hero {
            background: linear-gradient(135deg, #14855a 0%, #1aa972 100%);
            padding: 46px 20px 54px;
            text-align: center;
            color: #fff;
        }
        .ticket-hero__inner { max-width: 720px; margin: 0 auto; }
        .ticket-hero__chip {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.28);
            border-radius: 999px; padding: 5px 14px;
            font-size: .78rem; letter-spacing: .04em; text-transform: uppercase;
        }
        .ticket-hero__title { color: #fff; font-size: 2rem; font-weight: 700; margin: 14px 0 8px; }
        .ticket-hero__subtitle { color: rgba(255,255,255,.92); margin: 0; font-size: .95rem; }

        /* Fil d'Ariane */
        .ticket-fil {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
            font-size: .85rem; color: #77808c; margin: 22px 0 18px;
        }
        .ticket-fil a { color: #14855a; text-decoration: none; }
        .ticket-fil a:hover { text-decoration: underline; }
        .ticket-fil strong { color: #2b3038; font-weight: 600; }

        /* Alertes */
        .ticket-alerte {
            border-radius: 12px; padding: 14px 18px; margin-bottom: 18px;
            font-size: .92rem; border: 1px solid transparent;
        }
        .ticket-alerte--succes { background: #e8f7ef; border-color: #b7e4cd; color: #14663f; }
        .ticket-alerte--erreur { background: #fdecec; border-color: #f5c2c2; color: #922; }

        /* Cartes */
        .ticket-carte {
            background: #fff; border: 1px solid #ebedf1; border-radius: 16px;
            box-shadow: 0 2px 14px rgba(20,30,60,.05);
            padding: 24px; margin-bottom: 22px;
        }
        .ticket-carte__entete { border-bottom: 1px solid #f0f1f4; padding-bottom: 14px; margin-bottom: 20px; }
        .ticket-carte__titre {
            display: flex; align-items: center; gap: 9px;
            font-size: 1.05rem; font-weight: 700; color: #2b3038; margin: 0;
        }
        .ticket-carte__titre i { color: #14855a; }
        .ticket-carte--produit { border-left: 4px solid #14855a; }
        .ticket-carte--info { display: flex; gap: 16px; align-items: flex-start; border-left: 4px solid #1c57a3; }
        .ticket-carte--info > i { color: #1c57a3; font-size: 1.4rem; margin-top: 2px; }
        .ticket-carte--info p { margin: 6px 0 14px; color: #5b636e; font-size: .92rem; }

        /* Produit */
        .ticket-produit { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .ticket-produit__image {
            width: 88px; height: 88px; flex: 0 0 88px;
            border-radius: 12px; background: #f2f4f7;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; color: #a8b0bb; font-size: 1.8rem;
        }
        .ticket-produit__image img { width: 100%; height: 100%; object-fit: cover; }
        .ticket-produit__infos { flex: 1 1 220px; min-width: 0; }
        .ticket-produit__nom { font-size: 1.15rem; font-weight: 700; color: #2b3038; margin: 0 0 6px; }
        .ticket-produit__meta {
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
            color: #5b636e; font-size: .9rem;
        }
        .ticket-sep { width: 4px; height: 4px; border-radius: 50%; background: #c9ced6; }
        .ticket-produit__commande { margin-top: 8px; font-size: .85rem; color: #77808c; }
        .ticket-livre {
            display: inline-flex; align-items: center; gap: 4px; margin-left: 8px;
            background: #e8f7ef; color: #14663f; border-radius: 999px;
            padding: 2px 9px; font-size: .74rem; font-weight: 600;
        }

        /* Formulaire */
        .ticket-label { display: block; font-weight: 600; color: #2b3038; margin-bottom: 6px; }
        .ticket-obligatoire {
            display: inline-block; margin-left: 8px; padding: 2px 8px;
            background: #fdecec; color: #c0392b; border-radius: 999px;
            font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em;
        }
        .ticket-aide { color: #77808c; font-size: .88rem; margin-bottom: 10px; }
        .ticket-input, .ticket-textarea {
            width: 100%; border: 1px solid #dcdfe4; border-radius: 12px;
            padding: 12px 16px; font-size: .95rem; color: #2b3038;
            transition: border-color .15s, box-shadow .15s;
        }
        .ticket-textarea { resize: vertical; }
        .ticket-input:focus, .ticket-textarea:focus {
            outline: none; border-color: #14855a;
            box-shadow: 0 0 0 3px rgba(20,133,90,.12);
        }
        .ticket-input--erreur, .ticket-textarea--erreur { border-color: #d9534f; }
        .ticket-compteur { margin-top: 8px; font-size: .8rem; color: #8b929c; text-align: right; }
        .ticket-compteur__min { color: #a8b0bb; }
        .ticket-erreur {
            display: flex; align-items: center; gap: 7px;
            margin-top: 10px; color: #c0392b; font-size: .88rem;
        }

        /* Boutons */
        .ticket-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 22px; flex-wrap: wrap; }
        .ticket-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 24px; border-radius: 10px; border: 1px solid transparent;
            font-size: .92rem; font-weight: 600; cursor: pointer; text-decoration: none;
            transition: background .15s, color .15s, border-color .15s;
        }
        .ticket-btn--principal { background: #14855a; color: #fff !important; }
        .ticket-btn--principal:hover { background: #0f6746; }
        .ticket-btn--secondaire { background: #fff; color: #5b636e !important; border-color: #dcdfe4; }
        .ticket-btn--secondaire:hover { border-color: #b9bfc8; color: #2b3038 !important; }

        @media (max-width: 575px) {
            .ticket-hero { padding: 32px 16px 38px; }
            .ticket-hero__title { font-size: 1.5rem; }
            .ticket-carte { padding: 18px; }
            .ticket-actions { flex-direction: column-reverse; }
            .ticket-btn { width: 100%; }
        }
    </style>

    <script>
        // Compteur de caractères. Placé ici et non dans une section « jspart » :
        // le gabarit client.main ne réserve aucun emplacement de ce nom, si bien
        // que le script précédent n'était jamais rendu.
        //
        // Ne jamais écrire le nom d'une directive Blade dans un commentaire de
        // cette page : Blade compile le fichier entier, commentaires JavaScript
        // compris — le mot seul suffit à provoquer une erreur 500.
        (function () {
            var champ = document.getElementById('message');
            var compteur = document.getElementById('compteur');
            if (!champ || !compteur) { return; }

            var majuscule = function () { compteur.textContent = champ.value.length; };
            champ.addEventListener('input', majuscule);
            majuscule();
        })();
    </script>
@endsection
