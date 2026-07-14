@php
$coordonnees = [5.320357, -4.016107]; // Coordonnées par défaut (Abidjan)
$adresse = 0;
$type_affaire = 'VENTE'; // valeur par défaut si panier vide et pas de devis en session
$devis = null;

    if(session('type') == 'devis'){
        $type_affaire = session('type_affaire') ?? 'VENTE';
        $devis = app\Models\Devis::find(session('devisAModifier'));

        if($devis && $devis->adresse){
            $adresse = $devis->adresse->affichage;
            $coordonnees = [$devis->adresseLivraison->latitude, $devis->adresseLivraison->longitude];

        }

    }else{
        foreach(Cart::content() as $produit){

            $type_affaire = $produit->options->type_affaire;
            break;
        }
    }


@endphp

@extends('client.main')
@section('title', 'Ajoutez une adresse')
@section('content')
    <main class="main checkout-main">

        {{-- ===== HERO ===== --}}
        <section class="checkout-hero">
            <div class="checkout-hero__inner">
                <span class="checkout-hero__chip"><i class="fi-rs-shipping-fast"></i> Étape 2 / 3</span>
                <h1 class="checkout-hero__title">Mode de livraison</h1>
                <p class="checkout-hero__subtitle">Indiquez l'adresse où livrer votre commande, ou retirez la sur place.</p>

                {{-- Stepper visuel --}}
                <ol class="checkout-steps">
                    <li class="checkout-steps__item is-done"><span>1</span> Panier</li>
                    <li class="checkout-steps__item is-active"><span>2</span> Livraison</li>
                    <li class="checkout-steps__item"><span>3</span> Paiement</li>
                </ol>
            </div>
        </section>

        <div class="container mb-80 mt-40">
            <div class="row g-4">
                <div class="col-lg-7">
                    {{-- ===== CARD LIVRAISON ===== --}}
                    <div class="checkout-card">
                        <div class="checkout-card__header">
                            <h5 class="checkout-card__title"><i class="fi-rs-marker"></i> Choix de livraison</h5>
                        </div>
                        <div class="checkout-card__body">
                            <form method="post" style="display: block">
                                @csrf

                                {{-- Cards radio Livraison / Retrait --}}
                                <div class="delivery-mode-grid mb-4">
                                    <label class="delivery-mode-card" for="radio1">
                                        <input class="form-check-input" type="radio" name="onMeLivre" value="oui" id="radio1" checked>
                                        <span class="delivery-mode-card__icon"><i class="fi-rs-shipping-fast"></i></span>
                                        <span class="delivery-mode-card__body">
                                            <span class="delivery-mode-card__title">Me faire livrer</span>
                                            <span class="delivery-mode-card__desc">Livraison sur votre chantier</span>
                                        </span>
                                        <span class="delivery-mode-card__check"><i class="fi-rs-check"></i></span>
                                    </label>
                                    <label class="delivery-mode-card" for="radio2">
                                        <input class="form-check-input" type="radio" name="onMeLivre" value="non" id="radio2">
                                        <span class="delivery-mode-card__icon delivery-mode-card__icon--alt"><i class="fi-rs-store"></i></span>
                                        <span class="delivery-mode-card__body">
                                            <span class="delivery-mode-card__title">Retrait sur place</span>
                                            <span class="delivery-mode-card__desc">Je viens chercher la commande</span>
                                        </span>
                                        <span class="delivery-mode-card__check"><i class="fi-rs-check"></i></span>
                                    </label>
                                </div>

                                {{-- information de la livraison --}}
                                <div class="row shipping_calculator" id="formulaire">
                                    {{-- Choix de la ville (en premier pour permettre l'auto-selection de la region) --}}
                                    <div class="custom_select mb-3" style="z-index: 9999">
                                        <label class="checkout-field-label">
                                            <i class="fi-rs-marker"></i> Ville de livraison
                                        </label>
                                        <select id="ville" class="form-control select-active form-bordered checkout-select" name="ville">
                                            <option value="" selected>Selectionnez votre ville de livraison...</option>
                                            @foreach ($villes as $ville)
                                                <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Tapez le nom de votre ville pour la rechercher</small>
                                        @error('ville')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Region (auto-selectionnee ou selection manuelle) --}}
                                    <div class="custom_select mb-3">
                                        <label class="checkout-field-label">
                                            <i class="fi-rs-globe"></i> Région
                                        </label>
                                        <select id="region" class="form-control select-active form-bordered checkout-select" name="region">
                                            <option value="-1">Selectionnez une region...</option>
                                            @foreach ($regions as $region)
                                                <option value="{{ $region->id }}">{{ $region->nom }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Se remplit automatiquement en selectionnant une ville</small>
                                        @error('region')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Adresse precise --}}
                                    <div class="mb-3">
                                        <label class="checkout-field-label">
                                            <i class="fi-rs-home"></i> Adresse précise (quartier, rue, repère)
                                        </label>
                                        <input name="infoSup" type="text" class="form-control checkout-input"
                                               placeholder="Ex: Cocody Angré, près du supermarché..."
                                               id="adressePrecise">
                                        @error('infoSup')
                                            <div class="text-danger mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- champ de recherche de l'adresse sur carte --}}
                                    <div style="position: relative; margin-bottom: 3rem">
                                        <div id="search-container" style="position: absolute; top: 10px; left: 10px; height: 70px; width: 100%; margin-bottom: 3rem; z-index: 999"></div>
                                    </div>
                                    <div class="checkout-map-wrap" style="margin-top: 3rem;">
                                        @error('infoSup')
                                            <div class="text-danger mt-2">{{ $message }}</div>
                                        @enderror
                                        <label class="checkout-field-label">
                                            <i class="fi-rs-marker"></i> Veuillez préciser sur la carte
                                        </label>
                                        <div id="map" style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>
                                    </div>
                                    <div id="coordinates" class="checkout-coords"></div>
                                    @error('lat')
                                        <div class="text-danger mt-2">{{ $message }}</div>
                                    @enderror
                                    @error('long')
                                        <div class="text-danger mt-2">{{ $message }}</div>
                                    @enderror

                                    <input type="hidden" name="long" id="long"><br><br>
                                    <input type="hidden" name="lat" id="lat">
                                </div>

                                {{-- Boutons cachés (ne pas modifier — JS/labels en dépendent) --}}
                                <button type="submit" style="display: none" id="modePaiement" formaction="{{ route('client.modeDePaiement') }}">
                                    Choisir le mode de paiement
                                    <i class="fi-rs-money ml-15"></i>
                                </button>

                                <button style="display: none" id="enregistrerEnDevis" formaction="{{ route('devis.recapDevis') }}"></button>

                                @if (session('type') == 'devis')
                                    <button type="submit" style="display: none" id="modePaiementDevis" formaction="{{ route('client.devisModeDePaiement',$devis) }}">
                                        Enregistrer la modification
                                        <i class="fi-rs-money ml-15"></i>
                                    </button>

                                    <button type="submit" style="display: none" formaction="{{ route('devis.recapDevis',$devis) }}" id="modifierDevis"></button>

                                    @if (session('type') == 'devis')
                                        {{-- ancien bouton commenté conservé --}}
                                    @endif
                                @endif
                                @if ($type_affaire == 'LOCATION')
                                    <button formaction="{{route('client.choixDateProduitLocation')}}" id="choixDeDate" style="display: none"></button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>

                {{-- ===== COLONNE TOTAUX ===== --}}
                <div class="col-lg-5">
                    <div class="checkout-card checkout-summary">
                        <div class="checkout-card__header">
                            <h5 class="checkout-card__title"><i class="fi-rs-shopping-bag"></i> Votre commande</h5>
                        </div>
                        <div class="checkout-card__body">
                            @if (session('type') == 'devis')
                                <div class="table-responsive order_table checkout">
                                    <table class="table no-border col-12 mt-10 checkout-totals">
                                        <tbody>
                                            <tr>
                                                <td><h6 class="text-muted">Montant HT</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                   <h6 class="text-brand text-end"><span id="montantHT">{{ number_format($devis->montant, 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="tva">{{ number_format($total*$tva, 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">Livraison</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="cout_livraison">{{ number_format($conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                                <th></th>
                                                <th class="cart_total_amount">
                                                    <h6 class="text-brand text-end checkout-total-final"><span id="montantTTC">{{ number_format($total+($total*$tva), 0, '', ' ') }}</span> fcfa</h6>
                                                </th>
                                            </tr>
                                            <tr>
                                                <th colspan="3">
                                                    <span class="text-danger" id="messageAlert">
                                                        @if($total+($total*$tva) > 2000000)
                                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                        @endif
                                                    </span>
                                                </th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <label class="checkout-cta" for="modifierDevis">Enregistrer la modification<i class="fi-rs-check ml-15"></i></label>
                                    @if ($type_affaire == 'LOCATION')
                                        <label class="checkout-cta checkout-cta--secondary" for="choixDeDate">Choisir les dates par produit<i class="fi-rs-calendar ml-15"></i></label>
                                    @else
                                        <label class="checkout-cta checkout-cta--secondary lolo" for="modePaiementDevis">
                                            Modifier le mode de paiement
                                            <i class="fi-rs-money ml-15"></i>
                                        </label>
                                    @endif
                                    <a href="{{ route('devis.annulerModificationDevis', $devis) }}" class="checkout-cta-cancel">Annuler la modification</a>
                                </div>
                            @else
                                <div class="table-responsive order_table checkout">
                                    <table class="table no-border checkout-products">
                                        <tbody>
                                            @foreach (Cart::content() as $produit)
                                                <tr>
                                                    <td class="image product-thumbnail">
                                                        <img src="storage/{{ $produit->options->image }}" alt="{{ $produit->name }}">
                                                    </td>
                                                    <td>
                                                        <h6 class="w-160 mb-5 checkout-product-name">{{ $produit->name }}</h6>
                                                        @if(($produit->options->note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $produit->options->note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">
                                                                    ({{ round(($produit->options->note * 5) / 100, 1) }})
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td><h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }}</h6></td>
                                                    <td><h4 class="text-brand">{{ number_format($produit->price, 0, '', ' ') }} <small>fcfa</small></h4></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <table class="table no-border col-12 mt-10 checkout-totals">
                                    <tbody>
                                        <tr>
                                            <td><h6 class="text-muted">Montant HT</h6></td>
                                            <td></td>
                                            <td class="cart_total_amount">
                                                <h6 class="text-brand text-end" id="montantHT">{{ number_format($total, 0, '', ' ') }} fcfa</h6>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                            <td></td>
                                            <td class="cart_total_amount">
                                                <h6 class="text-brand text-end"> <span id="tva">{{ number_format($total*$tva, 0, '', ' ') }}</span> fcfa</h6>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="cart_total_label"><h6 class="text-muted">Livraison</h6></td>
                                            <td></td>
                                            <td class="cart_total_amount">
                                                <h6 class="text-brand text-end"> <span id="cout_livraison">{{ number_format($conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa</h6>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                            <th></th>
                                            <th class="cart_total_amount">
                                                <h6 class="text-brand text-end checkout-total-final"><span id="montantTTC">{{ number_format($total+($total*$tva)+$conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa</h6>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th colspan="3">
                                                <span class="text-danger" id="messageAlert">
                                                    @if($total+($total*$tva)+$conf->cout_livraison_min > 2000000)
                                                        Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                    @endif
                                                </span>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                                @if ($type_affaire == 'LOCATION')
                                    <label class="checkout-cta" for="choixDeDate">Choisir les dates par produit<i class="fi-rs-calendar ml-15"></i></label>
                                @else
                                    <label class="checkout-cta lolo" for="modePaiement">
                                        {{ $client->client_a_terme == 0 ? 'Choisir le mode de paiement' : 'Finaliser la commande' }}
                                        <i class="fi-rs-money ml-15"></i>
                                    </label>
                                    <label class="checkout-cta checkout-cta--secondary lolo" for="enregistrerEnDevis">
                                        Enregistrer en devis
                                        <i class="fi-rs-file"></i>
                                    </label>
                                @endif
                            @endif

                            {{-- Garanties --}}
                            <ul class="checkout-summary__trust">
                                <li><i class="fi-rs-shield-check"></i> Paiement sécurisé</li>
                                <li><i class="fi-rs-truck-side"></i> Suivi de livraison</li>
                                <li><i class="fi-rs-headset"></i> Support 7j/7</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .checkout-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 44px 20px 32px;
            overflow: hidden;
            isolation: isolate;
        }
        .checkout-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .checkout-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .checkout-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
        }
        .checkout-hero__chip i { color: #fbbf24; font-size: 14px; }
        .checkout-hero__title,
        h1.checkout-hero__title {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .checkout-hero__subtitle {
            margin: 0 0 22px;
            color: rgba(255,255,255,0.92);
            font-size: 0.92rem;
            text-shadow: 0 1px 6px rgba(0,0,0,0.25);
        }

        /* ===== STEPPER ===== */
        .checkout-steps {
            display: inline-flex;
            list-style: none;
            padding: 0; margin: 0;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .checkout-steps__item {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 8px 16px;
            font-size: 0.82rem;
            color: rgba(255,255,255,0.65);
            font-weight: 600;
            position: relative;
        }
        .checkout-steps__item + .checkout-steps__item::before {
            content: "›";
            position: absolute;
            left: -3px;
            color: rgba(255,255,255,0.4);
            font-size: 1rem;
        }
        .checkout-steps__item span {
            width: 22px; height: 22px;
            border-radius: 50%;
            background: rgba(255,255,255,0.18);
            color: rgba(255,255,255,0.8);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .checkout-steps__item.is-done { color: #ffffff; }
        .checkout-steps__item.is-done span {
            background: rgba(16, 185, 129, 0.85);
            color: #fff;
        }
        .checkout-steps__item.is-active { color: #ffffff; }
        .checkout-steps__item.is-active span {
            background: #fbbf24;
            color: #0a2540;
            box-shadow: 0 0 0 4px rgba(251, 191, 36, 0.30);
        }

        /* ===== CARDS ===== */
        .checkout-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }
        .checkout-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .checkout-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .checkout-card__title i { color: #1c57a3; font-size: 18px; }
        .checkout-card__body { padding: 22px; }

        /* ===== DELIVERY MODE CARDS ===== */
        .delivery-mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .delivery-mode-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: #ffffff;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.18s ease;
            margin: 0;
        }
        .delivery-mode-card:hover { border-color: #cbd5e1; background: #f9fafb; }
        .delivery-mode-card input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
        .delivery-mode-card__icon {
            width: 42px; height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1c57a3, #134380);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .delivery-mode-card__icon i { font-size: 18px; }
        .delivery-mode-card__icon--alt { background: linear-gradient(135deg, #fb923c, #c2410c); }
        .delivery-mode-card__body { display: flex; flex-direction: column; min-width: 0; }
        .delivery-mode-card__title { font-weight: 700; color: #0a2540; font-size: 0.95rem; }
        .delivery-mode-card__desc { color: #6b7280; font-size: 0.78rem; }
        .delivery-mode-card__check {
            margin-left: auto;
            opacity: 0;
            transform: scale(0.7);
            transition: all 0.2s ease;
            color: #10b981;
            font-size: 18px;
        }
        .delivery-mode-card:has(input:checked) {
            border-color: #1c57a3;
            background: #eff6ff;
            box-shadow: 0 4px 12px rgba(28, 87, 163, 0.10);
        }
        .delivery-mode-card:has(input:checked) .delivery-mode-card__check { opacity: 1; transform: scale(1); }

        /* ===== FORM ===== */
        .checkout-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .checkout-field-label i { color: #1c57a3; font-size: 16px; }

        .checkout-input,
        .checkout-select {
            display: block;
            width: 100%;
            padding: 11px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            color: #111827 !important;
            font-size: 0.92rem !important;
            line-height: 1.4 !important;
            height: auto !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .checkout-input:focus,
        .checkout-select:focus {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12) !important;
            outline: none !important;
        }

        /* ===== MAP ===== */
        .checkout-map-wrap #map {
            border-radius: 14px !important;
            border: 1.5px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }
        .checkout-coords {
            font-size: 0.82rem;
            color: #6b7280;
            background: #f9fafb;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 10px;
        }
        .checkout-coords:empty { display: none; }

        /* ===== SUMMARY ===== */
        .checkout-summary { position: sticky; top: 20px; }
        .checkout-products img {
            width: 56px; height: 56px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .checkout-product-name {
            color: #0a2540 !important;
            font-weight: 700;
            font-size: 0.92rem;
            margin: 0 0 4px;
            line-height: 1.4;
        }
        .checkout-totals { margin: 0; }
        .checkout-totals td, .checkout-totals th { padding: 6px 0 !important; border: 0 !important; }
        .checkout-totals .text-brand { font-weight: 700; color: #0a2540 !important; }
        .checkout-total-final {
            font-size: 1.3rem !important;
            color: #ea580c !important;
            font-weight: 800 !important;
        }
        #messageAlert {
            display: block;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-top: 6px;
        }
        #messageAlert:not(:empty) {
            padding: 10px 12px;
            background: #fef2f2;
            border-radius: 8px;
            border-left: 3px solid #ef4444;
            color: #b91c1c;
        }

        .checkout-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 18px;
            margin-top: 14px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(234, 88, 12, 0.32);
            transition: all 0.18s ease;
            letter-spacing: 0.01em;
            cursor: pointer;
            border: 0;
        }
        .checkout-cta:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 14px 28px rgba(234, 88, 12, 0.42);
            color: #ffffff !important;
        }
        .checkout-cta i { font-size: 16px; }
        .checkout-cta--secondary {
            background: #ffffff;
            color: #1c57a3 !important;
            border: 1.5px solid #1c57a3;
            box-shadow: 0 1px 3px rgba(28, 87, 163, 0.08);
        }
        .checkout-cta--secondary:hover {
            background: #1c57a3;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(28, 87, 163, 0.30);
        }
        .checkout-cta-cancel {
            display: block;
            margin-top: 12px;
            text-align: center;
            color: #6b7280 !important;
            font-size: 0.88rem;
            text-decoration: none;
        }
        .checkout-cta-cancel:hover { color: #ef4444 !important; text-decoration: underline; }

        .checkout-summary__trust {
            list-style: none;
            padding: 16px 0 0;
            margin: 16px 0 0;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .checkout-summary__trust li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4b5563;
            font-size: 0.84rem;
            font-weight: 500;
        }
        .checkout-summary__trust i {
            color: #10b981;
            font-size: 14px;
            width: 26px; height: 26px;
            background: #ecfdf5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .checkout-summary { position: static; }
        }
        @media (max-width: 575px) {
            .checkout-hero { padding: 34px 16px 28px; }
            .checkout-hero__title { font-size: 1.5rem; }
            .delivery-mode-grid { grid-template-columns: 1fr; }
            .checkout-steps__item { padding: 6px 12px; font-size: 0.72rem; }
            .checkout-map-wrap #map { height: 360px !important; }
        }
    </style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    $(function () {
        let villeID = -1;
        let regionID = -1;
        let longitude = 0;
        let latitude = 0;
        let villeChangeTriggered = false; // empêche le rechargement des villes quand on sélectionne une ville

        // SELECTION DE LA REGION (manuelle uniquement)
        $('#region').on('change',function(){
            let region = this.value

            // Si le changement de région vient de la sélection d'une ville, ne pas recharger les villes
            if(villeChangeTriggered){
                villeChangeTriggered = false;
                // Juste recalculer le coût de livraison si coordonnées dispo
                if(longitude != 0 && latitude != 0){
                    calculCoutLivraison(longitude, latitude, region)
                }
                return;
            }

            if(region){
                console.log("region id " + region);
                let url ='/villes/region/'+region
                $.ajax({
                    url: url,
                    type: 'GET',

                    success: function (response) {
                        console.log(response)
                        $('#ville').empty();

                        // Ajouter l'option par défaut
                        $('#ville').append('<option value="">Selectionnez une ville...</option>');

                        // Parcourir l'objet des villes
                        $.each(response.villes, function(nom, id) {
                            $('#ville').append(`<option value="${id}">${nom}</option>`);
                        });

                        // Rafraîchir Select2
                        $('#ville').trigger('change.select2');

                    },
                    error: function () {
                        alert('Une erreur est survenue.');
                    },
                    complete: function(){
                        console.log("Ajax region terminé");
                        if(villeID != -1){
                            $('#ville').val(villeID).trigger('change.select2');
                            villeID = -1;
                        }
                        console.log(longitude, latitude);
                        if(longitude != 0 && latitude != 0){
                            calculCoutLivraison(longitude, latitude, region)
                        }
                    }
                });
            }

        })

        // SELECTION DE LA VILLE (écoute à la fois le change natif et Select2)
        $('#ville').on('change select2:select', function(){
            let ville = $(this).val();
            if(ville){
                villeID = ville;

                let url ='/region/villes/'+ville;
                $.ajax({
                    url: url,
                    type: 'GET',

                    success: function (response) {
                        console.log('Région trouvée:', response.region);
                        // Marquer que le changement de région vient d'une sélection de ville
                        villeChangeTriggered = true;

                        // Mettre à jour le select natif
                        $('#region').val(response.region);

                        // Mettre à jour l'affichage Select2 sans déclencher le rechargement des villes
                        if ($('#region').data('select2')) {
                            $('#region').trigger('change.select2');
                        }
                    },
                    error: function () {
                        alert('Une erreur est survenue.');
                    },
                    complete : function(){
                        console.log("Ajax ville terminé");
                    }
                });
            }
        })
    });

});
</script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // récupération des coordonnées depuis php

        const coord = <?php echo json_encode($coordonnees); ?>;
        console.log('coordonnees:', coord);

        // vérification de devis existant
        const adresse = @json($adresse);
        console.log('adresse:', adresse);
        if(adresse != 0){
            console.log('adresse != 0');
            $('#afficheAdresse').val(adresse);
            // document.getElementById('afficheAdresse').value = adresse;
        }



        const initialCoords = coord;
        const initialZoom = 13;
        var map = L.map('map').setView(initialCoords, initialZoom);
        var marker;
        var currentMarker = null;
        var region = document.getElementById("region");
        var ville = document.getElementById("ville");

        let ttcLiv = $('#montantTTC').text()
        const ttcInt = parseInt(ttcLiv.replace(/\s/g, ''))

        let livraison = $('#cout_livraison').text()
        const livraisonInt = parseInt(livraison.replace(/\s/g, ''))


        console.log('ttcAvecLivraison:', ttcInt, 'livraison', livraisonInt)
        //const infoSup = document.getElementById("input1").value = "";


        // initialisation de la carte
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);


        // INITIALISATION DE LA BARRE DE RECHERCHE
        var geocoder = L.Control.geocoder({
            title: 'Barre de recherche',
            placeholder: 'Entrez votre adresse',
            collapsed: false,
            defaultMarkGeocode: false,

        });

        // LE CONTENEUR DE LA BARRE DE RECHERCHE
        var geocoderContainer = document.getElementById('search-container');
        geocoder.onAdd(map);  // Cette étape initialise le contrôle
        geocoderContainer.appendChild(geocoder.getContainer());

        // AJOUT DE STYLE A LA BARRE DE RECHERCHE
        var searchInput = document.querySelector('.leaflet-control-geocoder input');
            if (searchInput) {
                searchInput.id = 'afficheAdresse'; // Ajouter l'ID
                searchInput.name = 'infoSup'; // Ajouter le name
                // searchInput.style.backgroundColor = 'red';
                searchInput.style.width = '500px';
            }

        //SUPPRIMER LE RESULTAT DE RECHERCHE
        geocoder.on('markgeocode', function (e) {
            // Masquer ou supprimer la liste des résultats
            var resultsContainer = document.querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'none'; // Masquer la liste
                // ou
                // resultsContainer.remove(); // Supprimer la liste
            }
        });

        // RETABLIR L'AFFICHAGE PAR DEFAUT
        geocoder.on('startgeocode', function() {
            var resultsContainer = geocoder.getContainer().querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'block'; // Rétablir l'affichage par défaut
            }
        });

        // LON
        geocoder.on('markgeocode', function(e) {
            var latlng = e.geocode.center;
            updateMarkerPosition(latlng, e.geocode.name);
        });

        function calculCoutLivraison(lng, lat, region){
            console.log('ok')
            $.ajax({
                url:'/calcul/cout/livraison'+lng+'/'+lat+'/'+region,
                type: 'GET',
                success: function(response){
                    console.log('response:', response)

                    $('#cout_livraison').text('0');
                    $('#cout_livraison').text('('+response.km+' km) '+ formatNumber(response.cout_livraison)+' fcfa')


                    let tva = parseInt($('#tva').text().replace(/\s/g, ''))
                    console.log('tva:', parseInt(tva))


                    let montantHT = parseInt($('#montantHT').text().replace(/\s/g, ''))
                    console.log('montantHT:', montantHT)

                    let total = tva + response.cout_livraison+montantHT
                    console.log('voila le totall', total)
                    $('#montantTTC').text('');
                    $('#montantTTC').text(formatNumber(total));
                    console.log('', tva, response.cout_livraison,montantHT)

                    if(total > 2000000) {
                        console.log("Montant TTC depasse 2battons"+ total);
                        $('#messageAlert').html('');
                        $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                    } else {
                        console.log("Montant TTC inferieur ou egal à 2battons"+ total);
                        $('#messageAlert').html('');
                    }

                },
                error: function(error){
                    console.log('error:', error)

                }
            })
        }

        map.on('click', function(e) {

            var latlng = e.latlng;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)

                .then(response => response.json())
                .then(data => {
                    var address = data.display_name; // Nom complet du lieu
                    console.log(address);
                    updateMarkerPosition(latlng, address);
                })
                .catch(error => {
                    console.error('Erreur de géocodage:', error);
                    updateMarkerPosition(latlng); // Sans adresse en cas d'erreur
                });

            // updateMarkerPosition(e.latlng);
            console.log('latlng:', latlng)
            let region = $('#region').val()
            console.log('region:', region)

            longitude = latlng.lng;
            latitude = latlng.lat;

            console.log('les deux', longitude, latitude)
            if(region != -1){
                calculCoutLivraison(latlng.lng, latlng.lat, region)
            }
        });


        let form = document.getElementById('formulaire');

        let livrer = document.getElementById('radio1');
        let recuperer = document.getElementById('radio2');

        livrer.addEventListener('click', function() {
            form.style.display = 'block';
            $('#cout_livraison').text(formatNumber(<?php echo $conf->cout_livraison_min; ?>) +' fcfa');

            let ttc = parseInt($('#montantTTC').text().replace(/\s/g, ''));

            if(ttc != ttcInt){
                $('#montantTTC').text(formatNumber(ttcInt))
            }

            if(ttc > 2000000) {
                console.log("Montant TTC depasse 2battons"+ ttc);
                $('#messageAlert').html('');
                $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
            } else {
                console.log("Montant TTC inferieur ou egal à 2battons"+ ttc);
                $('#messageAlert').html('');
            }
           resetMap();
        });

        recuperer.addEventListener('click', function() {
            form.style.display = 'none';

            let livraison = $('#cout_livraison').text()
            const livraisonInt = parseInt(livraison.replace(/\s/g, ''))

            $('#cout_livraison').text('0 fcfa');

            let ttc = parseInt($('#montantTTC').text().replace(/\s/g, ''));

            if(ttc == ttcInt){
                $('#montantTTC').text(formatNumber(ttcInt-livraisonInt));
                ttc = ttcInt-livraisonInt;
            }

            if(ttc > 2000000) {
                console.log("Montant TTC depasse 2battons"+ ttc);
                $('#messageAlert').html('');
                $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
            } else {
                console.log("Montant TTC inferieur ou egal à 2battons"+ ttc);
                $('#messageAlert').html('');
            }

           resetMap();
        });

        $('#ville').on('change', function () {
            const nomVille = $('#ville option:selected').text();
            if (nomVille && nomVille !== 'Selectionnez une ville...') {
                geocodeVille(nomVille);
            }
        });
/******************************* partie Fonction ******************************************/
          // Réinitialisation de la carte
        function resetMap() {
            // Réinitialiser la carte
            map.setView(initialCoords, initialZoom);

            // Supprimer tous les marqueurs (laisser uniquement la couche de tuiles)
            map.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
            });

            // Réinitialiser les valeurs des champs du formulaire
            // document.getElementById('adresse').value = '';
            // document.getElementById('coordinates').innerHTML = '';
            searchInput.value = '';
            // region.selectedIndex = 0;
            // ville.selectedIndex = 0;
           // Réinitialiser les select
            region.value = "";
            ville.value = "";

            // Déclencher l’événement "change" pour que Select2 se mette à jour
            region.dispatchEvent(new Event('change'));
            ville.dispatchEvent(new Event('change'));
        }

 //
        // Fonction pour mettre à jour la position du marqueur et les informations
        function updateMarkerPosition(latlng, address = null) {
            if (marker) {
                marker.setLatLng(latlng);
            } else {
                marker = L.marker(latlng).addTo(map);
            }
            map.setView(latlng, 13);

            document.getElementById('afficheAdresse').value = address
            document.getElementById('coordinates').innerHTML =
                // 'Latitude: ' + latlng.lat.toFixed(6) + ',  Longitude: ' +latlng.lng.toFixed(6)+', Adresse: '+ address;
                'Latitude: ' + latlng.lat.toFixed(6) + ', Longitudde: ' + latlng.lng.toFixed(6);
            document.getElementById('long').value = latlng.lng;
            document.getElementById('lat').value = latlng.lat;
            // document.getElementById('adresse').value = address;
            // document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
        }

        // Fonction pour géocoder une ville avec Nominatim
        function geocodeVille(nomVille) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nomVille + ', Ivory Coast')}`;

            $.getJSON(url, function(data) {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lon = parseFloat(data[0].lon);

                // Centrer la carte
                map.setView([lat, lon], 13);

                // Supprimer l'ancien marqueur
                if (currentMarker) {
                map.removeLayer(currentMarker);
                }

                // Ajouter un nouveau marqueur
                //currentMarker = L.marker([lat, lon]).addTo(map).bindPopup(nomVille).openPopup();
            } else {
                console.log("Ville non trouvée !");
            }
            });


        }

        function formatNumber(number) {
            return number.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }

    });
    </script>
@endsection
