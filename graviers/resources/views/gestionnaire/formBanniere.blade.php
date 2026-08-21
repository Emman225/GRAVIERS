<form action="" method="post" enctype="multipart/form-data">
    @csrf
    <div class="card-body">
            @if(session('ok'))
                <div class="alert alert-success text-center" id="notify">
                    {{session('ok')}}
                </div>
            @endif

            <div class="mb-4">
                <label for="product_name" class="form-label">Titre</label>
                <input type="text" class="form-control" name="titre" value="{{ old('titre', $banniere->titre) }}" required />
                <span class="text-danger">
                    @error('titre')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <label for="product_name" class="form-label">Sous titre</label>
                <input type="text" class="form-control" name="sous_titre" value="{{ old('sous_titre', $banniere->sous_titre) }}" />
                <span class="text-danger">
                    @error('sous_titre')
                        {{$message}}
                    @enderror
                </span>
            </div>


            <div class="mb-4">
                <label class="form-label">N° d'ordre</label>
                <input type="number" min="0" max="999" class="form-control" name="num_ordre" value="{{ old('num_ordre', $banniere->num_ordre) }}" required />
                <span class="text-danger">
                    @error('num_ordre')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                {{-- <label class="form-label">N° d'ordre</label> --}}
                {{-- Les valeurs envoyées sont désormais les libellés eux-mêmes.
                     Avant, le formulaire envoyait 1, 2 et 3 : MySQL les interprétait
                     comme les rangs de la liste ENUM et retombait par chance sur les
                     bonnes valeurs. Le jour où l'ordre de cette liste changeait, toutes
                     les bannières créées auraient basculé dans le mauvais type. --}}
                <select class="form-control" name="type_banniere" required>
                    <option value="">Type de bannière</option>
                    <option value="TOP" @selected(old('type_banniere', $banniere->type_banniere) == 'TOP')>Top</option>
                    <option value="FLASH" @selected(old('type_banniere', $banniere->type_banniere) == 'FLASH')>Flash</option>
                    <option value="BOTTOM" @selected(old('type_banniere', $banniere->type_banniere) == 'BOTTOM')>Bottom</option>
                </select>
                <span class="text-danger">
                    @error('type_banniere')
                        {{$message}}
                    @enderror
                </span>
            </div>

            <div class="mb-4">
                <label class="form-label">Date de fin du décompte <small class="text-muted">(bannières Flash)</small></label>
                {{-- La valeur existante est maintenant reprise : le champ repartait vide
                     à chaque modification, et la date enregistrée était perdue. --}}
                <input type="date" class="form-control" name="heure_decompte"
                       value="{{ old('heure_decompte', $banniere->date_heure_decompte ? \Carbon\Carbon::parse($banniere->date_heure_decompte)->format('Y-m-d') : '') }}" />
                <span class="text-danger">
                    @error('heure_decompte')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <label class="form-label">
                    {{ $banniere->exists ? 'Remplacer l\'image' : 'Choisissez une image de face' }}
                </label>

                {{-- Aperçu de l'image en place : sans lui, impossible de savoir ce qu'on
                     s'apprête à remplacer. --}}
                @if ($banniere->exists && $banniere->image)
                    <div class="mb-2">
                        <img src="{{ asset('storage/'.$banniere->image) }}" alt="Bannière actuelle"
                             class="img-thumbnail" style="max-height:120px">
                        <small class="d-block text-muted">Image actuelle — laissez le champ vide pour la conserver.</small>
                    </div>
                @endif

                <input type="file" {{ $banniere->exists ? '' : 'required' }} class="form-control"
                       name="image" accept="image/*" />
                <small class="text-muted">jpg, jpeg, png ou webp — 2 Mo maximum.</small>
                <span class="text-danger">
                    @error('image')
                        {{$message}}
                    @enderror
                </span>
            </div>

            <div class="mb-4 row">
                <div class="col-6">
                    <a href="{{route('show.listeDesBannieres')}}" class="btn btn-primary">Retour</a>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn btn-success">{{ $banniere->exists ? 'Enregistrer les modifications' : 'Créer la bannière' }}</button>
                </div>



            </div>

        </div>
</form>
