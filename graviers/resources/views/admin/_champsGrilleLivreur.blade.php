{{--
    Les champs d'une tranche de facturation livreur.

    Le même bloc pour l'ajout et la modification : deux copies divergeraient à la
    première évolution, et l'une des deux se mettrait à accepter ce que l'autre
    refuse. $t vaut null à l'ajout.
--}}
<div class="mb-3">
    <label class="form-label">Unité <span class="text-danger">*</span></label>
    <select name="unite_produit_id" class="form-control" required>
        <option value="">— Choisir —</option>
        @foreach ($unites as $u)
            <option value="{{ $u->id }}" @selected(old('unite_produit_id', $t->unite_produit_id ?? null) == $u->id)>
                {{ $u->libelle }}
            </option>
        @endforeach
    </select>
</div>

<div class="row gx-2">
    <div class="col-6 mb-3">
        <label class="form-label">Quantité minimale <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="unite_min" class="form-control" required
               value="{{ old('unite_min', $t->unite_min ?? 0) }}">
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">Quantité maximale <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="unite_max" class="form-control" required
               value="{{ old('unite_max', $t->unite_max ?? 0) }}">
    </div>
</div>

<div class="row gx-2">
    <div class="col-6 mb-3">
        <label class="form-label">Distance min (km) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="distance_min_km" class="form-control" required
               value="{{ old('distance_min_km', $t->distance_min_km ?? 0) }}">
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">Distance max (km) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" name="distance_max_km" class="form-control" required
               value="{{ old('distance_max_km', $t->distance_max_km ?? 0) }}">
    </div>
</div>

<div class="mb-1">
    <label class="form-label">Montant versé au livreur <span class="text-danger">*</span></label>
    <div class="input-group">
        <input type="number" step="1" min="0" name="prix" class="form-control" required
               value="{{ old('prix', $t->prix ?? 0) }}">
        <span class="input-group-text">FCFA</span>
    </div>
    <small class="text-muted">
        Pour tout le chargement de la tranche, rotations comprises. Doit rester
        inférieur au tarif client de la même tranche.
    </small>
</div>
