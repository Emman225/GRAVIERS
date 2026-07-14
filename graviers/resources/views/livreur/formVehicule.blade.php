<h4 class="card-title mb-4">{{$vehicule->id? 'Modifier' : 'Ajouter'}} un vehicule</h4>
        <form action="" method="post" >
            @csrf
            <input type="hidden" name="mode" value="{{$mode}}">
            <input type="hidden" name="vehicule" value="{{$vehicule}}" >
            <div class="mb-3">
                <select name="type_vehicule" class="form-control" id="">
                    <option value="">Type de Vehicule</option>
                    @foreach ($types as $type)
                        <option @selected($vehicule->type_vehicule_id == $type->id ) value="{{$type->id}}"> {{$type->libelle}} </option>
                    @endforeach
                </select>
                 <span class="text-danger">
                    @error('type_vehicule')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div class="mb-3">
                <label class="form-label">Nom : <span class="text-danger">*</span></label>
                <input class="form-control" value="{{old('nom',$vehicule->nom)}}" name="nom" type="text" />
                <span class="text-danger">
                    @error('nom')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div class="mb-3">
                <label class="form-label">Matricule : <span class="text-danger">*</span></label>
                <input class="form-control" value="{{old('matricule', $vehicule->immatriculation)}}" name="matricule" type="text" />
                <span class="text-danger">
                    @error('matricule')
                        {{ $message }}
                    @enderror
                </span>
            </div>

            <div class="mb-3">
                <label class="form-label">Marque : <span class="text-danger">*</span></label>
                <input class="form-control" value="{{old('marque', $vehicule->marque)}}" name="marque" type="text" />
                <span class="text-danger">
                    @error('marque')
                        {{ $message }}
                    @enderror
                </span>
            </div>

            <div class="mb-3">
                <label class="form-label">Modèle : <span class="text-danger">*</span></label>
                <input class="form-control" value="{{old('modele', $vehicule->modele)}}" name="modele" type="text" />
                <span class="text-danger">
                    @error('modele')
                        {{ $message }}
                    @enderror
                </span>
            </div>


            <div class="mb-3">
                <label class="form-label"> Capacité (en tonne) : <span class="text-danger">*</span></label>
                <input class="form-control" name="capacite" value="{{old('capacite', $vehicule->capacite)}}" type="number" />
                <span class="text-danger">
                    @error('capacite')
                        {{ $message }}
                    @enderror
                </span>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
