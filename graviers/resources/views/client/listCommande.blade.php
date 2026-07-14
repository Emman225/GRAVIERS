@extends('client.main')
@section('title','Détail de la commande')
@section('content')
@include('client.navMobile')

<main class="main list-cmd-main">
    {{-- ===== HERO ===== --}}
    <section class="list-cmd-hero">
        <div class="list-cmd-hero__inner">
            <span class="list-cmd-hero__chip"><i class="fi-rs-shopping-bag"></i> Détail commande</span>
            <h1 class="list-cmd-hero__title">Commande N°{{ $commande->numero }}</h1>
            <p class="list-cmd-hero__subtitle">Récapitulatif des produits commandés et de leur prix.</p>
        </div>
    </section>

    <div class="container mb-80 mt-30">
        <div class="row">
            <div class="col-12">
                <div class="list-cmd-card">
                    <div class="list-cmd-card__header">
                        <div>
                            <h5 class="list-cmd-card__title"><i class="fi-rs-shopping-bag"></i> Commande N°{{ $commande->numero }}</h5>
                            <span class="list-cmd-card__state list-cmd-card__state--{{ strtolower(str_replace(' ', '-', $commande->etat_commande)) }}">{{ $commande->etat_commande }}</span>
                        </div>
                        <div class="list-cmd-card__total">
                            <span class="list-cmd-card__total-label">Total commande</span>
                            <span class="list-cmd-card__total-value">{{ number_format($commande->montant_total, 0, '', ' ') }} <small>FCFA</small></span>
                        </div>
                    </div>

                    <div class="list-cmd-card__body">
                        <div class="table-responsive shopping-summery">
                            <table class="table table-wishlist list-cmd-table">
                                <thead>
                                    <tr class="main-heading">
                                        <th colspan="2">Produit</th>
                                        <th class="text-end">Prix unitaire</th>
                                        <th class="text-center">Quantité</th>
                                        <th class="text-end">Sous-total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $total = 0 @endphp
                                    <form>
                                        @csrf
                                        @foreach ($commande->detailCommande as $detail)
                                            <tr class="pt-30">
                                                @foreach ($detail->produit->image as $image)
                                                    <td class="image product-thumbnail pt-40"><img src="/storage/{{ $image->image }}"
                                                @endforeach
                                                        alt="{{ $detail->produit->nom }}"></td>
                                                <td class="product-des product-name">
                                                    <h6 class="mb-5 list-cmd-product__name">{{ $detail->produit->nom }}</h6>
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
                                                    @if(isset($prixPerso[$detail->produit->id]))
                                                        <h4 class="list-cmd-price">{{ number_format($prixPerso[$detail->produit->id], 0, '', ' ') }} <small>FCFA</small></h4>
                                                        <span class="old-price text-muted text-decoration-line-through">{{ number_format($detail->produit->prix_moyen, 0, '', ' ') }} FCFA</span>
                                                    @else
                                                        <h4 class="list-cmd-price">{{ number_format($detail->produit->prix_moyen, 0, '', ' ') }} <small>FCFA</small></h4>
                                                    @endif
                                                </td>
                                                <td class="text-center detail-info" data-title="Quantité">
                                                    <span class="list-cmd-qte-badge">{{ $detail->qte }}</span>
                                                </td>
                                                <td class="price text-end" data-title="Sous-total">
                                                    @if(isset($prixPerso[$detail->produit->id]))
                                                        <h4 class="list-cmd-subtotal">{{ number_format($prixPerso[$detail->produit->id] * $detail->qte, 0, '', ' ') }} <small>FCFA</small></h4>
                                                    @else
                                                        <h4 class="list-cmd-subtotal">{{ number_format($detail->produit->prix_moyen * $detail->qte, 0, '', ' ') }} <small>FCFA</small></h4>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                </tbody>
                            </table>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .list-cmd-hero {
        position: relative;
        background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
        color: #fff;
        padding: 40px 20px 44px;
        overflow: hidden;
        isolation: isolate;
    }
    .list-cmd-hero::after {
        content: "";
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
            radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
        z-index: -1;
    }
    .list-cmd-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
    .list-cmd-hero__chip {
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
    .list-cmd-hero__chip i { color: #fbbf24; font-size: 14px; }
    .list-cmd-hero__title,
    h1.list-cmd-hero__title {
        font-size: 2rem;
        font-weight: 800;
        margin: 0 0 6px;
        color: #ffffff !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.35);
    }
    .list-cmd-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

    .list-cmd-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
    }
    .list-cmd-card__header {
        padding: 18px 22px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(to right, #f8fafc, #ffffff);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }
    .list-cmd-card__title {
        display: flex; align-items: center; gap: 10px;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0a2540;
        margin: 0 0 6px;
    }
    .list-cmd-card__title i { color: #1c57a3; font-size: 18px; }
    .list-cmd-card__state {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        background: #f3f4f6;
        color: #4b5563;
    }
    .list-cmd-card__state--en-attente { background: #fef3c7; color: #92400e; }
    .list-cmd-card__state--en-traitement { background: #dbeafe; color: #1e40af; }
    .list-cmd-card__state--terminee { background: #d1fae5; color: #065f46; }

    .list-cmd-card__total {
        text-align: right;
    }
    .list-cmd-card__total-label {
        display: block;
        color: #6b7280;
        font-size: 0.78rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .list-cmd-card__total-value {
        color: #ea580c;
        font-weight: 800;
        font-size: 1.35rem;
    }
    .list-cmd-card__total-value small { color: #6b7280; font-size: 0.65em; font-weight: 600; }

    .list-cmd-card__body { padding: 6px 0 0; }

    .list-cmd-table thead th {
        background: #f9fafb;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.78rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 14px 12px !important;
        border-bottom: 1px solid #e5e7eb !important;
        border-top: 0 !important;
    }
    .list-cmd-table tbody td {
        padding: 16px 12px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
    }
    .list-cmd-table .product-thumbnail img {
        width: 70px; height: 70px;
        object-fit: cover;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }
    .list-cmd-product__name { color: #0a2540; font-weight: 700; margin: 0; }
    .list-cmd-price {
        color: #1c57a3;
        font-weight: 700;
        font-size: 1.02rem;
        margin: 0;
    }
    .list-cmd-price small { color: #6b7280; font-size: 0.7em; font-weight: 600; }
    .list-cmd-subtotal {
        color: #ea580c;
        font-weight: 800;
        font-size: 1.08rem;
        margin: 0;
    }
    .list-cmd-subtotal small { color: #6b7280; font-size: 0.65em; font-weight: 600; }
    .list-cmd-qte-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 50px;
        padding: 6px 14px;
        background: #eff6ff;
        color: #1c57a3;
        font-weight: 700;
        border-radius: 8px;
    }

    @media (max-width: 575px) {
        .list-cmd-hero { padding: 30px 16px 36px; }
        .list-cmd-hero__title { font-size: 1.4rem; }
        .list-cmd-card__header { flex-direction: column; align-items: flex-start; }
        .list-cmd-card__total { text-align: left; }
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
