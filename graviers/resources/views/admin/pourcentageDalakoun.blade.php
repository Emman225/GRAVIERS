@extends('layout.main')
@section('title','Pourcentage DALAKOUN')

@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
@endphp

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">Pourcentage DALAKOUN</h2>
        <div>
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#saisirDerogation">
                <i class="material-icons md-plus"></i> Déroger sur un produit
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#saisirPourcentage">
                <i class="material-icons md-plus"></i> Saisir un pourcentage
            </button>
        </div>
    </div>

    {{-- Messages sous une clé propre : Flasher capte success/error/warning/info
         et les rejoue en toast, ce qui les ferait disparaître de la page. --}}
    @if (!empty($tableAbsente))
        <div class="alert alert-danger">
            <strong>La migration n'a pas encore été lancée sur ce serveur.</strong><br>
            Les fichiers sont en place, mais la table qui conserve les pourcentages n'existe
            pas. Lancez <code>php artisan migrate --force</code> à la racine du site, puis
            rechargez cette page.
        </div>
    @endif

    @if (session('pourcentage_succes'))
        <div class="alert alert-success">{{ session('pourcentage_succes') }}</div>
    @endif
    @if (session('pourcentage_erreur'))
        <div class="alert alert-danger">{{ session('pourcentage_erreur') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $erreur)
                <div>{{ $erreur }}</div>
            @endforeach
        </div>
    @endif

    <div class="card mb-4">
        <header class="card-header">
            <div class="row gx-3 text-center">
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Pourcentage en vigueur</div>
                    <div class="h3 mb-0 text-success">
                        {{ $enVigueur ? $fmt($enVigueur->taux) . ' %' : 'Aucun' }}
                    </div>
                    @if ($enVigueur)
                        <div class="text-muted small">
                            depuis le {{ \Carbon\Carbon::parse($enVigueur->date_validation_2)->format('d/m/Y à H:i:s') }}
                        </div>
                    @endif
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Prix de vente d'un produit acheté 10 000 F</div>
                    <div class="h3 mb-0 text-success">
                        {{ $enVigueur ? $fmt(10000 * (1 + $enVigueur->taux / 100)) . ' fcfa' : '10 000 fcfa' }}
                    </div>
                </div>
            </div>
        </header>

        <div class="card-body">
            @if (!$enVigueur)
                <div class="alert alert-warning">
                    Aucun pourcentage n'est encore en vigueur : le catalogue affiche donc le prix
                    d'achat, sans marge. Saisissez un pourcentage, puis faites-le valider par un
                    second administrateur.
                </div>
            @endif

            {{-- Ce que le taux général ne régit pas. Sans cette liste, une
                 dérogation posée il y a six mois devient invisible : on
                 relèverait le taux général en croyant toucher tout le
                 catalogue. --}}
            @if ($derogations->isNotEmpty())
                <div class="alert alert-info">
                    <strong>{{ $derogations->count() }}</strong>
                    produit{{ $derogations->count() > 1 ? 's échappent' : ' échappe' }} au taux général :
                    @foreach ($derogations as $d)
                        <span class="badge bg-light text-dark border me-1">
                            {{ $d->nom }} — {{ $fmt($d->pourcentage_dalakoun) }} %
                        </span>
                    @endforeach
                </div>
            @endif

            <x-export-buttons table-id="liste"
                              filename="pourcentages-dalakoun"
                              title="Pourcentages DALAKOUN" />
            <div class="table-responsive">
                <table class="table table-striped align-middle" id="liste">
                    <thead>
                        <tr>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-left-radius:5px">Pourcentage</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Portée</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Motif</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Saisi par</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Validé par</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white;">Statut</th>
                            <th class="text-center" style="background-color: #1c57a3; color: white; border-top-right-radius:5px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($taux as $t)
                            <tr>
                                <td class="text-center fw-bold">
                                    @if ($t->estUnRetraitDeDerogation())
                                        <span class="text-muted">retour au général</span>
                                    @else
                                        {{ $fmt($t->taux) }} %
                                    @endif
                                </td>
                                <td>
                                    @if ($t->estUneDerogation())
                                        <span class="badge bg-info text-dark">Dérogation</span>
                                        <br><small>{{ $t->produit->nom ?? 'Produit supprimé' }}</small>
                                    @else
                                        <span class="badge bg-secondary">Tout le catalogue</span>
                                    @endif
                                </td>
                                <td>{{ $t->motif ?: '—' }}</td>
                                <td>
                                    {{ $t->initie_par }}
                                    <br><small class="text-muted">
                                        {{ $t->date_validation_1 ? \Carbon\Carbon::parse($t->date_validation_1)->format('d/m/Y H:i:s') : '—' }}
                                    </small>
                                </td>
                                <td>
                                    {{ $t->user_valide2_id ? $t->valide_par : '—' }}
                                    @if ($t->date_validation_2)
                                        <br><small class="text-muted">
                                            {{ \Carbon\Carbon::parse($t->date_validation_2)->format('d/m/Y H:i:s') }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($t->estApplique())
                                        <span class="badge bg-success">{{ $t->libelleStatut() }}</span>
                                    @elseif ($t->attendUneSecondeValidation())
                                        <span class="badge bg-warning text-dark">{{ $t->libelleStatut() }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $t->libelleStatut() }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($t->attendUneSecondeValidation())
                                        @if (!$t->peutEtreValidePar(auth()->user()))
                                            <div class="small text-muted mb-1">
                                                En attente d'un autre administrateur
                                            </div>
                                        @endif
                                        {{-- Les messages ne portent aucune apostrophe : delete-confirm.js
                                             construit le texte du SweetAlert par une expression
                                             régulière qui s'arrête à la première, même échappée. --}}
                                        @if ($t->peutEtreValidePar(auth()->user()))
                                            <form method="post" action="{{ route('product.pourcentage.valider', $t) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success"
                                                    onclick="return confirm('@if ($t->estUnRetraitDeDerogation())Confirmez-vous le retour de ce produit au taux general ?@elseif ($t->estUneDerogation())Confirmez-vous la derogation de {{ $fmt($t->taux) }} pour cent sur ce produit ? Son prix sera recalcule.@elseConfirmez-vous le pourcentage de {{ $fmt($t->taux) }} pour cent ? Tous les prix du catalogue seront recalcules.@endif')">
                                                    Valider
                                                </button>
                                            </form>
                                        @endif
                                        @if ($t->peutEtreRefusePar(auth()->user()))
                                            <form method="post" action="{{ route('product.pourcentage.refuser', $t) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Confirmez-vous le refus de ce pourcentage ? Le taux en vigueur restera inchangé.')">
                                                    Refuser
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    Aucun pourcentage saisi pour le moment.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Le prix affiché au catalogue vaut le prix d'achat majoré de ce pourcentage.
                Un pourcentage ne s'applique qu'après validation par un administrateur
                <strong>différent</strong> de celui qui l'a saisi.
            </p>
        </div>
    </div>


    {{-- La fenêtre de dérogation --}}
    <div class="modal fade" id="saisirDerogation" tabindex="-1" aria-labelledby="titreSaisirDerogation" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="{{ route('product.pourcentage.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="titreSaisirDerogation">Déroger au taux sur un produit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="produit_id">Produit <span class="text-danger">*</span></label>
                        <select class="form-control" name="produit_id" id="produit_id" required>
                            <option value="">— Choisir un produit —</option>
                            @foreach ($produits as $produitChoix)
                                <option value="{{ $produitChoix->id }}"
                                        @selected(old('produit_id') == $produitChoix->id)>
                                    {{ $produitChoix->nom }}@if ($produitChoix->pourcentage_dalakoun !== null) — déroge déjà à {{ $fmt($produitChoix->pourcentage_dalakoun) }} %@endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="tauxDerogation">Pourcentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="1000" class="form-control"
                                   name="taux" id="tauxDerogation"
                                   value="{{ old('taux') }}" aria-describedby="aideDerogation">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text" id="aideDerogation">
                            Ce taux remplacera le taux général <strong>pour ce seul produit</strong>.
                            Zéro est une décision : le produit serait vendu à son prix d'achat.
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" name="retrait" id="retrait">
                        <label class="form-check-label" for="retrait">
                            Retirer la dérogation : ce produit revient au taux général.
                        </label>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="motifDerogation">Motif <span class="text-muted">(facultatif)</span></label>
                        <input type="text" class="form-control" name="motif" id="motifDerogation"
                               maxlength="255" value="{{ old('motif') }}"
                               placeholder="Produit d'appel, marge réduite sur ce matériau...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    {{-- La fenêtre de saisie --}}
    <div class="modal fade" id="saisirPourcentage" tabindex="-1" aria-labelledby="titreSaisirPourcentage" aria-hidden="true">
        <div class="modal-dialog">
            <form method="post" action="{{ route('product.pourcentage.store') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="titreSaisirPourcentage">Saisir un pourcentage DALAKOUN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="taux">Pourcentage <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="1000" class="form-control"
                                   name="taux" id="taux" required
                                   value="{{ old('taux') }}" aria-describedby="aideTaux">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text" id="aideTaux">
                            Marge ajoutée au prix d'achat. Exemple : 20 signifie qu'un produit
                            acheté 10 000 F sera affiché à 12 000 F.
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="motif">Motif <span class="text-muted">(facultatif)</span></label>
                        <input type="text" class="form-control" name="motif" id="motif"
                               maxlength="255" value="{{ old('motif') }}"
                               placeholder="Révision tarifaire, hausse du carburant...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
@endsection
