@extends('layout.main')

@section('contenu')
@section('title', 'Enregistrer un agent')

<x-back-to-list :route="route('show.listeAgent')" />

<div class="card mx-auto mt-100 "style=" width: 40rem; ">
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    <div class="card-body">
        <h4 class="card-title mb-4">Créez un compte Agent</h4>
        <form action="" method="post" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nom et prénoms</label>
                <input class="form-control" name="nom_prenoms" type="text" />
                <span style="color: red">
                    @error('nom_prenoms')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            {{-- <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input class="form-control" value="nume" name="nom" placeholder="Your Name" type="text" />
                                <span style="color: red">
                                    @error('nom')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input class="form-control" value="risk" name="prenom" placeholder="Your first name" type="text" />
                                <span style="color: red">
                                    @error('prenom')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div> --}}

            <div class="mb-3">
                <label class="form-label">Numéro de téléphone</label>
                <div class="row gx-2">
                    {{-- <div class="col-4"><input name="indicatif" class="form-control" value="+225" type="text" /></div> --}}
                    <div class="col-8"><input class="form-control" type="number" name="contact" /></div>
                    <span style="color: red">
                        @error('number')
                            {{ $message }}
                        @enderror
                    </span>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" name="email" type="email" />
                <span style="color: red">
                    @error('email')
                        {{ $message }}
                    @enderror
                </span>
            </div>

            <div class="mb-3">
                <label class="form-label">Adresse</label>
                <input class="form-control" name="adresse" type="text" />
                <span style="color: red">
                    @error('adresse')
                        {{ $message }}
                    @enderror
                </span>
            </div>

            <!-- form-group// -->
            <div class="mb-3">
                <label class="form-label">Image</label>
                <input class="form-control" type="file" name="photo" id="photo" />
                <span style="color: red">
                    @error('photo')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            <div class="mb-3">
                <label class="form-label">Login</label>
                <input class="form-control" name="login" type="text" />
                <span style="color: red">
                    @error('login')
                        {{ $message }}
                    @enderror
                </span>
            </div>
            {{-- Champ mot de passe supprimé : généré automatiquement et envoyé
                 à l'agent par email (l'admin ne doit pas le connaître). --}}
            <div class="mb-3">
                <div class="alert alert-info py-2 mb-0">
                    Le mot de passe sera <strong>généré automatiquement</strong> et envoyé à l'agent par email.
                </div>
            </div>
            <!-- form-group// -->

            <!-- form-group  .// -->
            <div class="mb-4">
                <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
            </div>
            <!-- form-group// -->
        </form>
        <a href="{{ route('show.listeAgent') }}" class="fw-bold">Liste des agents</a>


    </div>
</div>
@endsection
