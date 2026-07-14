@extends('client.main')
@section('title', 'Demande de livraison')
@section('content')
    <main class="main">

        {{-- @dd(Cart::content()) --}}
        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Demande de livraison</h1>

                </div>
            </div>
            <div class="container">

                <a href="{{ route('client.monCompte') }}" class="btn btn-primary">Demandes de livraison en cours</a>

            </div>
            <div class="row">
                <div class="col-lg-10 container">
                    <div class="row mb-50">


                    </div>
                    <button class="btn" id="btnAjt">+ ajouter un produit</button>
                    <div class="row">
                        {{-- <h4 class="mb-30">Ajoutez votre adresse</h4> --}}
                        <form method="post" action="{{ route('client.recapLivraison') }}">
                            @csrf
                            <div class="row shipping_calculator">
                                <div class="container" id="info">

                                    <table class="table table-bordered mt-5" id="table" style="border: 1px solid #000;">
                                        <thead>
                                            <th>Produit</th>
                                            <th>Description</th>
                                            <th>Qté</th>
                                            <th>Unité</th>
                                            <th>Action</th>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input style="border: 1px solid #000;" type="text" required
                                                        style="margin-top: 3rem;" name="produit[]" class="form-control"
                                                        placeholder="Nom du produit">
                                                </td>
                                                <td>
                                                    <textarea name="description[]" style="border: 1px solid #000;" required class="form-control "
                                                        style="margin-top: 3rem; height: 200px" id="" placeholder="Description" cols="30" rows="10"></textarea>
                                                </td>
                                                <td>
                                                    <input class="form-control" style="border: 1px solid #000;" required
                                                        name="qte[]" placeholder="Quantité" type="number" />
                                                </td>
                                                <td>
                                                    <select required class="form-select " style="border: 1px solid #000;"
                                                        name="unite[]">
                                                        <option value="">Unité</option>
                                                        @foreach ($unites as $unite)
                                                            @if ($unite->id !== 5)
                                                                <option value="{{ $unite->id }}"> {{ $unite->libelle }}
                                                                </option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <a class="btn btn-danger bg-danger">x</a>
                                                </td>

                                            </tr>
                                        </tbody>

                                    </table>
                                    <hr>

                                </div>
                                <div class="container">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="custom_select mb-5" style="margin-top: 3rem;">
                                                <select required class="form-control" style="border: 1px solid #000;"
                                                    name="paiement">

                                                    <option> Choisissez un mode de paiement</option>
                                                    @foreach ($paiements as $paiement)
                                                        <option value="{{ $paiement->id }}">{{ $paiement->libelle }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="custom_select mb-5" style="margin-top: 3rem;">
                                                <select required class="form-control" style="border: 1px solid #000;"
                                                    name="type_livraison">
                                                    <option value="">Choisissez le type de livraison souhaité...
                                                    </option>
                                                    @foreach ($types_livraison as $type_livraison)
                                                        <option value="{{ $type_livraison->libelle }}">
                                                            {{ $type_livraison->libelle }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="custom_select mb-5" style="margin-top: 1rem;">
                                                <label for="">Date de livraison souhaitée</label>
                                                <input required style="border: 1px solid #000;" type="date"
                                                    class="form-control" min="{{now()->format('Y-m-d')}}" name="date" value="{{ date('Y-m-d') }}">
                                            </div>

                                        </div>
                                    </div>


                                </div>
                                {{-- <input type="text" name="poids" class="form-control" placeholder="Poids du vehicule souhaité (en tonne)"> --}}

                                <div class="container" style="margin-top: 3rem;">
                                    <H3>Lieu de prise en charge</h3>
                                    <div id="demo"></div>
                                    <div class="custom_select mb-5 mt-2">
                                        <select required class="form-control select-active" style="border: 1px solid #000;"
                                            name="ville" id="ville">
                                            <option value="">Selectionnez une ville...</option>
                                            @foreach ($villes as $ville)
                                                <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{-- <input type="text" required class=" mb-5 mt-5 form-control" disabled id="affichage" placeholder="Lieu de pris en charge" value=""> --}}
                                    <div style="position: relative;margin-bottom: 7rem">
                                        <div id="search-container1" style="position: absolute; top: 10px; left: 10px; height: 70px; width: 100%; margin-bottom: 3rem; z-index: 999"></div>
                                    </div>
                                    <p for="" class="text-center h5">Veuillez préciser sur la carte</p>
                                    <div id="map"
                                        style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>
                                    <div class="text-center" id="coordinates"></div>
                                    <div class="text-center" id="latlng"></div>
                                    <input required type="hidden" name="long" id="long"><br><br>
                                    <input required type="hidden" name="lat" id="lat">
                                    <input required type="hidden" name="affichage" id="affichages">
                                </div>

                                <div class="container" style="margin-top: 3rem;">
                                    <H3>Lieu de destination</h3>
                                    <div class="custom_select mb-5 mt-2">
                                        <select required class="form-control select-active" style="border: 1px solid #000;"
                                            style="border: 1px solid #000;" id="ville1" name="ville1">
                                            <option value="">Selectionnez une ville...</option>
                                            @foreach ($villes as $ville)
                                                <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div style="position: relative;margin-bottom: 7rem">
                                        <div id="search-container2" style="position: absolute; top: 10px; left: 10px; height: 70px; width: 100%; margin-bottom: 3rem; z-index: 999"></div>
                                    </div>

                                    {{-- <input required type="text" class=" mb-5 mt-5 form-control" id="affichage1"
                                        disabled placeholder="Lieu de destination" value=""> --}}
                                    <p for="" class="text-center h5">Veuillez préciser sur la carte</p>
                                    <div id="map1"
                                        style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>

                                    <div class="text-center" id="coordinates1"></div>
                                    <div class="text-center" id="latlng1"></div>
                                    {{-- info bon de commande --}}

                                    <div class="row shipping_calculator mt-50 text-center">
                                        <div class="form-group col-lg-10">
                                            <H3> Bon de commande </h3>

                                            <div class=" custom_select">
                                                <input type="text"
                                                    {{ Auth::user()->client->client_a_terme ? 'required' : '' }}
                                                    placeholder="Entrez un numero de bon commande"
                                                    style="border: solid 1px grey" class="form-control"
                                                    name="numero_bon">
                                            </div>

                                            <div class="mt-20 custom_select">
                                                <input type="file"
                                                    {{ Auth::user()->client->client_a_terme ? 'required' : '' }}
                                                    placeholder="Entrez un numero de bon commande"
                                                    style="border: solid 1px grey" class="form-control" name="fichier">
                                            </div>

                                        </div>

                                    </div>

                                    {{-- info bon de commande --}}
                                    <input required type="hidden" name="long1" id="long1"><br><br>
                                    <input required type="hidden" name="lat1" id="lat1">
                                    <input required type="hidden" name="km" id="km">
                                    <input required type="hidden" name="affichage1" id="affichages1">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-fill-out btn-block mb-30">Voir le récapitulatif</button>
                            {{-- Ce bouton s'affiche si le client veut passer une commande --}}
                        </form>
                    </div>
                </div>
                {{-- <div class="col-lg-5">
                    <div class="border p-40 cart-totals ml-30 mb-50">
                        <div class="d-flex align-items-end justify-content-between mb-30">
                            <h4>Votre commande</h4>
                            <h6 class=" h3" id="lePrix"></h6>
                        </div>
                        <div class="divider-2 mb-30"></div>
                        <div class="table-responsive order_table checkout">
                            <table class="table no-border">
                                <tbody>

                                        <tr>
                                            <td class="image product-thumbnail"><img src="" alt="#"></td>
                                            <td>
                                                <h6 class="w-160 mb-5"><a href="shop-product-full.html" class="text-heading"></a></h6></span>
                                                <div class="product-rate-cover">
                                                    <span class="font-small ml-5 text-muted"></span>
                                                </div>
                                            </td>
                                            <td>
                                                <h6 class="text-muted pl-20 pr-20"></h6>
                                            </td>
                                            <td>
                                                <h4 class="text-brand" id="prix">gff</h4>
                                            </td>
                                        </tr>


                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> --}}
            </div>
        </div>
    </main>
@endsection
@section('jspart')

    {{-- TRAITEMENT DE LA CARTE 1 --}}
    <script>
        // let lat = 5.247091;
        // let lon = -5.009180;
        // let lat = 5.320357;
        // let lon = -4.016107;
        let lat = 5.320357;
        let lon = -4.016107;

        if ('geolocation' in navigator) {

            function success(position) {
                lat = position.coords.latitude;
                lon = position.coords.longitude;
                console.log(position.coords.latitude);
            }
            navigator.geolocation.getCurrentPosition(success);
        }






        var map = L.map('map').setView([lat, lon], 13);
        var marker, pt1

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var geocoder = L.Control.geocoder({
            // position: 'topright',
            titLe: 'Barre de recherche',
            placeholder: 'Entrez votre adresse',
            collapsed: false,
            defaultMarkGeocode: false
        }).addTo(map);

        // INITIALISATION DE LA BARRE DE RECHERCHE
        var geocoder = L.Control.geocoder({
            title: 'Barre de recherche',
            placeholder: 'Entrez votre adresse',
            collapsed: false,
            defaultMarkGeocode: false,

        });

        // LE CONTENEUR DE LA BARRE DE RECHERCHE
        var geocoderContainer = document.getElementById('search-container1');
        geocoder.onAdd(map);  // Cette étape initialise le contrôle
        geocoderContainer.appendChild(geocoder.getContainer());

        // AJOUT DE STYLE A LA BARRE DE RECHERCHE
        var searchInput = document.querySelector('.leaflet-control-geocoder-form input');
            if (searchInput) {
                searchInput.id = 'afficheAdresse1'; // Ajouter l'ID
                searchInput.name = 'infoSup'; // Ajouter le name
                // searchInput.style.backgroundColor = 'red';
                searchInput.style.width = '500px;';
            }

            //SUPPRIMER LE RESULTAT DE RECHERCHE
        geocoder.on('markgeocode', function (e) {
            // Masquer ou supprimer la liste des résultats
            var resultsContainer = document.querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'none'; // Masquer la liste
                // ou
                // resultsContainer.remove(); // Supprimer la liste
            }
        });

        // RETABLIR L'AFFICHAGE PAR DEFAUT
        geocoder.on('startgeocode', function() {
            var resultsContainer = geocoder.getContainer().querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'block'; // Rétablir l'affichage par défaut
            }
        });

        function updateMarkerPosition(latlng, address = null) {
            if (marker) {
                marker.setLatLng(latlng);
            } else {
                marker = L.marker(latlng).addTo(map);
            }
            map.setView(latlng, 13);

            document.getElementById('coordinates').innerHTML =
                'Latitude: ' + latlng.lat.toFixed(6) + ', Longitude: ' + latlng.lng.toFixed(6);
            // document.getElementById('affichage').value = address;
            document.getElementById('long').value = latlng.lng;
            document.getElementById('lat').value = latlng.lat;
            document.getElementById('affichages').value = address;
            console.log(address)

            pt1 = latlng;



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
                    updateMarkerPosition(latlng, address);
                })
                .catch(error => {
                    console.error('Erreur de géocodage:', error);
                    updateMarkerPosition(latlng); // Sans adresse en cas d'erreur
                });
            // updateMarkerPosition(e.latlng);
        });
        // let currentMarker = null;

        // Récuperation de la ville selectionnée
        $('#ville').on('change', function () {
            const nomVille = $('#ville option:selected').text();
            // alert(nomVille)

            if (nomVille && nomVille !== 'Selectionnez une ville...') {
                geocodeVille(nomVille);
            }
        });

        // Fonction pour géocoder une ville avec Nominatim
        function geocodeVille(nomVille) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nomVille + ', Ivory Coast')}`;

            $.getJSON(url, function(data) {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lon = parseFloat(data[0].lon);

                // Centrer la carte
                map.setView([lat, lon], 13);
                // currentMarker = [5.320357, -4.016107;]
                // Supprimer l'ancien marqueur
                // if (currentMarker) {
                // map.removeLayer(currentMarker);
                // }


                // Ajouter un nouveau marqueur
                //currentMarker = L.marker([lat, lon]).addTo(map).bindPopup(nomVille).openPopup();
            } else {
                console.log("Ville non trouvée !");
            }
            });


        }

        // FIN CARTE 1
    </script>

    {{-- TRAITEMENT DE LA CARTE 2 --}}
<script>
    // ----------- CARTE 2 -----------
    const map1 = L.map('map1').setView([5.320357, -4.016107], 13);
    let marker1, pt2;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map1);

    const geocoder1 = L.Control.geocoder({
        title: 'Barre de recherche',
        placeholder: 'Entrez votre adresse',
        collapsed: false,
        defaultMarkGeocode: false
    });

    const geocoderContainer2 = document.getElementById('search-container2');
    map1.addControl(geocoder1);
    geocoderContainer2.appendChild(geocoder1.getContainer());

    const searchInput2 = geocoder1.getContainer().querySelector('input');
    if (searchInput2) {
        searchInput2.id = 'afficheAdresse2';
        searchInput2.name = 'infoSup';
        searchInput2.style.width = '500px';
    }

    function updateMarkerPosition1(latlng, address = null) {
        if (marker1) {
            marker1.setLatLng(latlng);
        } else {
            marker1 = L.marker(latlng).addTo(map1);
        }
        map1.setView(latlng, 13);

        pt2 = latlng;
        document.getElementById('coordinates1').innerHTML =
            `Latitude: ${latlng.lat.toFixed(6)}, Longitude: ${latlng.lng.toFixed(6)}`;
        document.getElementById('long1').value = latlng.lng;
        document.getElementById('lat1').value = latlng.lat;
        document.getElementById('afficheAdresse2').value = address;
        document.getElementById('affichages1').value = address;

        if (pt1) {
            const distanceKm = (pt1.distanceTo(pt2) / 1000);
            document.getElementById('km').value = (Math.trunc(distanceKm)) * 5000;
        }

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ lat: latlng.lat, lng: latlng.lng, address })
        })
        .then(res => res.json())
        .then(console.log)
        .catch(console.error);
    }

    geocoder1.on('markgeocode', e => updateMarkerPosition1(e.geocode.center, e.geocode.name));

    map1.on('click', e => {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
            .then(res => res.json())
            .then(data => updateMarkerPosition1(e.latlng, data.display_name))
            .catch(() => updateMarkerPosition1(e.latlng));
    });

    // Récuperation de la ville selectionnée
        $('#ville1').on('change', function () {

            const nomVille = $('#ville1 option:selected').text();

            if (nomVille && nomVille !== 'Selectionnez une ville...') {
                geocodeVille(nomVille);
            }
        });

        // Fonction pour géocoder une ville avec Nominatim
        function geocodeVille(nomVille) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nomVille + ', Ivory Coast')}`;

            $.getJSON(url, function(data) {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lon = parseFloat(data[0].lon);

                // Centrer la carte
                map1.setView([lat, lon], 13);

                // Supprimer l'ancien marqueur
                if (currentMarker) {
                map1.removeLayer(currentMarker);
                }

                // Ajouter un nouveau marqueur
                //currentMarker = L.marker([lat, lon]).addTo(map).bindPopup(nomVille).openPopup();
            } else {
                console.log("Ville non trouvée !");
            }
            });


        }
</script>

    {{-- FONCTION POUR AJOUTER UNE NOUVELLE LIGNE DE PRODUIT --}}
    <script>
        $(function() {
            const table = $('#table');
            let ligne = $('#table tbody tr:first').clone();
            // Réinitialise les valeurs des sélections
            //ligne.find('select').each(function() { $(this).val($(this).find('option:first').val()); });

            console.log(ligne);

            function plusDeProduit() {
                ligne = $('#table tbody tr:first').clone();
                ligne.find('input').val(''); // Vide les valeurs des champs input
                ligne.find('select').prop('selectedIndex', 0);
                $('#table tbody').append(ligne);
            }

            $('#btnAjt').click(function(e) {
                e.preventDefault();
                plusDeProduit()

            });

            $('#table').on('click', '.btn-danger', function() {
                // Sélectionner la ligne (tr) parente du bouton cliqué
                var nombreDeLignes = $('#table tbody tr').length;


                var ligne = $(this).closest('tr');
                if (nombreDeLignes == 1) return;

                // Supprimer la ligne
                ligne.remove();
            });

        });
    </script>


@endsection
