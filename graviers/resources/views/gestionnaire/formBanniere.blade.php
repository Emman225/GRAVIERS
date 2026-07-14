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
                <input type="text" class="form-control" name="titre" value="{{$banniere->titre}}"  id="product_name" />
                <span class="text-danger">
                    @error('titre')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <label for="product_name" class="form-label">Sous titre</label>
                <input type="text" class="form-control" name="sous_titre" value="{{$banniere->sous_titre}}"  id="product_name" />
                <span class="text-danger">
                    @error('sous_titre')
                        {{$message}}
                    @enderror
                </span>
            </div>


            <div class="mb-4">
                <label class="form-label">N° d'ordre</label>
                <input   class="form-control" name="num_ordre"  rows="4" value="{{$banniere->num_ordre}}"/>
                <span class="text-danger">
                    @error('num_ordre')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                {{-- <label class="form-label">N° d'ordre</label> --}}
                <select class="form-control" name="type_banniere"  rows="4">
                    <option value="">Type de bannière</option>
                    <option value="1" @selected($banniere->type_banniere == 'TOP') >Top</option>
                    <option value="2" @selected($banniere->type_banniere == 'FLASH')>Flash</option>
                    <option value="3" @selected($banniere->type_banniere == 'BOTTOM')>Bottom</option>
                </select>
                <span class="text-danger">
                    @error('type_banniere')
                        {{$message}}
                    @enderror
                </span>
            </div>

            <div class="mb-4">
                <label class="form-label">Heure de décompte</label>
                <input type="date" {{$banniere->titre == null ? 'required' : ''}}  class="form-control" name="heure_decompte" id="product_name" />
                <span class="text-danger">
                    @error('heure_decompte')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <label class="form-label">Choisissez une image de face</label>
                <input type="file" {{$banniere->titre == null ? 'required' : ''}}  class="form-control" name="image" id="product_name" />
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
                    <button type="submit" class="btn btn-success">{{$banniere->titre == null ? 'Publier' : 'Modifier'}}</button>
                </div>



            </div>

        </div>
</form>
