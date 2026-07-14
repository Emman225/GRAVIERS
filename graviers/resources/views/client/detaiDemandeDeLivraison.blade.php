@extends('client.main')
@section('title','Détail de la demande de livraison')
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

    <main class="main detail-livraison-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="detail-livraison-hero">
            <div class="detail-livraison-hero__inner">
                <span class="detail-livraison-hero__chip"><i class="fi-rs-shipping-fast"></i> Demande de livraison</span>
                <h1 class="detail-livraison-hero__title">Suivi de ma demande</h1>
                <p class="detail-livraison-hero__subtitle">
                    Visualisez l'état de chaque produit demandé et les livreurs assignés.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-12">
                    <div class="detail-livraison-card">
                        <div class="detail-livraison-card__header">
                            <h5 class="detail-livraison-card__title">
                                <i class="fi-rs-shopping-bag"></i> Produits & livraisons associées
                            </h5>
                        </div>
                        <div class="detail-livraison-card__body">
                            <div class="table-responsive shopping-summery">
                                <table class="table table-wishlist detail-livraison-table">
                                    <thead>
                                        <tr class="main-heading">
                                            <th class="custome-checkbox start pl-30"></th>
                                            <th scope="col" colspan="2">Produit</th>
                                            <th class="text-center" scope="col">Quantité</th>
                                            <th scope="col">Livraisons</th>
                                            <th scope="col">Contact livreur</th>
                                            <th scope="col" class="text-center">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <form action="" method="post">
                                            @csrf
                                            @foreach ($livraison->detailLivraison as $detail)
                                                <tr class="pt-30">
                                                    <td></td>
                                                    <td class="custome-checkbox pl-30"></td>

                                                    <td class="product-des product-name">
                                                        <h6 class="mb-5 detail-livraison-product__name">{{ $detail->nom_produit }}</h6>
                                                        <small class="text-muted">
                                                            {{ $detail->livraisons->count() }} livraison{{ ($detail->livraisons->count() > 1) ? 's' : '' }} prévu{{ ($detail->livraisons->count() > 1) ? 'es' : 'e' }}
                                                        </small>
                                                    </td>

                                                    <td class="text-center detail-info" data-title="Quantité">
                                                        <span class="detail-livraison-qte-badge">{{ $detail->qte }}</span>
                                                    </td>

                                                    <td class="price" data-title="Livraisons">
                                                        @php $cpte @endphp
                                                        @foreach ($detail->livraisons as $livraison)
                                                            <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                <span class="detail-livraison-num">{{ $livraison->numero }}</span>
                                                                <span class="text-muted">|</span>
                                                                <span class="fw-bold">{{ $livraison->qte }}</span>
                                                            </span>
                                                            <br>
                                                        @endforeach
                                                    </td>

                                                    <td>
                                                        @php $livree = 0; @endphp
                                                        @foreach ($detail->livraisons as $livraison)
                                                            <span {{ $livraison->etat_livraison == 'LIVREE' ? 'class=barre-livree' : '' }}>
                                                                <i class="fi-rs-phone-call" style="font-size:11px;color:#10b981;"></i>
                                                                {{ $livraison->livreur->user->contact }}
                                                            </span>
                                                            <br>
                                                            @if ($livraison->etat_livraison == 'LIVREE')
                                                                @php $livree++; @endphp
                                                            @endif
                                                        @endforeach
                                                    </td>

                                                    <td class="action text-center" data-title="Statut">
                                                        @if($livree > 0)
                                                            <div class="detail-livraison-progress">
                                                                <span class="detail-livraison-progress__count">{{ $livree }}/{{ $detail->livraisons->count() }}</span>
                                                            </div>
                                                        @endif
                                                        @switch($detail->livraisons()->first()?->etat_livraison)
                                                            @case('EN ATTENTE')
                                                                <span class="detail-livraison-badge detail-livraison-badge--attente">{{ $detail->livraisons()->first()->etat_livraison }}</span>
                                                                @break
                                                            @case('EN TRAITEMENT')
                                                                <span class="detail-livraison-badge detail-livraison-badge--traitement">{{ $detail->livraisons()->first()->etat_livraison }}</span>
                                                                @break
                                                            @case('EN COURS DE LIVRAISON')
                                                                <span class="detail-livraison-badge detail-livraison-badge--cours">{{ $detail->livraisons()->first()->etat_livraison }}…</span>
                                                                @break
                                                            @case('LIVREE')
                                                                <span class="detail-livraison-badge detail-livraison-badge--livree"><i class="fi-rs-check"></i> {{ $detail->livraisons()->first()->etat_livraison }}</span>
                                                                @break
                                                            @default
                                                        @endswitch
                                                    </td>
                                                </tr>
                                            @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="detail-livraison-actions">
                                <a class="detail-livraison-back" href="{{ route('client.monCompte') }}">
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
        .detail-livraison-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .detail-livraison-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .detail-livraison-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .detail-livraison-hero__chip {
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
        .detail-livraison-hero__chip i { color: #fbbf24; font-size: 14px; }
        .detail-livraison-hero__title,
        h1.detail-livraison-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .detail-livraison-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .detail-livraison-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .detail-livraison-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .detail-livraison-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .detail-livraison-card__title i { color: #1c57a3; font-size: 18px; }
        .detail-livraison-card__body { padding: 6px 0 18px; }

        .detail-livraison-table thead th {
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
        .detail-livraison-table tbody td {
            padding: 16px 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.9rem;
        }
        .detail-livraison-product__name {
            color: #0a2540 !important;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .detail-livraison-qte-badge {
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
        .detail-livraison-num {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #1c57a3;
            background: #eff6ff;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.82rem;
        }

        .detail-livraison-progress {
            display: inline-block;
            margin-bottom: 6px;
        }
        .detail-livraison-progress__count {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            background: linear-gradient(135deg, #10b981, #047857);
            color: #ffffff;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        /* Badges statut */
        .detail-livraison-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .detail-livraison-badge--attente { background: #f3f4f6; color: #4b5563; }
        .detail-livraison-badge--traitement { background: #fef3c7; color: #92400e; }
        .detail-livraison-badge--cours { background: #fef3c7; color: #92400e; }
        .detail-livraison-badge--livree { background: #d1fae5; color: #065f46; }
        .detail-livraison-badge i { font-size: 10px; }

        .barre-livree {
            text-decoration: line-through;
            color: #9ca3af;
        }

        .detail-livraison-actions {
            padding: 16px 22px 0;
            display: flex;
            justify-content: flex-start;
        }
        .detail-livraison-back {
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
        .detail-livraison-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .detail-livraison-hero { padding: 30px 16px 36px; }
            .detail-livraison-hero__title { font-size: 1.5rem; }
        }
    </style>
@endsection
