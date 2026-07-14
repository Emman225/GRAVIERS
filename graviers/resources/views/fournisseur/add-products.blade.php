@extends('layout.main')

@section('contenu')
        <div class="screen-overlay"></div>

                <div class="row">
                    <div class="col-9">
                        <div class="content-header">
                            <h2 class="content-title">Add New Product</h2>
                            <div>
                                <button class="btn btn-light rounded font-sm mr-5 text-body hover-up">Save to draft</button>
                                <button class="btn btn-md rounded font-sm hover-up">Publich</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Basic</h4>
                            </div>
                            <form action="" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="card-body">
                                        @if(session('succes'))
                                            <div class="alert alert-success">
                                                {{session('succes')}}
                                            </div>
                                        @endif

                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Nom du produit</label>
                                            <input type="text" placeholder="Type here" value="nom" name="nom" class="form-control" id="product_name" />
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Abreviation</label>
                                            <input type="text" placeholder="Type here" value="abr" name="abreviation" class="form-control" id="product_name" />
                                        </div>
                                        <div class="mb-4">
                                            <label for="product_name" class="form-label">Prix</label>
                                            <input type="number" placeholder="Type here" value="200" name="unite" class="form-control" id="product_name" />
                                        </div>
                                        <select class="form-control" name="categorie" id="">
                                            <option value="" selected>Choisissez une catégorie</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{$category->id}}"> {{$category->nom}} </option>
                                                @endforeach
                                                    @error('categorie')
                                                        {{$message}}
                                                    @enderror
                                           </select>
                                        <div class="mb-4">
                                            <label class="form-label">Description</label>
                                            <textarea placeholder="Type here" name="description"  class="form-control" rows="4">je décris</textarea>
                                        </div>
                                        <div class="row">
                                            <div class="col-lg-4">
                                                <div class="mb-4">
                                                    <label class="form-label">Prix moyen</label>
                                                    <div class="row gx-2">
                                                        <input placeholder="$" name="prix_moyen" type="number" value="75" class="form-control" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-4">
                                                <div class="mb-4">
                                                    <label class="form-label">Réduction</label>
                                                    <input placeholder="$" type="number" value="25" name="reduction" class="form-control" />
                                                </div>
                                            </div>

                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Tax rate</label>
                                            <input type="number" name="meilleur_note" value="1" placeholder="x/10" class="form-control" id="product_name" />
                                        </div>
                                        <div class="mb-4">
                                            <label class="form-label">Choisissez une image</label>
                                            <input type="file"  class="form-control" name="image" id="product_name" />
                                                @error('image')
                                                    {{$message}}
                                                @enderror
                                        </div>
                                        <div class="mb-4">
                                            <button type="submit" class="btn btn-primary w-100">Publier</button>

                                        </div>
                                      
                                    </div>
                            </form>
                        </div>

                    </div>


                </div>

        @endsection
