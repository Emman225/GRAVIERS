@php
    use Illuminate\Support\Carbon;
@endphp

@extends('client.main')
@section('title', 'Annulation de commande')
@section('content')
    <main class="main annul-cmd-main">
        @if (session('success'))
            <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">
                {{ session('success') }}
            </div>
        @endif

        {{-- ===== HERO ===== --}}
        <section class="annul-cmd-hero">
            <div class="annul-cmd-hero__inner">
                <span class="annul-cmd-hero__chip"><i class="fi-rs-cross-circle"></i> Annulation</span>
                <h1 class="annul-cmd-hero__title">Annuler une commande</h1>
                <p class="annul-cmd-hero__subtitle">
                    Vérifiez les produits concernés et indiquez le motif de votre demande d'annulation.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row g-4">
                <div class="col-lg-12">
                    @if ($commande->etat_commande == "EN ATTENTE")
                        <div class="annul-cmd-card">
                            <div class="annul-cmd-card__header">
                                <h5 class="annul-cmd-card__title">
                                    <i class="fi-rs-shopping-bag"></i> Commande N°{{ $commande->numero }}
                                </h5>
                                <span class="annul-cmd-status-badge annul-cmd-status-badge--attente">
                                    {{ $commande->etat_commande }}
                                </span>
                            </div>
                            <div class="annul-cmd-card__body">
                                <div class="annul-cmd-warning">
                                    <i class="fi-rs-info"></i>
                                    <div>
                                        <strong>Confirmer l'annulation ?</strong>
                                        <p>Cette action enverra une demande d'annulation à notre équipe. La décision finale appartient au gestionnaire.</p>
                                    </div>
                                </div>

                                <form method="post">
                                    @csrf
                                    <div class="row shipping_calculator">
                                        <div class="table-responsive shopping-summery">
                                            <table id="table" class="table table-wishlist annul-cmd-table">
                                                <thead>
                                                    <tr class="main-heading">
                                                        <th class="custome-checkbox start pl-30"></th>
                                                        <th scope="col" colspan="2">Produit</th>
                                                        <th scope="col">Prix unitaire</th>
                                                        <th scope="col" class="text-end">Quantité</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="listProduit">
                                                    @foreach ($commande->detailCommande as $detail)
                                                        <tr class="pt-30">
                                                            <td class="custome-checkbox pl-30"></td>
                                                            <td class="image product-thumbnail pt-40">
                                                                @foreach ($detail->produit->image as $image)
                                                                    <img src="/storage/{{ $image->image }}" alt="{{ $detail->produit->nom }}">
                                                                @endforeach
                                                            </td>
                                                            <td class="product-des product-name">
                                                                <h6 class="mb-5 annul-cmd-table__name">{{ $detail->produit->nom }}</h6>
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
                                                            <td class="price" data-title="Prix">
                                                                <div class="mr-15">
                                                                    <div class="detail-qty annul-cmd-price">
                                                                        {{ number_format($detail->prix ?? $detail->produit->prix_moyen, 0, '', ' ') }} fcfa /
                                                                        {{ $detail->produit->UniteProduit->abreviation }}
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="text-end detail-info" data-title="Quantité">
                                                                <span class="annul-cmd-qte-badge">{{ $detail->qte }}</span>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="annul-cmd-motif">
                                            <label class="annul-cmd-field-label" for="motif">
                                                <i class="fi-rs-edit"></i> Motif de la demande d'annulation
                                            </label>
                                            <textarea name="motif" id="motif" rows="5"
                                                      placeholder="Expliquez pourquoi vous souhaitez annuler cette commande..."
                                                      class="annul-cmd-textarea"></textarea>
                                        </div>

                                        <div class="annul-cmd-actions">
                                            <a href="{{ route('client.monCompte') }}" class="annul-cmd-back">
                                                <i class="fi-rs-arrow-left"></i> Retour
                                            </a>
                                            <button type="submit" class="annul-cmd-submit">
                                                <i class="fi-rs-paper-plane"></i> Envoyer la demande d'annulation
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="annul-cmd-blocked">
                            <div class="annul-cmd-blocked__icon">
                                <i class="fi-rs-lock"></i>
                            </div>
                            <h3 class="annul-cmd-blocked__title">Annulation impossible</h3>
                            <p class="annul-cmd-blocked__text">
                                Cette commande est déjà en traitement et ne peut plus être annulée en ligne.
                                <br>Pour plus d'aide, rendez-vous dans l'une de nos agences ou contactez le support.
                            </p>
                            <a href="{{ route('client.monCompte') }}" class="annul-cmd-blocked__btn">
                                <i class="fi-rs-arrow-left"></i> Retour à mon compte
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <style>
        .annul-cmd-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .annul-cmd-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(239,68,68,0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28,87,163,0.4), transparent 50%);
            z-index: -1;
        }
        .annul-cmd-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .annul-cmd-hero__chip {
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
        .annul-cmd-hero__chip i { color: #fca5a5; font-size: 14px; }
        .annul-cmd-hero__title,
        h1.annul-cmd-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .annul-cmd-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .annul-cmd-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .annul-cmd-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #fff7ed, #ffffff);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .annul-cmd-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .annul-cmd-card__title i { color: #ea580c; font-size: 18px; }
        .annul-cmd-card__body { padding: 24px; }
        .annul-cmd-status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .annul-cmd-status-badge--attente { background: #fef3c7; color: #92400e; }

        .annul-cmd-warning {
            display: flex;
            gap: 14px;
            align-items: flex-start;
            background: linear-gradient(135deg, #fef3c7, #ffffff);
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .annul-cmd-warning i { font-size: 24px; color: #f59e0b; flex-shrink: 0; }
        .annul-cmd-warning strong { color: #92400e; display: block; margin-bottom: 4px; }
        .annul-cmd-warning p { margin: 0; color: #78350f; font-size: 0.88rem; line-height: 1.5; }

        .annul-cmd-table thead th {
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
        .annul-cmd-table tbody td {
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 14px 12px !important;
        }
        .annul-cmd-table .product-thumbnail img {
            width: 70px; height: 70px;
            object-fit: cover;
            border-radius: 10px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .annul-cmd-table__name { color: #0a2540; font-weight: 700; }
        .annul-cmd-price { color: #ea580c; font-weight: 700; }
        .annul-cmd-qte-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 50px;
            padding: 6px 14px;
            background: #eff6ff;
            color: #1c57a3;
            font-weight: 700;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .annul-cmd-motif { margin-top: 24px; }
        .annul-cmd-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.88rem;
            margin-bottom: 8px;
        }
        .annul-cmd-field-label i { color: #ea580c; font-size: 14px; }
        .annul-cmd-textarea {
            display: block;
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            background: #ffffff;
            color: #111827;
            font-size: 0.92rem;
            line-height: 1.5;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            resize: vertical;
            min-height: 100px;
        }
        .annul-cmd-textarea:focus {
            border-color: #ea580c;
            box-shadow: 0 0 0 3px rgba(234,88,12,0.12);
            outline: none;
        }

        .annul-cmd-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        .annul-cmd-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6b7280 !important;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            transition: all 0.15s ease;
        }
        .annul-cmd-back:hover { color: #1c57a3 !important; background: #f3f4f6; }
        .annul-cmd-submit {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 22px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.92rem;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(239,68,68,0.32);
            transition: all 0.18s ease;
        }
        .annul-cmd-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(239,68,68,0.42);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }
        .annul-cmd-submit i { font-size: 14px; }

        /* État bloqué */
        .annul-cmd-blocked {
            max-width: 520px;
            margin: 30px auto 0;
            padding: 50px 30px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 4px 14px rgba(15,23,42,0.05);
        }
        .annul-cmd-blocked__icon {
            width: 90px; height: 90px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #f59e0b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .annul-cmd-blocked__title {
            color: #0a2540;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0 0 8px;
        }
        .annul-cmd-blocked__text {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0 0 22px;
        }
        .annul-cmd-blocked__btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #1c57a3, #134380);
            color: #ffffff !important;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(28,87,163,0.30);
            transition: all 0.18s ease;
        }
        .annul-cmd-blocked__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(28,87,163,0.42);
        }

        @media (max-width: 575px) {
            .annul-cmd-hero { padding: 30px 16px 36px; }
            .annul-cmd-hero__title { font-size: 1.5rem; }
            .annul-cmd-actions { flex-direction: column-reverse; }
            .annul-cmd-back, .annul-cmd-submit { width: 100%; justify-content: center; }
        }
    </style>
@endsection
@section('jspart')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
<script>
    let notification = document.getElementById('notification');
    setTimeout(() => {
        if (notification) {
            notification.classList.add("off")
        }
    },5000)
</script>
@endsection
