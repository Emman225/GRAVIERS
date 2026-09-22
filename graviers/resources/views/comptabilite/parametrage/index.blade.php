@extends('layout.main')
@section('title', 'Paramétrage comptable')

@php
    use App\Models\CompteComptable;
    use App\Models\JournalComptable;

    $titresOnglets = [
        'comptes'    => 'Plan de comptes',
        'familles'   => 'Grandes familles',
        'produits'   => 'Produits',
        'rubriques'  => 'Rubriques de facture',
        'tiers'      => 'Comptes tiers',
        'journaux'   => 'Journaux et règlements',
        'reglages'   => 'Réglages',
        'controle'   => 'Contrôle',
        'historique' => 'Historique',
    ];
    $nombreAnomalies = count($anomalies);
    $journauxDeTresorerie = $journaux->filter(fn ($j) => $j->statut && $j->estDeTresorerie());
    $analytiquesPourJs = $comptesAnalytiques->map(fn ($c) => ['id' => $c->id, 'texte' => $c->designation])->values();
@endphp

@section('contenu')
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">Paramétrage <span class="dash-welcome-name">comptable</span></h2>
                <p class="dash-welcome-subtitle">
                    Le compte général vient de la grande famille, le compte analytique du produit ;
                    chaque rubrique d'une facture, chaque client et chaque mode de règlement a son compte.
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ===== ÉTAT DU PARAMÉTRAGE ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-account_balance"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Comptes généraux</div>
                    <div class="kpi-card-value">{{ $comptes->where('nature', CompteComptable::NATURE_GENERAL)->count() }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-info">
                <div class="kpi-card-icon"><i class="material-icons md-label"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Comptes analytiques</div>
                    <div class="kpi-card-value">{{ $comptes->where('nature', CompteComptable::NATURE_ANALYTIQUE)->count() }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card kpi-card-success">
                <div class="kpi-card-icon"><i class="material-icons md-category"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Grandes familles</div>
                    <div class="kpi-card-value">{{ $familles->count() }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card {{ $nombreAnomalies ? 'kpi-card-warning' : 'kpi-card-success' }}">
                <div class="kpi-card-icon"><i class="material-icons {{ $nombreAnomalies ? 'md-error' : 'md-check_circle' }}"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Points à régler</div>
                    <div class="kpi-card-value" id="nombreAnomalies">{{ $nombreAnomalies }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-4">
        <div class="card-body">
            <ul class="nav nav-tabs mb-4 onglets-une-ligne" id="ongletsComptables" role="tablist">
                @foreach ($titresOnglets as $cle => $titre)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $onglet === $cle ? 'active' : '' }}" id="onglet-{{ $cle }}-bouton"
                                data-bs-toggle="tab" data-bs-target="#onglet-{{ $cle }}" data-onglet="{{ $cle }}" type="button" role="tab">
                            {{ $titre }}
                            @if ($cle === 'controle' && $nombreAnomalies)
                                <span class="badge bg-danger ms-1">{{ $nombreAnomalies }}</span>
                            @elseif (($anomaliesParOnglet[$cle] ?? 0) > 0)
                                <span class="badge bg-warning text-dark ms-1">{{ $anomaliesParOnglet[$cle] }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">

                {{-- ============================================================ PLAN DE COMPTES --}}
                <div class="tab-pane fade {{ $onglet === 'comptes' ? 'show active' : '' }}" id="onglet-comptes" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <p class="text-muted mb-0">
                            Comptes généraux sur {{ $longueur }} chiffres. Un compte déjà employé ne se supprime pas : il se désactive.
                        </p>
                        <div class="d-flex gap-2">
                            <a href="{{ route('show.comptabilite.comptes.create', ['nature' => CompteComptable::NATURE_GENERAL]) }}" class="btn btn-primary btn-sm">
                                <i class="material-icons md-plus"></i> Nouveau compte général
                            </a>
                            <a href="{{ route('show.comptabilite.comptes.create', ['nature' => CompteComptable::NATURE_ANALYTIQUE]) }}" class="btn btn-primary btn-sm">
                                <i class="material-icons md-plus"></i> Nouveau compte analytique
                            </a>
                        </div>
                    </div>
                    <x-export-buttons table-id="tableComptes" filename="plan-de-comptes" title="Plan de comptes" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0 js-table" id="tableComptes">
                            <thead>
                                <tr>
                                    <th>Nature</th>
                                    <th>Numéro</th>
                                    <th>Libellé</th>
                                    <th class="text-center">État</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($comptes as $compte)
                                    <tr>
                                        <td>{{ CompteComptable::NATURES[$compte->nature] ?? $compte->nature }}</td>
                                        <td><strong>{{ $compte->numero }}</strong></td>
                                        <td>{{ $compte->libelle }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $compte->statut ? 'bg-success' : 'bg-secondary' }}">{{ $compte->statut ? 'Actif' : 'Désactivé' }}</span>
                                        </td>
                                        <td class="text-nowrap text-end">
                                            <a href="{{ route('show.comptabilite.comptes.edit', $compte) }}" class="btn btn-sm btn-primary rounded" title="Modifier le compte">
                                                <i class="material-icons md-edit"></i>
                                            </a>
                                            <form action="{{ route('show.comptabilite.comptes.basculer', $compte) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm rounded {{ $compte->statut ? 'btn-warning' : 'btn-success' }}"
                                                        title="{{ $compte->statut ? 'Désactiver le compte' : 'Activer le compte' }}">
                                                    <i class="material-icons {{ $compte->statut ? 'md-block' : 'md-check_circle' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('show.comptabilite.comptes.destroy', $compte) }}" method="POST" class="d-inline js-delete-form"
                                                  data-item-name="le compte {{ $compte->numero }}"
                                                  data-confirm-text="Un compte employé par une famille, un produit, une rubrique, un journal ou une écriture ne sera pas supprimé.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger rounded" title="Supprimer le compte">
                                                    <i class="material-icons md-delete"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">Aucun compte enregistré : commencez par créer les comptes généraux fournis par le comptable.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ============================================================ GRANDES FAMILLES --}}
                <div class="tab-pane fade {{ $onglet === 'familles' ? 'show active' : '' }}" id="onglet-familles" role="tabpanel">
                    <p class="text-muted">
                        Les grandes familles sont celles du catalogue : une famille ajoutée au catalogue apparaît ici aussitôt, une famille retirée disparaît.
                        Elles se créent et se modifient dans <a href="{{ route('product.category') }}">Produits › Categories</a> ; ici, on enregistre le compte général en face de chacune.
                    </p>
                    <form method="POST" action="{{ route('show.comptabilite.familles.update') }}" class="js-seulement-les-changements">
                        @csrf
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-3">
                                <thead>
                                    <tr>
                                        <th>Grande famille</th>
                                        <th class="text-center">Produits rattachés</th>
                                        <th style="min-width:320px;">Compte général</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($familles as $famille)
                                        <tr>
                                            <td><strong>{{ $famille->nom }}</strong></td>
                                            <td class="text-center">{{ $famille->nombre_produits_comptables }}</td>
                                            <td>
                                                <select name="compte[{{ $famille->id }}]" id="famille-compte-{{ $famille->id }}" class="form-select form-select-sm"
                                                        data-initial="{{ $famille->compte_comptable_id }}">
                                                    <option value="">— À renseigner —</option>
                                                    @if ($famille->compte_general && !$comptesGeneraux->contains('id', $famille->compte_general->id))
                                                        <option value="{{ $famille->compte_general->id }}" selected disabled>{{ $famille->compte_general->designation }} (désactivé ou supprimé)</option>
                                                    @endif
                                                    @foreach ($comptesGeneraux as $compte)
                                                        <option value="{{ $compte->id }}" {{ (int) $famille->compte_comptable_id === (int) $compte->id ? 'selected' : '' }}>{{ $compte->designation }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">Aucune catégorie active dans le catalogue.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les comptes des familles</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ PRODUITS --}}
                <div class="tab-pane fade {{ $onglet === 'produits' ? 'show active' : '' }}" id="onglet-produits" role="tabpanel">
                    <p class="text-muted">
                        Un produit n'a qu'une grande famille comptable — donc un seul compte général —, même s'il est rangé dans plusieurs catégories du catalogue, et un compte analytique qui lui est propre.
                    </p>

                    <div class="row g-3 mb-4">
                        <div class="col-lg-5">
                            <div class="border rounded p-3 h-100">
                                <h6 class="form-label fw-bold mb-2">Par catégorie, en un clic</h6>
                                <form method="POST" action="{{ route('show.comptabilite.produits.enUnClic') }}" class="row g-2 align-items-end">
                                    @csrf
                                    <input type="hidden" name="action" value="categorie">
                                    <div class="col-12">
                                        <label class="form-label" for="clic-categorie">Rattacher tous les produits de la catégorie</label>
                                        <select name="categorie_id" id="clic-categorie" class="form-select form-select-sm" required>
                                            <option value="">— Choisir —</option>
                                            @foreach ($familles as $famille)
                                                <option value="{{ $famille->id }}">{{ $famille->nom }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remplacer" value="1" id="clic-remplacer">
                                            <label class="form-check-label" for="clic-remplacer">Remplacer aussi la famille des produits qui en ont déjà une</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-sm btn-primary">Rattacher</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="border rounded p-3 h-100">
                                <h6 class="form-label fw-bold mb-2">Pour tous les produits restants</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('show.comptabilite.produits.enUnClic') }}" class="js-confirmer"
                                          data-confirm-title="Proposer les grandes familles ?"
                                          data-confirm-text="Chaque produit sans grande famille recevra sa première catégorie du catalogue, dans l'ordre alphabétique. Les produits déjà réglés ne changent pas.">
                                        @csrf
                                        <input type="hidden" name="action" value="familles">
                                        <button type="submit" class="btn btn-sm btn-primary">Proposer la grande famille des produits qui n'en ont pas</button>
                                    </form>
                                    <form method="POST" action="{{ route('show.comptabilite.produits.enUnClic') }}" class="js-confirmer"
                                          data-confirm-title="Créer les comptes analytiques ?"
                                          data-confirm-text="Un compte analytique sera créé pour chaque produit qui n'en a pas, d'après la référence du produit. Vous pourrez ensuite en modifier le numéro.">
                                        @csrf
                                        <input type="hidden" name="action" value="analytiques">
                                        <button type="submit" class="btn btn-sm btn-primary">Créer les comptes analytiques manquants</button>
                                    </form>
                                </div>
                                <small class="text-muted d-block mt-2">Ces deux boutons ne touchent jamais à un réglage déjà fait.</small>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('show.comptabilite.produits.update') }}" class="js-seulement-les-changements" data-table="#tableProduits">
                        @csrf
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-3 js-table-formulaire" id="tableProduits">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Référence</th>
                                        <th>Activité</th>
                                        <th>Catégories du catalogue</th>
                                        <th style="min-width:200px;">Grande famille comptable</th>
                                        <th style="min-width:240px;">Compte analytique</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($produits as $produit)
                                        @php $analytique = $comptes->firstWhere('id', $produit->compte_analytique_id); @endphp
                                        <tr>
                                            <td><strong>{{ $produit->nom }}</strong></td>
                                            <td>{{ $produit->reference ?: '-' }}</td>
                                            <td>{{ $produit->type_affaire === \Help::$LOCATION ? 'Location' : 'Vente' }}</td>
                                            <td><small>{{ $produit->categories->pluck('nom')->implode(', ') ?: '-' }}</small></td>
                                            <td data-order="{{ $produit->categorie_comptable_id ? 1 : 0 }}">
                                                <select name="famille[{{ $produit->id }}]" id="produit-famille-{{ $produit->id }}" class="form-select form-select-sm"
                                                        data-initial="{{ $produit->categorie_comptable_id }}">
                                                    <option value="">— À renseigner —</option>
                                                    @foreach ($familles as $famille)
                                                        <option value="{{ $famille->id }}" {{ (int) $produit->categorie_comptable_id === (int) $famille->id ? 'selected' : '' }}>{{ $famille->nom }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td data-order="{{ $produit->compte_analytique_id ? 1 : 0 }}">
                                                {{-- La liste complète n'est versée qu'à l'ouverture : 200 produits × 200 comptes alourdiraient la page. --}}
                                                <select name="analytique[{{ $produit->id }}]" id="produit-analytique-{{ $produit->id }}" class="form-select form-select-sm js-analytique"
                                                        data-initial="{{ $produit->compte_analytique_id }}">
                                                    <option value="">— À renseigner —</option>
                                                    @if ($analytique)
                                                        <option value="{{ $analytique->id }}" selected>{{ $analytique->designation }}{{ $analytique->statut ? '' : ' (désactivé)' }}</option>
                                                    @endif
                                                </select>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted">Aucun produit actif.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les produits</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ RUBRIQUES --}}
                <div class="tab-pane fade {{ $onglet === 'rubriques' ? 'show active' : '' }}" id="onglet-rubriques" role="tabpanel">
                    <p class="text-muted">Les grandes rubriques d'une facture, hors produits, puis celles des règlements (avances, fournisseurs, livreurs, apporteurs, cautions), chacune avec son compte.</p>
                    <form method="POST" action="{{ route('show.comptabilite.rubriques.update') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-3">
                                <thead>
                                    <tr>
                                        <th>Rubrique</th>
                                        <th>Rôle dans l'écriture</th>
                                        <th style="min-width:260px;">Compte général</th>
                                        <th style="min-width:220px;">Compte analytique</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rubriques as $rubrique)
                                        <tr>
                                            <td>
                                                <strong>{{ $rubrique->libelle }}</strong>
                                                @if ($rubrique->estFacultative()) <span class="badge bg-secondary ms-1">Facultatif</span> @endif
                                            </td>
                                            <td class="td-texte-long"><small>{{ $rubrique->role }}</small></td>
                                            <td>
                                                <select name="compte[{{ $rubrique->code }}]" id="rubrique-compte-{{ $rubrique->code }}" class="form-select form-select-sm">
                                                    <option value="">— À renseigner —</option>
                                                    @foreach ($comptesGeneraux as $compte)
                                                        <option value="{{ $compte->id }}" {{ (int) $rubrique->compte_comptable_id === (int) $compte->id ? 'selected' : '' }}>{{ $compte->designation }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                @if ($rubrique->accepteUnAnalytique())
                                                    <select name="analytique[{{ $rubrique->code }}]" id="rubrique-analytique-{{ $rubrique->code }}" class="form-select form-select-sm">
                                                        <option value="">— Aucun —</option>
                                                        @foreach ($comptesAnalytiques as $compte)
                                                            <option value="{{ $compte->id }}" {{ (int) $rubrique->compte_analytique_id === (int) $compte->id ? 'selected' : '' }}>{{ $compte->designation }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les rubriques</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ COMPTES TIERS --}}
                <div class="tab-pane fade {{ $onglet === 'tiers' ? 'show active' : '' }}" id="onglet-tiers" role="tabpanel">
                    <p class="text-muted">
                        Chaque client a son compte tiers, rattaché au compte général « Clients » ; chaque fournisseur, chaque livreur et chaque apporteur a le sien, rattaché à son compte fournisseurs dédié (onglet « Rubriques de facture »).
                    </p>

                    <div class="border rounded p-3 mb-4">
                        <h6 class="form-label fw-bold mb-2">Attribuer les comptes manquants</h6>
                        <form method="POST" action="{{ route('show.comptabilite.tiers.generer') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label" for="generer-cible">Pour</label>
                                <select name="cible" id="generer-cible" class="form-select form-select-sm">
                                    <option value="client">Les clients sans compte tiers</option>
                                    <option value="fournisseur">Les fournisseurs sans compte tiers</option>
                                    <option value="livreur">Les livreurs sans compte tiers</option>
                                    <option value="apporteur">Les apporteurs sans compte tiers</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="generer-prefixe">Préfixe <span class="text-danger">*</span></label>
                                <input type="text" name="prefixe" id="generer-prefixe" class="form-control form-control-sm" value="{{ old('prefixe', '4111') }}" maxlength="12" required>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-sm btn-primary">Attribuer</button>
                                <small class="text-muted ms-2">
                                    Préfixe + numéro de fiche, complété à la longueur des comptes : 4111 donne 41110012.
                                    Clients 4111, fournisseurs 4011, livreurs 4012, apporteurs 4013. Un compte déjà saisi ne change pas.
                                </small>
                            </div>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('show.comptabilite.tiers.update') }}" class="js-seulement-les-changements" data-table="#tableClientsTiers,#tableFournisseursTiers,#tableLivreursTiers,#tableApporteursTiers">
                        @csrf
                        <h6 class="form-label fw-bold">Clients</h6>
                        <div class="table-responsive mb-4">
                            <table class="table dash-table align-middle mb-0 js-table-formulaire" id="tableClientsTiers">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Type</th>
                                        <th>NCC</th>
                                        <th>Téléphone</th>
                                        <th style="min-width:200px;">Compte tiers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($clients as $client)
                                        <tr>
                                            <td><strong>{{ $client->display_name ?: 'Client n° ' . $client->id }}</strong></td>
                                            <td>{{ $client->type_client === \Help::$ENTREPRISE ? 'Entreprise' : 'Particulier' }}</td>
                                            <td>{{ $client->ncc_clt ?: '-' }}</td>
                                            <td>{{ $client->contact1 ?: '-' }}</td>
                                            <td data-order="{{ $client->compte_tiers }}">
                                                <input type="text" name="client[{{ $client->id }}]" id="client-tiers-{{ $client->id }}" class="form-control form-control-sm"
                                                       value="{{ $client->compte_tiers }}" data-initial="{{ $client->compte_tiers }}" maxlength="20" placeholder="À renseigner">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted">Aucun client.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h6 class="form-label fw-bold">Fournisseurs</h6>
                        <div class="table-responsive mb-3">
                            <table class="table dash-table align-middle mb-0 js-table-formulaire" id="tableFournisseursTiers">
                                <thead>
                                    <tr>
                                        <th>Fournisseur</th>
                                        <th>Code</th>
                                        <th>Téléphone</th>
                                        <th style="min-width:200px;">Compte tiers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($fournisseurs as $fournisseur)
                                        <tr>
                                            <td><strong>{{ \App\Services\Comptabilite\ParametrageComptable::nomDuFournisseur($fournisseur) }}</strong></td>
                                            <td>{{ $fournisseur->code ?: '-' }}</td>
                                            <td>{{ $fournisseur->contact1 ?: ($fournisseur->contact ?: '-') }}</td>
                                            <td data-order="{{ $fournisseur->compte_tiers }}">
                                                <input type="text" name="fournisseur[{{ $fournisseur->id }}]" id="fournisseur-tiers-{{ $fournisseur->id }}" class="form-control form-control-sm"
                                                       value="{{ $fournisseur->compte_tiers }}" data-initial="{{ $fournisseur->compte_tiers }}" maxlength="20" placeholder="À renseigner">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Aucun fournisseur.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h6 class="form-label fw-bold">Livreurs</h6>
                        <div class="table-responsive mb-4">
                            <table class="table dash-table align-middle mb-0 js-table-formulaire" id="tableLivreursTiers">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Code</th>
                                        <th>Téléphone</th>
                                        <th style="min-width:200px;">Compte tiers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($livreurs as $partenaire)
                                        <tr>
                                            <td><strong>{{ \App\Services\Comptabilite\MoteurTresorerie::nomDuPartenaire('livreur', $partenaire) }}</strong></td>
                                            <td>{{ $partenaire->code ?: '-' }}</td>
                                            <td>{{ $partenaire->user->contact1 ?? ($partenaire->user->telephone ?? '-') }}</td>
                                            <td data-order="{{ $partenaire->compte_tiers }}">
                                                <input type="text" name="livreur[{{ $partenaire->id }}]" id="livreur-tiers-{{ $partenaire->id }}" class="form-control form-control-sm"
                                                       value="{{ $partenaire->compte_tiers }}" data-initial="{{ $partenaire->compte_tiers }}" maxlength="20" placeholder="À renseigner">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Aucun.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <h6 class="form-label fw-bold">Apporteurs d'affaires</h6>
                        <div class="table-responsive mb-4">
                            <table class="table dash-table align-middle mb-0 js-table-formulaire" id="tableApporteursTiers">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Code</th>
                                        <th>Téléphone</th>
                                        <th style="min-width:200px;">Compte tiers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($apporteurs as $partenaire)
                                        <tr>
                                            <td><strong>{{ \App\Services\Comptabilite\MoteurTresorerie::nomDuPartenaire('apporteur', $partenaire) }}</strong></td>
                                            <td>{{ $partenaire->code ?: '-' }}</td>
                                            <td>{{ $partenaire->user->contact1 ?? ($partenaire->user->telephone ?? '-') }}</td>
                                            <td data-order="{{ $partenaire->compte_tiers }}">
                                                <input type="text" name="apporteur[{{ $partenaire->id }}]" id="apporteur-tiers-{{ $partenaire->id }}" class="form-control form-control-sm"
                                                       value="{{ $partenaire->compte_tiers }}" data-initial="{{ $partenaire->compte_tiers }}" maxlength="20" placeholder="À renseigner">
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted">Aucun.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les comptes tiers</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ JOURNAUX --}}
                <div class="tab-pane fade {{ $onglet === 'journaux' ? 'show active' : '' }}" id="onglet-journaux" role="tabpanel">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <p class="text-muted mb-0">Un journal de trésorerie (banque, caisse, Mobile Money) porte son compte de trésorerie.</p>
                        <a href="{{ route('show.comptabilite.journaux.create') }}" class="btn btn-primary btn-sm"><i class="material-icons md-plus"></i> Nouveau journal</a>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table dash-table align-middle mb-0" id="tableJournaux">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Libellé</th>
                                    <th>Type</th>
                                    <th>Compte de trésorerie</th>
                                    <th class="text-center">État</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($journaux as $journal)
                                    <tr>
                                        <td><strong>{{ $journal->code }}</strong></td>
                                        <td>{{ $journal->libelle }}</td>
                                        <td>{{ JournalComptable::TYPES[$journal->type] ?? $journal->type }}</td>
                                        <td>
                                            @if (!$journal->estDeTresorerie())
                                                <span class="text-muted">Sans objet</span>
                                            @elseif ($journal->compte)
                                                {{ $journal->compte->designation }}
                                            @else
                                                <span class="badge bg-warning text-dark">À renseigner</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $journal->statut ? 'bg-success' : 'bg-secondary' }}">{{ $journal->statut ? 'Actif' : 'Désactivé' }}</span>
                                        </td>
                                        <td class="text-nowrap text-end">
                                            <a href="{{ route('show.comptabilite.journaux.edit', $journal) }}" class="btn btn-sm btn-primary rounded" title="Modifier le journal">
                                                <i class="material-icons md-edit"></i>
                                            </a>
                                            <form action="{{ route('show.comptabilite.journaux.basculer', $journal) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm rounded {{ $journal->statut ? 'btn-warning' : 'btn-success' }}"
                                                        title="{{ $journal->statut ? 'Désactiver le journal' : 'Activer le journal' }}">
                                                    <i class="material-icons {{ $journal->statut ? 'md-block' : 'md-check_circle' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('show.comptabilite.journaux.destroy', $journal) }}" method="POST" class="d-inline js-delete-form"
                                                  data-item-name="le journal {{ $journal->code }}"
                                                  data-confirm-text="Un journal employé par un mode de règlement ou une écriture ne sera pas supprimé.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger rounded" title="Supprimer le journal">
                                                    <i class="material-icons md-delete"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Aucun journal.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h6 class="form-label fw-bold">Journal de chaque mode de règlement</h6>
                    <p class="text-muted">Un encaissement est écrit dans le journal de son mode de règlement, au débit du compte de trésorerie de ce journal.</p>
                    <form method="POST" action="{{ route('show.comptabilite.modes.update') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-3">
                                <thead>
                                    <tr>
                                        <th>Mode de règlement</th>
                                        <th>Canal</th>
                                        <th style="min-width:300px;">Journal de trésorerie</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($modes as $modeReglement)
                                        <tr>
                                            <td><strong>{{ $modeReglement->libelle }}</strong></td>
                                            <td>{{ $modeReglement->en_ligne ? 'En ligne' : 'Au guichet' }}</td>
                                            <td>
                                                <select name="journal[{{ $modeReglement->id }}]" id="mode-journal-{{ $modeReglement->id }}" class="form-select form-select-sm">
                                                    <option value="">— À renseigner —</option>
                                                    @foreach ($journauxDeTresorerie as $journal)
                                                        <option value="{{ $journal->id }}" {{ (int) $modeReglement->journal_comptable_id === (int) $journal->id ? 'selected' : '' }}>{{ $journal->designation }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted">Aucun mode de règlement actif.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les journaux des règlements</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ RÉGLAGES --}}
                <div class="tab-pane fade {{ $onglet === 'reglages' ? 'show active' : '' }}" id="onglet-reglages" role="tabpanel">
                    <form method="POST" action="{{ route('show.comptabilite.reglages.update') }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label" for="longueur_compte_comptable">Longueur des numéros de comptes généraux <span class="text-danger">*</span></label>
                            <select name="longueur_compte_comptable" id="longueur_compte_comptable" class="form-select" required>
                                @for ($n = \App\Services\Comptabilite\ParametrageComptable::LONGUEUR_MIN; $n <= \App\Services\Comptabilite\ParametrageComptable::LONGUEUR_MAX; $n++)
                                    <option value="{{ $n }}" {{ (int) old('longueur_compte_comptable', $longueur) === $n ? 'selected' : '' }}>{{ $n }} chiffres</option>
                                @endfor
                            </select>
                            <small class="text-muted">Selon le plan de comptes de l'entreprise. Elle ne change que si tous les comptes généraux ont déjà la nouvelle longueur.</small>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="format_libelle_ecriture">Format du libellé d'écriture</label>
                            <input type="text" name="format_libelle_ecriture" id="format_libelle_ecriture" class="form-control" maxlength="190"
                                   value="{{ old('format_libelle_ecriture', $formatLibelle) }}">
                            <small class="text-muted">
                                Mots remplacés :
                                @foreach (\App\Services\Comptabilite\ParametrageComptable::JETONS_LIBELLE as $jeton => $sens)
                                    <code>{{ $jeton }}</code> {{ $sens }}{{ $loop->last ? '.' : ' ;' }}
                                @endforeach
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="journal_cautions_id">Journal des cautions des locations</label>
                            <select name="journal_cautions_id" id="journal_cautions_id" class="form-select">
                                <option value="">— À renseigner —</option>
                                @foreach ($journauxDeTresorerie as $journal)
                                    <option value="{{ $journal->id }}" {{ (int) old('journal_cautions_id', $journalCautions) === (int) $journal->id ? 'selected' : '' }}>{{ $journal->designation }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Le journal de trésorerie où les cautions sont reçues et rendues : la caution n'a pas de ligne de règlement, donc pas de moyen de paiement connu.</small>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary"><i class="material-icons md-save"></i> Enregistrer les réglages</button>
                        </div>
                    </form>
                </div>

                {{-- ============================================================ CONTRÔLE --}}
                <div class="tab-pane fade {{ $onglet === 'controle' ? 'show active' : '' }}" id="onglet-controle" role="tabpanel">
                    @if (!$nombreAnomalies)
                        <div class="alert alert-success mb-0">Le paramétrage est complet : chaque facture peut produire son écriture.</div>
                    @else
                        <p class="text-muted">
                            Tant que ces points ne sont pas réglés, une facture qui les rencontre ne pourra pas être exportée. Chaque ligne dit où corriger.
                        </p>
                        <x-export-buttons table-id="tableControle" filename="controle-parametrage-comptable" title="Contrôle du paramétrage comptable" />
                        <div class="table-responsive">
                            <table class="table dash-table align-middle mb-0 js-table" id="tableControle">
                                <thead>
                                    <tr>
                                        <th>Nature</th>
                                        <th>Concerné</th>
                                        <th>Cause</th>
                                        <th>Colonne à corriger</th>
                                        <th class="text-end">Corriger</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($anomalies as $anomalie)
                                        <tr>
                                            <td>{{ $anomalie['categorie'] }}</td>
                                            <td><strong>{{ $anomalie['objet'] }}</strong></td>
                                            <td class="td-texte-long">{{ $anomalie['cause'] }}</td>
                                            <td>{{ $anomalie['colonne'] }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('show.comptabilite.parametrage', ['onglet' => $anomalie['onglet']]) }}" class="btn btn-sm btn-primary">
                                                    Ouvrir « {{ $titresOnglets[$anomalie['onglet']] }} »
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- ============================================================ HISTORIQUE --}}
                <div class="tab-pane fade {{ $onglet === 'historique' ? 'show active' : '' }}" id="onglet-historique" role="tabpanel">
                    <p class="text-muted">Qui a créé, modifié ou supprimé quoi, et quand — les 500 derniers changements.</p>
                    <x-export-buttons table-id="tableHistorique" filename="historique-parametrage-comptable" title="Historique du paramétrage comptable" />
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0 js-table" id="tableHistorique" data-ordre="desc">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Objet</th>
                                    <th>Désignation</th>
                                    <th>Action</th>
                                    <th>Changement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($historique as $ligne)
                                    <tr>
                                        <td data-order="{{ optional($ligne->created_at)->format('YmdHis') }}">{{ \Help::dateHeure($ligne->created_at) }}</td>
                                        <td>{{ $ligne->user ? trim(($ligne->user->nom ?? '') . ' ' . ($ligne->user->prenom ?? '')) ?: $ligne->user->email : '-' }}</td>
                                        <td>{{ $ligne->libelle_objet }}</td>
                                        <td><strong>{{ $ligne->designation }}</strong></td>
                                        <td>{{ $ligne->libelle_action }}</td>
                                        <td class="td-texte-long">{{ $ligne->resume ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Aucun changement enregistré.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
    <style>
        /* Les neuf onglets tiennent sur UNE ligne ; s'ils débordent, la ligne défile à l'horizontale. */
        .onglets-une-ligne { flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
        .onglets-une-ligne .nav-item { flex: 0 0 auto; }
        .onglets-une-ligne .nav-link { white-space: nowrap; }
    </style>
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var langue = { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' };
            var tables = {};

            function aDesLignes($table) {
                return $table.find('tbody tr').length > 0 && $table.find('tbody tr td[colspan]').length === 0;
            }

            // Tables de lecture : paginées.
            $('.js-table').each(function () {
                var $table = $(this);
                if (!aDesLignes($table)) { return; }
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: langue,
                    order: [[0, $table.data('ordre') === 'desc' ? 'desc' : 'asc']],
                });
            });

            // Tables de saisie : jamais paginées — une ligne hors de la page quitterait le formulaire.
            $('.js-table-formulaire').each(function () {
                var $table = $(this);
                if (!aDesLignes($table)) { return; }
                tables['#' + this.id] = $table.DataTable({
                    paging: false,
                    info: false,
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: langue,
                    order: [[0, 'asc']],
                });
            });

            // L'onglet actif est amené dans la partie visible de la ligne d'onglets.
            var actif = document.querySelector('#ongletsComptables .nav-link.active');
            if (actif) { actif.parentNode.parentNode.scrollLeft = Math.max(0, actif.parentNode.offsetLeft - 40); }

            // Un onglet ouvert à la main se retrouve après l'enregistrement et au rechargement.
            $('#ongletsComptables button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('onglet', this.getAttribute('data-onglet'));
                window.history.replaceState(null, '', url.toString());
                $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            });

            // La liste des comptes analytiques n'est versée dans un sélecteur qu'à sa première ouverture.
            var analytiques = {!! json_encode($analytiquesPourJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
            function verser(select) {
                if (select.dataset.verse) { return; }
                select.dataset.verse = '1';
                var courant = select.value;
                analytiques.forEach(function (compte) {
                    if (String(compte.id) === String(courant)) { return; }
                    var option = document.createElement('option');
                    option.value = compte.id;
                    option.textContent = compte.texte;
                    select.appendChild(option);
                });
            }
            $(document).on('mousedown focus keydown', 'select.js-analytique', function () { verser(this); });

            // N'envoyer que ce qui a changé : des centaines de champs dépasseraient la limite du serveur.
            $('form.js-seulement-les-changements').on('submit', function () {
                var cibles = (this.getAttribute('data-table') || '').split(',');
                cibles.forEach(function (cible) {
                    if (tables[cible]) { tables[cible].search('').draw(); }   // une ligne filtrée est sortie du formulaire
                });
                $(this).find('[data-initial]').each(function () {
                    if (String(this.value || '') === String(this.getAttribute('data-initial') || '')) {
                        this.disabled = true;
                    }
                });
            });

            // Confirmation SweetAlert2 des réglages « en un clic ».
            $('form.js-confirmer').on('submit', function (evenement) {
                var formulaire = this;
                if (formulaire.dataset.confirme) { return; }
                evenement.preventDefault();
                Swal.fire({
                    title: formulaire.getAttribute('data-confirm-title'),
                    text: formulaire.getAttribute('data-confirm-text'),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Oui, continuer',
                    cancelButtonText: 'Annuler',
                }).then(function (reponse) {
                    if (reponse.isConfirmed) {
                        formulaire.dataset.confirme = '1';
                        formulaire.submit();
                    }
                });
            });
        });
    </script>
@endsection
