@extends('layout.main')
@section('title', $mode === 'create' ? 'Nouveau type de véhicule' : 'Modifier le type de véhicule')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">
            {{ $mode === 'create' ? 'Nouveau type de véhicule' : 'Modifier — '.$type->libelle }}
        </h2>
        <div>
            <a href="{{ route('show.typeVehiculeLivreur.index') }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour à la liste
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="post"
                          action="{{ $mode === 'create' ? route('show.typeVehiculeLivreur.store') : route('show.typeVehiculeLivreur.update', $type) }}">
                        @csrf
                        @if ($mode === 'edit') @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="libelle" class="form-control" required maxlength="60"
                                   value="{{ old('libelle', $type->libelle) }}" placeholder="Ex: Camion 5T" />
                            <small class="text-muted">Doit être unique. Exemples : Tricycle, Camion 5T, Benne…</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Capacité (en tonnes)</label>
                            <input type="number" step="0.01" min="0" name="capacite_tonnes" class="form-control"
                                   value="{{ old('capacite_tonnes', $type->capacite_tonnes) }}" placeholder="Ex: 5" />
                            <small class="text-muted">Permet de classer les véhicules par capacité.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" maxlength="255"
                                      placeholder="Usage typique, restrictions…">{{ old('description', $type->description) }}</textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-block">Statut</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="statut" id="statut1" value="1"
                                       {{ old('statut', $type->statut ?? 1) ? 'checked' : '' }}>
                                <label class="form-check-label" for="statut1">Actif</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="statut" id="statut0" value="0"
                                       {{ !old('statut', $type->statut ?? 1) ? 'checked' : '' }}>
                                <label class="form-check-label" for="statut0">Désactivé</label>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('show.typeVehiculeLivreur.index') }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i>
                                {{ $mode === 'create' ? 'Enregistrer' : 'Mettre à jour' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
