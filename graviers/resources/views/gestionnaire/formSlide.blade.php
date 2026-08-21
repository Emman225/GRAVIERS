@php
    $modification = $slide->exists;
@endphp

@extends('layout.main')
@section('title', $modification ? 'Modifier une diapositive' : 'Nouvelle diapositive')

@section('contenu')
    <x-notify::notify />

    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    {{ $modification ? 'Modifier la' : 'Nouvelle' }} <span class="dash-welcome-name">diapositive</span> 🖼️
                </h2>
                <p class="dash-welcome-subtitle">
                    Elle s'affichera dans le carrousel de la page d'accueil, à la position indiquée.
                </p>
            </div>
            <div class="dash-welcome-actions">
                <a href="{{ route('show.listeDesSlides') }}" class="btn btn-light">
                    <i class="material-icons md-arrow_back"></i> Retour à la liste
                </a>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $erreur)
                    <li>{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $modification ? route('show.modificationDeSlide', $slide->id) : route('show.creationDeSlideTraitement') }}"
          enctype="multipart/form-data">
        @csrf

        <div class="row">
            {{-- ===== Colonne principale ===== --}}
            <div class="col-lg-8">
                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Contenu affiché</h5></div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">Titre — 1<sup>re</sup> ligne <span class="text-danger">*</span></label>
                            <input type="text" name="titre" class="form-control" maxlength="150" required
                                   value="{{ old('titre', $slide->titre) }}"
                                   placeholder="Ex. : Construisez vos rêves,">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Titre — 2<sup>e</sup> ligne (mise en valeur)</label>
                            <input type="text" name="titre_accent" class="form-control" maxlength="150"
                                   value="{{ old('titre_accent', $slide->titre_accent) }}"
                                   placeholder="Ex. : on fournit le reste">
                            <small class="text-muted">Cette seconde ligne s'affiche dans la couleur d'accent.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control" maxlength="1000"
                                      placeholder="Une ou deux phrases sous le titre.">{{ old('description', $slide->description) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Arguments</label>
                            <textarea name="caracteristiques" rows="3" class="form-control" maxlength="600"
                                      placeholder="Un argument par ligne">{{ old('caracteristiques', $slide->caracteristiques) }}</textarea>
                            <small class="text-muted">Un par ligne. Chacun s'affiche précédé d'une coche. Trois maximum recommandés.</small>
                        </div>
                    </div>
                </div>

                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Boutons</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bouton principal — libellé</label>
                                <input type="text" name="bouton1_texte" class="form-control" maxlength="60"
                                       value="{{ old('bouton1_texte', $slide->bouton1_texte) }}"
                                       placeholder="Ex. : Commander maintenant">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bouton principal — lien</label>
                                <input type="text" name="bouton1_lien" class="form-control" maxlength="255"
                                       value="{{ old('bouton1_lien', $slide->bouton1_lien) }}"
                                       placeholder="Ex. : #popular-categories ou /demande-de-livraison">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bouton secondaire — libellé</label>
                                <input type="text" name="bouton2_texte" class="form-control" maxlength="60"
                                       value="{{ old('bouton2_texte', $slide->bouton2_texte) }}"
                                       placeholder="Ex. : Demander un devis">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bouton secondaire — lien</label>
                                <input type="text" name="bouton2_lien" class="form-control" maxlength="255"
                                       value="{{ old('bouton2_lien', $slide->bouton2_lien) }}"
                                       placeholder="Ex. : /demande-de-livraison">
                            </div>
                        </div>
                        <small class="text-muted">
                            Un bouton n'apparaît que si son libellé ET son lien sont renseignés.
                            Un lien commençant par « # » mène à une section de la page d'accueil.
                        </small>
                    </div>
                </div>
            </div>

            {{-- ===== Colonne latérale ===== --}}
            <div class="col-lg-4">
                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Image de fond</h5></div>
                    <div class="card-body">
                        @if ($slide->image)
                            <img src="{{ $slide->urlImage() }}" class="img-fluid mb-3"
                                 style="border-radius:8px;" alt="{{ $slide->titre }}">
                        @endif

                        <input type="file" name="image" class="form-control"
                               accept=".jpg,.jpeg,.png,.webp" {{ $modification ? '' : 'required' }}>
                        <small class="text-muted d-block mt-2">
                            JPG, PNG ou WEBP — 4 Mo maximum. Format paysage recommandé (1920 × 800 px).
                            @if ($modification)
                                <br>Laissez vide pour conserver l'image actuelle.
                            @endif
                        </small>
                    </div>
                </div>

                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Pastille</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Texte</label>
                            <input type="text" name="badge_texte" class="form-control" maxlength="100"
                                   value="{{ old('badge_texte', $slide->badge_texte) }}"
                                   placeholder="Ex. : OFFRE LIMITÉE">
                            <small class="text-muted">Laissez vide pour ne pas afficher de pastille.</small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            @php $typeCourant = old('badge_type', $slide->badge_type ?? 'NEUTRE'); @endphp
                            <select name="badge_type" class="form-select" required>
                                <option value="NEUTRE" {{ $typeCourant === 'NEUTRE' ? 'selected' : '' }}>Neutre</option>
                                <option value="NEW"    {{ $typeCourant === 'NEW'    ? 'selected' : '' }}>Nouveauté</option>
                                <option value="PROMO"  {{ $typeCourant === 'PROMO'  ? 'selected' : '' }}>Promotion</option>
                                <option value="HOT"    {{ $typeCourant === 'HOT'    ? 'selected' : '' }}>Offre forte</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Encart chiffré</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Valeur</label>
                            <input type="text" name="deco_valeur" class="form-control" maxlength="40"
                                   value="{{ old('deco_valeur', $slide->deco_valeur) }}"
                                   placeholder="Ex. : 15+">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Libellé</label>
                            <input type="text" name="deco_libelle" class="form-control" maxlength="80"
                                   value="{{ old('deco_libelle', $slide->deco_libelle) }}"
                                   placeholder="Ex. : années d'expérience">
                            <small class="text-muted">L'encart n'apparaît que si la valeur est renseignée.</small>
                        </div>
                    </div>
                </div>

                <div class="card dash-card mb-4">
                    <div class="card-header"><h5 class="mb-0">Affichage</h5></div>
                    <div class="card-body">
                        <label class="form-label">Ordre <span class="text-danger">*</span></label>
                        <input type="number" name="num_ordre" class="form-control" min="0" max="999" required
                               value="{{ old('num_ordre', $slide->num_ordre ?? 0) }}">
                        <small class="text-muted">Les diapositives défilent du plus petit au plus grand.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-4">
                    <i class="material-icons md-save"></i>
                    {{ $modification ? 'Enregistrer les modifications' : 'Créer la diapositive' }}
                </button>
            </div>
        </div>
    </form>
@endsection

@section('jsParts')
    @notifyJs
@endsection
