@php
    use Illuminate\Support\Carbon;
@endphp

@extends('client.main')
@section('title', 'Retour de produit')
@section('content')
@include('client.navMobile')

<main class="main retour-produit-main">
    @if (session('success'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('success') }}</div>
    @endif

    {{-- ===== HERO ===== --}}
    <section class="retour-produit-hero">
        <div class="retour-produit-hero__inner">
            <span class="retour-produit-hero__chip"><i class="fi-rs-refresh"></i> Retour de produit</span>
            <h1 class="retour-produit-hero__title">Demander un retour</h1>
            <p class="retour-produit-hero__subtitle">
                Sélectionnez un produit livré pour demander son retour.
            </p>
        </div>
    </section>

    <div class="container mb-80 mt-30">
        <div class="row">
            <div class="col-12">
                {{-- Info banner --}}
                <div class="retour-produit-info">
                    <i class="fi-rs-info"></i>
                    <div>
                        <strong>À savoir avant de demander un retour</strong>
                        <p>Pour une demande de retour valide, le produit ne doit pas avoir été utilisé.</p>
                        <p>Dans le cas contraire, veuillez plutôt <a href="{{ route('client.ticketSAV') }}">générer un ticket de service après-vente</a>.</p>
                    </div>
                </div>

                <div class="retour-produit-card">
                    <div class="retour-produit-card__header">
                        <h5 class="retour-produit-card__title">
                            <i class="fi-rs-shopping-bag"></i> Produits livrés disponibles au retour
                        </h5>
                    </div>
                    <div class="retour-produit-card__body">
                        <div class="table-responsive">
                            <table id="liste" class="table retour-produit-table">
                                <thead>
                                    <tr>
                                        <th class="text-center">N°</th>
                                        <th>Produit</th>
                                        <th class="text-center">Quantité</th>
                                        <th>N° commande</th>
                                        <th>Date commande</th>
                                        <th class="text-center">Statut retour</th>
                                        <th>Observation</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $i = 1; @endphp
                                    @foreach ($commandes as $commande)
                                        @foreach ($commande->detailCommande as $detail)
                                            @if ($detail->etat_livraison == 'LIVREE')
                                                <tr>
                                                    <td class="text-center fw-bold">{{ $i }}</td>
                                                    <td><strong class="retour-produit-name">{{ $detail->produit->nom }}</strong></td>
                                                    <td class="text-center">
                                                        <span class="retour-produit-qte-badge">{{ $detail->qte }}</span>
                                                    </td>
                                                    <td><span class="retour-produit-num">{{ $commande->numero }}</span></td>
                                                    <td><small>{{ Carbon::parse($detail->updated_at)->format('d/m/Y H:i') }}</small></td>
                                                    <td class="text-center">
                                                        @if($detail->retour)
                                                            @switch($detail->retour->statut)
                                                                @case(1)
                                                                    <span class="retour-produit-badge retour-produit-badge--attente">En attente</span>
                                                                    @break
                                                                @case(2)
                                                                    <span class="retour-produit-badge retour-produit-badge--approuve"><i class="fi-rs-check"></i> Approuvé</span>
                                                                    @break
                                                                @case(3)
                                                                    <span class="retour-produit-badge retour-produit-badge--refuse"><i class="fi-rs-cross-small"></i> Refusé</span>
                                                                    @break
                                                                @default
                                                            @endswitch
                                                        @else
                                                            <span class="text-muted" style="font-style:italic">Aucune demande</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($detail->retour && $detail->retour->observation_reception)
                                                            <small>{{ $detail->retour->observation_reception }}</small>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-center">
                                                        @if(!$detail->retour)
                                                            <a href="{{ route('client.motifPage', $detail) }}" class="retour-produit-btn">
                                                                <i class="fi-rs-refresh"></i> Demander
                                                            </a>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                            @php $i++ @endphp
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    .retour-produit-hero {
        position: relative;
        background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
        color: #fff;
        padding: 40px 20px 44px;
        overflow: hidden;
        isolation: isolate;
    }
    .retour-produit-hero::after {
        content: "";
        position: absolute; inset: 0;
        background:
            radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
            radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
        z-index: -1;
    }
    .retour-produit-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
    .retour-produit-hero__chip {
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
    .retour-produit-hero__chip i { color: #fbbf24; font-size: 14px; }
    .retour-produit-hero__title,
    h1.retour-produit-hero__title {
        font-size: 2rem;
        font-weight: 800;
        margin: 0 0 6px;
        color: #ffffff !important;
        text-shadow: 0 2px 18px rgba(0,0,0,0.35);
    }
    .retour-produit-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }

    .retour-produit-info {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        background: linear-gradient(135deg, #fef3c7, #ffffff);
        border: 1px solid #fde68a;
        border-left: 4px solid #f59e0b;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 22px;
    }
    .retour-produit-info i { font-size: 24px; color: #f59e0b; flex-shrink: 0; margin-top: 2px; }
    .retour-produit-info strong { color: #92400e; display: block; margin-bottom: 4px; }
    .retour-produit-info p { margin: 0 0 4px; color: #78350f; font-size: 0.9rem; line-height: 1.5; }
    .retour-produit-info p:last-child { margin: 0; }
    .retour-produit-info a { color: #c2410c; font-weight: 700; text-decoration: underline; }

    .retour-produit-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(15,23,42,0.05);
    }
    .retour-produit-card__header {
        padding: 16px 22px;
        border-bottom: 1px solid #f1f5f9;
        background: linear-gradient(to right, #f8fafc, #ffffff);
    }
    .retour-produit-card__title {
        display: flex; align-items: center; gap: 10px;
        font-size: 1rem;
        font-weight: 700;
        color: #0a2540;
        margin: 0;
    }
    .retour-produit-card__title i { color: #1c57a3; font-size: 18px; }
    .retour-produit-card__body { padding: 6px 0; }

    .retour-produit-table thead th {
        background: #f9fafb !important;
        color: #374151 !important;
        font-weight: 700 !important;
        font-size: 0.74rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 14px 12px !important;
        border-bottom: 1px solid #e5e7eb !important;
        border-top: 0 !important;
    }
    .retour-produit-table tbody td {
        padding: 14px 12px !important;
        border-bottom: 1px solid #f1f5f9 !important;
        vertical-align: middle !important;
        font-size: 0.88rem;
    }
    .retour-produit-name { color: #0a2540; font-weight: 700; }
    .retour-produit-num {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        color: #1c57a3;
        background: #eff6ff;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.8rem;
    }
    .retour-produit-qte-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        padding: 4px 10px;
        background: #eff6ff;
        color: #1c57a3;
        font-weight: 700;
        border-radius: 6px;
    }

    .retour-produit-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .retour-produit-badge--attente { background: #fef3c7; color: #92400e; }
    .retour-produit-badge--approuve { background: #d1fae5; color: #065f46; }
    .retour-produit-badge--refuse { background: #fee2e2; color: #991b1b; }
    .retour-produit-badge i { font-size: 10px; }

    .retour-produit-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        background: linear-gradient(135deg, #fb923c, #ea580c);
        color: #ffffff !important;
        font-weight: 700;
        font-size: 0.82rem;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0 4px 10px rgba(234,88,12,0.25);
        transition: all 0.18s ease;
    }
    .retour-produit-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(234,88,12,0.40);
    }
    .retour-produit-btn i { font-size: 12px; }

    @media (max-width: 575px) {
        .retour-produit-hero { padding: 30px 16px 36px; }
        .retour-produit-hero__title { font-size: 1.5rem; }
    }
</style>
@endsection
@section('jspart')
<script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
<script type="text/javascript">
    $(function() {
        var $table = $('#liste').DataTable({
            language: {
                url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
            },
        });
    });
</script>
@endsection
