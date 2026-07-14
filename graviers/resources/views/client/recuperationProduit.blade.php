@extends('client.main')
@section('title','Récupération de produit')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if(session('errorQte'))
        <div class="alert alert-danger text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('errorQte') }}</div>
    @endif
    @if(session('livree'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('livree') }}</div>
    @endif

    <main class="main recup-produit-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="recup-produit-hero">
            <div class="recup-produit-hero__inner">
                <span class="recup-produit-hero__chip"><i class="fi-rs-box"></i> Récupération sur place</span>
                <h1 class="recup-produit-hero__title">Commande N°{{ $commande->numero }}</h1>
                <p class="recup-produit-hero__subtitle">
                    Récupérez vos produits directement chez les fournisseurs avec les codes d'enlèvement ci-dessous.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-12">
                    <div class="recup-produit-card">
                        <div class="recup-produit-card__header">
                            <h5 class="recup-produit-card__title">
                                <i class="fi-rs-shopping-bag"></i> Codes d'enlèvement & fournisseurs
                            </h5>
                        </div>
                        <div class="recup-produit-card__body">
                            <div class="table-responsive shopping-summery">
                                <table class="table table-wishlist recup-produit-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th class="custome-checkbox start pl-30"></th>
                                            <th colspan="2">Produit</th>
                                            <th class="text-end">Prix unitaire</th>
                                            <th class="text-center">Quantité</th>
                                            <th class="text-center">Code enlèvement</th>
                                            <th class="text-center">À livrer</th>
                                            <th class="text-center">Restante</th>
                                            <th>Contact fournisseur</th>
                                            <th class="text-center">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @csrf
                                        @php
                                            $commandeQuiOntLivraison = [];
                                            $yaLivraison = false;
                                        @endphp

                                        @foreach ($commande->detailCommande as $key => $detail)
                                            @php $total = 0; $fautMettre = true; @endphp

                                            @foreach ($detail->livraisons->where('accepte','!=',3)->where('deleted_at', null) as $livraison)
                                                <tr class="pt-30">
                                                    @if ($fautMettre)
                                                        @php
                                                            $yaLivraison = true;
                                                            array_push($commandeQuiOntLivraison, $detail->id);
                                                        @endphp

                                                        <td rowspan="{{ $detail->livraisons->where('accepte','!=',3)->count() }}" class="custome-checkbox pl-30"></td>

                                                        <td rowspan="{{ $detail->livraisons->where('accepte','!=',3)->count() }}" class="image product-thumbnail pt-40">
                                                            <img src="/storage/{{ $detail->produit->image->first()->image }}" alt="{{ $detail->produit->nom }}">
                                                        </td>

                                                        <td rowspan="{{ $detail->livraisons->where('accepte','!=',3)->count() }}" class="product-des product-name">
                                                            <h6 class="mb-5 recup-produit__name">{{ $detail->produit->nom }}</h6>
                                                            <small class="text-muted">
                                                                {{ $detail->livraisons->where('accepte','!=',3)->count() }}
                                                                livraison{{ ($detail->livraisons->where('accepte','!=',3)->count() > 1) ? 's' : '' }} prévu{{ ($detail->livraisons->where('accepte','!=',3)->count() > 1) ? 'es' : 'e' }}
                                                            </small>
                                                            @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                                <div class="product-rate-cover mt-1">
                                                                    <div class="product-rate d-inline-block">
                                                                        <div class="product-rating" style="width: {{ $detail->produit->meilleur_note }}%"></div>
                                                                    </div>
                                                                    <span class="font-small ml-5 text-muted">({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                                </div>
                                                            @endif
                                                        </td>

                                                        <td rowspan="{{ $detail->livraisons->where('accepte','!=',3)->count() }}" class="price text-end" data-title="Prix">
                                                            <span class="recup-produit-price">{{ number_format($detail->prix,'0','',' ') }} <small>fcfa</small></span>
                                                        </td>

                                                        <td rowspan="{{ $detail->livraisons->where('accepte','!=',3)->count() }}" class="text-center detail-info" data-title="Quantité">
                                                            <span class="recup-produit-qte-badge">{{ $detail->qte }}</span>
                                                        </td>
                                                        @php
                                                            $fautMettre = false;
                                                            $qteLivree = 0;
                                                        @endphp
                                                    @endif

                                                    @if ($detail->livraisons->where('accepte','!=',3)->count() > 0)
                                                        @if ($livraison->accepte != 3)
                                                            <td class="price text-center" data-title="Code">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                    @if ($livraison->accepte == 2)
                                                                        <span class="recup-produit-badge recup-produit-badge--wait">À accepter</span>
                                                                    @else
                                                                        <span class="recup-produit-code">{{ $livraison->enlevement->code_enleve }}</span>
                                                                    @endif
                                                                </span>
                                                            </td>

                                                            <td class="text-center" data-title="À livrer">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }} class="fw-bold">
                                                                    {{ $livraison->enlevement?->qte_servi }}
                                                                </span>
                                                                @php $qteLivree += $livraison->enlevement?->qte_servi; @endphp
                                                            </td>

                                                            <td class="text-center" data-title="Restante">
                                                                <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }} class="recup-produit-restante">
                                                                    {{ $detail->qte - $qteLivree }}
                                                                </span>
                                                            </td>

                                                            <td data-title="Contact fournisseur">
                                                                <div {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                    <strong class="d-block">
                                                                        <i class="fi-rs-phone-call" style="font-size:11px;color:#10b981;"></i>
                                                                        {{ $livraison->enlevement->fournisseur->user->contact }}
                                                                    </strong>
                                                                    <small class="text-muted d-block">{{ $livraison->enlevement->fournisseur->adresse_geo }}</small>
                                                                    <a class="recup-produit-map" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $livraison->enlevement->fournisseur->latitude }},{{ $livraison->enlevement->fournisseur->longitude }}">
                                                                        <i class="fi-rs-marker"></i> Voir sur la carte
                                                                    </a>
                                                                </div>
                                                            </td>

                                                            <td class="action text-center" data-title="Statut">
                                                                @switch($livraison->etat_livraison)
                                                                    @case('EN ATTENTE')
                                                                        <span class="recup-produit-badge recup-produit-badge--attente">{{ $livraison->etat_livraison }}</span>
                                                                        @break
                                                                    @case('EN TRAITEMENT')
                                                                        <span class="recup-produit-badge recup-produit-badge--traitement">{{ $livraison->etat_livraison }}</span>
                                                                        @break
                                                                    @case('EN COURS LIVRAISON')
                                                                        <span class="recup-produit-badge recup-produit-badge--cours">{{ $livraison->etat_livraison }}…</span>
                                                                        @break
                                                                    @case('LIVREE')
                                                                        <span class="recup-produit-badge recup-produit-badge--livree"><i class="fi-rs-check"></i> Récupéré</span>
                                                                        @break
                                                                    @default
                                                                @endswitch
                                                            </td>
                                                        @endif
                                                    @else
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                        <td></td>
                                                        <td class="action text-center"></td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        @endforeach

                                        @foreach ($commande->detailCommande as $key => $detail)
                                            @if(!in_array($detail->id, $commandeQuiOntLivraison))
                                                <tr class="pt-30">
                                                    <td class="custome-checkbox pl-30"></td>

                                                    <td class="image product-thumbnail pt-40">
                                                        <img src="/storage/{{ $detail->produit->image->first()->image }}" alt="{{ $detail->produit->nom }}">
                                                    </td>

                                                    <td class="product-des product-name">
                                                        <h6 class="mb-5 recup-produit__name">{{ $detail->produit->nom }}</h6>
                                                        @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                            <div class="product-rate-cover">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $detail->produit->meilleur_note }}%"></div>
                                                                </div>
                                                                <span class="font-small ml-5 text-muted">({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="price text-end" data-title="Prix">
                                                        <span class="recup-produit-price">{{ number_format($detail->prix,'0','',' ') }} <small>fcfa</small></span>
                                                    </td>

                                                    <td class="text-center detail-info" data-title="Quantité">
                                                        <span class="recup-produit-qte-badge">{{ $detail->qte }}</span>
                                                    </td>

                                                    <td colspan="4" class="text-center" data-title="Statut">
                                                        <span class="recup-produit-badge recup-produit-badge--pending"><i class="fi-rs-clock"></i> En attente d'assignation</span>
                                                    </td>
                                                    <td class="action text-center"></td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="recup-produit-actions">
                                <a class="recup-produit-back" href="{{ route('client.monCompte') }}">
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
        .recup-produit-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .recup-produit-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .recup-produit-hero__inner { max-width: 1180px; margin: 0 auto; text-align: center; }
        .recup-produit-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .recup-produit-hero__chip i { color: #fbbf24; font-size: 14px; }
        .recup-produit-hero__title,
        h1.recup-produit-hero__title {
            font-size: 1.9rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .recup-produit-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .recup-produit-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .recup-produit-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .recup-produit-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .recup-produit-card__title i { color: #1c57a3; font-size: 18px; }
        .recup-produit-card__body { padding: 6px 0 0; }

        .recup-produit-table thead th {
            background: #f9fafb;
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.72rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 8px !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-top: 0 !important;
        }
        .recup-produit-table tbody td {
            padding: 14px 8px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.86rem;
        }
        .recup-produit-table .product-thumbnail img {
            width: 60px; height: 60px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .recup-produit__name { color: #0a2540 !important; font-weight: 700; margin: 0 0 4px; }
        .recup-produit-price {
            color: #ea580c;
            font-weight: 700;
            white-space: nowrap;
        }
        .recup-produit-price small { color: #6b7280; font-size: 0.7em; }
        .recup-produit-qte-badge {
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
        .recup-produit-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #10b981, #047857);
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            letter-spacing: 0.04em;
            box-shadow: 0 4px 10px rgba(16,185,129,0.25);
        }
        .recup-produit-restante { color: #ea580c; font-weight: 700; }

        .recup-produit-map {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: #eff6ff;
            color: #1c57a3 !important;
            font-size: 0.74rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 4px;
            transition: all 0.15s ease;
        }
        .recup-produit-map:hover {
            background: #1c57a3;
            color: #ffffff !important;
        }
        .recup-produit-map i { font-size: 11px; }

        /* Badges statut */
        .recup-produit-badge {
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
        .recup-produit-badge--attente { background: #f3f4f6; color: #4b5563; }
        .recup-produit-badge--traitement { background: #fef3c7; color: #92400e; }
        .recup-produit-badge--cours { background: #fef3c7; color: #92400e; }
        .recup-produit-badge--livree { background: #d1fae5; color: #065f46; }
        .recup-produit-badge--pending { background: #fef3c7; color: #92400e; }
        .recup-produit-badge--wait { background: #f3f4f6; color: #6b7280; }
        .recup-produit-badge i { font-size: 10px; }

        .barre-livree {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .recup-produit-actions {
            padding: 18px 22px 20px;
            display: flex;
            justify-content: flex-start;
            border-top: 1px solid #f1f5f9;
        }
        .recup-produit-back {
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
        .recup-produit-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .recup-produit-hero { padding: 30px 16px 36px; }
            .recup-produit-hero__title { font-size: 1.4rem; }
        }
    </style>
@endsection
