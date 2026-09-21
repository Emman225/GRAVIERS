@php
    use Illuminate\Support\Carbon;
@endphp
@extends('layout.main')
@section('title','Paramètres')
@section('contenu')
    <section class="content-main">
        <div class="content-header">
            <h2 class="content-title">Paramètres</h2>
        </div>

        <div class="card">
            <div class="card-body">

                {{-- ===== ONGLETS =====
                     Les neuf onglets passaient à la ligne dès que la fenêtre se
                     rétrécissait, sur deux ou trois rangs. Ils tiennent désormais
                     sur une seule ligne qui défile horizontalement, avec deux
                     flèches et un fondu aux extrémités pour signaler ce qui reste
                     hors champ — sans quoi rien n'indique qu'il y a d'autres
                     onglets à droite. --}}
                <div class="onglets-defilants" id="ongletsParametre">
                    <button type="button" class="onglets-defilants__fleche onglets-defilants__fleche--gauche"
                            aria-label="Onglets précédents" hidden>
                        <i class="material-icons md-chevron_left"></i>
                    </button>

                <ul class="nav nav-tabs" id="parametreTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-general-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-general" data-target="#tab-general"
                            type="button" role="tab" aria-controls="tab-general" aria-selected="true">
                            <i class="material-icons md-settings align-middle"></i>
                            Configuration générale
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-livraison-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-livraison" data-target="#tab-livraison"
                            type="button" role="tab" aria-controls="tab-livraison" aria-selected="false">
                            <i class="material-icons md-local_shipping align-middle"></i>
                            Livraison & TVA
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-gestionnaires-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-gestionnaires" data-target="#tab-gestionnaires"
                            type="button" role="tab" aria-controls="tab-gestionnaires" aria-selected="false">
                            <i class="material-icons md-supervisor_account align-middle"></i>
                            Gestionnaires & Notifications
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-prix-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-prix" data-target="#tab-prix"
                            type="button" role="tab" aria-controls="tab-prix" aria-selected="false">
                            <i class="material-icons md-tune align-middle"></i>
                            Prix personnalisés
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-creance-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-creance" data-target="#tab-creance"
                            type="button" role="tab" aria-controls="tab-creance" aria-selected="false">
                            <i class="material-icons md-receipt_long align-middle"></i>
                            Créances à terme
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-comptant-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-comptant" data-target="#tab-comptant"
                            type="button" role="tab" aria-controls="tab-comptant" aria-selected="false">
                            <i class="material-icons md-store align-middle"></i>
                            Comptant / Agence
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-livreurs-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-livreurs" data-target="#tab-livreurs"
                            type="button" role="tab" aria-controls="tab-livreurs" aria-selected="false">
                            <i class="material-icons md-directions_car align-middle"></i>
                            Livreurs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-apporteurs-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-apporteurs" data-target="#tab-apporteurs"
                            type="button" role="tab" aria-controls="tab-apporteurs" aria-selected="false">
                            <i class="material-icons md-people align-middle"></i>
                            Apporteurs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-termes-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-termes" data-target="#tab-termes"
                            type="button" role="tab" aria-controls="tab-termes" aria-selected="false">
                            <i class="material-icons md-gavel align-middle"></i>
                            Termes & conditions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-entreprise-tab" data-bs-toggle="tab"
                            data-toggle="tab" data-bs-target="#tab-entreprise" data-target="#tab-entreprise"
                            type="button" role="tab" aria-controls="tab-entreprise" aria-selected="false">
                            <i class="material-icons md-domain align-middle"></i>
                            Entreprise & mentions légales
                        </button>
                    </li>
                    {{-- AUDIT — RÉSERVÉ AU SUPERADMINISTRATEUR ET À L'ADMINISTRATEUR.
                         Le journal formait un menu principal ; il est devenu cet
                         onglet le 29/08/2026. « Paramètre » s'ouvre aussi au
                         gestionnaire, à qui le journal n'a jamais été montré :
                         l'onglet ne lui est donc pas affiché, et le contrôleur ne
                         lit même pas la table pour lui. --}}
                    @if ($peutVoirAudit ?? false)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-audit-tab" data-bs-toggle="tab"
                                data-toggle="tab" data-bs-target="#tab-audit" data-target="#tab-audit"
                                type="button" role="tab" aria-controls="tab-audit" aria-selected="false">
                                <i class="material-icons md-history align-middle"></i>
                                Audit
                            </button>
                        </li>
                    @endif
                </ul>

                    <button type="button" class="onglets-defilants__fleche onglets-defilants__fleche--droite"
                            aria-label="Onglets suivants" hidden>
                        <i class="material-icons md-chevron_right"></i>
                    </button>
                </div>

                {{-- ===== CONTENU DES ONGLETS ===== --}}
                <div class="tab-content" id="parametreTabsContent">

                    {{-- ============================================================
                         ONGLET 1 : CONFIGURATION GÉNÉRALE
                         ============================================================ --}}
                    <div class="tab-pane fade show active" id="tab-general" role="tabpanel"
                        aria-labelledby="tab-general-tab">

                        {{-- MODE « SITE EN CONSTRUCTION » (lot 114, 19/09/2026) : un interrupteur, réservé aux
                             administrateurs. Actif : le site public n'est visible que des personnes connectées. --}}
                        @php $enConstruction = \App\Models\Configuration::siteEnConstruction(); @endphp
                        <div class="card mb-4" id="carte-site-en-construction"
                             style="border: 1px solid {{ $enConstruction ? '#f5c26b' : '#d5dbe3' }}; background: {{ $enConstruction ? '#fff7e0' : '#f8fafc' }};">
                            <div class="card-body d-flex align-items-center justify-content-between flex-wrap" style="gap: 14px;">
                                <div>
                                    <h5 class="mb-1">
                                        Site en construction
                                        <span class="badge {{ $enConstruction ? 'bg-warning text-dark' : 'bg-secondary' }}" style="vertical-align: middle;">
                                            {{ $enConstruction ? 'Activé' : 'Désactivé' }}
                                        </span>
                                    </h5>
                                    <p class="text-muted small mb-0" style="max-width: 640px;">
                                        Activé : un visiteur non connecté ne voit que la page
                                        <a href="{{ route('siteEnConstruction') }}" target="_blank">« site en construction »</a> ;
                                        les personnes connectées naviguent normalement et arrivent sur l'accueil après leur connexion.
                                        Les pages de connexion et les applications mobiles restent ouvertes.
                                    </p>
                                </div>
                                @if (in_array((int) Auth::user()->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true))
                                    <form method="post" action="{{ route('show.basculerSiteEnConstruction') }}" class="js-delete-form"
                                          data-confirm-mode="confirm"
                                          data-confirm-title="{{ $enConstruction ? 'Rouvrir le site public ?' : 'Mettre le site en construction ?' }}"
                                          data-confirm-button="{{ $enConstruction ? 'Oui, rouvrir le site' : 'Oui, activer le mode' }}"
                                          data-confirm-html="{{ $enConstruction
                                              ? 'Le site public redeviendra visible de <strong>tous les visiteurs</strong>.'
                                              : 'Les visiteurs <strong>non connectés</strong> ne verront plus que la page « site en construction ».' }}">
                                        @csrf
                                        <button type="submit" class="btn {{ $enConstruction ? 'btn-success' : 'btn-warning' }}">
                                            <i class="material-icons md-{{ $enConstruction ? 'lock_open' : 'construction' }} align-middle"></i>
                                            {{ $enConstruction ? 'Désactiver le mode' : 'Activer le mode' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted small">Réservé aux administrateurs.</span>
                                @endif
                            </div>
                        </div>

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="general">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Devise</label>
                                    <input class="form-control" name="devise" required value="{{ $config->devise }}" />
                                    <small class="text-muted">Devise utilisée dans toute l'application (ex. FCFA).</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Montant par point</label>
                                    <input class="form-control" required type="number" name="montant_point"
                                        value="{{ $config->montant_point }}" />
                                    <small class="text-muted">Valeur d'un point fidélité en {{ $config->devise }}.</small>
                                </div>

                                {{-- LA REGLE D'ATTRIBUTION, ENFIN PARAMETRABLE.
                                     Elle vivait en dur dans le code — 200 points
                                     forfaitaires au guichet — et differait selon
                                     le canal de paiement. --}}
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Montant pour un point</label>
                                    <input class="form-control" required type="number" min="1" step="1"
                                        name="montant_pour_un_point"
                                        value="{{ $config->montant_pour_un_point }}" />
                                    <small class="text-muted">
                                        Somme encaissée donnant 1 point.
                                        @php
                                            $taux = ($config->montant_pour_un_point ?? 0) > 0
                                                ? 100 * ($config->montant_point ?? 0) / $config->montant_pour_un_point
                                                : 0;
                                        @endphp
                                        Soit <strong>{{ rtrim(rtrim(number_format($taux, 2, ',', ' '), '0'), ',') }} %</strong>
                                        des achats rendus au client.
                                    </small>
                                </div>

                                {{-- PLANCHER DE PAIEMENT.
                                     Les points pouvaient couvrir une commande
                                     ENTIERE : sans livraison, le total tombait a
                                     zero et la commande devenait une impasse — la
                                     passerelle appelee avec 0, l'encaissement au
                                     guichet refusant le montant nul, le virement
                                     supposant un justificatif de 0. --}}
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Minimum à payer</label>
                                    <input class="form-control" required type="number" min="0" step="1"
                                        name="montant_minimum_a_payer"
                                        value="{{ $config->montant_minimum_a_payer }}" />
                                    <small class="text-muted">
                                        Somme minimale qu'une commande doit laisser à payer.
                                        Les points ne descendent pas en dessous ; le reliquat
                                        reste au compte du client.
                                        <strong>0 retire la limite.</strong>
                                    </small>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 2 : LIVRAISON & TVA
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-livraison" role="tabpanel"
                        aria-labelledby="tab-livraison-tab">

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="livraison">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">TVA (%)</label>
                                    <input class="form-control" required type="text" name="tva"
                                        value="{{ $config->tva }}" />
                                    <small class="text-muted">Taux par défaut appliqué aux factures.</small>
                                </div>

                                {{-- La TVA sur le transport : une decision de gestion, pas un taux.
                                     Le champ cache accompagne la case — decochee, une case n est pas
                                     transmise, et la desactivation serait impossible. --}}
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">TVA sur le transport</label>
                                    <input type="hidden" name="tva_transport" value="0">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               name="tva_transport" id="tvaTransport"
                                               @checked((int) ($config->tva_transport ?? 0) === 1)>
                                        <label class="form-check-label" for="tvaTransport">
                                            Appliquer la TVA au transport (ventes, locations et demandes de livraison)
                                        </label>
                                    </div>
                                    <small class="text-muted">
                                        Désactivée, le transport est facturé hors taxe. Le choix vaut pour
                                        les <strong>nouvelles</strong> demandes : les courses déjà chiffrées
                                        gardent leur montant. Un client donné peut en être dispensé depuis la
                                        liste des clients.
                                    </small>
                                </div>

                                {{-- L'AIRSI (10/09/2026) : acompte prélevé sur le client sans régime réel
                                     d'imposition (RNI / RSI), calculé sur le HT + TVA, ajouté au net à payer. --}}
                                <div class="col-lg-4 mb-3">
                                    <label class="form-label" for="tauxAirsi">Taux de l'AIRSI (%)</label>
                                    <input type="number" class="form-control" name="taux_airsi" id="tauxAirsi"
                                           min="0" max="100" step="0.01" value="{{ $config->taux_airsi ?? 5 }}">
                                    <small class="text-muted">
                                        Acompte d'impôt sur le revenu du secteur informel : dû par le client
                                        qui n'a pas déclaré un régime réel (RNI ou RSI), sur le HT + TVA,
                                        dans la case « Autres taxes » des documents. 5 % par défaut ; à 0, plus
                                        aucun AIRSI sur les <strong>nouvelles</strong> affaires.
                                    </small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Prix par km ({{ $config->devise }})</label>
                                    <input class="form-control" name="prixKm" required value="{{ $config->prixKm }}" />
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Coût livraison minimum ({{ $config->devise }})</label>
                                    <input class="form-control" name="cout_livraison_min" required
                                        value="{{ $config->cout_livraison_min }}" />
                                    <small class="text-muted">
                                        Plancher appliqué quelle que soit la distance. C'est lui qui doit
                                        couvrir le forfait versé au livreur sur les courses courtes.
                                    </small>
                                </div>

                                {{-- Choix du mode de tarification du transport pour les VENTES et les
                                     LOCATIONS. Un interrupteur plutôt qu'un déploiement : la grille
                                     doit d'abord être complète, et le basculement change les prix
                                     facturés dans des proportions importantes. --}}
                                <div class="col-lg-12 mb-3">
                                    <label class="form-label">Tarification du transport (ventes et locations)</label>
                                    <select class="form-control" name="livraison_sur_grille">
                                        <option value="0" {{ (int) ($config->livraison_sur_grille ?? 0) === 0 ? 'selected' : '' }}>
                                            Formule kilométrique — distance × prix par km, avec plancher
                                        </option>
                                        <option value="1" {{ (int) ($config->livraison_sur_grille ?? 0) === 1 ? 'selected' : '' }}>
                                            Grille tarifaire — forfait par unité, quantité et distance
                                        </option>
                                    </select>
                                    <small class="text-muted">
                                        La grille est celle des demandes de livraison. Avant de basculer,
                                        lancez <code>php artisan grille:auditer</code> : une unité non
                                        tarifée fait retomber ses produits sur la formule kilométrique,
                                        et deux régimes de prix coexisteraient.
                                    </small>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 3 : GESTIONNAIRES & NOTIFICATIONS
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-gestionnaires" role="tabpanel"
                        aria-labelledby="tab-gestionnaires-tab">

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="gestionnaires">
                            <div class="row">

                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Email trésorier</label>
                                    <input class="form-control" required name="email_tresorier" type="email"
                                        value="{{ $config->email_tresorier }}" />
                                </div>

                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Email Directeur marketing</label>
                                    <input class="form-control" required name="email_directeur_marketing"
                                        type="email" value="{{ $config->email_directeur_marketing }}" />
                                </div>

                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Gestionnaire validant 1</label>
                                    <select name="gestionnaire1_id" class="form-control">
                                        <option value="">Sélectionner un gestionnaire</option>
                                        @foreach ($gestionnaires as $gestionnaire)
                                            <option value="{{ $gestionnaire->id }}"
                                                {{ $gestionnaire->id == $config->gestionnaire1?->id ? 'selected' : '' }}>
                                                {{ $gestionnaire->nom_prenoms }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-6 mb-3">
                                    <label class="form-label">Gestionnaire validant 2</label>
                                    <select name="gestionnaire2_id" class="form-control">
                                        <option value="">Sélectionner un gestionnaire</option>
                                        @foreach ($gestionnaires as $gestionnaire)
                                            <option value="{{ $gestionnaire->id }}"
                                                {{ $gestionnaire->id == $config->gestionnaire2?->id ? 'selected' : '' }}>
                                                {{ $gestionnaire->nom_prenoms }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 4 : PRIX PERSONNALISÉS
                         (anciennement page /configuration-prix)
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-prix" role="tabpanel" aria-labelledby="tab-prix-tab">

                        {{-- Formulaire d'ajout de prix personnalisé --}}
                        <div class="card mb-4">
                            <header class="card-header" style="background-color: #1c57a3;">
                                <h5 class="mb-0" style="color: white;">Configurer un prix personnalisé</h5>
                            </header>
                            <div class="card-body">
                                <form action="{{ route('configPrix.store') }}" method="POST">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="client_id" class="form-label"><strong>Client (Particulier / Entreprise)</strong></label>
                                            <select name="client_id" id="client_id" class="form-select form-control" required>
                                                <option value="">-- Sélectionner un client --</option>
                                                @foreach ($clients as $client)
                                                    <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                                        {{ $client->display_name }} - {{ $client->user?->email ?? '' }} ({{ $client->type_client }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('client_id')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="col-md-4 mb-3">
                                            <label for="produit_id" class="form-label"><strong>Produit</strong></label>
                                            <select name="produit_id" id="produit_id" class="form-select form-control" required>
                                                <option value="">-- Sélectionner un produit --</option>
                                                @php
                                                    $prodVente = $produits->filter(fn ($p) => $p->type_affaire !== 'LOCATION');
                                                    $prodLoc   = $produits->filter(fn ($p) => $p->type_affaire === 'LOCATION');
                                                @endphp
                                                @if ($prodVente->count())
                                                    <optgroup label="Produits — Vente">
                                                        @foreach ($prodVente as $produit)
                                                            <option value="{{ $produit->id }}" {{ old('produit_id') == $produit->id ? 'selected' : '' }}>
                                                                {{ $produit->nom }} ({{ number_format($prixFournisseur[$produit->id] ?? $produit->prix_moyen, 0, ',', ' ') }} FCFA)
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                                @if ($prodLoc->count())
                                                    <optgroup label="Produits — Location (prix / jour)">
                                                        @foreach ($prodLoc as $produit)
                                                            <option value="{{ $produit->id }}" {{ old('produit_id') == $produit->id ? 'selected' : '' }}>
                                                                {{ $produit->nom }} ({{ number_format($prixFournisseur[$produit->id] ?? $produit->prix_moyen, 0, ',', ' ') }} FCFA / jour)
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            </select>
                                            @error('produit_id')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="col-md-2 mb-3">
                                            <label for="prix" class="form-label"><strong>Prix (FCFA)</strong></label>
                                            <input type="number" name="prix" id="prix" class="form-control" min="0" step="1"
                                                value="{{ old('prix') }}" placeholder="Ex: 5000" required>
                                            @error('prix')
                                                <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="col-md-2 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="material-icons md-check"></i> Valider
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- Liste des prix personnalisés --}}
                        <div class="card mb-4">
                            <header class="card-header" style="background-color: #1c57a3;">
                                <h5 class="mb-0" style="color: white;">Liste des prix personnalisés par client</h5>
                            </header>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="listePrixPersonnalises">
                                        <thead>
                                            <tr>
                                                <th class="text-center">Nom du client</th>
                                                <th class="text-center">Email</th>
                                                <th class="text-center">Type</th>
                                                <th class="text-center">Nb produits</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($clientsAvecPrix as $item)
                                                <tr>
                                                    <td class="text-center align-middle">
                                                        {{ $item->client?->display_name }}
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        {{ $item->client?->user?->email ?? '' }}
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <span class="badge {{ $item->client?->type_client == 'ENTREPRISE' ? 'badge-info' : 'badge-secondary' }}">
                                                            {{ $item->client?->type_client }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        {{ count($item->produits) }} produit(s)
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <button type="button" class="btn btn-sm btn-primary btn-voir-produits"
                                                            data-modal-id="modalProduits-{{ $item->client?->id }}"
                                                            title="Voir les produits">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger btn-supprimer-client"
                                                            {{-- Client supprimé : sans le ?? 0, route() ferait tomber la page entière. --}}
                                                            data-url="{{ route('configPrix.supprimerClient', $item->client?->id ?? 0) }}"
                                                            data-nom="{{ $item->client?->display_name }}"
                                                            title="Supprimer tous les prix du client">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                    {{-- /onglet prix --}}

                    {{-- ============================================================
                         ONGLET 5 : CRÉANCES CLIENTS À TERME
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-creance" role="tabpanel"
                        aria-labelledby="tab-creance-tab">

                        <div class="alert alert-info auth-alert mb-4">
                            <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Source :</strong>
                            Feuille « Paramètres » du fichier <em>01_Suivi_Creances_Clients_Terme.xlsx</em>.
                            Pilote la relance automatique et l'alerte des factures à terme.
                        </div>

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="creance">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Délai de relance standard (jours)</label>
                                    <input class="form-control" required type="number" min="1" name="delai_relance_standard"
                                        value="{{ $config->delai_relance_standard ?? 7 }}" />
                                    <small class="text-muted">Nombre de jours avant l'envoi automatique d'une relance après l'échéance.</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Seuil alerte retard (jours)</label>
                                    <input class="form-control" required type="number" min="1" name="seuil_alerte_retard"
                                        value="{{ $config->seuil_alerte_retard ?? 15 }}" />
                                    <small class="text-muted">Au-delà de ce délai, une facture non soldée déclenche une alerte rouge.</small>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 6 : COMPTANT / AGENCE
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-comptant" role="tabpanel"
                        aria-labelledby="tab-comptant-tab">

                        <div class="alert alert-info auth-alert mb-4">
                            <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Source :</strong>
                            Feuille « Paramètres » du fichier <em>02_Suivi_Creances_Clients_Comptant.xlsx</em>.
                            Pilote les délais des commandes payables en agence.
                        </div>

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="comptant">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Délai max de paiement en agence (jours)</label>
                                    <input class="form-control" required type="number" min="1" name="delai_max_paiement_agence"
                                        value="{{ $config->delai_max_paiement_agence ?? 3 }}" />
                                    <small class="text-muted">Délai accordé au client pour payer en agence après création de la commande.</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Délai d'annulation automatique (jours)</label>
                                    <input class="form-control" required type="number" min="1" name="delai_annulation_auto"
                                        value="{{ $config->delai_annulation_auto ?? 7 }}" />
                                    <small class="text-muted">Au-delà de ce délai sans paiement, la commande passe automatiquement en « Annulée ».</small>
                                </div>

                            </div>

                            <p class="text-muted mb-3">
                                <i class="material-icons md-info" style="vertical-align: middle; font-size: 18px;"></i>
                                La liste des agences se gère dans
                                <a href="{{ route('show.agences.index') }}"><strong>Configuration → Agences</strong></a>.
                            </p>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 7 : LIVREURS
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-livreurs" role="tabpanel"
                        aria-labelledby="tab-livreurs-tab">

                        <div class="alert alert-info auth-alert mb-4">
                            <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Source :</strong>
                            Feuille « Paramètres » du fichier <em>04_Suivi_Dettes_Livreurs.xlsx</em>.
                            Cadence des paiements de prestations livreurs.
                        </div>

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="livreurs">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Fréquence de paiement</label>
                                    <select name="frequence_paiement_livreur" class="form-control" required>
                                        @foreach (['Quotidien', 'Hebdomadaire', 'Bimensuel', 'Mensuel'] as $freq)
                                            <option value="{{ $freq }}"
                                                {{ ($config->frequence_paiement_livreur ?? 'Hebdomadaire') === $freq ? 'selected' : '' }}>
                                                {{ $freq }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Cycle de versement aux livreurs.</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Jour de paiement</label>
                                    <select name="jour_paiement_livreur" class="form-control" required>
                                        @foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'] as $jour)
                                            <option value="{{ $jour }}"
                                                {{ ($config->jour_paiement_livreur ?? 'Vendredi') === $jour ? 'selected' : '' }}>
                                                {{ $jour }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Jour de la semaine où le paiement est effectué (pour les fréquences hebdo/bimensuel).</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Forfait de base (FCFA)</label>
                                    <input type="number" name="forfait_base_livreur" class="form-control" min="0" step="1"
                                           value="{{ $config->forfait_base_livreur ?? 0 }}">
                                    <small class="text-muted">Tarif de base appliqué par défaut à chaque nouveau livreur
                                        (modifiable ensuite individuellement sur son profil).</small>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET 8 : APPORTEURS
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-apporteurs" role="tabpanel"
                        aria-labelledby="tab-apporteurs-tab">

                        <div class="alert alert-info auth-alert mb-4">
                            <strong><i class="material-icons md-info" style="vertical-align: middle;"></i> Source :</strong>
                            Feuille « Paramètres » du fichier <em>05_Suivi_Dettes_Apporteurs.xlsx</em>.
                            Règle métier : <em>la commission n'est due que si le client a effectivement payé la commande</em> (comptant ou à terme).
                        </div>

                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <input type="hidden" name="_section" value="apporteurs">
                            <div class="row">

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Taux de commission standard (%)</label>
                                    <input class="form-control" required type="number" step="0.01" min="0" max="100"
                                        name="taux_commission_standard"
                                        value="{{ $config->taux_commission_standard ?? 3 }}" />
                                    <small class="text-muted">Taux par défaut appliqué quand un apporteur n'a pas de taux personnalisé.</small>
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Délai de paiement commission (jours)</label>
                                    <input class="form-control" required type="number" min="1"
                                        name="delai_paiement_commission"
                                        value="{{ $config->delai_paiement_commission ?? 15 }}" />
                                    <small class="text-muted">Délai après encaissement client pour reverser la commission à l'apporteur.</small>
                                </div>

                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Appliquer les changements
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET : TERMES & CONDITIONS (contenu paramétrable)
                         ============================================================ --}}
                    <div class="tab-pane fade" id="tab-termes" role="tabpanel" aria-labelledby="tab-termes-tab">
                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Contenu des Termes &amp; conditions</label>
                                <p class="text-muted small mb-2">
                                    Saisissez le contenu affiché sur la page publique
                                    <a href="{{ route('termesConditions') }}" target="_blank">/termes-et-conditions</a>.
                                    Le HTML est autorisé (titres, paragraphes, listes). Laissez vide pour conserver le contenu par défaut.
                                </p>
                                <textarea name="termes_conditions" class="form-control" rows="18"
                                    style="font-family: monospace;">{{ $config->termes_conditions }}</textarea>
                            </div>
                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Enregistrer les termes &amp; conditions
                            </button>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="tab-entreprise" role="tabpanel" aria-labelledby="tab-entreprise-tab">
                        <form method="post" action="{{ route('show.parametre') }}">
                            @csrf

                            {{-- CES CHAMPS EXISTAIENT EN BASE MAIS N'AVAIENT AUCUN ECRAN.
                                 Les factures et les recus les LISENT depuis toujours ; faute
                                 de formulaire, personne ne pouvait les corriger, et les
                                 valeurs enregistrees s'etaient decalees d'une case. --}}
                            <div class="alert alert-info">
                                <strong>Ces informations s'impriment sur CHAQUE facture et chaque reçu.</strong>
                                Laissées vides, les lignes correspondantes sortent vides chez le client.
                                Le NCC est le plus sensible : il est aussi encodé dans le QR code de la
                                facture normalisée et sert à préfixer son numéro — un NCC faux produit
                                des factures que l'administration rejette.
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Raison sociale</label>
                                    <input class="form-control" type="text" name="raison_sociale"
                                        placeholder="DALAKOUN SARLU"
                                        value="{{ $config->raison_sociale }}" />
                                    <div class="form-text">Le nom legal, tel qu il figure au registre du commerce.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">NCC — n° de compte contribuable</label>
                                    <input class="form-control" type="text" name="ncc"
                                        placeholder="2507546 J"
                                        value="{{ $config->ncc }}" />
                                    <div class="form-text">LE PLUS SENSIBLE : imprime sur la facture, repris en bas a la mention « CC N° », encode dans le QR code que l administration scanne, et utilise pour prefixer le numero de chaque facture normalisee.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Régime d’imposition</label>
                                    <input class="form-control" type="text" name="regime_imposition"
                                        placeholder="RSI — Regime Simplifie d Imposition"
                                        value="{{ $config->regime_imposition }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Centre des impôts de rattachement</label>
                                    <input class="form-control" type="text" name="centre_impots"
                                        placeholder="SAID II Plateaux — Djibi"
                                        value="{{ $config->centre_impots }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">RCCM — registre du commerce</label>
                                    <input class="form-control" type="text" name="rccm"
                                        placeholder="CI-ABJ-03-2025-B13-13523"
                                        value="{{ $config->rccm }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Références bancaires</label>
                                    <input class="form-control" type="text" name="ref_bancaires"
                                        placeholder="Banque, code guichet, n° de compte, cle"
                                        value="{{ $config->ref_bancaires }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adresse du siège</label>
                                    <input class="form-control" type="text" name="adresse_siege"
                                        placeholder="Abidjan, Cocody Angre…"
                                        value="{{ $config->adresse_siege }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Numéro de téléphone</label>
                                    <input class="form-control" type="text" name="telephone"
                                        placeholder="07 00 13 07 98"
                                        value="{{ $config->telephone }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adresse e-mail de l’entreprise</label>
                                    <input class="form-control" type="text" name="email_entreprise"
                                        placeholder="contact@exemple.ci"
                                        value="{{ $config->email_entreprise }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Capital social</label>
                                    <input class="form-control" type="text" name="capital_social"
                                        placeholder="20 000 000 FCFA"
                                        value="{{ $config->capital_social }}" />
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Numéro CNPS (employeur)</label>
                                    <input class="form-control" type="text" name="cnps"
                                        placeholder=""
                                        value="{{ $config->cnps }}" />
                                </div>
                            </div>

                            <button class="btn btn-primary" type="submit">
                                <i class="material-icons md-check align-middle"></i>
                                Enregistrer les informations de l'entreprise
                            </button>
                        </form>
                    </div>

                    {{-- ============================================================
                         ONGLET : JOURNAL D'AUDIT
                         Le corps vit dans un partiel : il n'existe qu'UNE version
                         du journal, et l'ancienne adresse « /audit » y renvoie.
                         ============================================================ --}}
                    @if ($peutVoirAudit ?? false)
                        <div class="tab-pane fade" id="tab-audit" role="tabpanel"
                            aria-labelledby="tab-audit-tab">
                            <h5 class="mb-1">Journal d'audit</h5>
                            <p class="text-muted small">
                                Toutes les opérations d'écriture du back-office : qui a fait
                                quoi, quand, et depuis quelle adresse.
                            </p>

                            {{-- Le formulaire de filtre revient ICI, sur cet onglet :
                                 sans « onglet=audit », filtrer renverrait sur
                                 « Configuration générale » et la recherche
                                 semblerait sans effet. --}}
                            @include('admin.audit._journal', [
                                'actionFiltre' => route('show.parametre'),
                                'champsCaches' => ['onglet' => 'audit'],
                            ])
                        </div>
                    @endif

                </div>
                {{-- /tab-content --}}

            </div>
        </div>
    </section>

    {{-- ============================================================
         Modals "Voir les produits" - placés HORS de <section> et HORS
         de tab-pane pour éviter les conflits de focus / z-index quand
         deux versions de Bootstrap sont chargées simultanément.
         ============================================================ --}}

    {{-- La fenêtre de détail du journal d'audit, pour la même raison : dans le
         panneau d'onglet et ouverte par « data-bs-toggle », elle tremblait. --}}
    @if ($peutVoirAudit ?? false)
        @include('admin.audit._fenetre-detail')
    @endif
    @foreach ($clientsAvecPrix as $item)
        <div class="modal fade param-modal param-modal-produits" id="modalProduits-{{ $item->client?->id }}" tabindex="-1"
            role="dialog" aria-labelledby="modalProduitsLabel-{{ $item->client?->id }}" aria-hidden="true"
            style="display:none;">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #1c57a3;">
                        <h5 class="modal-title" style="color: white;"
                            id="modalProduitsLabel-{{ $item->client?->id }}">
                            Produits & Prix personnalisés — {{ $item->client?->display_name }}
                        </h5>
                        <button type="button" class="close text-white btn-close-modal" aria-label="Fermer"
                            style="background:transparent;border:0;color:#fff;font-size:1.5rem;line-height:1;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Prix normal</th>
                                    <th>Prix personnalisé</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($item->produits as $p)
                                    <tr>
                                        <td>{{ $p->produit?->nom }}</td>
                                        <td>{{ number_format($prixFournisseur[$p->produit?->id] ?? $p->produit?->prix_moyen, 0, ',', ' ') }} FCFA</td>
                                        <td><strong>{{ number_format($p->prix, 0, ',', ' ') }} FCFA</strong></td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-danger btn-supprimer-produit"
                                                data-url="{{ route('configPrix.supprimerProduit', $p->id) }}"
                                                data-nom="{{ $p->produit?->nom }}"
                                                title="Supprimer ce prix">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-close-modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <style>
        /* ===== DE LA LARGEUR POUR LES TABLEAUX =====
           Le thème pose 3 % de retrait horizontal sur chaque « .content-main ».
           Cette page en ajoute un TROISIÈME : la carte perdait ainsi près de
           160 px de largeur utile, de quoi serrer les six colonnes du journal
           d'audit et ses commandes (recherche, pagination). */
        .main-wrap > .content-main > .content-main > .content-main {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Le panneau d'onglet ajoutait encore 12 px de chaque côté. */
        #parametreTabsContent > .tab-pane#tab-audit {
            padding-left: 0;
            padding-right: 0;
        }

        /* Sur grand écran, le retrait de la carte ne sert plus la lisibilité :
           il ne fait que rogner le tableau. On le resserre. */
        @media (min-width: 1200px) {
            .content-main .card > .card-body { padding-left: 14px; padding-right: 14px; }
        }

        /* Les commandes du tableau (recherche, pagination) se serrent sur une
           seule ligne quand la place manque : on les laisse passer à la ligne
           plutôt que de les tronquer. */
        #journalAudit_wrapper .dt-layout-row { flex-wrap: wrap; row-gap: .5rem; }
    </style>
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/select2.min.css') }}">
    <style>
        /* ===== Onglets sur une seule ligne, défilables ===== */
        .onglets-defilants {
            position: relative;
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 2px solid #e7ecf3;
        }

        /* La piste : une seule ligne, qui défile. flex-wrap est forcé car
           .nav de Bootstrap l'impose à « wrap ». */
        .onglets-defilants .nav-tabs {
            flex-wrap: nowrap !important;
            overflow-x: auto;
            overflow-y: hidden;
            border-bottom: 0;
            margin-bottom: -2px;
            /* Adoucissement confié au CSS : le script se contente de poser
               scrollLeft. Si le navigateur ne sait pas l'animer, le déplacement
               se fait d'un coup — la barre défile dans tous les cas. */
            /* Barre de défilement discrète : elle reste accessible à la souris
               et au pavé tactile, mais n'alourdit pas la barre d'onglets. */
            scrollbar-width: thin;
            scrollbar-color: #c7d3e2 transparent;
            -webkit-overflow-scrolling: touch;
        }
        .onglets-defilants .nav-tabs::-webkit-scrollbar { height: 4px; }
        .onglets-defilants .nav-tabs::-webkit-scrollbar-track { background: transparent; }
        .onglets-defilants .nav-tabs::-webkit-scrollbar-thumb { background: #c7d3e2; border-radius: 4px; }

        /* Respecte le réglage système « animations réduites ». */
        @media (prefers-reduced-motion: no-preference) {
            .onglets-defilants .nav-tabs { scroll-behavior: smooth; }
        }

        .onglets-defilants .nav-item { flex: 0 0 auto; }

        .onglets-defilants .nav-tabs .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            border: 0;
            border-bottom: 3px solid transparent;
            border-radius: 8px 8px 0 0;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            background: transparent;
            transition: color .18s ease, background-color .18s ease, border-color .18s ease;
        }
        .onglets-defilants .nav-tabs .nav-link .material-icons { font-size: 19px; }

        .onglets-defilants .nav-tabs .nav-link:hover {
            color: #1c57a3;
            background: #f2f6fb;
        }
        .onglets-defilants .nav-tabs .nav-link.active {
            color: #1c57a3;
            background: transparent;
            border-bottom-color: #1c57a3;
        }
        .onglets-defilants .nav-tabs .nav-link:focus-visible {
            outline: 2px solid #1c57a3;
            outline-offset: -2px;
        }

        /* Flèches de défilement, masquées quand il n'y a rien de plus de ce côté. */
        .onglets-defilants__fleche {
            flex: 0 0 auto;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #dbe3ec;
            border-radius: 50%;
            background: #fff;
            color: #1c57a3;
            cursor: pointer;
            z-index: 2;
            transition: background-color .15s ease, box-shadow .15s ease;
        }
        .onglets-defilants__fleche:hover:not(:disabled) { background: #eef4fb; box-shadow: 0 2px 6px rgba(16,42,72,.12); }
        .onglets-defilants__fleche:disabled { opacity: .35; cursor: default; }
        .onglets-defilants__fleche[hidden] { display: none; }
        .onglets-defilants__fleche--gauche { margin-right: 6px; }
        .onglets-defilants__fleche--droite { margin-left: 6px; }

        /* Fondu aux extrémités : indique qu'il reste des onglets hors champ.
           pointer-events:none pour ne pas voler le clic à l'onglet du dessous. */
        .onglets-defilants::before,
        .onglets-defilants::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 2px;
            width: 28px;
            pointer-events: none;
            opacity: 0;
            transition: opacity .18s ease;
            z-index: 1;
        }
        .onglets-defilants::before {
            left: 38px;
            background: linear-gradient(90deg, #fff 30%, rgba(255,255,255,0));
        }
        .onglets-defilants::after {
            right: 38px;
            background: linear-gradient(270deg, #fff 30%, rgba(255,255,255,0));
        }
        .onglets-defilants.a-gauche::before { opacity: 1; }
        .onglets-defilants.a-droite::after  { opacity: 1; }

        /* Le contenu des onglets s'appuie sur des grilles Bootstrap. Une « .row »
           porte une gouttière négative de 12px de chaque côté, qui suppose un
           parent lui offrant 12px de retrait — ce que « .tab-pane » ne fait pas.
           Le contenu débordait donc de 12px hors de son panneau, ce qui ouvrait
           un défilement horizontal à l'intérieur de l'onglet : mesuré à 320px de
           contenu pour 308px de place sur un écran de 375px, où il n'existe
           aucune marge à sacrifier. Sélecteur limité aux panneaux de PREMIER
           niveau : les onglets imbriqués gardent leur propre mise en page. */
        #parametreTabsContent > .tab-pane {
            padding-left: 12px;
            padding-right: 12px;
        }

        @media (max-width: 575px) {
            /* Les boutons du thème sont en « nowrap » : un libellé long, comme
               « Enregistrer les termes & conditions », ne peut pas se replier et
               dépasse alors la largeur de l'écran. Sur mobile on l'autorise à
               passer à la ligne plutôt que de rogner la page. */
            #parametreTabsContent .btn { white-space: normal; }

            .onglets-defilants .nav-tabs .nav-link { padding: 10px 13px; font-size: 13px; }
            .onglets-defilants__fleche { width: 28px; height: 28px; }
            .onglets-defilants::before { left: 34px; }
            .onglets-defilants::after  { right: 34px; }
        }
    </style>
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
    <script type="text/javascript">
        $(document).ready(function() {

            // ===== ONGLET DEMANDÉ PAR L'ADRESSE (« ?onglet=audit ») =====
            //
            // Sans cela, filtrer le journal ramenait sur « Configuration
            // générale » : le formulaire recharge la page, et Bootstrap
            // rouvre toujours le premier onglet. L'ancienne adresse « /audit »
            // s'appuie sur le même mécanisme.
            (function () {
                var demande = @js($ongletDemande ?? null);
                if (!demande) return;

                var bouton = document.getElementById('tab-' + demande + '-tab');
                if (!bouton) return;

                // On clique le bouton plutôt que d'ajouter les classes à la
                // main : c'est ainsi que les deux versions de Bootstrap
                // présentes sur cet écran s'accordent.
                bouton.click();
                bouton.scrollIntoView({ block: 'nearest', inline: 'center' });
            })();

            // ===== JOURNAL D'AUDIT =====
            (function () {
                var $table = $('#journalAudit');
                if (!$table.length) return;

                // Garde-fou : DataTables lève « Requested unknown parameter »
                // quand le tableau ne porte que la ligne « aucun résultat »,
                // dont le colspan ne correspond à aucune colonne déclarée.
                if ($table.find('tbody tr').length > 0 &&
                    $table.find('tbody tr td[colspan]').length === 0) {
                    $table.DataTable({
                        columnDefs: [{ targets: '_all', defaultContent: '-' }],
                        language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                        // Le tri vient du serveur (du plus récent au plus
                        // ancien) : on ne le rejoue pas ici, la date étant au
                        // format jj/mm/aaaa que DataTables trierait comme du
                        // texte.
                        order: [],
                        pageLength: 25,
                    });

                    // Un tableau construit dans un onglet MASQUÉ ne connaît pas
                    // sa largeur : ses colonnes se recalent de travers à
                    // l'ouverture. On les recalcule au moment où l'onglet
                    // s'affiche.
                    $('#tab-audit-tab').on('shown.bs.tab', function () {
                        $table.DataTable().columns.adjust();
                    });
                }

                $(document).on('click', '.voir-audit', function() {
                    var $b = $(this);

                    $('#auditRecit').text($b.attr('data-recit') || '-');

                    var $corps = $('#auditElements').empty();
                    var elements = [];

                    try {
                        elements = JSON.parse($b.attr('data-elements') || '[]');
                    } catch (e) {
                        elements = [];
                    }

                    if (!elements.length) {
                        $corps.append(
                            $('<tr>').append(
                                $('<td>').addClass('text-muted')
                                    .text('Aucune information supplementaire n a ete enregistree.')
                            )
                        );
                    } else {
                        elements.forEach(function (el) {
                            // .text() et non .html() : une valeur saisie par un
                            // utilisateur ne doit jamais etre interpretee comme
                            // du balisage dans un ecran d audit.
                            $corps.append(
                                $('<tr>')
                                    .append($('<th>').addClass('text-muted fw-normal')
                                        .css('width', '40%').text(el.libelle))
                                    .append($('<td>').addClass('fw-bold').text(el.valeur))
                            );
                        });
                    }

                    $('#auditMethode').text($b.data('methode') || '-');
                    $('#auditUrl').text($b.data('url') || '-');
                    $('#auditRoute').text($b.data('route') || '-');
                    $('#auditAgent').text($b.data('agent') || '-');

                    var donnees = $b.attr('data-donnees');
                    $('#auditDonnees').text(donnees && donnees.length ? donnees : 'Aucune donnée enregistrée.');

                    // OUVERTURE PAR LE MÉCANISME MANUEL DE CET ÉCRAN.
                    //
                    // Le bouton portait « data-bs-toggle="modal" ». Bootstrap 4
                    // et 5 étant chargés ensemble, les deux répondaient : deux
                    // voiles superposés et deux pièges à focus concurrents, et
                    // la fenêtre tremblait. C'est le défaut déjà rencontré sur
                    // les fenêtres « Voir les produits », et la même réponse.
                    openParamModal($b.data('modal-id'));
                });
            })();

            // ===== Barre d'onglets défilante =====
            (function () {
                var zone  = document.getElementById('ongletsParametre');
                if (!zone) return;
                var piste = zone.querySelector('.nav-tabs');
                var gauche = zone.querySelector('.onglets-defilants__fleche--gauche');
                var droite = zone.querySelector('.onglets-defilants__fleche--droite');

                // Les deux flèches apparaissent ensemble dès que la barre déborde,
                // et celle qui ne mène nulle part est simplement désactivée : les
                // faire disparaître une à une décalerait la barre à chaque bout de
                // course. Le fondu, lui, ne s'affiche que du côté encore masqué.
                // La marge de 2px absorbe les arrondis de sous-pixel, qui
                // laissaient sinon une flèche active sans rien à atteindre.
                function actualiser() {
                    var debordement = piste.scrollWidth - piste.clientWidth;
                    var deborde    = debordement > 2;
                    var aGauche    = deborde && piste.scrollLeft > 2;
                    var aDroite    = deborde && piste.scrollLeft < debordement - 2;

                    zone.classList.toggle('a-gauche', aGauche);
                    zone.classList.toggle('a-droite', aDroite);

                    gauche.hidden = droite.hidden = !deborde;
                    gauche.disabled = !aGauche;
                    droite.disabled = !aDroite;
                }

                // Déplacement DIRECT de scrollLeft, sans animation en JavaScript.
                // L'adoucissement est confié au CSS (scroll-behavior), qui s'en
                // charge quand le navigateur sait le faire et ne coûte rien sinon.
                //
                // Ni « behavior: 'smooth' » ni une boucle requestAnimationFrame :
                // les deux dépendent du rendu des images. Dans un onglet qui ne
                // compose pas — arrière-plan, économie d'énergie — les flèches
                // restaient alors sans aucun effet, ce que la vérification a montré.
                function allerA(cible) {
                    var max = piste.scrollWidth - piste.clientWidth;
                    piste.scrollLeft = Math.max(0, Math.min(cible, max));
                    actualiser();
                }

                function defiler(sens) {
                    allerA(piste.scrollLeft + sens * Math.max(160, piste.clientWidth * 0.6));
                }

                // Centre un onglet dans la piste. Position calculée à partir des
                // rectangles plutôt qu'avec scrollIntoView : celui-ci fait aussi
                // défiler les ancêtres, donc la page entière verticalement.
                // offsetLeft est écarté pour la même raison de fiabilité : il se
                // mesure depuis le premier ancêtre positionné, qui n'est pas
                // forcément la piste.
                function centrer(onglet) {
                    if (!onglet) return;
                    var ro = onglet.getBoundingClientRect();
                    var rp = piste.getBoundingClientRect();
                    allerA(piste.scrollLeft + (ro.left - rp.left) - (piste.clientWidth - ro.width) / 2);
                }

                gauche.addEventListener('click', function () { defiler(-1); });
                droite.addEventListener('click', function () { defiler(1); });
                piste.addEventListener('scroll', actualiser, { passive: true });
                window.addEventListener('resize', actualiser);

                $(piste).on('click', '.nav-link', function () { centrer(this); });

                // L'onglet actif est amené dans le champ de vision : après un
                // enregistrement, la page revient sur un onglet qui pouvait se
                // trouver hors écran, donnant l'impression d'avoir tout perdu.
                actualiser();
                setTimeout(function () { centrer(piste.querySelector('.nav-link.active')); }, 60);
            })();

            // ===== Onglet à ouvrir au chargement (depuis URL hash ou query) =====
            var hash = window.location.hash;
            if (hash && $('a[href="' + hash + '"], button[data-bs-target="' + hash + '"], button[data-target="' + hash + '"]').length) {
                $('button[data-bs-target="' + hash + '"], button[data-target="' + hash + '"]').trigger('click');
            }

            // Met à jour le hash quand on change d'onglet
            $('#parametreTabs button[data-toggle="tab"], #parametreTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var target = $(e.target).attr('data-bs-target') || $(e.target).attr('data-target');
                if (target) {
                    history.replaceState(null, null, target);
                }
            });

            // Select2
            if ($.fn.select2) {
                $('#client_id').select2({
                    placeholder: '-- Sélectionner un client --',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#tab-prix')
                });
                $('#produit_id').select2({
                    placeholder: '-- Sélectionner un produit --',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#tab-prix')
                });
            }

            // DataTable - initialiser uniquement quand l'onglet est visible
            var dtPrix = null;
            $('button[data-bs-target="#tab-prix"], button[data-target="#tab-prix"]').on('shown.bs.tab', function () {
                if (!dtPrix) {
                    dtPrix = $('#listePrixPersonnalises').DataTable({
                        language: {
                            url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}',
                        },
                        order: [],
                    });
                } else {
                    dtPrix.columns.adjust();
                }
            });

            // SweetAlert2 - messages session
            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Succès',
                    text: '{{ session('success') }}',
                    confirmButtonColor: '#1c57a3'
                });
            @endif

            @if (session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: '{{ session('error') }}',
                    confirmButtonColor: '#1c57a3'
                });
            @endif

            // ===== Gestion manuelle des modals "Voir les produits" =====
            // (Bootstrap 4 et 5 sont chargés simultanément dans le projet, ce
            // qui crée un conflit de focus quand on utilise data-toggle/data-bs-toggle.
            // On pilote donc l'ouverture/fermeture en JS pur.)
            function openParamModal(modalId) {
                var $modal = $('#' + modalId);
                if (!$modal.length) return;

                // Backdrop manuel pour éviter le double-backdrop BS4/BS5.
                if ($('#param-modal-backdrop').length === 0) {
                    $('body').append('<div id="param-modal-backdrop" class="modal-backdrop fade show" style="z-index:1040;"></div>');
                }

                $('body').addClass('modal-open').css('overflow', 'hidden');
                $modal.css({
                    display: 'block',
                    'padding-right': '0',
                    'z-index': 1050
                }).attr('aria-hidden', 'false').addClass('show');
            }

            function closeParamModal() {
                $('.param-modal').removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
                $('#param-modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
            }

            // Bouton "Voir les produits"
            $(document).on('click', '.btn-voir-produits', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var modalId = $(this).data('modal-id');
                openParamModal(modalId);
            });

            // Boutons de fermeture (croix + bouton "Fermer")
            $(document).on('click', '.btn-close-modal', function (e) {
                e.preventDefault();
                closeParamModal();
            });

            // Click hors du modal-content -> fermer
            $(document).on('click', '.param-modal', function (e) {
                if ($(e.target).is('.param-modal')) {
                    closeParamModal();
                }
            });

            // ESC -> fermer
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('.param-modal.show').length) {
                    closeParamModal();
                }
            });

            // Suppression d'un prix produit
            $(document).on('click', '.btn-supprimer-produit', function() {
                var url = $(this).data('url');
                var nom = $(this).data('nom');
                Swal.fire({
                    title: 'Confirmer la suppression',
                    text: 'Supprimer le prix personnalisé du produit "' + nom + '" ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Oui, supprimer',
                    cancelButtonText: 'Annuler'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });

            // Suppression de tous les prix d'un client
            $(document).on('click', '.btn-supprimer-client', function() {
                var url = $(this).data('url');
                var nom = $(this).data('nom');
                Swal.fire({
                    title: 'Confirmer la suppression',
                    text: 'Supprimer tous les prix personnalisés du client "' + nom + '" ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Oui, tout supprimer',
                    cancelButtonText: 'Annuler'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        window.location.href = url;
                    }
                });
            });
        });
    </script>
@endsection
