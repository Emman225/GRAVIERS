@php
    use Illuminate\Support\Carbon;
@endphp
@extends('client.main')
@section('title', 'Mes factures')
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

    <main class="main list-facture-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="list-facture-hero">
            <div class="list-facture-hero__inner">
                <span class="list-facture-hero__chip"><i class="fi-rs-receipt"></i> Factures</span>
                <h1 class="list-facture-hero__title">Factures de la commande N°{{ $commande->numero }}</h1>
                <p class="list-facture-hero__subtitle">
                    Consultez et téléchargez les factures associées à votre commande.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-12">
                    <div class="list-facture-card">
                        <div class="list-facture-card__header">
                            <h5 class="list-facture-card__title">
                                <i class="fi-rs-receipt"></i> Liste des factures
                            </h5>
                        </div>
                        <div class="list-facture-card__body">
                            @if ($commande->factures && count($commande->factures) > 0)
                                <div class="table-responsive">
                                    <table class="table list-facture-table" id="liste">
                                        <thead>
                                            <tr>
                                                <th>N° facture</th>
                                                <th>Date</th>
                                                <th class="text-end">Montant</th>
                                                <th class="text-center" colspan="2">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $livraison = 1;
                                                $supplement = 0;
                                            @endphp
                                            @foreach ($commande->factures as $key => $facture)
                                                @php
                                                    $supplement = $facture->commande->cout_livraison_client + $facture->commande->TvaCommande->montant - $commande->remise;
                                                @endphp
                                                @if($key > 0)
                                                    @php
                                                        $livraison = 0;
                                                        $supplement = 0;
                                                    @endphp
                                                @endif
                                                <tr>
                                                    <td><span class="list-facture-num">{{ $facture->numero }}</span></td>
                                                    <td>{{ Carbon::parse($facture->created_at)->format('d/m/Y') }}</td>
                                                    <td class="text-end fw-bold">{{ number_format($facture->montant, '0', '', ' ') }} <small>FCFA</small></td>
                                                    <td class="text-center">
                                                        <a href="{{ route('show.actionFacture', ['commande' => $commande, 'facture' => $facture, 'action' => 'voir', 'livraison' => $livraison]) }}"
                                                           class="list-facture-btn list-facture-btn--view">
                                                            <i class="fi-rs-eye"></i> Voir
                                                        </a>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="{{ route('show.actionFacture', ['commande' => $commande, 'facture' => $facture, 'action' => 'telecharger', 'livraison' => $livraison]) }}"
                                                           class="list-facture-btn list-facture-btn--download">
                                                            <i class="fi-rs-download"></i> Télécharger
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="list-facture-empty">
                                    <div class="list-facture-empty__icon"><i class="fi-rs-receipt"></i></div>
                                    <h4 class="list-facture-empty__title">Aucune facture disponible</h4>
                                    <p class="list-facture-empty__text">
                                        Aucune facture n'a encore été émise pour cette commande. Elle sera disponible dès la fin du traitement.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="list-facture-actions">
                            <a class="list-facture-back" href="{{ route('client.monCompte') }}">
                                <i class="fi-rs-arrow-left"></i> Retour à mon compte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .list-facture-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .list-facture-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .list-facture-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .list-facture-hero__chip {
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
        .list-facture-hero__chip i { color: #fbbf24; font-size: 14px; }
        .list-facture-hero__title,
        h1.list-facture-hero__title {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .list-facture-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .list-facture-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .list-facture-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .list-facture-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .list-facture-card__title i { color: #1c57a3; font-size: 18px; }
        .list-facture-card__body { padding: 6px 0; }

        .list-facture-table thead th {
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
        .list-facture-table tbody td {
            padding: 14px 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.92rem;
        }
        .list-facture-num {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #1c57a3;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .list-facture-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1.5px solid transparent;
        }
        .list-facture-btn--view {
            background: #eff6ff;
            color: #1c57a3 !important;
            border-color: #dbeafe;
        }
        .list-facture-btn--view:hover { background: #1c57a3; color: #ffffff !important; }
        .list-facture-btn--download {
            background: #ecfdf5;
            color: #10b981 !important;
            border-color: #d1fae5;
        }
        .list-facture-btn--download:hover { background: #10b981; color: #ffffff !important; }
        .list-facture-btn i { font-size: 12px; }

        .list-facture-empty { text-align: center; padding: 40px 20px; }
        .list-facture-empty__icon {
            width: 80px; height: 80px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: linear-gradient(135deg, #dbeafe, #93c5fd);
            color: #1c57a3;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }
        .list-facture-empty__title { color: #0a2540; font-weight: 700; margin: 0 0 6px; }
        .list-facture-empty__text { color: #6b7280; font-size: 0.92rem; margin: 0; max-width: 400px; margin: 0 auto; }

        .list-facture-actions {
            padding: 16px 22px 20px;
            display: flex;
            justify-content: flex-start;
            border-top: 1px solid #f1f5f9;
        }
        .list-facture-back {
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
        .list-facture-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .list-facture-hero { padding: 30px 16px 36px; }
            .list-facture-hero__title { font-size: 1.3rem; }
            .list-facture-btn { padding: 6px 10px; font-size: 0.75rem; }
        }
    </style>
@endsection
