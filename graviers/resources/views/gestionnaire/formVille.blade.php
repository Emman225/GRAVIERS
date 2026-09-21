{{-- LA SAISIE A SA PROPRE PAGE.

     Le formulaire partageait l'écran de la liste : on ne savait plus si l'on
     consultait ou si l'on saisissait, et la liste reculait de toute sa hauteur
     au premier clic sur « Nouvelle ville ». Création et modification se
     servent du même écran ; seule change la destination du formulaire. --}}
@extends('layout.main')
@section('title', $ville->id ? 'Modification de ville' : 'Nouvelle ville')

@section('contenu')

    @if (session('erreurVille'))
        <div class="alert alert-danger">
            <i class="material-icons md-error align-middle"></i>
            {{ session('erreurVille') }}
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card dash-card">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                    <h5 class="dash-card-title mb-0">
                        <i class="material-icons md-add_location text-primary"></i>
                        {{ $ville->id ? 'Modifier la ville' : 'Nouvelle ville' }}
                    </h5>
                    <a href="{{ route('dest.lesVilles') }}" class="btn btn-sm btn-secondary">
                        <i class="material-icons md-arrow_back align-middle"></i> Retour à la liste
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ $ville->id
                            ? route('dest.modifierVilleValid', $ville)
                            : route('dest.lesVillesValid') }}" method="post">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom de la ville <span class="text-danger">*</span></label>
                            <input class="form-control" value="{{ old('nom', $ville->nom) }}" name="nom" type="text" />
                            @error('nom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Région <span class="text-danger">*</span></label>
                            <select class="form-control" name="region_id">
                                <option value="">— Sélectionnez une région —</option>
                                @foreach ($lesRegions as $region)
                                    <option @selected(old('region_id', $ville->region_id) == $region->id) value="{{ $region->id }}">{{ $region->nom }}</option>
                                @endforeach
                            </select>
                            @error('region_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex" style="gap:.5rem">
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i>
                                {{ $ville->id ? 'Modifier' : 'Enregistrer' }}
                            </button>
                            <a href="{{ route('dest.lesVilles') }}" class="btn btn-secondary">Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
