@php
    if(session('type') == 'devis'){
        $type_affaire = session('type_affaire');
    }else{
        $type_affaire = 'LOCATION';
        foreach(Cart::content() as $produit){
            $type_affaire = $produit->options->type_affaire;
            break;
        }
    }
@endphp

@extends('client.main')
@section('title', 'Période de location')
@section('content')
    <main class="main choix-date-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="choix-date-hero">
            <div class="choix-date-hero__inner">
                <span class="choix-date-hero__chip"><i class="fi-rs-calendar"></i> Période de location</span>
                <h1 class="choix-date-hero__title">Choisissez vos dates</h1>
                <p class="choix-date-hero__subtitle">Définissez la période de location pour chaque produit du panier.</p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="choix-date-card">
                        <div class="choix-date-card__header">
                            <h5 class="choix-date-card__title">
                                <i class="fi-rs-calendar"></i> Dates par produit
                            </h5>
                        </div>
                        <div class="choix-date-card__body">
                            <form method="post" action="{{ route('client.choixDateProduitLocationTraitement') }}">
                                @csrf
                                @foreach (Cart::content() as $produit)
                                    <div class="choix-date-item">
                                        <div class="choix-date-item__product">
                                            <img src="storage/{{ $produit->options->image }}" alt="{{ $produit->name }}" class="choix-date-item__image">
                                            <div>
                                                <h6 class="choix-date-item__name">{{ ucfirst($produit->name) }}</h6>
                                                <small class="text-muted">Qté : {{ $produit->qty }}</small>
                                            </div>
                                        </div>
                                        <div class="row shipping_calculator g-2">
                                            <div class="col-md-6">
                                                <label class="choix-date-field-label"><i class="fi-rs-calendar"></i> Date de début</label>
                                                <input type="date" min="{{ now()->format('Y-m-d') }}" required name="debut[]" class="form-control choix-date-input">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="choix-date-field-label"><i class="fi-rs-calendar"></i> Date de fin</label>
                                                <input type="date" min="{{ now()->format('Y-m-d') }}" required name="fin[]" class="form-control choix-date-input">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                <button type="submit" style="display: none" id="modePaiement" class="btn btn-fill-out btn-block mt-30">
                                    Choisir le mode de paiement
                                    <i class="fi-rs-money ml-15"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="choix-date-card choix-date-summary">
                        <div class="choix-date-card__header">
                            <h5 class="choix-date-card__title">
                                <i class="fi-rs-shopping-bag"></i> Votre commande
                            </h5>
                        </div>
                        <div class="choix-date-card__body">
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border choix-date-products">
                                    <tbody>
                                        @foreach (Cart::content() as $produit)
                                            <tr>
                                                <td class="image product-thumbnail">
                                                    <img src="storage/{{ $produit->options->image }}" alt="{{ $produit->name }}">
                                                </td>
                                                <td>
                                                    <h6 class="w-160 mb-5 choix-date-product__name">{{ $produit->name }}</h6>
                                                    @if(($produit->options->note ?? 0) > 0)
                                                        <div class="product-rate-cover">
                                                            <div class="product-rate d-inline-block">
                                                                <div class="product-rating" style="width: {{ $produit->options->note }}%"></div>
                                                            </div>
                                                            <span class="font-small ml-5 text-muted">({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td><h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }}</h6></td>
                                                <td><h4 class="text-brand">{{ number_format($produit->price, 0, '', ' ') }} <small>fcfa</small></h4></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <table class="table no-border col-12 choix-date-totals">
                                    <tbody>
                                        <tr>
                                            <td><h6 class="text-muted">Montant HT</h6></td>
                                            <td></td>
                                            <td class="cart_total_amount">
                                                <h6 class="text-brand text-end">{{ number_format($total, 0, '', ' ') }} fcfa</h6>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="cart_total_label"><h6 class="text-muted">TVA</h6></td>
                                            <td></td>
                                            <td class="cart_total_amount">
                                                <h6 class="text-brand text-end"> <span id="montant_total">{{ number_format($total * $tva, 0, '', ' ') }}</span> fcfa</h6>
                                            </td>
                                        </tr>
                                        @if (session('0')['ville'] != null)
                                            <tr>
                                                <td class="cart_total_label"><h6 class="text-muted">Coût livraison</h6></td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span id="montant_total">({{ session('0')['km'] }} km) {{ number_format(session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="cart_total_label"><h6 class="text-muted text-start">Montant TTC</h6></th>
                                            <th></th>
                                            <th class="cart_total_amount">
                                                <h6 class="text-brand text-end choix-date-total-final"> <span id="montant_total">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                            </th>
                                        </tr>
                                        <tr id="mpRemise">
                                            <th class="cart_total_label"><h6 class="laRemise">Montant remise</h6></th>
                                            <th></th>
                                            <th class="cart_total_amount">
                                                <h6 class="text-brand text-end"> <span id="laRemise"></span> fcfa</h6>
                                            </th>
                                        </tr>
                                        <tr id="mpMontantTotal">
                                            <th class="cart_total_label"><h6 class="">Montant Total</h6></th>
                                            <th></th>
                                            <th class="cart_total_amount">
                                                <h6 class="text-brand text-end"> <span id="leMontantTotal">{{ number_format($total + $total * $tva + session('0')['cout_livraison'], 0, '', ' ') }}</span> fcfa</h6>
                                            </th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <label class="choix-date-cta" for="modePaiement">
                                <i class="fi-rs-credit-card"></i> Choisir le mode de paiement
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .choix-date-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .choix-date-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .choix-date-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .choix-date-hero__chip {
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
        .choix-date-hero__chip i { color: #fbbf24; font-size: 14px; }
        .choix-date-hero__title,
        h1.choix-date-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .choix-date-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .choix-date-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .choix-date-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .choix-date-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .choix-date-card__title i { color: #1c57a3; font-size: 18px; }
        .choix-date-card__body { padding: 22px; }

        .choix-date-item {
            padding: 18px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 14px;
        }
        .choix-date-item:last-child { margin-bottom: 0; }
        .choix-date-item__product {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .choix-date-item__image {
            width: 50px; height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
        }
        .choix-date-item__name {
            color: #0a2540 !important;
            font-weight: 700;
            margin: 0 0 2px;
            font-size: 0.95rem;
        }

        .choix-date-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.82rem;
            margin-bottom: 6px;
        }
        .choix-date-field-label i { color: #1c57a3; font-size: 12px; }
        .choix-date-input {
            padding: 10px 12px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 8px !important;
            background: #ffffff !important;
            font-size: 0.9rem !important;
            height: auto !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .choix-date-input:focus {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234,88,12,0.12) !important;
            outline: none !important;
        }

        /* Summary */
        .choix-date-summary { position: sticky; top: 20px; }
        .choix-date-products img {
            width: 50px; height: 50px;
            object-fit: cover;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .choix-date-product__name { color: #0a2540 !important; font-weight: 700; margin: 0 0 4px; font-size: 0.88rem; }
        .choix-date-totals { margin: 0; }
        .choix-date-totals td, .choix-date-totals th { padding: 6px 0 !important; border: 0 !important; }
        .choix-date-totals .text-brand { font-weight: 700; color: #0a2540 !important; }
        .choix-date-total-final {
            font-size: 1.3rem !important;
            color: #ea580c !important;
            font-weight: 800 !important;
        }

        .choix-date-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 13px 18px;
            margin-top: 18px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.92rem;
            border-radius: 12px;
            text-decoration: none;
            box-shadow: 0 10px 22px rgba(234,88,12,0.32);
            transition: all 0.18s ease;
            cursor: pointer;
        }
        .choix-date-cta:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
            box-shadow: 0 14px 28px rgba(234,88,12,0.42);
        }
        .choix-date-cta i { font-size: 14px; }

        @media (max-width: 991px) {
            .choix-date-summary { position: static; }
        }
        @media (max-width: 575px) {
            .choix-date-hero { padding: 30px 16px 36px; }
            .choix-date-hero__title { font-size: 1.5rem; }
        }
    </style>
@endsection
@section('jspart')
@endsection
