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

                {{-- ===== ONGLETS ===== --}}
                <ul class="nav nav-tabs mb-4" id="parametreTabs" role="tablist">
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
                            <i class="material-icons md-handshake align-middle"></i>
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
                </ul>

                {{-- ===== CONTENU DES ONGLETS ===== --}}
                <div class="tab-content" id="parametreTabsContent">

                    {{-- ============================================================
                         ONGLET 1 : CONFIGURATION GÉNÉRALE
                         ============================================================ --}}
                    <div class="tab-pane fade show active" id="tab-general" role="tabpanel"
                        aria-labelledby="tab-general-tab">

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

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Prix par km ({{ $config->devise }})</label>
                                    <input class="form-control" name="prixKm" required value="{{ $config->prixKm }}" />
                                </div>

                                <div class="col-lg-4 mb-3">
                                    <label class="form-label">Coût livraison minimum ({{ $config->devise }})</label>
                                    <input class="form-control" name="cout_livraison_min" required
                                        value="{{ $config->cout_livraison_min }}" />
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
                                                        {{ $client->display_name }} - {{ $client->user->email ?? '' }} ({{ $client->type_client }})
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
                                                        {{ $item->client->display_name }}
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        {{ $item->client->user->email ?? '' }}
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <span class="badge {{ $item->client->type_client == 'ENTREPRISE' ? 'badge-info' : 'badge-secondary' }}">
                                                            {{ $item->client->type_client }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        {{ count($item->produits) }} produit(s)
                                                    </td>
                                                    <td class="text-center align-middle">
                                                        <button type="button" class="btn btn-sm btn-primary btn-voir-produits"
                                                            data-modal-id="modalProduits-{{ $item->client->id }}"
                                                            title="Voir les produits">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger btn-supprimer-client"
                                                            data-url="{{ route('configPrix.supprimerClient', $item->client->id) }}"
                                                            data-nom="{{ $item->client->display_name }}"
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
                                <i class="material-icons md-tip" style="vertical-align: middle; font-size: 18px;"></i>
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
    @foreach ($clientsAvecPrix as $item)
        <div class="modal fade param-modal-produits" id="modalProduits-{{ $item->client->id }}" tabindex="-1"
            role="dialog" aria-labelledby="modalProduitsLabel-{{ $item->client->id }}" aria-hidden="true"
            style="display:none;">
            <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background-color: #1c57a3;">
                        <h5 class="modal-title" style="color: white;"
                            id="modalProduitsLabel-{{ $item->client->id }}">
                            Produits & Prix personnalisés — {{ $item->client->display_name }}
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
                                        <td>{{ $p->produit->nom }}</td>
                                        <td>{{ number_format($prixFournisseur[$p->produit->id] ?? $p->produit->prix_moyen, 0, ',', ' ') }} FCFA</td>
                                        <td><strong>{{ number_format($p->prix, 0, ',', ' ') }} FCFA</strong></td>
                                        <td class="text-center">
                                            <button type="button"
                                                class="btn btn-sm btn-danger btn-supprimer-produit"
                                                data-url="{{ route('configPrix.supprimerProduit', $p->id) }}"
                                                data-nom="{{ $p->produit->nom }}"
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
    <link rel="stylesheet" href="{{ asset('backend/assets/css/vendors/select2.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script src="{{ asset('backend/assets/js/vendors/select2.min.js') }}"></script>
    <script type="text/javascript">
        $(document).ready(function() {

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
                $('.param-modal-produits').removeClass('show').css('display', 'none').attr('aria-hidden', 'true');
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
            $(document).on('click', '.param-modal-produits', function (e) {
                if ($(e.target).is('.param-modal-produits')) {
                    closeParamModal();
                }
            });

            // ESC -> fermer
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('.param-modal-produits.show').length) {
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
