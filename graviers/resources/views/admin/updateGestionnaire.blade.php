@extends('layout.main')

@section('contenu')
@section('title','Enregistrer un gestionnaire')

                <x-back-to-list :route="route('show.listeGestionnaire')" />

                <div class="card mx-auto mt-100 "style=" width: 40rem; " >
                    @if(session('succes'))
                        <div class="alert alert-success">
                            {{session('succes')}}
                        </div>
                    @endif
                    <div class="card-body">
                        <h4 class="card-title mb-4">Modifier un compte gestionnaire</h4>
                        <form action="{{route('show.updateGestionnaire',$user)}}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Nom et prénoms</label>
                                <input class="form-control" value="{{$user->nom_prenoms}}" name="nom_prenoms"  type="text" />
                                <span style="color: red">
                                    @error('nom')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>


                            {{-- <div class="mb-3">
                                <label class="form-label">Nom</label>
                                <input class="form-control" value="{{$user->nom}}" name="nom" placeholder="Your Name" type="text" />
                                <span style="color: red">
                                    @error('nom')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Prénom</label>
                                <input class="form-control" value="{{$user->prenom}} " name="prenom" placeholder="Your first name" type="text" />
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
                                    <div class="col-8"><input value="{{$user->contact}}" class="form-control" type="number" name="contact" placeholder="Your phone number" /></div>
                                    <span style="color: red">
                                        @error('number')
                                            {{$message}}
                                        @enderror
                                    </span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input class="form-control" value="{{$user->email}}" name="email" placeholder="Your Email" type="email" />
                                <span style="color: red">
                                    @error('email')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Adresse</label>
                                <input class="form-control" value="{{$user->adresse}}" name="adresse" placeholder="Your adresse" type="text" />
                                <span style="color: red">
                                    @error('adresse')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>

                            <!-- form-group// -->
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input class="form-control" type="file" name="photo" id="photo"/>
                                <span style="color: red">
                                    @error('photo')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Login</label>
                                <input class="form-control" value="{{$user->login}}" name="login" placeholder="create a login" type="text" />
                                <span style="color: red">
                                    @error('login')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Créez un mot de passe</label>
                                <input class="form-control" value="" name="password" placeholder="Password" type="password" />
                                <span style="color: red">
                                    @error('password')
                                        {{$message}}
                                    @enderror
                                </span>
                            </div>
                            <!-- form-group// -->

                            <!-- form-group  .// -->
                            <div class="mb-4">
                                <button type="submit" class="btn btn-primary w-100">Enregistrer</button>
                            </div>
                            <!-- form-group// -->
                        </form>


                        {{-- <p class="text-center mb-2">Vous n'avez pas de compte ? <a href="{{route('show.login')}}">Se connecter</a></p> --}}
                    </div>
                </div>
     @endsection
