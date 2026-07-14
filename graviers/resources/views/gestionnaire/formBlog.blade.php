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
                <input type="text" class="form-control" name="titre" value="{{$blog->titre}}"  id="product_name" />
                <span class="text-danger">
                    @error('titre')
                        {{$message}}
                    @enderror
                </span>
            </div>


            <div class="mb-4">
                <label class="form-label">Description</label>
                <textarea   class="form-control" name="description"  rows="4"> {{$blog->description}} </textarea>
                <span class="text-danger">
                    @error('description')
                        {{$message}}
                    @enderror
                </span>
            </div>

            <div class="mb-4">
                <label class="form-label">Choisissez une image de face</label>
                <input type="file" {{$blog->titre == null ? 'required' : ''}}  class="form-control" name="image" id="product_name" />
                <span class="text-danger">
                    @error('image')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <label class="form-label">Choisissez une image de detail</label>
                <input type="file" {{$blog->titre == null ? 'required' : ''}}  class="form-control" name="image_detail" id="product_name" />
                <span class="text-danger">
                    @error('image_detail')
                        {{$message}}
                    @enderror
                </span>
            </div>
            <div class="mb-4">
                <button type="submit" class="btn btn-primary w-100">
                    {{$blog->titre == null ? 'Publier' : 'Modifier'}}
                </button>

            </div>
            {{-- <label class="form-check mb-4">
                <input class="form-check-input" type="checkbox" value="" />
                <span class="form-check-label"> Make a template </span>
            </label> --}}
        </div>
</form>
