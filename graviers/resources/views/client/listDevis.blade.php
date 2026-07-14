@extends('client.main')
@section('title','Mes devis')
@section('content')
@include('client.navMobile')

<main class="main list-devis-main">
    {{-- ===== HERO ===== --}}
    <section class="list-devis-hero">
        <div class="list-devis-hero__inner">
            <span class="list-devis-hero__chip"><i class="fi-rs-file"></i> Mes devis</span>
            <h1 class="list-devis-hero__title">Mes devis en attente</h1>
            <p class="list-devis-hero__subtitle">
                Vous avez <strong>{{ $devis->count() }}</strong> devis en attente de validation. Convertissez-les en commande quand vous êtes prêt(e).
            </p>
        </div>
    </section>

    <div class="container mb-80 mt-30">
        @if($devis->count() > 0)
            <div class="row g-4">
                @foreach($devis as $devis)
                    <div class="col-lg-6">
                        <div class="list-devis-card">
                            <div class="list-devis-card__header">
                                <div>
                                    <span class="list-devis-card__chip">Devis</span>
                                    <h5 class="list-devis-card__title">N°{{ $devis->numero }}</h5>
                                    @if (!empty($devis->libelle))
                                        <div class="list-devis-card__libelle">{{ $devis->libelle }}</div>
                                    @endif
                                </div>
                                <div class="list-devis-card__total">
                                    <span class="list-devis-card__total-label">Montant</span>
                                    <span class="list-devis-card__total-value">{{ number_format($devis->montant, 0, '', ' ') }} <small>FCFA</small></span>
                                </div>
                            </div>

                            <div class="list-devis-card__body">
                                <div class="table-responsive shopping-summery">
                                    <table class="table table-wishlist list-devis-table">
                                        <thead>
                                            <tr class="main-heading">
                                                <th colspan="2">Produit</th>
                                                <th class="text-end">Prix</th>
                                                <th class="text-center">Qté</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $total = 0 @endphp
                                            <form>
                                                @csrf
                                                @foreach ($devis->detailDevis as $detail)
                                                    <tr class="pt-30">
                                                        @foreach ($detail->produit->image as $image)
                                                            <td class="image product-thumbnail pt-40"><img src="/storage/{{ $image->image }}"
                                                        @endforeach
                                                                alt="{{ $detail->produit->nom }}"></td>
                                                        <td class="product-des product-name">
                                                            <h6 class="mb-5 list-devis-product__name">{{ $detail->produit->nom }}</h6>
                                                            @if(($detail->produit->meilleur_note ?? 0) > 0)
                                                                <div class="product-rate-cover">
                                                                    <div class="product-rate d-inline-block">
                                                                        <div class="product-rating" style="width: {{ $detail->produit->meilleur_note }}%"></div>
                                                                    </div>
                                                                    <span class="font-small ml-5 text-muted">({{ round(($detail->produit->meilleur_note * 5) / 100, 1) }})</span>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        <td class="price text-end" data-title="Price">
                                                            @if(isset($prixPerso[$detail->produit->id]))
                                                                <strong class="list-devis-price">{{ number_format($prixPerso[$detail->produit->id], 0, '', ' ') }}</strong>
                                                                <small class="text-muted text-decoration-line-through d-block">{{ number_format($detail->produit->prix_moyen, 0, '', ' ') }}</small>
                                                            @else
                                                                <strong class="list-devis-price">{{ number_format($detail->produit->prix_moyen, 0, '', ' ') }}</strong>
                                                            @endif
                                                        </td>
                                                        <td class="text-center detail-info" data-title="Stock">
                                                            <div class="detail-extralink">
                                                                <div class="detail-qty border radius list-devis-qty">
                                                                    <a href="#" class="qty-down"><i class="fi-rs-angle-small-down"></i></a>
                                                                    <input type="text" name="qte[]" class="qty-val" value="{{ $detail->qte }}" min="1">
                                                                    <a href="#" class="qty-up"><i class="fi-rs-angle-small-up"></i></a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="price text-end" data-title="Total">
                                                            @if(isset($prixPerso[$detail->produit->id]))
                                                                <strong class="list-devis-subtotal">{{ number_format($prixPerso[$detail->produit->id] * $detail->qte, 0, '', ' ') }}</strong>
                                                                @php $total += $prixPerso[$detail->produit->id] * $detail->qte @endphp
                                                            @else
                                                                <strong class="list-devis-subtotal">{{ number_format($detail->produit->prix_moyen * $detail->qte, 0, '', ' ') }}</strong>
                                                                @php $total += $detail->produit->prix_moyen * $detail->qte @endphp
                                                            @endif
                                                        </td>
                                                        <td class="action text-center" data-title="Remove">
                                                            <a href="{{ route('client.supprimer.produit', $detail->produit->id) }}" class="list-devis-remove" title="Retirer">
                                                                <i class="fi-rs-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <input type="hidden" name="rowId[]" value="{{ $detail->produit->id }}">
                                                @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="list-devis-actions">
                                    <a href="{{ route('client.factureDevis', $devis->numero) }}" class="list-devis-btn list-devis-btn--secondary" type="submit">
                                        <i class="fi-rs-print"></i> Format PDF
                                    </a>
                                    <button formaction="{{ route('client.devisModeDePaiement', $devis) }}" class="list-devis-btn list-devis-btn--primary" type="submit">
                                        <i class="fi-rs-check"></i> Passer la commande
                                    </button>
                                </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="list-devis-empty">
                <div class="list-devis-empty__icon"><i class="fi-rs-file"></i></div>
                <h3 class="list-devis-empty__title">Aucun devis en cours</h3>
                <p class="list-devis-empty__text">
                    Vous n'avez pas encore enregistré de devis. Ajoutez des produits au panier, puis sauvegardez-les en devis lors de la commande.
                </p>
                <a href="{{ route('client.index') }}" class="list-devis-empty__btn">
                    <i class="fi-rs-arrow-right"></i> Découvrir les produits
                </a>
            </div>
        @endif
    </div>
</main>

<style>
    .list-devis-hero {
        position: relative;
        background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
        color: #fff;
        padding: 40px 20px 44px;
        overflow: hidden;
        isolation: isolate;
    }
    .list-devis-hero::after {
        content: "";
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
            radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
        z-index: -1;
    }
    .list-devis-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
    .list-devis-hero__chip {
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
    .list-devis-hero__chip i { color: #fbbf24; font-size: 14px; }
    .list-devis-hero__title,
    h1.list-devis-hero__title {
        font-size: 2rem;
        font-weight: 800;
        margin: 0 0 6px;
        color: #ffffff !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.35);
    }
    .list-devis-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }
    .list-devis-hero__subtitle strong { color: #fbbf24; }

    .list-devis-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        height: 100%;
        transition: all 0.18s ease;
    }
    .list-devis-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(15,23,42,0.08);
    }
    .list-devis-card__header {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(to right, #f8fafc, #ffffff);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .list-devis-card__chip {
        display: inline-block;
        padding: 3px 10px;
        background: #dbeafe;
        color: #1e40af;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-radius: 999px;
        margin-bottom: 4px;
    }
    .list-devis-card__title { color: #0a2540; font-weight: 700; font-size: 1.05rem; margin: 0; }
    .list-devis-card__total { text-align: right; }
    .list-devis-card__total-label {
        display: block; color: #6b7280; font-size: 0.72rem;
        text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600;
    }
    .list-devis-card__total-value {
        color: #ea580c; font-weight: 800; font-size: 1.15rem;
    }
    .list-devis-card__total-value small { color: #6b7280; font-size: 0.7em; font-weight: 600; }
    .list-devis-card__body { padding: 0 0 6px; }

    .list-devis-table thead th {
        background: #f9fafb;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.72rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 12px 8px !important;
        border-bottom: 1px solid #e5e7eb !important;
        border-top: 0 !important;
    }
    .list-devis-table tbody td {
        padding: 12px 8px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
        font-size: 0.85rem;
    }
    .list-devis-table .product-thumbnail img {
        width: 54px; height: 54px;
        object-fit: cover;
        border-radius: 8px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }
    .list-devis-product__name { color: #0a2540; font-weight: 700; margin: 0 0 3px; font-size: 0.88rem; }
    .list-devis-price { color: #1c57a3; font-weight: 700; }
    .list-devis-subtotal { color: #ea580c; font-weight: 800; }

    .list-devis-qty {
        display: inline-flex !important;
        align-items: center;
        background: #ffffff;
        border: 1.5px solid #e5e7eb !important;
        border-radius: 8px !important;
        overflow: hidden;
        padding: 0 !important;
    }
    .list-devis-qty .qty-down,
    .list-devis-qty .qty-up {
        width: 28px; height: 32px;
        display: inline-flex !important;
        align-items: center; justify-content: center;
        background: #f9fafb;
        color: #6b7280 !important;
    }
    .list-devis-qty .qty-down:hover, .list-devis-qty .qty-up:hover {
        background: #1c57a3; color: #ffffff !important;
    }
    .list-devis-qty .qty-val {
        width: 44px !important;
        text-align: center;
        border: 0 !important;
        background: transparent !important;
        font-weight: 700;
        color: #0a2540;
        font-size: 0.88rem;
        outline: none !important;
        padding: 0 !important;
        height: 32px;
    }

    .list-devis-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px; height: 32px;
        border-radius: 50%;
        background: #fef2f2;
        color: #b91c1c !important;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .list-devis-remove:hover {
        background: #ef4444;
        color: #ffffff !important;
        transform: scale(1.05);
    }
    .list-devis-remove i { font-size: 13px; }

    .list-devis-actions {
        display: flex;
        gap: 10px;
        padding: 14px 20px 18px;
        border-top: 1px solid #f1f5f9;
        flex-wrap: wrap;
    }
    .list-devis-btn {
        flex: 1 1 auto;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 11px 16px;
        font-weight: 700;
        font-size: 0.88rem;
        border-radius: 10px;
        text-decoration: none;
        border: 0;
        cursor: pointer;
        transition: all 0.18s ease;
    }
    .list-devis-btn--primary {
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff !important;
        box-shadow: 0 8px 18px rgba(234,88,12,0.30);
    }
    .list-devis-btn--primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(234,88,12,0.42);
    }
    .list-devis-btn--secondary {
        background: #ffffff;
        color: #1c57a3 !important;
        border: 1.5px solid #1c57a3;
    }
    .list-devis-btn--secondary:hover {
        background: #1c57a3;
        color: #ffffff !important;
    }
    .list-devis-btn i { font-size: 14px; }

    .list-devis-empty {
        max-width: 520px;
        margin: 40px auto 0;
        padding: 50px 30px;
        text-align: center;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        box-shadow: 0 4px 14px rgba(15,23,42,0.05);
    }
    .list-devis-empty__icon {
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
    .list-devis-empty__title { color: #0a2540; font-weight: 700; margin: 0 0 8px; }
    .list-devis-empty__text { color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 0 0 22px; }
    .list-devis-empty__btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 22px;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff !important;
        border-radius: 12px;
        font-weight: 700;
        text-decoration: none;
        box-shadow: 0 8px 18px rgba(234,88,12,0.30);
        transition: all 0.18s ease;
    }
    .list-devis-empty__btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 26px rgba(234,88,12,0.42);
    }

    @media (max-width: 575px) {
        .list-devis-hero { padding: 30px 16px 36px; }
        .list-devis-hero__title { font-size: 1.5rem; }
        .list-devis-actions { flex-direction: column; }
    }
</style>
@endsection
@section('jspart')
    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>
@endsection
