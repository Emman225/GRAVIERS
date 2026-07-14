@extends('client.main')
@section('title', 'Validation de livraison')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if (session('errorQte'))
        <div class="alert alert-danger text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('errorQte') }}</div>
    @endif
    @if (session('livree'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('livree') }}</div>
    @endif

    <main class="main validation-livraison-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="validation-livraison-hero">
            <div class="validation-livraison-hero__inner">
                <span class="validation-livraison-hero__chip"><i class="fi-rs-shipping-fast"></i> Suivi commande</span>
                <h1 class="validation-livraison-hero__title">Commande N°{{ $commande->numero }}</h1>
                <p class="validation-livraison-hero__subtitle">
                    Visualisez en temps réel la progression des livraisons assignées à votre commande.
                </p>
                @if ($commande->statut == 1)
                    <a href="{{ route('client.modifierAdresseLivraison', $commande) }}" class="validation-livraison-action">
                        <i class="fi-rs-marker"></i> Changer d'adresse de livraison
                    </a>
                @endif
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-12">
                    <div class="validation-livraison-card">
                        <div class="validation-livraison-card__header">
                            <h5 class="validation-livraison-card__title">
                                <i class="fi-rs-list-check"></i> Produits & livraisons
                            </h5>
                        </div>
                        <div class="validation-livraison-card__body">
                            <div class="table-responsive shopping-summery">
                                <table class="table table-wishlist validation-livraison-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th class="custome-checkbox start pl-30"></th>
                                            <th scope="col" colspan="2">Produit</th>
                                            <th scope="col" class="text-end">Prix unitaire</th>
                                            <th scope="col" class="text-center">Quantité</th>
                                            <th scope="col">N° Livraison</th>
                                            <th scope="col" class="text-center">Qté livrée</th>
                                            <th scope="col" class="text-center">Restante</th>
                                            <th scope="col">Contact livreur</th>
                                            <th scope="col" class="text-center">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        @csrf
                                        @php
                                            $commandeQuiOntLivraison = [];
                                            $yaLivraison = false;
                                        @endphp

                                        @foreach ($commande->detailCommande as $key => $detail)
                                            @php
                                                $total = 0;
                                                $fautMettre = true;
                                            @endphp

                                            @foreach ($detail->livraisons as $livraison)
                                                @if ($livraison->accepte == 1)
                                                    <tr class="pt-30">
                                                        @if ($fautMettre)
                                                            @php
                                                                $yaLivraison = true;
                                                                array_push($commandeQuiOntLivraison, $detail->id);
                                                            @endphp

                                                            <td rowspan="{{ $detail->livraisons->where('accepte', 1)->count() }}" class="custome-checkbox pl-30"></td>

                                                            <td rowspan="{{ $detail->livraisons->where('accepte', 1)->count() }}" class="image product-thumbnail pt-40">
                                                                <img src="/storage/{{ $detail->produit->image->first()->image }}" alt="{{ $detail->produit->nom }}">
                                                            </td>

                                                            <td rowspan="{{ $detail->livraisons->where('accepte', 1)->count() }}" class="product-des product-name">
                                                                <h6 class="mb-5 validation-livraison-product__name">{{ $detail->produit->nom }}</h6>
                                                                <small class="text-muted">
                                                                    {{ $detail->livraisons->where('accepte', 1)->count() }}
                                                                    livraison{{ $detail->livraisons->where('accepte', 1)->count() > 1 ? 's' : '' }} prévu{{ $detail->livraisons->where('accepte', 1)->count() > 1 ? 'es' : 'e' }}
                                                                </small>
                                                                @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                                    <div class="product-rate-cover mt-1">
                                                                        <div class="product-rate d-inline-block">
                                                                            <div class="product-rating" style="width: {{ $detail->produit->meilleur_note }}%"></div>
                                                                        </div>
                                                                        <span class="font-small ml-5 text-muted">
                                                                            ({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})
                                                                        </span>
                                                                    </div>
                                                                @endif
                                                            </td>

                                                            <td rowspan="{{ $detail->livraisons->where('accepte', 1)->count() }}" class="price text-end" data-title="Prix">
                                                                <span class="validation-livraison-price">{{ number_format($detail->prix, '0', '', ' ') }} <small>fcfa</small></span>
                                                            </td>

                                                            <td rowspan="{{ $detail->livraisons->where('accepte', 1)->count() }}" class="text-center detail-info" data-title="Quantité">
                                                                <span class="validation-livraison-qte-badge">{{ $detail->qte }}</span>
                                                            </td>
                                                            @php
                                                                $fautMettre = false;
                                                                $qteLivree = 0;
                                                            @endphp
                                                        @endif

                                                        @if ($detail->livraisons->where('accepte', 1)->count() > 0)
                                                            <td class="text-center" data-title="N° Livraison">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                    <span class="validation-livraison-num">{{ $livraison->numero }}</span>
                                                                </span>
                                                            </td>

                                                            <td class="text-center" data-title="Qté livrée">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }} class="fw-bold">
                                                                    {{ $livraison->enlevement?->qte_servi }}
                                                                </span>
                                                                @php $qteLivree += $livraison->enlevement?->qte_servi; @endphp
                                                            </td>

                                                            <td class="text-center" data-title="Restante">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }} class="validation-livraison-restante">
                                                                    {{ $detail->qte - $qteLivree }}
                                                                </span>
                                                            </td>

                                                            <td>
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                    <i class="fi-rs-phone-call" style="font-size:11px;color:#10b981;"></i>
                                                                    {{ $livraison->livreur->user->contact }}
                                                                </span>
                                                            </td>

                                                            <td class="action text-center" data-title="Statut">
                                                                @switch($livraison->etat_livraison)
                                                                    @case('EN ATTENTE')
                                                                        <span class="validation-livraison-badge validation-livraison-badge--attente">{{ $livraison->etat_livraison }}</span>
                                                                        @break
                                                                    @case('EN TRAITEMENT')
                                                                        <span class="validation-livraison-badge validation-livraison-badge--traitement">{{ $livraison->etat_livraison }}</span>
                                                                        @break
                                                                    @case('EN COURS LIVRAISON')
                                                                        <span class="validation-livraison-badge validation-livraison-badge--cours">{{ $livraison->etat_livraison }}…</span>
                                                                        @break
                                                                    @case('LIVREE')
                                                                        <span class="validation-livraison-badge validation-livraison-badge--livree"><i class="fi-rs-check"></i> {{ $livraison->etat_livraison }}</span>
                                                                        @break
                                                                    @default
                                                                @endswitch
                                                            </td>
                                                        @else
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td></td>
                                                            <td class="action text-center"></td>
                                                        @endif
                                                    </tr>
                                                @endif
                                            @endforeach
                                        @endforeach

                                        @foreach ($commande->detailCommande as $key => $detail)
                                            @if (!in_array($detail->id, $commandeQuiOntLivraison))
                                                <tr class="pt-30">
                                                    <td class="custome-checkbox pl-30"></td>

                                                    <td class="image product-thumbnail pt-40">
                                                        <img src="/storage/{{ $detail->produit->image->first()->image }}" alt="{{ $detail->produit->nom }}">
                                                    </td>

                                                    <td class="product-des product-name">
                                                        <h6 class="mb-5 validation-livraison-product__name">{{ $detail->produit->nom }}</h6>
                                                        @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $detail->produit->meilleur_note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">
                                                                    ({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="price text-end" data-title="Prix">
                                                        <span class="validation-livraison-price">{{ number_format($detail->prix, '0', '', ' ') }} <small>fcfa</small></span>
                                                    </td>

                                                    <td class="text-center detail-info" data-title="Quantité">
                                                        <span class="validation-livraison-qte-badge">{{ $detail->qte }}</span>
                                                    </td>

                                                    <td colspan="4" class="text-center text-muted" style="font-style:italic">
                                                        <span class="validation-livraison-badge validation-livraison-badge--pending">
                                                            <i class="fi-rs-clock"></i> En attente d'assignation livreur
                                                        </span>
                                                    </td>
                                                    <td class="action text-center"></td>
                                                </tr>
                                            @endif
                                        @endforeach

                                    </tbody>
                                </table>
                            </div>

                            <div class="validation-livraison-actions">
                                <a class="validation-livraison-back" href="{{ route('client.monCompte') }}">
                                    <i class="fi-rs-arrow-left"></i> Retour à mon compte
                                </a>
                            </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .validation-livraison-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 48px;
            overflow: hidden;
            isolation: isolate;
        }
        .validation-livraison-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .validation-livraison-hero__inner { max-width: 1180px; margin: 0 auto; text-align: center; }
        .validation-livraison-hero__chip {
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
        .validation-livraison-hero__chip i { color: #fbbf24; font-size: 14px; }
        .validation-livraison-hero__title,
        h1.validation-livraison-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .validation-livraison-hero__subtitle { margin: 0 0 18px; color: rgba(255,255,255,0.92); font-size: 0.92rem; }
        .validation-livraison-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            background: rgba(255,255,255,0.12);
            border: 1.5px solid rgba(255,255,255,0.4);
            color: #ffffff !important;
            font-weight: 600;
            font-size: 0.88rem;
            border-radius: 10px;
            text-decoration: none;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.18s ease;
        }
        .validation-livraison-action:hover {
            background: #ffffff;
            color: #0a2540 !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.18);
        }
        .validation-livraison-action i { font-size: 14px; }

        .validation-livraison-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .validation-livraison-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .validation-livraison-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .validation-livraison-card__title i { color: #1c57a3; font-size: 18px; }
        .validation-livraison-card__body { padding: 6px 0 0; }

        .validation-livraison-table thead th {
            background: #f9fafb;
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 10px !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-top: 0 !important;
        }
        .validation-livraison-table tbody td {
            padding: 14px 10px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.88rem;
        }
        .validation-livraison-table .product-thumbnail img {
            width: 60px; height: 60px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .validation-livraison-product__name {
            color: #0a2540 !important;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .validation-livraison-price {
            color: #ea580c;
            font-weight: 700;
            white-space: nowrap;
        }
        .validation-livraison-price small { color: #6b7280; font-size: 0.7em; }
        .validation-livraison-qte-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
            padding: 5px 12px;
            background: #eff6ff;
            color: #1c57a3;
            font-weight: 700;
            border-radius: 8px;
        }
        .validation-livraison-num {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #1c57a3;
            background: #eff6ff;
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.82rem;
        }
        .validation-livraison-restante { color: #ea580c; font-weight: 700; }

        /* Badges statut */
        .validation-livraison-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }
        .validation-livraison-badge--attente { background: #f3f4f6; color: #4b5563; }
        .validation-livraison-badge--traitement { background: #dbeafe; color: #1e40af; }
        .validation-livraison-badge--cours { background: #fef3c7; color: #92400e; }
        .validation-livraison-badge--livree { background: #d1fae5; color: #065f46; }
        .validation-livraison-badge--pending { background: #fef3c7; color: #92400e; }
        .validation-livraison-badge i { font-size: 10px; }

        .barre-livree {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .validation-livraison-actions {
            padding: 18px 22px 20px;
            display: flex;
            justify-content: flex-start;
            border-top: 1px solid #f1f5f9;
        }
        .validation-livraison-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            color: #374151 !important;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.15s ease;
        }
        .validation-livraison-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .validation-livraison-hero { padding: 30px 16px 40px; }
            .validation-livraison-hero__title { font-size: 1.4rem; }
        }
    </style>
@endsection
