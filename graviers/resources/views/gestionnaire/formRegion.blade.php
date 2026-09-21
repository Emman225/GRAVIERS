{{-- LA SAISIE A SA PROPRE PAGE.

     Le formulaire partageait l'écran de la liste : on ne savait plus si l'on
     consultait ou si l'on saisissait, et la liste reculait de toute la hauteur
     de la carte au premier clic sur « Nouvelle région ». La carte et son style
     suivent le formulaire : la liste n'en avait pas l'usage. --}}
@extends('layout.main')
@section('title', $laRegion->id ? 'Modification de région' : 'Nouvelle région')

@section('contenu')

    <div class="row g-3">
        <div class="col-12">
            <div class="card dash-card">
                <div class="card-header dash-card-header d-flex justify-content-between align-items-center">
                    <h5 class="dash-card-title mb-0">
                        <i class="material-icons md-add_location text-primary"></i>
                        {{ $laRegion->id ? 'Modifier la région' : 'Nouvelle région' }}
                    </h5>
                    <a href="{{ route('show.lesRegions') }}" class="btn btn-sm btn-secondary"><i class="material-icons md-arrow_back align-middle"></i> Retour à la liste</a>
                </div>
                <div class="card-body">
                    <form action="{{ $laRegion->id
                            ? route('show.modifierRegionValid', $laRegion)
                            : route('show.lesRegionsValid') }}" method="post">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom de la région <span class="text-danger">*</span></label>
                            <input class="form-control" value="{{ $laRegion->nom }}" name="nom" type="text" />
                            @error('nom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adresse complète <span class="text-danger">*</span></label>
                            {{-- Le conteneur etait pose en ABSOLU, haut de 100px et
                                 large de 100% a partir de 10px du bord : il debordait
                                 a droite et recouvrait le texte « Veuillez preciser
                                 sur la carte » place en dessous. Il occupe maintenant
                                 sa propre place dans le flux. --}}
                            <div id="search-container" class="dl-recherche-region"></div>
                        </div>

                        <div class="mb-3">
                            <div>
                                <p class="h6 mb-2">Veuillez préciser sur la carte</p>
                                <div id="map" style="height: 300px; width: 100%; margin: auto; background: #cecece; border-radius: 6px;"></div>
                            </div>
                            {{-- LES COORDONNEES CHOISIES SUR LA CARTE.

                                 Elles n'existaient nulle part dans le formulaire :
                                 le script les ecrivait dans « #long » et « #lat »,
                                 mais ces champs n'existaient pas, et ses deux
                                 lignes etaient protegees par un « if » qui ne
                                 passait jamais. Rien n'etait donc transmis, et
                                 l'enregistrement echouait invariablement sur
                                 « La longitude est requise ». Aucune region ne
                                 pouvait etre creee depuis cet ecran. --}}
                            <input type="hidden" name="long" id="long"
                                   value="{{ old('long', $laRegion->long) }}" />
                            <input type="hidden" name="lat" id="lat"
                                   value="{{ old('lat', $laRegion->lat) }}" />
                            @error('long')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('lat')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            @error('adresse_geo')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons md-save"></i>
                                {{ $laRegion->id ? 'Modifier' : 'Enregistrer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <style>
        /* La barre de recherche du geocodeur, remise dans le flux de la page. */
        .dl-recherche-region .leaflet-control-geocoder {
            width: 100%; max-width: 100%; box-sizing: border-box;
            background: transparent; border: 0; box-shadow: none; margin: 0;
        }
        .dl-recherche-region .leaflet-control-geocoder-form input {
            width: 100%; box-sizing: border-box;
        }
        .dl-recherche-region .leaflet-control-geocoder-alternatives {
            width: 100%; box-sizing: border-box;
            max-height: 260px; overflow-y: auto;
            background: #fff; border: 1px solid #d4e1f0; border-radius: 10px;
            box-shadow: 0 12px 28px rgba(19, 26, 43, .14);
            list-style: none; margin: 6px 0 0; padding: 6px;
        }
        .dl-recherche-region .leaflet-control-geocoder-alternatives li a {
            display: block; padding: 10px 12px; border-radius: 8px;
            color: #131a2b; text-decoration: none; white-space: normal;
        }
        .dl-recherche-region .leaflet-control-geocoder-alternatives li a:hover,
        .dl-recherche-region .leaflet-control-geocoder-selected a { background: #e6eef9; }
    </style>
@endsection

@section('jsParts')
    <script>
        // ATTENDRE QUE LEAFLET SOIT LA.
        //
        // Le gabarit du back-office charge leaflet.js, Control.Geocoder.js et
        // recherche-lieu.js avec l'attribut « defer » (layout/footer.blade.php).
        // Un script differe ne s'execute QU'APRES l'analyse de toute la page,
        // donc APRES ce bloc-ci s'il s'executait immediatement : « L » n'existe
        // pas encore, la premiere ligne leve une erreur, et TOUT le bloc meurt.
        //
        // C'est ce qui se passait : ni carte, ni champ de recherche — un
        // rectangle gris (le fond du conteneur) et un emplacement vide sous
        // « Adresse complete ». Le tableau de droite, lui, fonctionnait : il
        // attend deja « $(function ... ) ».
        //
        // Les scripts differes s'executent AVANT « DOMContentLoaded ». Attendre
        // cet evenement suffit donc, et c'est exactement ce que fait deja
        // fournisseur/form.blade.php, dont la carte marche sur le meme gabarit.
        document.addEventListener('DOMContentLoaded', function () {
            // LA CARTE N'EXISTE QUE SUR LA PAGE DU FORMULAIRE.
            //
            // Depuis que la liste s'affiche seule, ce script tournait sur une
            // page sans conteneur : Leaflet levait « Map container not found. »
            // — une erreur non rattrapee, qui aurait fait taire tout ce qu'on
            // aurait ajoute a sa suite dans ce meme bloc.
            if (!document.getElementById('map')) return;

            var map = L.map('map').setView([5.320357, -4.016107], 13);
            var marker;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            var geocoder = creerRechercheLieu({
                title: 'Barre de recherche',
                placeholder: 'Entrez votre adresse',
                collapsed: false,
                defaultMarkGeocode: false,
            });

            var geocoderContainer = document.getElementById('search-container');
            geocoder.onAdd(map);
            geocoderContainer.appendChild(geocoder.getContainer());

            var searchInput = document.querySelector('.leaflet-control-geocoder input');
            if (searchInput) {
                searchInput.id = 'afficheAdresse';
                searchInput.name = 'adresse_geo';
                searchInput.value = '<?php echo $laRegion->description; ?>';
                searchInput.style.width = '100%';
                searchInput.style.height = '46px';
            }

            geocoder.on('markgeocode', function (e) {
                var resultsContainer = document.querySelector('.leaflet-control-geocoder-alternatives');
                if (resultsContainer) {
                    resultsContainer.style.display = 'none';
                }
            });

            // « startsuggest » s'ajoute a « startgeocode » : depuis que les suggestions
            // apparaissent des la frappe, c'est lui qui est emis. Sans cette ligne, la
            // liste refermee apres un premier choix ne serait plus jamais rouverte.
            geocoder.on('startgeocode startsuggest', function () {
                var resultsContainer = geocoder.getContainer().querySelector('.leaflet-control-geocoder-alternatives');
                if (resultsContainer) {
                    resultsContainer.style.display = 'block';
                }
            });

            function updateMarkerPosition(latlng, address = null) {
                if (marker) {
                    marker.setLatLng(latlng);
                } else {
                    marker = L.marker(latlng).addTo(map);
                }
                map.setView(latlng, 13);

                var champAdresse = document.getElementById('afficheAdresse');
                if (champAdresse && address) champAdresse.value = address;
                if (document.getElementById('long')) document.getElementById('long').value = latlng.lng;
                if (document.getElementById('lat')) document.getElementById('lat').value = latlng.lat;

            }

            geocoder.on('markgeocode', function (e) {
                updateMarkerPosition(e.geocode.center, e.geocode.name);
            });

            map.on('click', function (e) {
                var latlng = e.latlng;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`)
                    .then(response => response.json())
                    .then(data => updateMarkerPosition(latlng, data.display_name))
                    .catch(error => {
                        console.error('Erreur de géocodage:', error);
                        updateMarkerPosition(latlng);
                    });
            });
        });
    </script>
@endsection
