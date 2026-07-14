@php
    use Illuminate\Support\Carbon;
@endphp
@extends('client.main')
@section('title', 'Paiement de commande')
@section('content')
    @if (session('ok'))
        <div class="alert alert-info text-center mx-auto mt-3" style="max-width:680px;" id="notify">{{ session('ok') }}</div>
    @endif
    @if (session('errorQte'))
        <div class="alert alert-danger text-center mx-auto mt-3" style="max-width:680px;" id="notify"> {{ session('errorQte') }} </div>
    @endif
    @if (session('livree'))
        <div class="alert alert-success text-center mx-auto mt-3" style="max-width:680px;" id="notify"> {{ session('livree') }} </div>
    @endif

    <main class="main paiements-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="paiements-hero">
            <div class="paiements-hero__inner">
                <span class="paiements-hero__chip"><i class="fi-rs-receipt"></i> Espace client</span>
                <h1 class="paiements-hero__title">Mes paiements</h1>
                <p class="paiements-hero__subtitle">Consultez vos paiements en attente et l'historique des paiements effectués.</p>
            </div>
        </section>

        <div class="container mb-80 mt-30">

            {{-- ===== TABS ===== --}}
            <div class="paiements-tabs mb-4">
                <a href="{{ route('client.listePaiementCommande', 'en-attente') }}"
                   class="paiements-tab {{ $etat === 'effectues' ? '' : 'is-active' }}">
                    <i class="fi-rs-clock"></i>
                    <span>Paiements en attente</span>
                </a>
                <a href="{{ route('client.listePaiementCommande', 'effectues') }}"
                   class="paiements-tab {{ $etat === 'effectues' ? 'is-active' : '' }}">
                    <i class="fi-rs-check"></i>
                    <span>Paiements effectués</span>
                </a>
            </div>

            <div class="row">
                @if ($etat == 'effectues')
                    {{-- ===== Paiements effectués ===== --}}
                    <div class="col-12">
                        <div class="paiements-card">
                            <div class="paiements-card__header">
                                <h5 class="paiements-card__title">
                                    <i class="fi-rs-check"></i> Historique des paiements
                                </h5>
                            </div>
                            <div class="paiements-card__body">
                                @if (count($lignes) > 0)
                                    <div class="table-responsive">
                                        <table class="table paiements-table" id="liste">
                                            <thead>
                                                <tr>
                                                    <th>Code paiement</th>
                                                    <th>Moyen</th>
                                                    <th>N° commande</th>
                                                    <th class="text-end">Montant</th>
                                                    <th>Date commande</th>
                                                    <th>Date paiement</th>
                                                    <th class="text-center" colspan="2">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($lignes as $l)
                                                    <tr>
                                                        <td><span class="paiements-code">{{ $l->code_paiement }}</span></td>
                                                        <td>{{ $l->mode_paiement }}</td>
                                                        <td>
                                                            <a href="{{ route($l->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit', $l->commande_id) }}"
                                                               class="paiements-link">{{ $l->num_commande }}</a>
                                                        </td>
                                                        <td class="text-end fw-bold">{{ number_format($l->montant, 0, '', ' ') }} <small>FCFA</small></td>
                                                        <td><small>{{ Carbon::parse($l->date_commande)->format('d/m/Y H:i') }}</small></td>
                                                        <td><small>{{ Carbon::parse($l->date_paiement)->format('d/m/Y H:i') }}</small></td>
                                                        <td>
                                                            <a href="{{ route('paye.facture', ['reference' => $l->ligne_id, 'action' => 'voir']) }}"
                                                               class="paiements-action-btn paiements-action-btn--view">
                                                                <i class="fi-rs-eye"></i> Voir
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('paye.facture', ['reference' => $l->ligne_id, 'action' => 'telecharger']) }}"
                                                               class="paiements-action-btn paiements-action-btn--download">
                                                                <i class="fi-rs-download"></i> Télécharger
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="paiements-empty">
                                        <div class="paiements-empty__icon"><i class="fi-rs-receipt"></i></div>
                                        <h4 class="paiements-empty__title">Aucun paiement effectué</h4>
                                        <p class="paiements-empty__text">Vous n'avez pas encore effectué de paiement.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    {{-- ===== Paiements en attente ===== --}}
                    <div class="col-12">
                        <form action="{{ route('paye.effectuerPaiementTraitement', $client->id) }}" method="post">
                            @csrf

                            <div class="paiements-card">
                                <div class="paiements-card__header">
                                    <h5 class="paiements-card__title">
                                        <i class="fi-rs-clock"></i> Paiements en attente
                                    </h5>
                                </div>
                                <div class="paiements-card__body">
                                    @error('paiements')
                                        <div class="alert alert-danger text-center"> {{ $message }} </div>
                                    @enderror
                                    @if (session('error'))
                                        <div class="alert alert-danger text-center" id="notify">
                                            {{ session('error') }}
                                        </div>
                                    @endif
                                    @error ('factures')
                                        <div class="alert alert-danger text-center" id="notify">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    @if (count($lignes) > 0)
                                        <div class="table-responsive">
                                            <table class="table paiements-table" id="liste">
                                                <thead>
                                                    <tr>
                                                        <th class="text-center" style="width:50px"></th>
                                                        <th>N° commande</th>
                                                        <th class="text-end">Montant facturé</th>
                                                        <th class="text-end">Reste à payer</th>
                                                        <th>Date commande</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($lignes as $l)
                                                        <tr>
                                                            <td class="text-center">
                                                                @if ($client->client_a_terme == 1)
                                                                    <input type="checkbox" class="paiements-checkbox"
                                                                           name="factures[]"
                                                                           value="{{ isset($l->facture_id) ? $l->facture_id : $l->commande_id }}">
                                                                @endif
                                                            </td>
                                                            <td><strong class="paiements-link">{{ $l->num_commande }}</strong></td>
                                                            <td class="text-end fw-bold">{{ number_format($l->montant_a_payer, '0', '', ' ') }} <small>FCFA</small></td>
                                                            <td class="text-end fw-bold paiements-restant">{{ number_format($l->montant_restant, '0', '', ' ') }} <small>FCFA</small></td>
                                                            <td><small>{{ \Carbon\Carbon::parse($l->date_commande)->format('d/m/Y') }}</small></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        {{-- Bloc paiement (client à terme uniquement) --}}
                                        @if ($client->client_a_terme == 1)
                                            <div class="paiements-pay-block">
                                                <div class="paiements-pay-block__title">
                                                    <i class="fi-rs-credit-card"></i> Effectuer un paiement
                                                </div>
                                                <div class="paiements-pay-block__grid">
                                                    <div>
                                                        <label class="paiements-field-label">Montant à payer</label>
                                                        <input type="number" placeholder="Entrez le montant" name="montant" class="form-control paiements-input">
                                                    </div>
                                                    <div>
                                                        <label class="paiements-field-label">Moyen de paiement</label>
                                                        <select required name="mode" class="form-control paiements-input">
                                                            <option value="">Choisir un moyen...</option>
                                                            @foreach ($moyens as $moyen)
                                                                <option value="{{ $moyen->id }}">{{ $moyen->libelle }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="paiements-pay-block__action">
                                                        <button type="submit" class="paiements-pay-btn">
                                                            <i class="fi-rs-credit-card"></i> Payer
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="paiements-info-banner">
                                                <i class="fi-rs-info"></i>
                                                <div>
                                                    Vous ne pouvez pas effectuer un paiement en ligne car vous n'êtes pas un client à terme.
                                                    <a href="mailto:support@gravier.com">Contactez le support</a> pour plus d'informations.
                                                </div>
                                            </div>
                                        @endif
                                    @else
                                        <div class="paiements-empty">
                                            <div class="paiements-empty__icon paiements-empty__icon--success"><i class="fi-rs-check"></i></div>
                                            <h4 class="paiements-empty__title">Aucun paiement en attente</h4>
                                            <p class="paiements-empty__text">Toutes vos commandes sont à jour. Bravo !</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            <div class="paiements-back">
                <a class="paiements-back__link" href="{{ route('client.monCompte') }}">
                    <i class="fi-rs-arrow-left"></i> Retour à mon compte
                </a>
            </div>
        </div>
    </main>

    <style>
        /* ===== HERO ===== */
        .paiements-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .paiements-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .paiements-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .paiements-hero__chip {
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
        .paiements-hero__chip i { color: #fbbf24; font-size: 14px; }
        .paiements-hero__title,
        h1.paiements-hero__title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .paiements-hero__subtitle {
            margin: 0;
            color: rgba(255,255,255,0.92);
            font-size: 0.92rem;
        }

        /* ===== TABS ===== */
        .paiements-tabs {
            display: inline-flex;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 4px;
            gap: 2px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }
        .paiements-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            color: #6b7280 !important;
            font-weight: 600;
            font-size: 0.88rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .paiements-tab:hover { background: #f9fafb; color: #1c57a3 !important; }
        .paiements-tab.is-active {
            background: linear-gradient(135deg, #1c57a3, #134380);
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(28, 87, 163, 0.25);
        }
        .paiements-tab i { font-size: 14px; }

        /* ===== CARD ===== */
        .paiements-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }
        .paiements-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
        }
        .paiements-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .paiements-card__title i { color: #1c57a3; font-size: 18px; }
        .paiements-card__body { padding: 6px 0 20px; }

        /* ===== TABLE ===== */
        .paiements-table thead th {
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
        .paiements-table tbody td {
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding: 14px 12px !important;
            font-size: 0.92rem;
        }
        .paiements-table tbody tr:hover { background: #fafbfc; }
        .paiements-code {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #1c57a3;
            background: #eff6ff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .paiements-link {
            color: #1c57a3 !important;
            font-weight: 700;
            text-decoration: none;
        }
        .paiements-link:hover { color: #0a2540 !important; text-decoration: underline; }
        .paiements-restant { color: #ea580c !important; }

        /* Action buttons */
        .paiements-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            border: 1.5px solid transparent;
        }
        .paiements-action-btn--view {
            background: #eff6ff;
            color: #1c57a3 !important;
            border-color: #dbeafe;
        }
        .paiements-action-btn--view:hover {
            background: #1c57a3;
            color: #ffffff !important;
        }
        .paiements-action-btn--download {
            background: #ecfdf5;
            color: #10b981 !important;
            border-color: #d1fae5;
        }
        .paiements-action-btn--download:hover {
            background: #10b981;
            color: #ffffff !important;
        }
        .paiements-action-btn i { font-size: 12px; }

        /* Checkboxes */
        .paiements-checkbox {
            width: 18px; height: 18px;
            accent-color: #1c57a3;
            cursor: pointer;
        }

        /* ===== PAY BLOCK ===== */
        .paiements-pay-block {
            margin: 18px 22px 0;
            padding: 20px 22px;
            background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
            border: 1.5px solid #bfdbfe;
            border-radius: 14px;
        }
        .paiements-pay-block__title {
            display: flex; align-items: center; gap: 10px;
            color: #0a2540;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 16px;
        }
        .paiements-pay-block__title i { color: #1c57a3; font-size: 18px; }
        .paiements-pay-block__grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }
        .paiements-pay-block__action { display: flex; }
        .paiements-field-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }
        .paiements-input {
            padding: 11px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            font-size: 0.92rem !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            height: auto !important;
        }
        .paiements-input:focus {
            border-color: #1c57a3 !important;
            box-shadow: 0 0 0 3px rgba(28, 87, 163, 0.12) !important;
            outline: none !important;
        }
        .paiements-pay-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.92rem;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(234, 88, 12, 0.30);
            transition: all 0.18s ease;
        }
        .paiements-pay-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(234, 88, 12, 0.42);
        }
        .paiements-pay-btn i { font-size: 14px; }

        /* Info banner */
        .paiements-info-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 22px 0;
            padding: 14px 18px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #3b82f6;
            border-radius: 10px;
            color: #1e40af;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        .paiements-info-banner i { font-size: 22px; flex-shrink: 0; color: #3b82f6; }
        .paiements-info-banner a { color: #1c57a3; font-weight: 700; }

        /* Empty */
        .paiements-empty {
            text-align: center;
            padding: 40px 20px;
        }
        .paiements-empty__icon {
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
        .paiements-empty__icon--success {
            background: linear-gradient(135deg, #d1fae5, #6ee7b7);
            color: #10b981;
        }
        .paiements-empty__title { color: #0a2540; font-weight: 700; margin: 0 0 6px; }
        .paiements-empty__text { color: #6b7280; font-size: 0.92rem; margin: 0; }

        /* Back link */
        .paiements-back { margin-top: 24px; text-align: center; }
        .paiements-back__link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6b7280 !important;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.15s ease;
        }
        .paiements-back__link:hover { color: #1c57a3 !important; transform: translateX(-2px); }

        /* Responsive */
        @media (max-width: 768px) {
            .paiements-pay-block__grid { grid-template-columns: 1fr; }
            .paiements-pay-block__action { width: 100%; }
            .paiements-pay-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 575px) {
            .paiements-hero { padding: 30px 16px 36px; }
            .paiements-hero__title { font-size: 1.5rem; }
            .paiements-tabs { width: 100%; flex-direction: column; }
            .paiements-tab { justify-content: center; }
            .paiements-action-btn { padding: 6px 8px; font-size: 0.75rem; }
        }
    </style>
@endsection
