@extends('layout.main')
@section('title','Création de compte')
@section('contenu')
    <x-back-to-list :route="route('show.list')" />
    @if (session('login'))
        <div class="alert alert-success text-center">
            <h3>Login : {{session('login')}}</h3>
            @php
                session()->forget('login');
            @endphp
        </div>
    @endif
    <div class=" mt-25 card mx-auto "style=" width: 40rem; z-index: 0">
        <div class="card-body">
            <h4 class="card-title mb-4">Ajoutez un Livreur</h4>
            <form action="" method="post" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Nom et prénoms ou raison sociale : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('nom_prenoms') }}" name="nom_prenoms" placeholder="" type="text" />
                    <span class="text-danger">
                        @error('nom')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                {{-- <div class="mb-3">
                    <label class="form-label">Prenom livreur : <span class="text-danger">*</span></label>
                    <input class="form-control" value="" name="prenom" placeholder="" type="text" />
                    <span class="text-danger">
                        @error('nom')
                            {{$message}}
                        @enderror
                    </span>
                </div> --}}

                <div class="mb-3">
                    <label class="form-label">E-mail : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('email') }}" name="email" placeholder= "" type="text" />
                    <span class="text-danger">
                        @error('email')
                            {{$message}}
                        @enderror
                    </span>
                    @if (session('errorEmail'))
                        <span class="text-danger">
                            {{session('errorEmail')}}
                        </span>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Numéro de la pièce : <span class="text-danger">*</span></label>
                    <input class="form-control"  name="num_piece_identite" value="{{ old('num_piece_identite') }}" placeholder= "" type="text" />
                    <span class="text-danger">
                        @error('num_piece_identite')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Image recto de la pièce : <span class="text-danger">*</span></label>
                    <input class="form-control" name="piece_recto" required type="file" />
                    <span class="text-danger">
                        @error('piece_recto')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Image verso de la pièce : <span class="text-danger">*</span></label>
                    <input class="form-control"  name="piece_verso" required  type="file" />
                    <span class="text-danger">
                        @error('piece_verso')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                <label class="form-label">Adresse<span class="text-danger">*</span></label>
                    <input class="form-control"  name="adresse"  value="{{ old('adresse') }}" type="text" />
                    <span class="text-danger">
                        @error('adresse')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Zone d'intervention</label>
                    <input class="form-control" name="zone_intervention" type="text"
                           value="{{ old('zone_intervention') }}" placeholder="Ex : Yopougon, Abobo, Cocody...">
                    <span class="text-danger">
                        @error('zone_intervention')
                            {{$message}}
                        @enderror
                    </span>
                </div>

                {{-- Tarification : le forfait de base PAR DÉFAUT (paramètres > Livreurs)
                     est appliqué automatiquement à la création ; il reste modifiable
                     ensuite sur le profil du livreur. --}}
                @php $forfaitDefaut = \App\Models\Configuration::first()->forfait_base_livreur ?? 0; @endphp
                <div class="mb-3">
                    <div class="alert alert-info py-2 mb-0">
                        Tarification par défaut : <strong>forfait de base de {{ number_format($forfaitDefaut, 0, ',', ' ') }} FCFA</strong>
                        (défini dans <a href="{{ route('show.parametre') }}#tab-livreurs">Paramètres &gt; Livreurs</a>,
                        modifiable ensuite sur le profil du livreur).
                    </div>
                </div>

                <!-- form-group// -->
                <div class="mb-3">
                    <label class="form-label">Contact numero : <span class="text-danger">*</span> </label>
                    <div class="row gx-2">
                        <div class="col-4"><input disabled name="indicatif" class="form-control" value="+225" type="text" />
                        </div>

                        <div class="col-8"><input value="{{ old('contact') }}" class="form-control" type="number" name="contact"
                                placeholder="" /></div>
                                <span class="text-danger">
                                    @error('contact')
                                        {{$message}}
                                    @enderror
                                </span>
                    </div>

                </div>


                {{-- Champ mot de passe supprimé : il est généré automatiquement
                     et envoyé au livreur par email (l'admin ne doit pas le connaître). --}}
                <div class="mb-3">
                    <div class="alert alert-info py-2 mb-0">
                        <i class="fa-solid fa-circle-info"></i>
                        Le mot de passe sera <strong>généré automatiquement</strong> et envoyé au livreur par email.
                    </div>
                </div>
                <!-- form-group// -->

                <!-- form-group  .// -->
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
                <!-- form-group// -->
            </form>


            {{-- <p class="text-center mb-2">Already have an account? <a href="{{route('show.login')}}">Sign in now</a></p> --}}
        </div>
    </div>
@endsection

@section('jsParts')
    <script type="text/javascript">
        $(function() {
            $('#produits').select();
        });
    </script>
@endsection
