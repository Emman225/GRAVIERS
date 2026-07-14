@php
// recuperation des produits associés au fournisseur
$produitSelectionne = $fournisseur->produits()->pluck('produit_id');

@endphp
@include('fournisseur.style')
@if (session('updated'))
    <div class="alert alert-success text-center" id="notify">
        {{session('updated')}}
    </div>
@endif
{{-- @dd($fournisseur->user) --}}
    @if ($errors->any())
        <div class="alert alert-danger mx-auto" style="width: 50rem;">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class=" mt-20 card mx-auto "style=" width: 50rem; ">
        <div class="card-body" style="position: relative;
    overflow: visible;
    z-index: 10;">

                    <h4 class="card-title mb-4">Ajouter un Fournisseur</h4>
            <form action="{{route('show.storeSeller') }}" method="post" enctype="multipart/form-data" >
                @csrf
                {{-- <div class="mb-3" style="max-height: 300px; overflow-y: auto; overflow-x: hidden; border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                    <select class="form-multi-select" id="ms1" multiple multiple data-coreui-search="global">
                        @foreach ($produits as $p )
                            <option value="{{ $p->id }}">{{$p->nom}}</option>
                        @endforeach
                    </select>
                </div> --}}
                <div class="mb-3">
                    <label class="form-label">Nom et prénoms ou raison sociale  : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('nom_prenoms', $fournisseur->nom) }}" name="nom_prenoms" type="text" />
                    <span class="text-danger">
                        @error('nom_prenoms')
                            {{$message}}
                        @enderror
                    </span>
                </div>

                <div class="mb-3">
                    <label class="form-label">E-mail : <span class="text-danger">*</span></label>
                    <input class="form-control" value="{{ old('email', $fournisseur->user?->email) }}" name="email"  type="text" />
                    <span class="text-danger">
                        @error('email')
                            {{$message}}
                        @enderror
                        @if(session('emailExist'))
                            {{session('emailExist')}}
                        @endif
                    </span>
                </div>

                <!-- form-group// -->
                <div class="mb-3">
                    <label class="form-label">Contact numero 1 : <span class="text-danger">*</span> </label>
                    <div class="row gx-2">
                        <div class="col-4"><input name="indicatif" class="form-control" disabled value="+225" type="text" />
                        </div>
                        <div class="col-8">
                            <input value="{{ old('contact', $fournisseur->contact1) }}" class="form-control" type="number" name="contact"/>
                            <span class="text-danger">
                                @error('contact')
                                    {{$message}}
                                @enderror
                            </span>
                        </div>

                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact numero 2: </label>
                    <div class="row gx-2">
                        <div class="col-4"><input disabled name="indicatif" class="form-control" value="+225" type="text" />
                        </div>
                        <div class="col-8"><input value="{{ old('contact2', $fournisseur->contact2) }}" class="form-control" type="number" name="contact2"/>
                            <span class="text-danger">
                                @error('contact2')
                                    {{$message}}
                                @enderror
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Type de fournisseur + produit principal (alimentent les colonnes
                     "Type" et "Produit principal" de la liste des fournisseurs). --}}
                <div class="mb-3">
                    <label class="form-label">Type de fournisseur</label>
                    @php $typeFrs = old('type_fournisseur', $fournisseur->type_fournisseur); @endphp
                    <select class="form-control" name="type_fournisseur">
                        <option value="">-- Sélectionner --</option>
                        @foreach (['Carrière','Producteur','Grossiste','Détaillant','Importateur','Autre'] as $tf)
                            <option value="{{ $tf }}" {{ $typeFrs === $tf ? 'selected' : '' }}>{{ $tf }}</option>
                        @endforeach
                    </select>
                    <span class="text-danger">@error('type_fournisseur'){{ $message }}@enderror</span>
                </div>

                <div class="mb-3">
                    <label class="form-label">Produit principal</label>
                    @php $prodPrincipal = old('produit_principal', $fournisseur->produit_principal); @endphp
                    <select class="form-control" name="produit_principal">
                        <option value="">-- Sélectionner --</option>
                        @foreach ($produits as $pp)
                            <option value="{{ $pp->nom }}" {{ $prodPrincipal === $pp->nom ? 'selected' : '' }}>{{ $pp->nom }}</option>
                        @endforeach
                    </select>
                    <span class="text-danger">@error('produit_principal'){{ $message }}@enderror</span>
                </div>

                <div class="mb-3">
                    {{-- <label class="form-label">Coordonnées <span class="text-danger">*</span> </label> --}}
                    <div class="row gx-2">
                        <div class="col-6">
                            <input name="long" type="hidden" class="form-control" placeholder="Longitude" id="long" type="text" />
                            <span class="text-danger">
                                {{-- @error('long')
                                    {{$message}}
                                @enderror --}}
                            </span>
                        </div>
                        <div class="col-6"><input type="hidden" placeholder="Lattitude" class="form-control" type="text" name="lat" id="lat"/>
                            <span class="text-danger">
                                {{-- @error('lat')
                                    {{$message}}
                                @enderror --}}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adresse complète <span class="text-danger">*</span> </label>
                    <div class="row gx-2">
                        <div class="col-12">
                            <div style="position: relative;margin-bottom: 3rem">
                                <div id="search-container" style="position: absolute; top: 10px; left: 10px; height: 100px; width: 100%; margin-bottom: 3rem; z-index: 9999"></div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="mb-3">
                    {{-- <label class="form-label">Situation Géographique : <span class="text-danger">*</span></label> --}}
                    {{-- <textarea class="form-control" id="adresseGeo" name="adresse_geo"> {{$fournisseur->adresse_geo}}</textarea> --}}

                    <div class="container" style="margin-top: 3rem;">
                        <p for="" class="h5">Veuillez préciser sur la carte</p>
                        <div id="map" style="height: 300px; width: 100%; margin: auto; background: #cecece"></div>
                    </div>
                    {{-- <div id="coordinates"></div> --}}
                    <span class="text-danger">
                        @error('long')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adresse postale</label>
                    <input class="form-control"  value="{{ old('adresse_postale', $fournisseur->adresse_postale) }}" name="adresse_postale" placeholder="Votre adresse"
                        type="text" />
                        <span class="text-danger">
                            @error('adresse_postale')
                                {{$message}}
                            @enderror
                        </span>
                </div>

                <div class="mb-3">
                    <label class="form-label">DFE (Déclaration Fiscale d'Existence)</label>
                    <input class="form-control" name="dfe" type="file" accept=".jpg,.jpeg,.png,.pdf" />
                    <span class="text-danger">
                        @error('dfe')
                            {{$message}}
                        @enderror
                    </span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Registre du commerce</label>
                    <input class="form-control" name="registre_commerce" type="file" accept=".jpg,.jpeg,.png,.pdf" />
                    <span class="text-danger">
                        @error('registre_commerce')
                            {{$message}}
                        @enderror
                    </span>
                </div>

                <div class="mb-3" style="height: 300px; overflow-y: auto; overflow-x: hidden; border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                    <label for="ms1" class="form-label">Sélectionner les produits du fournisseur</label>
                    <select name="produits[]" class="form-multi-select" id="ms1" multiple multiple data-coreui-search="global">
                        @foreach ($produits as $p )
                            <option value="{{ $p->id }}">{{$p->nom}}</option>
                        @endforeach
                    </select>
                    {{-- <select style="height: 100px" name="produits[]" multiple class="form-select" id="produits">
                        @foreach ($produits as $produit)
                            <option @selected($produitSelectionne->contains($produit->id))  value="{{ $produit->id }}">{{ $produit->nom }}</option>
                        @endforeach
                    </select> --}}
                    <span class="text-danger">
                        @error('produits')
                            {{$message}}
                        @enderror
                    </span>
                </div>

                {{-- Mot de passe auto-généré et envoyé par email au fournisseur --}}
                @if($fournisseur->user)
                    <input type="hidden" @if($fournisseur->user !==null) value="{{$fournisseur->id}}" @endif name="id">
                @endif
                <!-- form-group// -->

                <!-- form-group  .// -->
                {{-- @if() --}}
                {{-- @if($fournisseur->user == null) --}}
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
                {{-- @else
                <div class="mb-4">
                    <button type="submit" class="btn btn-primary" formaction="{{route('sellers.updateSeller')}}">Modifier</button>
                </div>
                @endif --}}
                <!-- form-group// -->
            </form>


            {{-- <p class="text-center mb-2">Already have an account? <a href="{{route('show.login')}}">Sign in now</a></p> --}}
        </div>
    </div>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $ !== 'undefined') {
            $(function() {
                if ($('#produits').length) $('#produits').select();
            });
        }
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
        // alert('ok')
        var map = L.map('map').setView([5.320357, -4.016107], 13);
        var marker;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var geocoder = L.Control.geocoder({
            title: 'Barre de recherche',
            placeholder: 'Entrez votre adresse',
            collapsed: false,
            defaultMarkGeocode: false,

        });

        var geocoderContainer = document.getElementById('search-container');
        geocoder.onAdd(map);  // Cette étape initialise le contrôle
        geocoderContainer.appendChild(geocoder.getContainer());


        var searchInput = document.querySelector('.leaflet-control-geocoder input');
            if (searchInput) {
                searchInput.id = 'afficheAdresse'; // Ajouter l'ID
                searchInput.name = 'adresse_geo'; // Ajouter le name
                // searchInput.style.backgroundColor = 'red';
                searchInput.style.width = '500px';
                searchInput.style.height = '50px';
            }

        // *********************************
        geocoder.on('markgeocode', function (e) {
            // Masquer ou supprimer la liste des résultats
            var resultsContainer = document.querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'none'; // Masquer la liste
                // ou
                // resultsContainer.remove(); // Supprimer la liste
            }
        });

            geocoder.on('startgeocode', function() {
                var resultsContainer = geocoder.getContainer().querySelector('.leaflet-control-geocoder-alternatives');
                if (resultsContainer) {
                    resultsContainer.style.display = 'block'; // Rétablir l'affichage par défaut
                }
            });
        //
        // Fonction pour mettre à jour la position du marqueur et les informations


        function updateMarkerPosition(latlng, address = null) {
            if (marker) {
                marker.setLatLng(latlng);
            } else {
                marker = L.marker(latlng).addTo(map);
            }
            map.setView(latlng, 13);

            document.getElementById('afficheAdresse').value = address
            // document.getElementById('coordinates').innerHTML =
            //     // 'Latitude: ' + latlng.lat.toFixed(6) + ',  Longitude: ' +latlng.lng.toFixed(6)+', Adresse: '+ address;
            //     'Latitude: ' + latlng.lat.toFixed(6) + ', Longitudde: ' + latlng.lng.toFixed(6);
            document.getElementById('long').value = latlng.lng;
            document.getElementById('lat').value = latlng.lat;
            // document.getElementById('adresse').value = address;
            // document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        lat: latlng.lat,
                        lng: latlng.lng,
                        address: address
                    })
                })
                .then(response => response.json())
                .then(data => console.log(data))
                .catch(error => console.error('Error:', error));
        }

        geocoder.on('markgeocode', function(e) {
            var latlng = e.geocode.center;
            updateMarkerPosition(latlng, e.geocode.name);
        });

        map.on('click', function(e) {

            var latlng = e.latlng;

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)

                .then(response => response.json())
                .then(data => {
                    var address = data.display_name; // Nom complet du lieu
                    console.log(address);
                    updateMarkerPosition(latlng, address);
                })
                .catch(error => {
                    console.error('Erreur de géocodage:', error);
                    updateMarkerPosition(latlng); // Sans adresse en cas d'erreur
                });

            // updateMarkerPosition(e.latlng);

        });
});
    </script>
