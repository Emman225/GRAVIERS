@extends('layout.main')
@php
    use App\Models\CompteComptable;
    $estGeneral = $compte->nature === CompteComptable::NATURE_GENERAL;
    $titre = $mode === 'create'
        ? ($estGeneral ? 'Nouveau compte général' : 'Nouveau compte analytique')
        : 'Modifier le compte ' . $compte->numero;
@endphp
@section('title', $titre)

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">{{ $titre }}</h2>
        <div>
            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'comptes']) }}" class="btn btn-light">
                <i class="material-icons md-arrow_back"></i> Retour au plan de comptes
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="post"
                          action="{{ $mode === 'create' ? route('show.comptabilite.comptes.store') : route('show.comptabilite.comptes.update', $compte) }}">
                        @csrf
                        @if ($mode === 'edit') @method('PUT') @endif
                        <input type="hidden" name="nature" value="{{ $compte->nature }}">

                        <div class="mb-3">
                            <label class="form-label">Nature</label>
                            <input type="text" class="form-control" value="{{ CompteComptable::NATURES[$compte->nature] }}" disabled>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="numero">Numéro <span class="text-danger">*</span></label>
                            @if ($estGeneral)
                                <input type="text" name="numero" id="numero" class="form-control" required inputmode="numeric"
                                       minlength="{{ $longueur }}" maxlength="{{ $longueur }}" pattern="[0-9]{{ '{' . $longueur . '}' }}"
                                       value="{{ old('numero', $compte->numero) }}" placeholder="{{ str_pad('7011', $longueur, '0') }}">
                                <small class="text-muted">{{ $longueur }} chiffres, selon le plan de comptes de l'entreprise (onglet « Réglages »).</small>
                            @else
                                <input type="text" name="numero" id="numero" class="form-control" required maxlength="20"
                                       value="{{ old('numero', $compte->numero) }}" placeholder="GRA-0515">
                                <small class="text-muted">Lettres, chiffres, tirets ou points, 20 caractères au plus.</small>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="libelle">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="libelle" id="libelle" class="form-control" required maxlength="150"
                                   value="{{ old('libelle', $compte->libelle) }}"
                                   placeholder="{{ $estGeneral ? 'Ventes de granulats' : 'Gravier concassé 5/15' }}">
                        </div>

                        @if ($mode === 'edit' && !empty($emplois))
                            <div class="alert alert-info">
                                Ce compte est employé :
                                {{ collect($emplois)->map(fn ($nombre, $lieu) => $nombre . ' ' . $lieu)->implode(', ') }}.
                                Un changement de numéro vaut pour les écritures à venir ; les écritures déjà produites gardent le leur.
                            </div>
                        @endif

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('show.comptabilite.parametrage', ['onglet' => 'comptes']) }}" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i> {{ $mode === 'create' ? 'Enregistrer' : 'Mettre à jour' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
