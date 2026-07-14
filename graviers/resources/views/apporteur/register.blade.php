{{-- @extends('layout.main')
@section('title', 'Création de compte')
@section('contenu')
    @extends('layout.main')
@section('contenu') --}}
@include('layout.head')
@section('title','Création de compte')

    <div class="card mt-200 mx-auto "style=" width: 40rem; ">
        @if (session('succes'))
            <div class="alert alert-success">
                {{ session('succes') }}
            </div>
        @endif
        <div class="card-body">
            <div class="img" style="display:flex; align-item:center; justify-content: center">
                <img class="img-lg center" src="{{ asset(config("constantes.logo")) }}" alt="User" />
            </div>
            <h4 class="card-title text-center mb-4">Ajoutez appoprteur d'affaire</h4>
            <form action="" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nom complet : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('nom_prenom') }}" name="nom_prenom"
                        type="text" />
                    <span class="text-danger">
                        @error('nom_prenom')
                            {{ $message }}
                        @enderror
                    </span>
                </div>

                <div class="mb-3">
                    <label class="form-label">E-mail : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('email') }}" name="email"
                        type="text" />
                        @if(session('existEmail'))
                            <span class="text-danger">{{session('existEmail')}}</span>
                        @endif
                    <span class="text-danger">
                        @error('email')
                            {{ $message }}
                        @enderror
                    </span>
                </div>

                <!-- form-group// -->
                <div class="mb-3">
                    <label class="form-label">Contact numero : <span class="text-danger">*</span> </label>
                    <div class="row gx-2">
                        <div class="col-4"><input name="indicatif" class="form-control" value="+225" disabled type="text" />
                        </div>

                        <div class="col-8"><input value="{{ old(key: 'contact') }}" class="form-control" type="number" name="contact"
                             /></div>
                        <span class="text-danger">
                            @error('contact')
                                {{ $message }}
                            @enderror
                        </span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Lieu de résidence<span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('adresse') }}" name="adresse"
                        type="text" />
                    <span class="text-danger">
                        @error('adresse')
                            {{ $message }}
                        @enderror
                    </span>
                </div>

                <div class="mb-3">
                    <label class="form-label">Zone d'intervention</label>
                    <input class="form-control" value="{{ old('zone_intervention') }}" name="zone_intervention"
                        type="text" placeholder="Ex: Abidjan - Cocody, Yopougon..." />
                    <span class="text-danger">
                        @error('zone_intervention')
                            {{ $message }}
                        @enderror
                    </span>
                </div>

                <div class="mb-3">
                    {{-- <label class="form-label">Contact numero : <span class="text-danger">*</span> </label> --}}
                    <div class="row d-flex justify-content-center gx-2">
                        <div class="col-6">
                            <label for="">Pièce recto</label>
                            <input name="recto" class="form-control" type="file" />
                            <span class="text-danger">
                                @error('recto')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>

                        <div class="col-6">
                            <label for=""> pièce verso </label>
                            <div class="">
                                <input value="" class="form-control" type="file" name="verso"/>

                            </div>
                            <span class="text-danger">
                                @error('verso')
                                    {{ $message }}
                                @enderror
                            </span>
                        </div>
                    </div>

                </div>



                <div class="mb-3">
                    <label class="form-label">Créer un mot de passe</label>
                    <div class="position-relative">
                        <input class="form-control" value="" name="password" type="password" id="password"/>
                        <span class="position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;" id="oeil" onclick="togglePassword()"><i class="fa-solid fa-eye-slash"></i></span>
                    </div>
                    <span class="text-danger">
                        @error('password')
                            {{ $message }}
                        @enderror
                    </span>
                </div>
                <!-- form-group// -->

                <!-- form-group  .// -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary ">Enregistrer</button>
                </div>
                <!-- form-group// -->
            </form>


            <p class="text-center mb-2">Vous avez déjà un compte? <a href="{{route('apporteur.login')}}">Connectez vous !!</a></p>
        </div>
    </div>
    @include('layout.footer')
