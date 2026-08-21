@extends('client.main')
@section('title', 'Demande de livraison')
@section('cssPart')
    <style>
        /* ===================================================================
           Demande de livraison — mise en page

           La page enchaînait sans hiérarchie un bouton flottant, un tableau
           bordé de noir, trois listes déroulantes et deux cartes de 500px.
           Rien n'indiquait au visiteur combien d'étapes l'attendaient ni où il
           en était. Deux défauts mesurés sur mobile (375px) :

             · la page défilait horizontalement — 516px de contenu pour 375px
               d'écran, dus aux encarts de recherche d'adresse posés en absolu
               avec « left: 10px » ET « width: 100% » ;
             · le tableau des marchandises, à cinq colonnes, y était illisible.

           Les identifiants, les noms de champs et les classes restent
           STRICTEMENT identiques : le script de la page (ajout et suppression
           de lignes, cartes Leaflet, géocodeur) et le contrôleur s'y appuient.
           =================================================================== */

        /* Garde-fou : les grilles du thème portent une gouttière négative qui
           dépasse de 2px. Invisible sur ordinateur, elle suffit à faire
           apparaître une barre de défilement horizontale sur téléphone. */
        .dl-page { background: #f6f8fb; padding: 32px 0 64px; overflow-x: hidden; }

        .dl-entete { max-width: 900px; margin: 0 auto 28px; text-align: center; }
        .dl-entete h1 { font-size: 30px; font-weight: 700; color: #102a48; margin-bottom: 10px; }
        .dl-entete p { color: #5b6b7f; font-size: 16px; margin: 0 auto; max-width: 640px; }

        .dl-carte {
            background: #fff;
            border: 1px solid #e4eaf1;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(16, 42, 72, .05);
            padding: 26px 26px 22px;
            margin-bottom: 22px;
        }

        /* Titre d'étape : le numéro dit combien il en reste. */
        .dl-etape { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .dl-etape__num {
            flex: 0 0 auto;
            width: 34px; height: 34px;
            border-radius: 50%;
            background: #1c57a3; color: #fff;
            font-weight: 700; font-size: 15px;
            display: flex; align-items: center; justify-content: center;
        }
        .dl-etape__titre { margin: 0; font-size: 19px; font-weight: 700; color: #102a48; }
        .dl-etape__aide { margin: 2px 0 0; font-size: 13.5px; color: #7a8a9c; }

        /* Champs : le noir pur du thème d'origine écrasait la page. */
        .dl-carte .form-control,
        .dl-carte .form-select,
        .dl-carte select.form-control {
            border: 1px solid #d5dee8 !important;
            border-radius: 8px;
            min-height: 46px;
            font-size: 15px;
            box-shadow: none;
        }
        .dl-carte textarea.form-control { min-height: 92px; height: auto; }
        .dl-carte .form-control:focus,
        .dl-carte .form-select:focus {
            border-color: #1c57a3 !important;
            box-shadow: 0 0 0 3px rgba(28, 87, 163, .12);
        }
        .dl-libelle { display: block; font-size: 13.5px; font-weight: 600; color: #48586b; margin-bottom: 6px; }

        /* Tableau des marchandises */
        .dl-tableau { margin: 0; }
        .dl-tableau > thead th {
            background: #1c57a3; color: #fff;
            font-size: 13.5px; font-weight: 600;
            border: 0; padding: 12px 10px; white-space: nowrap;
        }
        .dl-tableau > thead th:first-child { border-top-left-radius: 8px; }
        .dl-tableau > thead th:last-child { border-top-right-radius: 8px; }
        .dl-tableau > tbody > tr > td { border-color: #e4eaf1; padding: 10px; vertical-align: top; }
        .dl-tableau .btn-danger {
            width: 38px; height: 38px; padding: 0;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; line-height: 1;
        }

        .dl-ajouter {
            background: #eef4fb; color: #1c57a3;
            border: 1px dashed #9dbbdd; border-radius: 8px;
            font-weight: 600; padding: 10px 18px;
        }
        .dl-ajouter:hover { background: #e2edf9; color: #14406f; }

        /* Recherche d'adresse.

           Le bloc est posé en absolu par-dessus la carte pour que la liste des
           suggestions la recouvre. Il portait « left: 10px » AVEC
           « width: 100% » : sa largeur partait donc du bord gauche du parent et
           débordait de 10px, et le formulaire du géocodeur, non contraint,
           poussait l'ensemble à 135px hors de l'écran. On borne par la droite. */
        .dl-recherche { position: relative; min-height: 54px; margin-bottom: 12px; }
        .dl-recherche > div {
            position: absolute; top: 0; left: 0; right: 0;
            width: auto !important;
            z-index: 999;
        }
        .dl-recherche .leaflet-control-geocoder { max-width: 100%; box-sizing: border-box; }
        .dl-recherche .leaflet-control-geocoder-form input {
            width: 100%; box-sizing: border-box;
            min-height: 44px; padding: 8px 12px;
            border: 1px solid #d5dee8; border-radius: 8px;
        }
        .dl-recherche .leaflet-control-geocoder-alternatives { max-width: 100%; }

        /* Sélecteur porté par l'identifiant : myStyle.css impose
           « #map { height: 500px; width: 70%; margin: auto } » à l'échelle du
           site. Une simple classe ne peut pas l'emporter — le balisage d'origine
           s'en sortait par un style en ligne. On ne touche pas au fichier
           global, d'autres pages s'appuient dessus. */
        #map.dl-carteleaflet,
        #map1.dl-carteleaflet {
            height: 420px; width: 100%; margin: 0;
            border: 1px solid #d5dee8; border-radius: 10px;
            overflow: hidden; background: #dde6f0;
        }
        .dl-coordonnees { font-size: 13px; color: #7a8a9c; margin-top: 8px; }

        .dl-envoyer {
            width: 100%; padding: 15px 20px;
            font-size: 16px; font-weight: 600; border-radius: 10px;
        }

        @media (max-width: 767px) {
            .dl-page { padding: 20px 0 40px; }
            .dl-entete h1 { font-size: 24px; }
            .dl-carte { padding: 18px 16px 16px; border-radius: 12px; }
            /* 500px de carte sur un téléphone reléguaient la suite du
               formulaire hors de l'écran. */
            #map.dl-carteleaflet,
            #map1.dl-carteleaflet { height: 300px; }

            /* Cinq colonnes ne tiennent pas sur un téléphone : chaque ligne
               devient un bloc, chaque cellule reçoit son libellé. Le tableau
               garde sa structure — le script clone un <tr>, il ne doit pas
               être remplacé par des <div>. */
            .dl-tableau > thead { display: none; }
            .dl-tableau, .dl-tableau > tbody, .dl-tableau > tbody > tr, .dl-tableau > tbody > tr > td { display: block; width: 100%; }
            .dl-tableau > tbody > tr {
                border: 1px solid #e4eaf1; border-radius: 10px;
                padding: 6px 10px 10px; margin-bottom: 14px; background: #fbfcfe;
            }
            .dl-tableau > tbody > tr > td { border: 0; padding: 8px 0; }
            .dl-tableau > tbody > tr > td::before {
                content: attr(data-libelle);
                display: block;
                font-size: 12.5px; font-weight: 600; color: #48586b;
                margin-bottom: 5px;
            }
            .dl-tableau > tbody > tr > td.dl-cellule-action { text-align: right; padding-top: 4px; }
            .dl-tableau > tbody > tr > td.dl-cellule-action::before { content: none; }
        }
    </style>
@endsection

@section('content')
    <main class="main dl-page">

        <div class="container">

            <div class="dl-entete">
                <h1>Demande de livraison</h1>
                <p>Vous avez une marchandise à faire transporter d'un point à un autre ?
                   Décrivez-la, indiquez le lieu de prise en charge et la destination :
                   nous calculons le coût avant que vous ne validiez quoi que ce soit.</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">

                    {{-- La page est consultable sans compte. On prévient d'emblée le
                         visiteur que l'envoi demande une connexion, plutôt que de le
                         laisser remplir tout le formulaire pour être renvoyé vers la
                         page de connexion en perdant sa saisie. Le bouton
                         « Demandes en cours » mène à un espace réservé : il n'a pas
                         de sens pour un visiteur. --}}
                    {{-- Aucun tarif ne couvre la marchandise demandée. Clé propre :
                         Flasher capte « error » et le rejoue en bulle fugace, alors
                         que le visiteur doit pouvoir lire ce message et corriger sa
                         saisie. --}}
                    @if (session('tarif_introuvable'))
                        <div class="alert alert-warning">
                            {{ session('tarif_introuvable') }}
                        </div>
                    @endif

                    @auth
                        <div class="mb-20 text-right">
                            <a href="{{ route('client.monCompte') }}" class="btn btn-primary">Mes demandes de livraison en cours</a>
                        </div>
                    @else
                        <div class="alert alert-info">
                            Vous pouvez préparer votre demande librement. Pour l'envoyer, il faudra
                            <a href="{{ route('client.login') }}" class="text-brand font-weight-bold">vous connecter</a>
                            ou <a href="{{ route('client.register') }}" class="text-brand font-weight-bold">créer un compte</a>.
                        </div>
                    @endauth

                    <form method="post" action="{{ route('client.recapLivraison') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- ÉTAPE 1 : la marchandise ------------------------------ --}}
                        <div class="dl-carte">
                            <div class="dl-etape">
                                <span class="dl-etape__num">1</span>
                                <div>
                                    <h3 class="dl-etape__titre">Marchandise à transporter</h3>
                                    <p class="dl-etape__aide">Ajoutez autant de lignes que nécessaire.</p>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table dl-tableau" id="table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Description</th>
                                            <th>Qté</th>
                                            <th>Unité</th>
                                            <th style="width: 60px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td data-libelle="Produit">
                                                <input type="text" required name="produit[]" class="form-control"
                                                    placeholder="Nom du produit">
                                            </td>
                                            <td data-libelle="Description">
                                                <textarea name="description[]" required class="form-control"
                                                    placeholder="Description" cols="30" rows="3"></textarea>
                                            </td>
                                            <td data-libelle="Quantité">
                                                <input class="form-control" required name="qte[]"
                                                    placeholder="Quantité" type="number" min="1" step="any" />
                                            </td>
                                            <td data-libelle="Unité">
                                                <select required class="form-select" name="unite[]">
                                                    <option value="">Unité</option>
                                                    @foreach ($unites as $unite)
                                                        @if ($unite->id !== 5)
                                                            <option value="{{ $unite->id }}">{{ $unite->libelle }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="dl-cellule-action">
                                                <a class="btn btn-danger bg-danger" title="Retirer cette ligne">&times;</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            {{-- Le bouton flottait AU-DESSUS du formulaire, sans lien
                                 visible avec le tableau qu'il alimente. --}}
                            <button type="button" class="btn dl-ajouter mt-15" id="btnAjt">+ Ajouter une marchandise</button>
                        </div>

                        {{-- ÉTAPE 2 : les modalités ------------------------------- --}}
                        <div class="dl-carte">
                            <div class="dl-etape">
                                <span class="dl-etape__num">2</span>
                                <div>
                                    <h3 class="dl-etape__titre">Modalités</h3>
                                    <p class="dl-etape__aide">Paiement, conditionnement et date souhaitée.</p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-20">
                                    <label class="dl-libelle" for="dl-paiement">Mode de paiement</label>
                                    <select required class="form-control" name="paiement" id="dl-paiement">
                                        <option value="">Choisissez un mode de paiement</option>
                                        @foreach ($paiements as $paiement)
                                            <option value="{{ $paiement->id }}">{{ $paiement->libelle }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 mb-20">
                                    <label class="dl-libelle" for="dl-type-livraison">Type de livraison</label>
                                    <select required class="form-control" name="type_livraison" id="dl-type-livraison">
                                        <option value="">Choisissez le type souhaité</option>
                                        @foreach ($types_livraison as $type_livraison)
                                            <option value="{{ $type_livraison->libelle }}">{{ $type_livraison->libelle }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4 mb-20">
                                    <label class="dl-libelle" for="dl-date">Date de livraison souhaitée</label>
                                    <input required type="date" class="form-control" id="dl-date"
                                        min="{{ now()->format('Y-m-d') }}" name="date" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                        </div>

                        {{-- ÉTAPE 3 : la prise en charge --------------------------- --}}
                        <div class="dl-carte">
                            <div class="dl-etape">
                                <span class="dl-etape__num">3</span>
                                <div>
                                    <h3 class="dl-etape__titre">Lieu de prise en charge</h3>
                                    <p class="dl-etape__aide">Où récupérons-nous la marchandise ? Placez le point exact sur la carte.</p>
                                </div>
                            </div>

                            <div id="demo"></div>

                            <label class="dl-libelle" for="ville">Ville</label>
                            <div class="custom_select mb-20">
                                <select required class="form-control select-active" name="ville" id="ville">
                                    <option value="">Sélectionnez une ville...</option>
                                    @foreach ($villes as $ville)
                                        <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <label class="dl-libelle">Rechercher une adresse</label>
                            <div class="dl-recherche">
                                <div id="search-container1"></div>
                            </div>

                            <div id="map" class="dl-carteleaflet"></div>
                            <div class="text-center dl-coordonnees" id="coordinates"></div>
                            <div class="text-center dl-coordonnees" id="latlng"></div>

                            <input required type="hidden" name="long" id="long">
                            <input required type="hidden" name="lat" id="lat">
                            <input required type="hidden" name="affichage" id="affichages">
                        </div>

                        {{-- ÉTAPE 4 : la destination ------------------------------- --}}
                        <div class="dl-carte">
                            <div class="dl-etape">
                                <span class="dl-etape__num">4</span>
                                <div>
                                    <h3 class="dl-etape__titre">Lieu de destination</h3>
                                    <p class="dl-etape__aide">Où livrons-nous ? Le coût du transport dépend de cette distance.</p>
                                </div>
                            </div>

                            <label class="dl-libelle" for="ville1">Ville</label>
                            <div class="custom_select mb-20">
                                <select required class="form-control select-active" id="ville1" name="ville1">
                                    <option value="">Sélectionnez une ville...</option>
                                    @foreach ($villes as $ville)
                                        <option value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <label class="dl-libelle">Rechercher une adresse</label>
                            <div class="dl-recherche">
                                <div id="search-container2"></div>
                            </div>

                            <div id="map1" class="dl-carteleaflet"></div>
                            <div class="text-center dl-coordonnees" id="coordinates1"></div>
                            <div class="text-center dl-coordonnees" id="latlng1"></div>

                            <input required type="hidden" name="long1" id="long1">
                            <input required type="hidden" name="lat1" id="lat1">
                            <input required type="hidden" name="km" id="km">
                            <input required type="hidden" name="affichage1" id="affichages1">
                        </div>

                        {{-- ÉTAPE 5 : le bon de commande ---------------------------
                             Il était imbriqué DANS le bloc « Lieu de destination »,
                             ce qui le rattachait visuellement à l'adresse. --}}
                        <div class="dl-carte">
                            <div class="dl-etape">
                                <span class="dl-etape__num">5</span>
                                <div>
                                    <h3 class="dl-etape__titre">Bon de commande</h3>
                                    <p class="dl-etape__aide">
                                        @if (Auth::user()?->client?->client_a_terme)
                                            Obligatoire pour un compte à terme.
                                        @else
                                            Facultatif.
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-20">
                                    <label class="dl-libelle" for="dl-numero-bon">Numéro du bon</label>
                                    <input type="text" id="dl-numero-bon"
                                        {{ Auth::user()?->client?->client_a_terme ? 'required' : '' }}
                                        placeholder="Entrez un numéro de bon de commande"
                                        class="form-control" name="numero_bon">
                                </div>
                                <div class="col-md-6 mb-20">
                                    <label class="dl-libelle" for="dl-fichier-bon">Pièce jointe</label>
                                    <input type="file" id="dl-fichier-bon"
                                        {{ Auth::user()?->client?->client_a_terme ? 'required' : '' }}
                                        class="form-control" name="fichier">
                                </div>
                            </div>
                        </div>

                        @auth
                            <button type="submit" class="btn btn-fill-out dl-envoyer mb-30">Voir le récapitulatif</button>
                        @else
                            {{-- Envoi réservé aux clients connectés : on propose la
                                 connexion au lieu d'un bouton qui échouerait. --}}
                            <a href="{{ route('client.login') }}" class="btn btn-fill-out dl-envoyer mb-30">
                                Se connecter pour envoyer la demande
                            </a>
                        @endauth

                    </form>
                </div>
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

        // Un SECOND géocodeur était déclaré ici, posé sur la carte par
        // « .addTo(map) », et écrasé aussitôt par celui ci-dessous — les deux
        // portaient le même nom de variable.
        //
        // Il en résultait deux champs de recherche pour la prise en charge :
        // celui de l'encart, et un autre en haut à droite de la carte. Le
        // second n'était pas seulement superflu : comme la variable avait été
        // réaffectée, TOUS les écouteurs (« markgeocode », « startgeocode »)
        // s'attachaient au premier. Chercher une adresse dans le champ posé
        // sur la carte ne renseignait donc NI les coordonnées, NI l'adresse
        // du formulaire — le client croyait avoir choisi son lieu de prise en
        // charge alors que rien n'était retenu.
        //
        // La carte de destination, elle, n'a jamais eu ce doublon.

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
                // Largeur fluide, jamais 500px en dur : le champ tient dans son
                // encart quelle que soit la taille de l'écran. (Cette ligne-ci
                // écrivait « 500px; » — le point-virgule rendait la valeur
                // invalide, elle était donc ignorée. Une faute de frappe
                // épargnait au premier champ le défaut que subissait le second.)
                searchInput.style.width = '100%';
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
        // 500px en dur poussaient ce champ 152px hors d'un écran de téléphone,
        // et c'est ce qui faisait défiler TOUTE la page horizontalement.
        searchInput2.style.width = '100%';
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
