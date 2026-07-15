@extends('client.main')
@section('title', 'Modifier le devis')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if (session('info'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('info') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info text-center modifie coller-en-haut mt-5" style="display: none;" id="notify">
        <span>Devis mis à jour</span>
    </div>

    <main class="main modif-devis-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="modif-devis-hero">
            <div class="modif-devis-hero__inner">
                <span class="modif-devis-hero__chip"><i class="fi-rs-edit"></i> Modification de devis</span>
                <h1 class="modif-devis-hero__title">Modifier le devis N°{{ $devis->numero }}</h1>
                @if (!empty($devis->libelle))
                    <p class="modif-devis-hero__subtitle"><i class="fi-rs-label"></i> {{ $devis->libelle }}</p>
                @endif
                @if (Cart::count() > 0)
                    <p class="modif-devis-hero__subtitle">
                        Vous avez <strong>{{ Cart::count() }}</strong> article{{ Cart::count() > 1 ? 's' : '' }} dans ce devis.
                    </p>
                @else
                    <p class="modif-devis-hero__subtitle">Aucun produit dans ce devis.</p>
                @endif
            </div>
        </section>

        <div class="container mb-80 mt-30">

            @if (Cart::count() > 0)
                {{-- Bandeau : rappel que le client MODIFIE un devis. Sans ça, il pouvait
                     croire être bloqué (le panier le renvoie ici tant que la modification
                     n'est pas quittée) et penser à un bug. --}}
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" style="border-radius:10px;">
                    <div>
                        <i class="fi-rs-info"></i>
                        <strong>Vous êtes en train de modifier le devis N°{{ $devis->numero ?? $devis->id }}.</strong>
                        Tant que vous ne l'avez pas quittée, votre panier reste rattaché à ce devis.
                    </div>
                    <a href="{{ route('devis.annulerModificationDevis', $devis) }}" class="btn btn-sm btn-danger">
                        <i class="fi-rs-cross"></i> Quitter et faire une commande normale
                    </a>
                </div>

                {{-- Top actions bar --}}
                <div class="modif-devis-toolbar mb-3">
                    <a href="{{ route('client.index') }}" class="modif-devis-toolbar__link">
                        <i class="fi-rs-arrow-left"></i> Continuer mes achats
                    </a>
                    <a href="{{ route('client.nettoyer') }}" class="modif-devis-toolbar__danger"
                       onclick="return confirm('Voulez-vous vraiment vider le panier??')">
                        <i class="fi-rs-trash"></i> Vider le panier
                    </a>
                </div>

                <div class="row g-4">
                    {{-- ===== COLONNE PRODUITS ===== --}}
                    <div class="col-lg-8 contenuPanier">
                        <div class="modif-devis-card">
                            <div class="modif-devis-card__header">
                                <h5 class="modif-devis-card__title"><i class="fi-rs-shopping-bag"></i> Articles du devis ({{ Cart::count() }})</h5>
                            </div>

                            <div class="table-responsive shopping-summery">
                                <table id="table" class="table table-wishlist modif-devis-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th class="custome-checkbox start pl-30"></th>
                                            <th scope="col" colspan="2">Produits</th>
                                            <th scope="col">Prix unitaire</th>
                                            <th scope="col">Quantité</th>
                                            <th scope="col">Sous-total</th>
                                            <th scope="col" class="end">Action</th>
                                        </tr>
                                    </thead>
                                    @php $i = 0; @endphp
                                    <form method="POST" action="{{ route('client.update.produit') }}">
                                        @csrf
                                        <tbody id="listProduit">
                                            @foreach (Cart::content() as $produit)
                                                @php $type_affaire = $produit->options->type_affaire @endphp
                                                <tr class="pt-30 modif-devis-row" data-rowid="{{ $produit->rowId }}">
                                                    <td class="custome-checkbox pl-30"></td>

                                                    <td class="image product-thumbnail pt-40">
                                                        <img src="/storage/{{ $produit->options->image }}" alt="{{ $produit->name }}" />
                                                    </td>

                                                    <td class="product-des product-name">
                                                        <h6 class="mb-5"><strong class="modif-devis-product__name">{{ $produit->name }}</strong></h6>
                                                        @if(($produit->model->meilleur_note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $produit->model->meilleur_note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">
                                                                    ({{ round(($produit->model->meilleur_note * 5) / 100, 1) }})
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="price" data-title="Prix">
                                                        <div>
                                                            <div class="detail-qty modif-devis-price">
                                                                {{ number_format($produit->price, 0, '', ' ') }} fcfa /
                                                                {{ $produit->model->UniteProduit->abreviation }}
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="text-center detail-info" data-title="Quantité">
                                                        <div class="detail-extralink mr-15">
                                                            <div class="detail-qty border radius modif-devis-qty">
                                                                <a href="#" class="qty-down" aria-label="Diminuer"><i class="fi-rs-angle-small-down"></i></a>
                                                                <input type="text" name="qte[]" class="qty-val" value="{{ $produit->qty }}" min="1">
                                                                <a href="#" class="qty-up" aria-label="Augmenter"><i class="fi-rs-angle-small-up"></i></a>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="price" data-title="Sous-total">
                                                        <div class="detail-extralink mr-15">
                                                            <div class="radius w-100 border-bleu modif-devis-subtotal-wrap">
                                                                <input type="text" id="montant"
                                                                       min="{{ $produit->price }}" name="montant[]"
                                                                       class="qty-val"
                                                                       value="{{ $produit->price * $produit->qty }}">
                                                            </div>
                                                        </div>
                                                    </td>

                                                    <td class="action text-center" data-title="Supprimer">
                                                        <a onclick="return confirm('Voulez vous supprimer ce produit?')"
                                                           href="{{ route('client.supprimer.produit', $produit->rowId) }}"
                                                           class="modif-devis-remove-btn" title="Retirer du devis">
                                                            <i class="fi-rs-trash"></i>
                                                        </a>
                                                    </td>
                                                    <input type="hidden" name="rowId[]" value="{{ $produit->rowId }}">
                                                </tr>
                                                @php $i++; @endphp
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- ===== COLONNE TOTAUX ===== --}}
                    <div class="col-lg-4">
                        <div class="modif-devis-card modif-devis-summary">
                            <div class="modif-devis-card__header">
                                <h5 class="modif-devis-card__title"><i class="fi-rs-calculator"></i> Récapitulatif</h5>
                            </div>
                            <div class="modif-devis-summary__body">
                                <div class="modif-devis-summary__row">
                                    <span class="modif-devis-summary__label">Montant HT</span>
                                    <span class="modif-devis-summary__value">
                                        <span id="montant_total">{{ number_format($total, 0, '', ' ') }}</span> FCFA
                                    </span>
                                </div>
                                <div class="modif-devis-summary__row">
                                    <span class="modif-devis-summary__label">TVA</span>
                                    <span class="modif-devis-summary__value">
                                        <span id="montant_tva">{{ number_format($total * $tva, 0, '', ' ') }}</span> FCFA
                                    </span>
                                </div>
                                <div class="modif-devis-summary__divider"></div>
                                <div class="modif-devis-summary__row modif-devis-summary__row--total">
                                    <span class="modif-devis-summary__label">Montant TTC</span>
                                    <span class="modif-devis-summary__value modif-devis-summary__value--big">
                                        <span id="montant_ttc">{{ number_format($total + ($total * $tva), 0, '', ' ') }}</span>
                                        <small>FCFA</small>
                                    </span>
                                </div>

                                <div class="modif-devis-summary__alert">
                                    <span class="text-danger" id="messageAlert">
                                        @if($totalCommande + ($totalCommande * $tva) > 2000000)
                                            Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                        @endif
                                    </span>
                                </div>

                                {{-- Actions du devis --}}
                                <div class="modif-devis-actions-stack">
                                    <a href="{{ route('devis.recapDevis', $devis) }}" class="modif-devis-cta modif-devis-cta--primary">
                                        <i class="fi-rs-file"></i> Récapitulatif du devis
                                    </a>
                                    <a href="{{ route('client.devisAdresse', $devis) }}" class="modif-devis-cta modif-devis-cta--secondary">
                                        <i class="fi-rs-marker"></i> Modifier l'adresse
                                    </a>
                                    <a href="{{ route('devis.annulerModificationDevis', $devis) }}" class="modif-devis-cancel-link">
                                        <i class="fi-rs-cross"></i> Annuler la modification
                                    </a>
                                </div>

                                {{-- Garanties --}}
                                <ul class="modif-devis-summary__trust">
                                    <li><i class="fi-rs-shield-check"></i> Paiement sécurisé</li>
                                    <li><i class="fi-rs-truck-side"></i> Livraison rapide</li>
                                    <li><i class="fi-rs-headset"></i> Support 7j/7</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                {{-- ===== ÉTAT VIDE ===== --}}
                <div class="modif-devis-empty">
                    <div class="modif-devis-empty__icon"><i class="fi-rs-file"></i></div>
                    <h3 class="modif-devis-empty__title">Ce devis est vide</h3>
                    <p class="modif-devis-empty__text">
                        Ajoutez des produits depuis le catalogue pour les inclure dans ce devis.
                    </p>
                    <a href="{{ route('client.index') }}" class="modif-devis-empty__btn">
                        <i class="fi-rs-arrow-right"></i> Parcourir le catalogue
                    </a>
                </div>
            @endif
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .modif-devis-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 44px 20px 50px;
            overflow: hidden;
            isolation: isolate;
        }
        .modif-devis-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .modif-devis-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .modif-devis-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 7px 14px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .modif-devis-hero__chip i { color: #fbbf24; font-size: 16px; }
        .modif-devis-hero__title,
        h1.modif-devis-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .modif-devis-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.95rem; }
        .modif-devis-hero__subtitle strong { color: #fbbf24; font-weight: 700; }

        /* ===== TOOLBAR ===== */
        .modif-devis-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .modif-devis-toolbar__link {
            display: inline-flex; align-items: center; gap: 8px;
            color: #1c57a3;
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
        }
        .modif-devis-toolbar__link:hover { color: #0a2540; transform: translateX(-2px); }
        .modif-devis-toolbar__danger {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 7px 14px;
            border-radius: 10px;
            background: #fef2f2;
            color: #b91c1c !important;
            font-weight: 600;
            font-size: 0.88rem;
            text-decoration: none;
            border: 1px solid #fecaca;
        }
        .modif-devis-toolbar__danger:hover { background: #fee2e2; }

        /* ===== CARD ===== */
        .modif-devis-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .modif-devis-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .modif-devis-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .modif-devis-card__title i { color: #1c57a3; font-size: 18px; }

        /* ===== TABLE ===== */
        .modif-devis-table thead .main-heading { background: #f9fafb; }
        .modif-devis-table thead th {
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.82rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 12px !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        .modif-devis-table tbody td {
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 18px 12px !important;
        }
        .modif-devis-table .product-thumbnail img {
            width: 80px; height: 80px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .modif-devis-product__name {
            color: #0a2540;
            font-weight: 700;
        }
        .modif-devis-price { font-weight: 700; color: #ea580c; font-size: 0.95rem; white-space: nowrap; }

        .modif-devis-table .modif-devis-qty {
            display: inline-flex !important;
            align-items: center;
            background: #ffffff;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            overflow: hidden;
            padding: 0 !important;
            width: auto !important;
            min-width: 130px;
        }
        .modif-devis-table .modif-devis-qty .qty-down,
        .modif-devis-table .modif-devis-qty .qty-up {
            flex: 0 0 36px;
            width: 36px !important;
            height: 40px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            color: #6b7280 !important;
            background: #f9fafb;
            transition: all 0.15s ease;
        }
        .modif-devis-table .modif-devis-qty .qty-down:hover,
        .modif-devis-table .modif-devis-qty .qty-up:hover {
            background: #1c57a3;
            color: #ffffff !important;
        }
        .modif-devis-table .modif-devis-qty input.qty-val {
            flex: 1 1 auto !important;
            width: 60px !important;
            min-width: 60px !important;
            text-align: center !important;
            border: 0 !important;
            background: transparent !important;
            background-color: transparent !important;
            font-weight: 700 !important;
            color: #0a2540 !important;
            font-size: 0.95rem !important;
            outline: none !important;
            height: 40px !important;
            padding: 0 6px !important;
            margin: 0 !important;
            display: block !important;
            box-shadow: none !important;
        }
        .modif-devis-table .modif-devis-qty input.qty-val:focus {
            outline: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .modif-devis-subtotal-wrap {
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 7px 10px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .modif-devis-subtotal-wrap:focus-within {
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }
        .modif-devis-subtotal-wrap input.qty-val {
            width: 100% !important;
            text-align: center;
            border: 0 !important;
            background: transparent !important;
            font-weight: 700;
            color: #0a2540;
            outline: none !important;
            font-size: 0.92rem;
        }

        .modif-devis-remove-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px; height: 36px;
            border-radius: 50%;
            background: #fef2f2;
            color: #b91c1c !important;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .modif-devis-remove-btn:hover {
            background: #ef4444;
            color: #ffffff !important;
            transform: scale(1.06);
            box-shadow: 0 6px 14px rgba(239, 68, 68, 0.30);
        }
        .modif-devis-remove-btn i { font-size: 16px; }

        /* ===== SUMMARY ===== */
        .modif-devis-summary { position: sticky; top: 20px; }
        .modif-devis-summary__body { padding: 20px 22px 22px; }
        .modif-devis-summary__row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 8px 0;
            font-size: 0.92rem;
        }
        .modif-devis-summary__label { color: #6b7280; font-weight: 500; }
        .modif-devis-summary__value { color: #0a2540; font-weight: 700; }
        .modif-devis-summary__divider {
            height: 1px;
            background: #e5e7eb;
            margin: 8px 0;
        }
        .modif-devis-summary__row--total .modif-devis-summary__label {
            color: #0a2540;
            font-weight: 700;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .modif-devis-summary__value--big {
            font-size: 1.4rem !important;
            color: #ea580c !important;
        }
        .modif-devis-summary__value--big small { font-size: 0.65em; color: #6b7280; font-weight: 600; margin-left: 4px; }
        .modif-devis-summary__alert { min-height: 0; }
        .modif-devis-summary__alert .text-danger {
            display: block;
            font-size: 0.82rem;
            line-height: 1.5;
            margin-top: 8px;
            padding: 10px 12px;
            background: #fef2f2;
            border-radius: 8px;
            border-left: 3px solid #ef4444;
        }
        .modif-devis-summary__alert .text-danger:empty { display: none; }

        .modif-devis-actions-stack {
            margin-top: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modif-devis-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 13px 18px;
            font-weight: 700;
            font-size: 0.92rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.18s ease;
            letter-spacing: 0.01em;
            border: 1.5px solid transparent;
        }
        .modif-devis-cta--primary {
            background: linear-gradient(135deg, #fb923c, #ea580c);
            color: #ffffff !important;
            box-shadow: 0 10px 22px rgba(234, 88, 12, 0.32);
        }
        .modif-devis-cta--primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c, #c2410c);
            box-shadow: 0 14px 28px rgba(234, 88, 12, 0.42);
            color: #ffffff !important;
        }
        .modif-devis-cta--secondary {
            background: #ffffff;
            color: #1c57a3 !important;
            border-color: #1c57a3;
        }
        .modif-devis-cta--secondary:hover {
            background: #1c57a3;
            color: #ffffff !important;
            box-shadow: 0 8px 18px rgba(28, 87, 163, 0.30);
        }
        .modif-devis-cta i { font-size: 14px; }

        .modif-devis-cancel-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px;
            color: #ef4444 !important;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.15s ease;
        }
        .modif-devis-cancel-link:hover {
            background: #fef2f2;
            text-decoration: underline;
        }
        .modif-devis-cancel-link i { font-size: 12px; }

        .modif-devis-summary__trust {
            list-style: none;
            padding: 16px 0 0;
            margin: 16px 0 0;
            border-top: 1px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modif-devis-summary__trust li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4b5563;
            font-size: 0.84rem;
            font-weight: 500;
        }
        .modif-devis-summary__trust i {
            color: #10b981;
            font-size: 16px;
            width: 26px; height: 26px;
            background: #ecfdf5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ===== ÉTAT VIDE ===== */
        .modif-devis-empty {
            max-width: 520px;
            margin: 40px auto 0;
            padding: 50px 30px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .modif-devis-empty__icon {
            width: 90px; height: 90px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1c57a3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .modif-devis-empty__title { color: #0a2540; font-weight: 700; font-size: 1.3rem; margin: 0 0 8px; }
        .modif-devis-empty__text { color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 0 0 22px; }
        .modif-devis-empty__btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #fb923c, #ea580c);
            color: #ffffff !important;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.30);
            transition: all 0.18s ease;
        }
        .modif-devis-empty__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(234, 88, 12, 0.42);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 991px) {
            .modif-devis-summary { position: static; }
        }
        @media (max-width: 575px) {
            .modif-devis-hero { padding: 34px 16px 38px; }
            .modif-devis-hero__title { font-size: 1.4rem; }
            .modif-devis-table thead { display: none; }
            .modif-devis-table tbody td { display: block; border-bottom: 0 !important; padding: 8px 12px !important; }
            .modif-devis-table tbody tr { border-bottom: 1px solid #f1f5f9; padding: 14px 0 !important; }
            .modif-devis-summary__value--big { font-size: 1.2rem !important; }
        }
    </style>
@endsection

@section('jspart')
    <script>
        // Garde Leaflet (l'élément #map n'existe pas dans cette vue mais le script reste pour compat)
        if (document.getElementById('map')) {
            var map = L.map('map').setView([51.505, -0.09], 13);

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            L.marker([51.5, -0.09]).addTo(map)
                .bindPopup('A pretty CSS popup.<br> Easily customizable.')
                .openPopup();
        }
    </script>

    <script>
    $(document).ready(function () {

        let donnees = getColonnesAvecRowId("table", [4, 5]);

        console.log('first donnes:', donnees);

        function updateTotals(row, newQty) {
            let rowId = row.data('rowid');

            $.ajax({
                url: '{{ route('panier.update.quantite') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qty: newQty
                },
                success: function (response) {
                    if (response.success) {
                        row.find('input[id=montant]').val(response.subtotal);
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);

                        if(response.ttc.replace(/\s/g, '') > 2000000) {
                            console.log("Montant TTC depasse 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                            $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                        } else {
                            console.log("Montant TTC inferieur ou egal à 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                        }
                    }
                },
                error: function () {
                    alert('Une erreur est survenue.');
                }
            });
        }

        function getColonnesAvecRowId(idTable, indexColonnes = []) {
            const resultat = [];
            const table = document.getElementById(idTable);
            const lignes = table.querySelectorAll("tbody tr");

            lignes.forEach(ligne => {
                const rowId = ligne.getAttribute("data-rowid") || "";
                const cellules = ligne.querySelectorAll("td");
                const ligneDonnees = [rowId];

                indexColonnes.forEach(index => {
                const cellule = cellules[index];
                if (!cellule) {
                    ligneDonnees.push("");
                    return;
                }

                const input = cellule.querySelector("input");
                if (input) {
                    ligneDonnees.push(input.value.trim());
                } else {
                    ligneDonnees.push(cellule.textContent.trim());
                }
                });

                resultat.push(ligneDonnees);
            });

            return resultat;
        }

        console.log("Resultats : ", donnees);

        $('.qty-up').click(function (e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');
            let newQty = parseInt(input.val()) + 1;
            console.log("New Qty:", newQty);

            updateTotals(row, newQty);
        });

        $('.qty-down').click(function (e) {
            e.preventDefault();
            let row = $(this).closest('tr');
            let input = row.find('input[name="qte[]"]');
            let currentQty = parseInt(input.val());
            if (currentQty > 1) {
                let newQty = currentQty - 1;
                updateTotals(row, newQty);
            }
        });

        $('input[name="qte[]"]').on('keyup', function () {
            let row = $(this).closest('tr');
            let newQty = parseInt($(this).val());

            if (newQty > 0) {
                updateTotals(row, newQty);
            }
        });

        function updateByTotal(rowId, qte, prixUnitaire, montant) {
             $.ajax({
                url: '{{ route('panier.update.all') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    rowId: rowId,
                    qte: qte,
                    prixUnitaire: prixUnitaire,
                    montant: montant
                },
                success: function (response) {
                    if (response.success) {
                        console.log("Response:", response.qte);
                        if (response.qte < 0.1) {
                            alert('La quantité doit être supérieure à 0.1 !');
                            return;
                        }
                        console.log("Response:", response);
                        const tr = $('tr[data-rowid="' + response.rowId + '"]');

                        tr.find('input[name="qte[]"]').val(response.qte);
                        tr.find('input[name="montant[]"]').val(response.subtotal);
                        $('#montant_total').text(response.total);
                        $('#montant_tva').text(response.tva);
                        $('#montant_ttc').text(response.ttc);

                        if(response.ttc.replace(/\s/g, '') > 2000000) {
                            console.log("Montant TTC depasse 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                            $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                        } else {
                            console.log("Montant TTC inferieur ou egal à 2battons"+ response.ttc.replace(/\s/g, ''));
                            $('#messageAlert').html('');
                        }

                    }
                },
                error: function () {
                    alert('Une erreur est survenue lors de la mise à jour du total.');
                }
            });
        }

        const debounceTimers = {};

        $(document).on('input', 'input[name="montant[]"]', function () {
            const inputMontant = $(this);
            const inputId = inputMontant.closest('tr').data('rowid');

            clearTimeout(debounceTimers[inputId]);

            debounceTimers[inputId] = setTimeout(function () {
                const tr = inputMontant.closest('tr');
                const rowId = tr.data('rowid');

                const montant = parseFloat(inputMontant.val()) || 0;
                const qte = parseFloat(tr.find('input[name="qte[]"]').val()) || 0;

                const prixText = tr.find('td.price .detail-qty').text().trim();
                const matchPrix = prixText.match(/(\d+([.,]?\d+)?)/);
                const prixUnitaire = matchPrix ? parseFloat(matchPrix[1].replace(',', '.')) : 0;
                if(montant > 0){
                    console.log('montant là: ' + montant);
                    updateByTotal(rowId, qte, prixUnitaire, montant);
                }

            }, 500);
        });

        $('#maj').click(function (e) {
            donnees = getColonnesAvecRowId("table", [4, 5]);

            $.ajax({
                url: form.action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    if (response.success) {
                        $('#notify').text('Panier mis à jour avec succès').show();
                        setTimeout(() => {
                            $('#notify').fadeOut();
                        }, 3000);
                    }
                },
                error: function () {
                    alert('Une erreur est survenue lors de la mise à jour du panier.');
                }
            });
        });

        function envoyerTableauParFetch(tableau) {
            fetch('{{ route('panier.update.all') }}', {
                method: "POST",
                headers: {
                "Content-Type": "application/json",
                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ data: tableau })
            })
            .then(response => response.json())
            .then(result => {
                console.log("Succès :", result);
            })
            .catch(error => {
                console.error("Erreur AJAX :", error);
            });
        }


    });
</script>
@endsection
