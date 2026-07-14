@extends('client.main')
@section('title','Récapitulatif de la demande')
@section('content')
    <main class="main recap-livraison-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="recap-livraison-hero">
            <div class="recap-livraison-hero__inner">
                <span class="recap-livraison-hero__chip"><i class="fi-rs-clipboard"></i> Récapitulatif</span>
                <h1 class="recap-livraison-hero__title">Vérifiez votre demande</h1>
                <p class="recap-livraison-hero__subtitle">
                    Prenez un instant pour confirmer les produits demandés avant de valider votre demande de livraison.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row g-4">
                <div class="col-lg-9">
                    <div class="recap-livraison-card">
                        <div class="recap-livraison-card__header">
                            <h5 class="recap-livraison-card__title">
                                <i class="fi-rs-shopping-bag"></i> Produits demandés
                            </h5>
                        </div>
                        <div class="recap-livraison-card__body">
                            <div class="table-responsive">
                                <table class="table recap-livraison-table" id="table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Description</th>
                                            <th class="text-end">Quantité</th>
                                            <th>Unité</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (session('produits') as $produit)
                                            <tr>
                                                <td><strong class="recap-livraison-table__name">{{ $produit['nom_produit'] }}</strong></td>
                                                <td><span class="text-muted">{{ $produit['desc'] }}</span></td>
                                                <td class="text-end fw-bold">{{ $produit['qte'] }}</td>
                                                <td>
                                                    @foreach ($unites as $uneUnite)
                                                        @if ($uneUnite->id == $produit['unite'])
                                                            <span class="recap-livraison-table__unit">{{ $uneUnite->libelle }}</span>
                                                        @endif
                                                    @endforeach
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Side card avec CTA validation --}}
                <div class="col-lg-3">
                    <div class="recap-livraison-card recap-livraison-side">
                        <div class="recap-livraison-card__body text-center">
                            <div class="recap-livraison-side__icon">
                                <i class="fi-rs-shipping-fast"></i>
                            </div>
                            <h6 class="recap-livraison-side__title">Tout est correct ?</h6>
                            <p class="recap-livraison-side__text">
                                Validez votre demande pour la transmettre à notre équipe. Vous serez recontacté(e) rapidement.
                            </p>
                            <a href="{{ route('client.valideDemande') }}" class="recap-livraison-cta">
                                <i class="fi-rs-check"></i> Valider la demande
                            </a>
                            <a href="javascript:history.back()" class="recap-livraison-back">
                                <i class="fi-rs-arrow-left"></i> Modifier
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .recap-livraison-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .recap-livraison-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .recap-livraison-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .recap-livraison-hero__chip {
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
        .recap-livraison-hero__chip i { color: #fbbf24; font-size: 14px; }
        .recap-livraison-hero__title,
        h1.recap-livraison-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .recap-livraison-hero__subtitle {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 0.92rem;
        }

        .recap-livraison-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .recap-livraison-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .recap-livraison-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .recap-livraison-card__title i { color: #1c57a3; font-size: 18px; }
        .recap-livraison-card__body { padding: 6px 0 16px; }

        .recap-livraison-table thead th {
            background: #f9fafb;
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.78rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 16px !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-top: 0 !important;
        }
        .recap-livraison-table tbody td {
            padding: 14px 16px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle;
        }
        .recap-livraison-table tbody tr:last-child td { border-bottom: 0 !important; }
        .recap-livraison-table__name { color: #0a2540; }
        .recap-livraison-table__unit {
            background: #eff6ff;
            color: #1c57a3;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        /* Side */
        .recap-livraison-side { position: sticky; top: 20px; }
        .recap-livraison-side__icon {
            width: 70px; height: 70px;
            margin: 10px auto 14px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1c57a3, #134380);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 8px 20px rgba(28,87,163,0.25);
        }
        .recap-livraison-side__title {
            color: #0a2540;
            font-weight: 700;
            margin: 0 0 6px;
            font-size: 1rem;
        }
        .recap-livraison-side__text {
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.55;
            margin: 0 0 16px;
        }
        .recap-livraison-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 18px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.92rem;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(234, 88, 12, 0.32);
            transition: all 0.18s ease;
            margin-bottom: 8px;
        }
        .recap-livraison-cta:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 14px 28px rgba(234, 88, 12, 0.42);
            color: #ffffff !important;
        }
        .recap-livraison-cta i { font-size: 14px; }
        .recap-livraison-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 10px;
            color: #6b7280 !important;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
        }
        .recap-livraison-back:hover { color: #1c57a3 !important; }

        @media (max-width: 991px) {
            .recap-livraison-side { position: static; }
        }
    </style>
@endsection
