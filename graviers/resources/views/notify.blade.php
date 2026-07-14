{{-- <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Leaflet</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.css" />
    <script
      src="https://unpkg.com/leaflet@1.6.0/dist/leaflet.js"
      integrity="sha512-gZwIG9x3wUXg2hdXF6+rVkLF/0Vi9U8D2Ntg4Ga5I5BZpVkVxlJWbSQtXPSiUTtC0TjtGOmxa1AJPuV0CPthew=="
      crossorigin=""
    ></script>

    <!-- Load Esri Leaflet from CDN -->
    <script
      src="https://unpkg.com/esri-leaflet@2.3.3/dist/esri-leaflet.js"
      integrity="sha512-cMQ5e58BDuu1pr9BQ/eGRn6HaR6Olh0ofcHFWe5XesdCITVuSBiBZZbhCijBe5ya238f/zMMRYIMIIg1jxv4sQ=="
      crossorigin=""
    ></script>

    <!-- Load Esri Leaflet Geocoder from CDN -->
    <link
      rel="stylesheet"
      href="https://unpkg.com/esri-leaflet-geocoder@2.3.2/dist/esri-leaflet-geocoder.css"
      integrity="sha512-IM3Hs+feyi40yZhDH6kV8vQMg4Fh20s9OzInIIAc4nx7aMYMfo+IenRUekoYsHZqGkREUgx0VvlEsgm7nCDW9g=="
      crossorigin=""
    />
    <script
      src="https://unpkg.com/esri-leaflet-geocoder@2.3.2/dist/esri-leaflet-geocoder.js"
      integrity="sha512-8twnXcrOGP3WfMvjB0jS5pNigFuIWj4ALwWEgxhZ+mxvjF5/FBPVd5uAxqT8dd2kUmTVK9+yQJ4CmTmSg/sXAQ=="
      crossorigin=""
    ></script>
    <style>
        #map {
            height: 500px;
            width: 70%;
            margin: auto;
            }
    </style>
</head>
<body>
    <div id="map"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.js"></script>
    <script>
        // Initialisation de la carte
        var map = L.map('map').setView([48.8566, 2.3522], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Fonction pour sauvegarder les données de la carte
        function saveMapData() {
            var center = map.getCenter();
            var zoom = map.getZoom();

            fetch('{{ route("welcome") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    lat: center.lat,
                    lng: center.lng,
                    zoom: zoom
                })
            })
            .then(response => response.json())
            .then(data => console.log('Données sauvegardées:', data))
            .catch(error => console.error('Erreur:', error));
        }

        // Sauvegarder les données quand la carte est déplacée
        map.on('moveend', saveMapData);

        // Fonction pour charger les dernières données sauvegardées
        function loadMapData() {
            fetch('{{ route("welcome") }}')
            .then(response => response.json())
            .then(data => {
                if (data) {
                    map.setView([data.lat, data.lng], data.zoom);
                }
            })
            .catch(error => console.error('Erreur lors du chargement des données:', error));
        }

        // Charger les données au démarrage
        loadMapData();
    </script>
</body>
</html> --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte avec recherche</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        #map{
            height: 500px;
            width: 70%;
            margin: auto
        }
    </style>
</head>
<body><form action="">
    <div id="map"></div>
    <div id="coordinates"></div>
    <input type="text" id="long"><br><br>
    <input type="text" id="lat">
{{-- <button></button> --}}
</form>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    {{-- <script>
        var map = L.map('map').setView([48.8566, 2.3522], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var geocoder = L.Control.geocoder({
            defaultMarkGeocode: false
        }).addTo(map);

        geocoder.on('markgeocode', function(e) {
            var latlng = e.geocode.center;
            L.marker(latlng).addTo(map);
            map.fitBounds(e.geocode.bbox);

            document.getElementById('coordinates').innerHTML =
                'Latitude: ' + latlng.lat + ', Longitude: ' + latlng.lng;

            document.getElementById('long').value =latlng.lng  ;
            document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
            fetch('{{ route("welcome") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    lat: latlng.lat,
                    lng: latlng.lng,
                    address: e.geocode.name
                })
            })
            .then(response => response.json())
            .then(data => console.log(data))
            .catch(error => console.error('Error:', error));
        });
        geocoder.on('markgeocode', function(e) {
            var latlng = e.geocode.center;
            updateMarkerPosition(latlng, e.geocode.name);});

            map.on('click', function(e) {
            updateMarkerPosition(e.latlng);
        });


    </script> --}}

    <script>
        var map = L.map('map').setView([48.8566, 2.3522], 13);
        var marker;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var geocoder = L.Control.geocoder({
            defaultMarkGeocode: false
        }).addTo(map);

        function updateMarkerPosition(latlng, address = null) {
            if (marker) {
                marker.setLatLng(latlng);
            } else {
                marker = L.marker(latlng).addTo(map);
            }
            map.setView(latlng, 13);

            document.getElementById('coordinates').innerHTML =
                'Latitude: ' + latlng.lat.toFixed(6) + ', Longitude: ' + latlng.lng.toFixed(6);
                document.getElementById('long').value =latlng.lng  ;
                document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
            fetch('{{ route("welcome") }}', {
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
            updateMarkerPosition(e.latlng);
        });
    </script>
</body>
</html>
