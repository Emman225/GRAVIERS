@php
    use Illuminate\Support\Carbon;
@endphp
@extends('client.main')
@section('title','Grand livre')
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

    <main class="main grand-livre-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="grand-livre-hero">
            <div class="grand-livre-hero__inner">
                <span class="grand-livre-hero__chip"><i class="fi-rs-receipt"></i> Grand livre</span>
                <h1 class="grand-livre-hero__title">Mon grand livre</h1>
                <p class="grand-livre-hero__subtitle">
                    Historique détaillé de vos factures et paiements par commande.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-12">
                    <div class="grand-livre-card">
                        <div class="grand-livre-card__header">
                            <h5 class="grand-livre-card__title">
                                <i class="fi-rs-list"></i> Mouvements comptables
                            </h5>
                        </div>
                        <div class="grand-livre-card__body">
                            @php
                                $aDesFactures = Help::commandeHasFacture($commandes) == true && !$commandes->isEmpty();
                            @endphp

                            @if ($aDesFactures)
                                <div class="table-responsive">
                                    <table class="table grand-livre-table" id="liste">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Libellé</th>
                                                <th>N° facture</th>
                                                <th class="text-end">Facture</th>
                                                <th class="text-end">Paiement</th>
                                                <th class="text-end">Solde</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php
                                                $montantFacture = 0;
                                                $montantPaiement = 0;
                                            @endphp
                                            @foreach($commandes as $commande)
                                                @php
                                                    $montant = (!is_null($commande->TvaCommande)) ? $commande->TvaCommande->montant : 0;
                                                @endphp
                                                @if(!$commande->factures->isEmpty())
                                                    <tr class="grand-livre-divider-row">
                                                        <td colspan="6">
                                                            <strong><i class="fi-rs-shopping-bag"></i> Commande N°{{ $commande->numero }}</strong>
                                                        </td>
                                                    </tr>

                                                    @foreach ($commande->factures as $facture)
                                                        @php $montant += $facture->montant @endphp
                                                        <tr>
                                                            <td><small>{{ $facture->created_at->format('d/m/Y') }}</small></td>
                                                            <td>{{ $facture->commande->paiements->libelle ?? '—' }}</td>
                                                            <td><span class="grand-livre-num">{{ $facture->numero }}</span></td>
                                                            <td class="text-end fw-bold">{{ number_format($facture->montant, '0', '', ' ') }} <small>fcfa</small></td>
                                                            <td class="text-end fw-bold text-success">{{ number_format($commande->montant_total, '0', '', ' ') }} <small>fcfa</small></td>
                                                            <td class="text-end fw-bold">{{ number_format($commande->montant_total - $montant, '0', '', ' ') }} <small>fcfa</small></td>
                                                        </tr>
                                                    @endforeach
                                                    @php
                                                        $montantFacture += $montant;
                                                        $montantPaiement += $commande->montant_total;
                                                    @endphp
                                                @endif
                                            @endforeach
                                        </tbody>
                                        <tfoot class="grand-livre-tfoot">
                                            <tr>
                                                <td colspan="3"><strong>SOLDE</strong></td>
                                                <td class="text-end"><strong>{{ number_format($montantFacture, '0', '', ' ') }} <small>fcfa</small></strong></td>
                                                <td class="text-end text-success"><strong>{{ number_format($montantPaiement, '0', '', ' ') }} <small>fcfa</small></strong></td>
                                                <td class="text-end grand-livre-final-solde">
                                                    <strong>{{ number_format($montantPaiement - $montantFacture, '0', '', ' ') }} <small>fcfa</small></strong>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @else
                                <div class="grand-livre-empty">
                                    <div class="grand-livre-empty__icon"><i class="fi-rs-receipt"></i></div>
                                    <h4 class="grand-livre-empty__title">Pas de facture pour l'instant</h4>
                                    <p class="grand-livre-empty__text">
                                        Aucun mouvement n'a encore été enregistré sur votre grand livre.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="grand-livre-actions">
                            <a class="grand-livre-back" href="{{ route('client.monCompte') }}">
                                <i class="fi-rs-arrow-left"></i> Retour à mon compte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .grand-livre-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .grand-livre-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .grand-livre-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .grand-livre-hero__chip {
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
        .grand-livre-hero__chip i { color: #fbbf24; font-size: 14px; }
        .grand-livre-hero__title,
        h1.grand-livre-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .grand-livre-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

        .grand-livre-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .grand-livre-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .grand-livre-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .grand-livre-card__title i { color: #1c57a3; font-size: 18px; }
        .grand-livre-card__body { padding: 6px 0; }

        .grand-livre-table thead th {
            background: #f9fafb !important;
            color: #374151 !important;
            font-weight: 700 !important;
            font-size: 0.76rem !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 14px 12px !important;
            border-bottom: 1px solid #e5e7eb !important;
            border-top: 0 !important;
        }
        .grand-livre-table tbody td {
            padding: 12px 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            font-size: 0.88rem;
        }
        .grand-livre-table tbody tr:hover:not(.grand-livre-divider-row) { background: #fafbfc; }
        .grand-livre-num {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #1c57a3;
            background: #eff6ff;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.82rem;
        }
        .grand-livre-divider-row {
            background: linear-gradient(to right, #eff6ff, #ffffff) !important;
        }
        .grand-livre-divider-row td {
            padding: 12px 16px !important;
            border-bottom: 1px solid #dbeafe !important;
        }
        .grand-livre-divider-row strong {
            color: #1c57a3;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .grand-livre-tfoot td {
            padding: 14px 12px !important;
            border-top: 2px solid #1c57a3 !important;
            background: #f8fafc !important;
            font-size: 0.95rem;
        }
        .grand-livre-final-solde { color: #ea580c !important; font-size: 1.1rem; }

        .grand-livre-empty { text-align: center; padding: 40px 20px; }
        .grand-livre-empty__icon {
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
        .grand-livre-empty__title { color: #0a2540; font-weight: 700; margin: 0 0 6px; }
        .grand-livre-empty__text { color: #6b7280; font-size: 0.92rem; margin: 0; }

        .grand-livre-actions {
            padding: 18px 22px 20px;
            display: flex;
            justify-content: flex-start;
            border-top: 1px solid #f1f5f9;
        }
        .grand-livre-back {
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
        .grand-livre-back:hover {
            border-color: #1c57a3;
            color: #1c57a3 !important;
            background: #eff6ff;
        }

        @media (max-width: 575px) {
            .grand-livre-hero { padding: 30px 16px 36px; }
            .grand-livre-hero__title { font-size: 1.5rem; }
        }
    </style>
@endsection
