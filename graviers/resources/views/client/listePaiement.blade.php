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
                                        <table class="table paiements-table" id="tablePaiementsEffectues">
                                            <thead>
                                                <tr>
                                                    <th>Code paiement</th>
                                                    <th>Moyen</th>
                                                    <th>N° commande</th>
                                                    <th class="text-end">Montant</th>
                                                    <th>Date commande</th>
                                                    <th>Date paiement</th>
                                                    <th class="text-center">État</th>
                                                    {{-- Deux en-têtes distincts, et non un seul avec colspan="2" :
                                                         DataTables exige autant de cellules d'en-tête que de colonnes
                                                         dans le corps, sinon l'initialisation échoue et le tableau
                                                         reste brut. --}}
                                                    <th class="text-center">Facture</th>
                                                    <th class="text-center">Téléchargement</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($lignes as $l)
                                                    <tr>
                                                        <td><span class="paiements-code">{{ $l->code_paiement }}</span></td>
                                                        <td>{{ $l->mode_paiement }}</td>
                                                        <td>
                                                            {{-- Trois affaires (10/09/2026) : commande, location, demande de livraison. --}}
                                                            @if (($l->type_affaire ?? 'COMMANDE') === 'LOCATION')
                                                                <a href="{{ route('client.detailDeLocation', $l->commande_id) }}"
                                                                   class="paiements-link">{{ $l->num_commande }}</a>
                                                                <span class="badge bg-light text-dark">Location</span>
                                                            @elseif (($l->type_affaire ?? 'COMMANDE') === 'LIVRAISON')
                                                                <a href="{{ route('client.detaiDemandeDeLivraison', $l->commande_id) }}"
                                                                   class="paiements-link">{{ $l->num_commande }}</a>
                                                                <span class="badge bg-light text-dark">Livraison</span>
                                                            @else
                                                                <a href="{{ route($l->est_livrable == 1 ? 'client.validationLivraisonPage' : 'client.recuperationProduit', $l->commande_id) }}"
                                                                   class="paiements-link">{{ $l->num_commande }}</a>
                                                            @endif
                                                        </td>
                                                        <td class="text-end fw-bold">{{ number_format($l->montant, 0, '', ' ') }} <small>FCFA</small></td>
                                                        <td><small>{{ Carbon::parse($l->date_commande)->format('d/m/Y H:i:s') }}</small></td>
                                                        <td><small>{{ Carbon::parse($l->date_paiement)->format('d/m/Y H:i:s') }}</small></td>
                                                        <td class="text-center">
                                                            {{-- Point 20 (09/09/2026) : l'opération, une fois finalisée au guichet, est « Effectuée ». --}}
                                                            @if ((int) ($l->statut_paiement ?? 1) === 2)
                                                                <span class="badge bg-warning text-dark">En attente de validation</span>
                                                            @elseif (($l->etat_reglement ?? null) === \App\Models\DemandePaiement::EFFECTUEE)
                                                                <span class="badge bg-success">Effectuée</span>
                                                            @elseif (!empty($l->etat_reglement ?? null))
                                                                <span class="badge bg-info text-dark">Validée — en cours</span>
                                                            @else
                                                                <span class="badge bg-success">Payé</span>
                                                            @endif
                                                        </td>
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
                        {{-- Plus de formulaire : cette page ne déclenche aucun paiement.
                             Elle envoyait auparavant vers paye.effectuerPaiementTraitement,
                             qui lançait la passerelle en ligne dès que la demande venait
                             d'un client. Le règlement se fait désormais au guichet, où il
                             est saisi par la caisse — le même traitement, appelé depuis le
                             back-office, reste en place pour cela. --}}
                        <div>

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
                                            <table class="table paiements-table" id="tablePaiementsEnAttente">
                                                <thead>
                                                    <tr>
                                                        {{-- Pour un client à terme, chaque ligne est une FACTURE : son
                                                             numéro doit être visible, sinon deux factures d'une même
                                                             commande sont indiscernables. Un client comptant, lui, n'a
                                                             pas de facture ici : la colonne ne lui est pas montrée. --}}
                                                        @if ($client->client_a_terme == 1)
                                                            <th>N° facture</th>
                                                        @endif
                                                        <th>N° commande</th>
                                                        <th class="text-end">Montant facturé</th>
                                                        <th class="text-end">Reste à payer</th>
                                                        <th>{{ $client->client_a_terme == 1 ? 'Date facture' : 'Date commande' }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($lignes as $l)
                                                        <tr>
                                                            @if ($client->client_a_terme == 1)
                                                                <td><strong class="paiements-link">{{ $l->num_facture ?? '—' }}</strong></td>
                                                            @endif
                                                            <td>{{ $l->num_commande }}</td>
                                                            <td class="text-end fw-bold">{{ number_format($l->montant_a_payer, '0', '', ' ') }} <small>FCFA</small></td>
                                                            <td class="text-end fw-bold paiements-restant">{{ number_format($l->montant_restant, '0', '', ' ') }} <small>FCFA</small></td>
                                                            <td><small>{{ \Help::dateHeure($client->client_a_terme == 1 ? ($l->date_facture ?? $l->date_commande) : $l->date_commande) }}</small></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        {{-- Cette page est une CONSULTATION, pour tous les clients.
                                             Le règlement se fait en agence, jamais ici.

                                             Elle proposait auparavant aux comptes à terme de payer
                                             eux-mêmes en ligne, sur n'importe quelle facture. Or le mode
                                             annoncé à la commande — « Paiement en agence » le plus
                                             souvent — n'était consulté nulle part : le client déclarait
                                             qu'il réglerait au guichet, et le site lui proposait quand
                                             même de payer par mobile money. Le bloc offrait d'ailleurs
                                             « Paiement en agence » parmi les moyens, alors que ce choix
                                             lançait lui aussi la passerelle en ligne.

                                             Décision de gestion du 11/08/2026 : les factures et
                                             commandes non réglées s'affichent en lecture seule, et le
                                             règlement se fait au guichet. --}}
                                        <div class="paiements-info-banner">
                                            <i class="fi-rs-info"></i>
                                            <div>
                                                @if ($client->client_a_terme == 1)
                                                    <strong>Ces factures se règlent en agence.</strong>
                                                    Présentez le numéro de la facture à nos guichets ;
                                                    le règlement est enregistré immédiatement et disparaît de cette liste.
                                                @else
                                                    <strong>Ces commandes se règlent en agence.</strong>
                                                    Présentez le numéro de la commande à nos guichets pour la régler ;
                                                    elle sera traitée dès le paiement enregistré.
                                                @endif
                                                <br>
                                                Une question ? <a href="mailto:{{ \Help::emailContact() }}">{{ \Help::emailContact() }}</a>
                                            </div>
                                        </div>
                                    @else
                                        <div class="paiements-empty">
                                            <div class="paiements-empty__icon paiements-empty__icon--success"><i class="fi-rs-check"></i></div>
                                            <h4 class="paiements-empty__title">Aucun paiement en attente</h4>
                                            <p class="paiements-empty__text">Toutes vos commandes sont à jour. Bravo !</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
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

        /* Habillage des commandes DataTables : la feuille de style officielle
           n'est pas chargée sur les pages client, et sans ces quelques règles
           la recherche et la pagination s'affichent sans mise en forme, en
           rupture avec le reste de la page.
           La bibliothèque installée est la version 2 : ses classes s'appellent
           « dt-search », « dt-paging »… et non plus « dataTables_filter »,
           « dataTables_paginate » comme en version 1. Les deux séries sont
           visées pour rester correct si la version change. */
        .dt-search input, .dataTables_filter input,
        .dt-length select, .dataTables_length select {
            border: 1px solid #dcdcdc;
            border-radius: 8px;
            padding: 6px 10px;
            margin-left: 8px;
        }
        .dt-search, .dataTables_filter { margin-bottom: 12px; }
        .dt-info, .dataTables_info { padding-top: 12px; font-size: 0.85rem; color: #666; }
        .dt-paging, .dataTables_paginate { padding-top: 10px; }
        .dt-paging .dt-paging-button,
        .dataTables_paginate .paginate_button {
            padding: 5px 11px;
            margin-left: 4px;
            border: 1px solid #e2e2e2;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
        }
        .dt-paging .dt-paging-button.current,
        .dataTables_paginate .paginate_button.current {
            background: #1c57a3;
            border-color: #1c57a3;
            color: #fff !important;
        }
        .dt-paging .dt-paging-button.disabled,
        .dataTables_paginate .paginate_button.disabled {
            opacity: .45;
            cursor: default;
        }
    </style>

    {{-- DataTables ne peut PAS être inclus par une simple balise ici : sur les
         pages client, jQuery est chargé par le pied de page, donc APRÈS le
         contenu. Un <script src="datatables"> placé à cet endroit s'exécute
         avant que jQuery n'existe et ne s'y rattache jamais : le fichier est
         bien téléchargé (200), mais $.fn.DataTable reste introuvable et le
         tableau demeure brut. C'est exactement ce qui se produit aujourd'hui
         sur « Mon compte », où aucun des tableaux n'est réellement activé.

         On attend donc le chargement complet de la page — pied de page inclus,
         donc jQuery disponible — avant d'injecter la bibliothèque, puis on
         initialise une fois celle-ci prête. --}}
    <script>
        window.addEventListener('load', function () {
            if (typeof window.jQuery === 'undefined') {
                return; // sans jQuery, rien à faire : le tableau reste lisible tel quel
            }

            var script = document.createElement('script');
            script.src = '{{ asset('backend/plugins/DataTables/datatables.min.js') }}';
            script.onload = function () { activerLesTableaux(window.jQuery); };
            document.body.appendChild(script);
        });

        function activerLesTableaux($) {
            // Les deux tableaux ne s'affichent jamais ensemble : l'onglet actif
            // décide lequel est rendu. On initialise donc celui qui est présent,
            // et seulement s'il l'est — initialiser un tableau absent, ou vide,
            // provoque l'erreur « Requested unknown parameter » déjà rencontrée
            // ailleurs dans le projet après un vidage de données.
            if (typeof $.fn.DataTable === 'undefined') {
                return;
            }

            var communs = {
                language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                order: [],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Tout']],
                // Une cellule absente ne doit pas interrompre le rendu de toute
                // la table : elle s'affiche vide.
                columnDefs: [{ targets: '_all', defaultContent: '' }]
            };

            var $attente = $('#tablePaiementsEnAttente');
            if ($attente.length && $attente.find('tbody tr').length) {
                $attente.DataTable(communs);
            }

            var $effectues = $('#tablePaiementsEffectues');
            if ($effectues.length && $effectues.find('tbody tr').length) {
                $effectues.DataTable($.extend({}, communs, {
                    // Les deux dernières colonnes ne portent que des boutons :
                    // les trier ou les fouiller n'a aucun sens.
                    columnDefs: [
                        { targets: '_all', defaultContent: '' },
                        { targets: [-1, -2], orderable: false, searchable: false }
                    ]
                }));
            }
        }
    </script>
@endsection
