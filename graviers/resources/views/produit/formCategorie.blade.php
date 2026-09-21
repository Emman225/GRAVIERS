{{-- LE FORMULAIRE A SA PROPRE PAGE.

     Il partageait l'écran avec la liste : on ne savait plus si l'on consultait
     ou si l'on saisissait, et la liste reculait de tout un formulaire dès
     qu'on cliquait sur « Nouvelle catégorie ». La création et la modification
     se servent du même écran ; seule change la destination du formulaire. --}}
@extends('layout.main')
@section('title', $categorie->id ? 'Modification de catégorie' : 'Nouvelle catégorie')
@section('contenu')

    <div class="content-header">
        <div>
            <h2 class="content-title card-title">
                {{ $categorie->id ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}
            </h2>
            <p>
                {{ $categorie->id
                    ? 'Modifiez le nom, le parent, l\'image ou la description de cette catégorie.'
                    : 'Renseignez la catégorie à ajouter au catalogue.' }}
            </p>
        </div>
        <div>
            <a href="{{ route('product.category') }}" class="btn btn-sm btn-secondary">
                <i class="material-icons md-arrow_back align-middle"></i> Retour à la liste
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-8">
                    <form method="post"
                          action="{{ $categorie->id
                              ? route('product.editCategoryTraitement', $categorie)
                              : route('product.saveCategorie') }}"
                          enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            @if (session('succes'))
                                <div class="alert alert-success text-center">
                                    {{ session('succes') }}
                                </div>
                            @endif
                            <label for="categorie_nom" class="form-label">Nom</label>
                            <input type="text" value="{{ old('nom', $categorie->nom) }}" name="nom"
                                   class="form-control" id="categorie_nom" />
                            <span class="text-danger">
                                @error('nom')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>
                        <div class="mb-4">
                            <label for="categorie_parent" class="form-label">Parent</label>
                            <select name="parent" class="form-control" id="categorie_parent">
                                <option value="">Aucun (catégorie principale)</option>
                                @foreach ($lists as $cat)
                                    @continue($categorie->id && $cat->id == $categorie->id)
                                    <option @selected(old('parent', $categorie->parent_id) == $cat->id) value="{{ $cat->id }}">
                                        {{ $cat->nom }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-danger">
                                @error('parent')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>
                        <div class="mb-4">
                            <label for="categorie_image" class="form-label">Choisissez une image</label>
                            <input type="file" {{ $categorie->id > 0 ? '' : 'required' }} class="form-control"
                                   name="image" id="categorie_image" />
                            @if ($categorie->id && $categorie->image)
                                <small class="text-muted">
                                    Une image est déjà enregistrée. Laissez vide pour la conserver.
                                </small>
                            @endif
                            <span class="text-danger">
                                @error('image')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>
                        <div class="mb-4">
                            <label for="categorie_description" class="form-label">Description</label>
                            <textarea name="description" id="categorie_description"
                                      class="form-control">{{ old('description', $categorie->description) }}</textarea>
                            <span class="text-danger">
                                @error('description')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>
                        <div class="d-flex" style="gap:.5rem">
                            <button class="btn btn-primary">
                                {{ $categorie->id ? 'Enregistrer les modifications' : 'Créer la catégorie' }}
                            </button>
                            <a href="{{ route('product.category') }}" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@endsection
