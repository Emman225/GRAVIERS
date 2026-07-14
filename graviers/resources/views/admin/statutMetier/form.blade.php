@extends('layout.main')
@section('title', $mode === 'create' ? 'Nouveau statut métier' : 'Modifier le statut métier')

@section('contenu')
    <div class="content-header">
        <h2 class="content-title">
            {{ $mode === 'create' ? 'Nouveau statut métier' : 'Modifier — '.$statut->libelle }}
        </h2>
        <div>
            <a href="{{ route('show.statutMetier.index', ['domaine' => $statut->domaine]) }}" class="btn btn-light">
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
                          action="{{ $mode === 'create' ? route('show.statutMetier.store') : route('show.statutMetier.update', $statut) }}">
                        @csrf
                        @if ($mode === 'edit') @method('PUT') @endif

                        <div class="mb-3">
                            <label class="form-label">Domaine <span class="text-danger">*</span></label>
                            <select name="domaine" class="form-select" required>
                                @foreach ($domaines as $key => $label)
                                    <option value="{{ $key }}" {{ old('domaine', $statut->domaine) === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Module dans lequel ce statut est utilisé.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" name="libelle" class="form-control" required maxlength="80"
                                   value="{{ old('libelle', $statut->libelle) }}"
                                   placeholder="Ex: À échoir, Soldée, Payée…" />
                            <small class="text-muted">Doit être unique dans son domaine.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Classe de badge (couleur) <span class="text-danger">*</span></label>
                            <select name="badge_class" id="badge_class" class="form-select" required>
                                @foreach ($badgeChoices as $cls => $label)
                                    <option value="{{ $cls }}"
                                            {{ old('badge_class', $statut->badge_class ?? 'bg-light text-dark') === $cls ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-2">
                                <small class="text-muted me-2">Aperçu :</small>
                                <span class="badge {{ old('badge_class', $statut->badge_class ?? 'bg-light text-dark') }}" id="badgePreview">
                                    {{ old('libelle', $statut->libelle) ?: 'Libellé du statut' }}
                                </span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" maxlength="255"
                                      placeholder="Quand utiliser ce statut…">{{ old('description', $statut->description) }}</textarea>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ordre d'affichage</label>
                                <input type="number" min="0" name="ordre" class="form-control"
                                       value="{{ old('ordre', $statut->ordre ?? 0) }}" />
                                <small class="text-muted">Plus petit = affiché en premier.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label d-block">Statut</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="statut" id="statut1" value="1"
                                           {{ old('statut', $statut->statut ?? 1) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="statut1">Actif</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="statut" id="statut0" value="0"
                                           {{ !old('statut', $statut->statut ?? 1) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="statut0">Désactivé</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('show.statutMetier.index') }}" class="btn btn-secondary">Annuler</a>
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

@section('jsParts')
<script>
    // Aperçu live du badge
    document.addEventListener('DOMContentLoaded', function () {
        var libelleInput = document.querySelector('input[name="libelle"]');
        var badgeSelect  = document.getElementById('badge_class');
        var preview      = document.getElementById('badgePreview');
        function refresh() {
            preview.textContent = libelleInput.value || 'Libellé du statut';
            preview.className = 'badge ' + (badgeSelect.value || 'bg-light text-dark');
        }
        if (libelleInput && badgeSelect && preview) {
            libelleInput.addEventListener('input', refresh);
            badgeSelect.addEventListener('change', refresh);
        }
    });
</script>
@endsection
