@extends('layout.main')
@section('title', $mode === 'create' ? 'Nouvelle agence' : 'Modifier agence')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">
            {{ $mode === 'create' ? 'Nouvelle agence' : 'Modifier agence — ' . $agence->code }}
        </h2>
        <div>
            <a href="{{ route('show.agences.index') }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour
            </a>
        </div>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ $mode === 'create' ? route('show.agences.store') : route('show.agences.update', $agence) }}"
                  method="POST">
                @csrf
                @if ($mode === 'edit')
                    @method('PUT')
                @endif

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="code" name="code"
                               value="{{ old('code', $agence->code) }}" required
                               placeholder="Ex: AG-ABJ-COC">
                        <small class="text-muted">Format suggéré : AG-VILLE-QUARTIER</small>
                    </div>
                    <div class="col-md-8">
                        <label for="nom" class="form-label">Nom de l'agence <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom"
                               value="{{ old('nom', $agence->nom) }}" required
                               placeholder="Ex: Agence Cocody">
                    </div>
                    <div class="col-md-12">
                        <label for="adresse" class="form-label">Adresse</label>
                        <input type="text" class="form-control" id="adresse" name="adresse"
                               value="{{ old('adresse', $agence->adresse) }}"
                               placeholder="Ex: Riviera Bonoumin, Abidjan">
                    </div>
                    <div class="col-md-6">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="text" class="form-control" id="telephone" name="telephone"
                               value="{{ old('telephone', $agence->telephone) }}"
                               placeholder="Ex: +225 27 22 00 00 00">
                    </div>
                    <div class="col-md-6">
                        <label for="responsable" class="form-label">Responsable</label>
                        <input type="text" class="form-control" id="responsable" name="responsable"
                               value="{{ old('responsable', $agence->responsable) }}"
                               placeholder="Ex: M. KOUASSI">
                    </div>
                    <div class="col-md-6">
                        <label for="statut" class="form-label">Statut</label>
                        <select class="form-control" id="statut" name="statut">
                            <option value="1" @selected(old('statut', $agence->statut ?? 1) == 1)>Active</option>
                            <option value="2" @selected(old('statut', $agence->statut) == 2)>Inactive</option>
                        </select>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('show.agences.index') }}" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons md-save"></i>
                        {{ $mode === 'create' ? 'Créer' : 'Enregistrer' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
