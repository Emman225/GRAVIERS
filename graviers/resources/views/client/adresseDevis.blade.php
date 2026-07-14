@php
// dd(session('type'));

// dd(session('type'));
// session()->forget([
//     'nbre_jour',
//     'dateFinLocation',
//     'dateDebutLocation'
// ]);

    if(session('type') == 'devis'){
        $type_affaire = session('type_affaire');
    }else{
        foreach(Cart::content() as $produit){

            $type_affaire = $produit->options->type_affaire;
            break;
        }
    }


@endphp

@extends('client.main')
@section('title', 'Ajoutez une adresse')
@section('content')
    <main class="main">

        {{-- map --}}

        {{-- map --}}
        {{-- @dd(Cart::content()) --}}
        <div class="container mb-80 mt-50">
            {{-- <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Checkout</h1>
                    <div class="d-flex justify-content-between">
                        <h6 class="text-body">Il y a <span class="text-brand"> {{ Cart::count() }} </span> articles dans
                            votre panier</h6>
                    </div>
                </div>
            </div> --}}
            <div class="row">
                <div class="col-lg-7">

                    <div class="row">
                        <h4 class="mb-30">Choix de livraison</h4>
                        <form method="post" style="display: block" >
                            @csrf
                            <div class=" row container d-flex ">

                                <div class="col-3"><h5>Me faire livrer</h5></div>
                                <div class="col-1">
                                    <div class="form-check me-5" >
                                        <input class="form-check-input" type="radio" name="onMeLivre" value="oui" id="radio1" value="option1" checked>
                                        <label class="form-check-label text-black" for="radio1" >
                                            Oui
                                        </label>
                                    </div>
                                </div>
                                <div class="col-1">
                                    <div class="form-check ms-5">
                                        <input class="form-check-input" type="radio" name="onMeLivre" value="non" id="radio2" value="option2">
                                        <label class="form-check-label text-black" for="radio2">
                                            Non
                                        </label>
                                    </div>
                                </div>

                            </div>
                            <div class="row shipping_calculator" id="formulaire">
                                {{-- <div class="form-group col-lg-6"> --}}
                                <div class="custom_select mb-5" style="z-index: 9999">
                                    <select id="region" style="border: solid 1px grey" class="form-control select-active form-bordered" name="region" id="region">
                                        <option value="-1">Selectionnez une region...</option>
                                        @foreach ($regions as $region)
                                            <option  value="{{ $region->id }}">{{ $region->nom }}</option>
                                        @endforeach
                                    </select>

                                    @error('region')
                                        <div class="text-danger mt-2">
                                            {{ $message }}
                                        </div>

                                    @enderror
                                </div>
                                <div class="custom_select mb-5">
                                    <select id="ville" style="border: solid 1px grey" class="form-control select-active form-bordered" name="ville" id="ville">
                                        <option value="">Selectionnez une ville...</option>
                                        @foreach ($villes as $ville)
                                            <option
                                            {{-- @selected($ville->id == $client->user->ville_id) --}}
                                            value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                        @endforeach
                                    </select>

                                    @error('ville')
                                        <div class="text-danger mt-2">
                                            {{ $message }}
                                        </div>

                                    @enderror
                                </div>

                                <!-- <input style="border: solid 1px grey" name="affichage" type="text" required class=" mb-5 mt-5 form-control" value="" id="afficheAdresse"> -->

                                <div style="position: relative;margin-bottom: 3rem">
                                    <div id="search-container" style="position: absolute; top: 10px; left: 10px; height: 70px; width: 100%; margin-bottom: 3rem; z-index: 999"></div>
                                </div>
                                <div class="container" style="margin-top: 3rem;">
                                    @error('infoSup')
                                        <div class=" text-danger mt-2">
                                            {{ $message }}
                                        </div>

                                    @enderror
                                    <p for="" class="h5">Veuillez préciser sur la carte</p>
                                    <div id="map" style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>
                                </div>
                                <div id="coordinates"></div>
                                @error('lat')
                                    <div class="text-danger mt-2">
                                        {{ $message }}
                                    </div>
                                @enderror
                                @error('long')
                                    <div class="text-danger mt-2">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <input type="hidden" name="long" id="long"><br><br>
                                <input type="hidden" name="lat" id="lat">
                                <!-- <input type="hidden" id="adresse"> -->

                            </div>

                                <button type="submit" style="display: none" id="modePaiement" formaction="{{ route('client.modeDePaiement') }}">
                                    Choisir le mode de paiement
                                    <i class="fi-rs-money ml-15"></i>
                                </button> {{-- Ce bouton s'affiche si le client veut passer une commande --}}

                                {{-- <button style="display: none" id="enregistrerEnDevis" formaction="{{ route('client.recapDevis') }}"></button> --}}

                                @if (session('type') == 'devis')
                                    <button type="submit" style="display: none" id="modePaiementDevis" formaction="{{ route('client.devisModeDePaiement',$devis) }}">
                                        Enregistrer la modification
                                        <i class="fi-rs-money ml-15"></i>
                                    </button>
                                    {{-- Ce bouton s'affiche si le client veut passer une commande --}}
                                    <button type="submit" style="display: none" id="modePaiementDevis" formaction="{{ route('client.devisModeDePaiement',$devis) }}">
                                        Modifier le mode de paiement
                                        <i class="fi-rs-money ml-15"></i>
                                    </button>
                                    {{-- Ce bouton s'affiche si le client veut passer une commande --}}

                                @endif
                                @if ($type_affaire == 'LOCATION')
                                    <button formaction="{{route('client.choixDateProduitLocation')}}" id="choixDeDate" style="display: none"></button>

                                @endif
                        </form>
                    </div>
                </div>
                <div class="col-lg-5">

                    <div class="border p-40 cart-totals ml-30 mb-50">
                        <h4>Votre commande</h4>
                        <div class="d-flex align-items-end justify-content-between mb-30">
                            <p class="">
                            </p>
                        </div>
                        <div class="divider-2 mb-30"></div>
                        @if (session('type') == 'devis')
                            <div class="table-responsive order_table checkout">
                                <table class="table no-border col-12 mt-20">
                                    <tbody>
                                        <tr>
                                            <td >
                                                <h6 class="text-muted">Montant HT</h6>
                                            </td>
                                            <td></td>
                                            <td class="cart_total_amount">

                                               <h6 class="text-brand text-end"><span id="montantHT">{{ number_format($devis->montant, 0, '', ' ') }}</span> fcfa</h6>
                                            </td>
                                        </tr>
                                        {{-- @if($client->client_a_terme) --}}
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">TVA</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end"> <span id="tva">{{ number_format($total*$tva, 0, '', ' ') }}</span> fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">Livraison</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end" > <span id="cout_livraison">{{ number_format($conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>
                                        {{-- @endif --}}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="cart_total_label">
                                                <h6 class="text-muted text-start">Montant TTC</h6>
                                            </th>
                                            <th></th>
                                            <th class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span  id="montantTTC">{{ number_format($total+($total*$tva), 0, '', ' ') }}</span> fcfa <br>
                                                </h6>
                                            </th>
                                        </tr>
                                        <tr>
                                        <th colspan="3">

                                                <span class="text-danger" id="messageAlert">
                                                    @if($total+($total*$tva) > 2000000)
                                                        Pour tout montant supérieur à 2 000 000 fcfa le paiement
                                                        doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                    @endif
                                                </span>
                                        </th>
                                    </tr>
                                    </tfoot>

                                        {{-- <tr>
                                            <td scope="col" colspan="2">
                                                <div class="divider-2 mt-10 mb-10"></div>
                                            </td>
                                        </tr> --}}

                                </table>
                                <label class="btn btn-fill-out btn-block mt-30" for="">Enregistrer la modification<i class="fi-rs-calendar ml-15"></i></label>
                                 @if ($type_affaire == 'LOCATION')
                                    <label class="btn btn-fill-out btn-block mt-20" for="choixDeDate">Choisir les dates par produit<i class="fi-rs-calendar ml-15"></i></label>
                                @else
                                    <label class="btn btn-fill-out btn-block mt-20 lolo" for="modePaiementDevis">
                                    {{-- {{$client->client_a_terme == 0 ? 'Choisir le mode de paiement' : 'Finaliser la commande'}} --}}
                                    Modifier le mode de paiement
                                    <i class="fi-rs-money ml-15"></i></label>

                                @endif <br>
                                <a href="{{ route('devis.annulerModificationDevis', $devis) }}" class="mb-20 w-100">Annuler la modification</a>

                        @else
                                <div class="table-responsive order_table checkout">

                                    <table class="table no-border">
                                        <tbody>
                                            @foreach (Cart::content() as $produit)
                                                <tr>
                                                    <td class="image product-thumbnail"><img
                                                            src="storage/{{ $produit->options->image }}" alt="#"></td>
                                                    <td>
                                                        <h6 class="w-160 mb-5"><a href="shop-product-full.html"
                                                                class="text-heading">{{ $produit->name }}</a></h6></span>
                                                        <div class="product-rate-cover">
                                                            <div class="product-rate d-inline-block">
                                                                <div class="product-rating"
                                                                    style="width :{{ $produit->options->note }}%">
                                                                </div>
                                                            </div>
                                                            <span class="font-small ml-5 text-muted">
                                                                ({{ round(($produit->options->note * 5) / 100, 1) }})</span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <h6 class="text-muted pl-20 pr-20">x {{ $produit->qty }} </h6>
                                                    </td>
                                                    <td>
                                                        <h4 class="text-brand"> {{ number_format($produit->price, 0, '', ' ') }} fcfa
                                                        </h4>
                                                    </td>
                                                </tr>
                                            @endforeach

                                        </tbody>
                                    </table>
                                </div>
                                {{-- @dd('ok') --}}

                                <table class="table no-border col-12 mt-20">
                                    <tbody>
                                        <tr>
                                            <td >
                                                <h6 class="text-muted">Montant HT</h6>
                                            </td>
                                            <td></td>
                                            <td class="cart_total_amount">

                                               <h6 class="text-brand text-end" id="montantHT">{{ number_format($total, 0, '', ' ') }} fcfa</h6>
                                            </td>
                                        </tr>
                                        {{-- @if($client->client_a_terme) --}}
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">TVA</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">
                                                    <h6 class="text-brand text-end"> <span
                                                            id="tva">{{ number_format($total*$tva, 0, '', ' ') }}</span> fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="cart_total_label">
                                                    <h6 class="text-muted">Livraison</h6>
                                                </td>
                                                <td></td>
                                                <td class="cart_total_amount">

                                                    <h6 class="text-brand text-end" id="cout_livraison"> <span
                                                            id="cout_livraison">{{ number_format($conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa <br>
                                                    </h6>
                                                </td>
                                            </tr>
                                        {{-- @endif --}}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th class="cart_total_label">
                                                <h6 class="text-muted text-start">Montant TTC</h6>
                                            </th>
                                            <th></th>
                                            <th class="cart_total_amount">

                                                <h6 class="text-brand text-end"> <span id="montantTTC">{{ number_format($total+($total*$tva)+$conf->cout_livraison_min, 0, '', ' ') }}</span> fcfa <br>
                                                </h6>
                                            </th>
                                        </tr>
                                        <tr>
                                        <th colspan="3">
                                            <span class="text-danger" id="messageAlert">
                                                @if($total+($total*$tva)+$conf->cout_livraison_min > 2000000)
                                                    Pour tout montant supérieur à 2 000 000 fcfa le paiement
                                                    doit se faire par virement bancaire, en agence ou en plusieurs commandes.
                                                @endif
                                            </span>
                                        </th>
                                    </tr>
                                    </tfoot>

                                        {{-- <tr>
                                            <td scope="col" colspan="2">
                                                <div class="divider-2 mt-10 mb-10"></div>
                                            </td>
                                        </tr> --}}

                                </table>
                                @if ($type_affaire == 'LOCATION')
                                    <label class="btn btn-fill-out btn-block mt-30" for="choixDeDate">Choisir les dates par produit<i class="fi-rs-calendar ml-15"></i></label>
                                @else
                                    <label class="btn btn-fill-out btn-block mt-30 lolo" for="modePaiement">
                                    {{$client->client_a_terme == 0 ? 'Choisir le mode de paiement' : 'Finaliser la commande'}}
                                    <i class="fi-rs-money ml-15"></i></label>

                                    {{-- <label class="btn btn-fill-out btn-block mt-30 lolo" for="enregistrerEnDevis">
                                    Enregistrer en devis
                                    <i class="fi-rs-money ml-15"></i></label> --}}
                                @endif


                            @endif

                    </div>
                    {{-- <div clas
                     --}}
                </div>
            </div>
        </div>
    </main>
@endsection
@section('jspart')


<script>

    $(function () {
        let villeID = -1;
        let regionID = -1;
        let longitude = 0;
        let latitude = 0;


        // SELECTION DE LA REGION
        $('#region').on('change',function(){
            let region = this.value

            if(region){
                console.log("regopn id " + region);
                let url ='/villes/region/'+region
                $.ajax({
                    url: url,
                    type: 'GET',

                    success: function (response) {
                        console.log(response)
                        $('#ville').empty();

                        // Ajouter l'option par défaut
                        $('#ville').append('<option value="">Selectionnez une ville...</option>');

                        // Parcourir l'objet des villes
                        $.each(response.villes, function(nom, id) {
                            $('#ville').append(`<option value="${id}">${nom}</option>`);
                        });

                        // Rafraîchir Select2
                        $('#ville').trigger('change.select2');

                    },
                    error: function () {
                        alert('Une erreur est survenue.');
                    },
                    complete: function(){
                        console.log("Ajax region terminé");
                        if(villeID != -1){
                            $('#ville').val(villeID).trigger('change.select2');
                            villeID = -1;
                        }
                        console.log(longitude, latitude);
                        if(longitude != 0 && latitude != 0){
                            calculCoutLivraison(longitude, latitude, region)
                            console.log("SIOUUUUUUU");
                        }
                    }
                });
            }

        })

        // SELECTION DE LA VILLE
        $('#ville').on('change', function(){
            let ville = this.value
            if(ville){
                villeID = ville

                let url ='/region/villes/'+ville
                $.ajax({
                    url: url,
                    type: 'GET',

                    success: function (response) {
                        console.log(response)
                    $('#region')
                        .val(response.region)                 // sélectionne la valeur
                        .trigger('change')                    // déclenche l'événement "change" (utile si tu charges les villes après)
                        .trigger('change.select2');



                    },
                    error: function () {
                        alert('Une erreur est survenue.');
                    },
                    complete : function(){
                        console.log("Ajax terminé");
                    }
                });
            }

             //selectionner laville actuelle



        })
    });

</script>

    <script>
        const initialCoords = [5.320357, -4.016107];
        const initialZoom = 13;
        var map = L.map('map').setView([5.320357, -4.016107], 13);
        var marker;
        var region = document.getElementById("region");
        var ville = document.getElementById("ville");

        let ttcLiv = $('#montantTTC').text()
        const ttcInt = parseInt(ttcLiv.replace(/\s/g, ''))

        let livraison = $('#cout_livraison').text()
        const livraisonInt = parseInt(livraison.replace(/\s/g, ''))


        console.log('ttcAvecLivraison:', ttcInt, 'livraison', livraisonInt)
        //const infoSup = document.getElementById("input1").value = "";


        // initialisation de la carte
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);


        // INITIALISATION DE LA BARRE DE RECHERCHE
        var geocoder = L.Control.geocoder({
            title: 'Barre de recherche',
            placeholder: 'Entrez votre adresse',
            collapsed: false,
            defaultMarkGeocode: false,

        });

        // LE CONTENEUR DE LA BARRE DE RECHERCHE
        var geocoderContainer = document.getElementById('search-container');
        geocoder.onAdd(map);  // Cette étape initialise le contrôle
        geocoderContainer.appendChild(geocoder.getContainer());

        // AJOUT DE STYLE A LA BARRE DE RECHERCHE
        var searchInput = document.querySelector('.leaflet-control-geocoder input');
            if (searchInput) {
                searchInput.id = 'afficheAdresse'; // Ajouter l'ID
                searchInput.name = 'infoSup'; // Ajouter le name
                // searchInput.style.backgroundColor = 'red';
                searchInput.style.width = '500px';
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

        // LON
        geocoder.on('markgeocode', function(e) {
            var latlng = e.geocode.center;
            updateMarkerPosition(latlng, e.geocode.name);
        });

        function calculCoutLivraison(lng, lat, region){
            console.log('ok')
            $.ajax({
                url:'/calcul/cout/livraison'+lng+'/'+lat+'/'+region,
                type: 'GET',
                success: function(response){
                    console.log('response:', response)

                    $('#cout_livraison').text('0');
                    $('#cout_livraison').text('('+response.km+' km) '+ formatNumber(response.cout_livraison)+' fcfa')


                    let tva = parseInt($('#tva').text().replace(/\s/g, ''))
                    console.log('tva:', parseInt(tva))


                    let montantHT = parseInt($('#montantHT').text().replace(/\s/g, ''))
                    console.log('montantHT:', montantHT)

                    let total = tva + response.cout_livraison+montantHT
                    console.log('voila le totall', total)
                    $('#montantTTC').text('');
                    $('#montantTTC').text(formatNumber(total));
                    console.log('', tva, response.cout_livraison,montantHT)

                    if(total > 2000000) {
                        console.log("Montant TTC depasse 2battons"+ total);
                        $('#messageAlert').html('');
                        $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
                    } else {
                        console.log("Montant TTC inferieur ou egal à 2battons"+ total);
                        $('#messageAlert').html('');
                    }

                },
                error: function(error){
                    console.log('error:', error)

                }
            })
        }

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
            console.log('latlng:', latlng)
            let region = $('#region').val()
            console.log('region:', region)

            longitude = latlng.lng;
            latitude = latlng.lat;

            console.log('les deux', longitude, latitude)
            if(region != -1){
                calculCoutLivraison(latlng.lng, latlng.lat, region)
            }
        });


        let form = document.getElementById('formulaire');

        let livrer = document.getElementById('radio1');
        let recuperer = document.getElementById('radio2');

        livrer.addEventListener('click', function() {
            form.style.display = 'block';
            $('#cout_livraison').text(formatNumber(<?php echo $conf->cout_livraison_min; ?>) +' fcfa');

            let ttc = parseInt($('#montantTTC').text().replace(/\s/g, ''));

            if(ttc != ttcInt){
                $('#montantTTC').text(formatNumber(ttcInt))
            }

            if(ttc > 2000000) {
                console.log("Montant TTC depasse 2battons"+ ttc);
                $('#messageAlert').html('');
                $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
            } else {
                console.log("Montant TTC inferieur ou egal à 2battons"+ ttc);
                $('#messageAlert').html('');
            }
           resetMap();
        });

        recuperer.addEventListener('click', function() {
            form.style.display = 'none';

            let livraison = $('#cout_livraison').text()
            const livraisonInt = parseInt(livraison.replace(/\s/g, ''))

            $('#cout_livraison').text('0 fcfa');

            let ttc = parseInt($('#montantTTC').text().replace(/\s/g, ''));

            if(ttc == ttcInt){
                $('#montantTTC').text(formatNumber(ttcInt-livraisonInt));
                ttc = ttcInt-livraisonInt;
            }

            if(ttc > 2000000) {
                console.log("Montant TTC depasse 2battons"+ ttc);
                $('#messageAlert').html('');
                $('#messageAlert').html('Pour tout montant supérieur à 2 000 000 fcfa le paiement doit se faire par virement bancaire, en agence ou en plusieurs commandes.');
            } else {
                console.log("Montant TTC inferieur ou egal à 2battons"+ ttc);
                $('#messageAlert').html('');
            }

           resetMap();
        });

        $('#ville').on('change', function () {
            const nomVille = $('#ville option:selected').text();
            if (nomVille && nomVille !== 'Selectionnez une ville...') {
                geocodeVille(nomVille);
            }
        });
/******************************* partie Fonction ******************************************/
          // Réinitialisation de la carte
        function resetMap() {
            // Réinitialiser la carte
            map.setView(initialCoords, initialZoom);

            // Supprimer tous les marqueurs (laisser uniquement la couche de tuiles)
            map.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
            });

            // Réinitialiser les valeurs des champs du formulaire
            // document.getElementById('adresse').value = '';
            // document.getElementById('coordinates').innerHTML = '';
            searchInput.value = '';
            // region.selectedIndex = 0;
            // ville.selectedIndex = 0;
           // Réinitialiser les select
            region.value = "";
            ville.value = "";

            // Déclencher l’événement "change" pour que Select2 se mette à jour
            region.dispatchEvent(new Event('change'));
            ville.dispatchEvent(new Event('change'));
        }

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
            document.getElementById('coordinates').innerHTML =
                // 'Latitude: ' + latlng.lat.toFixed(6) + ',  Longitude: ' +latlng.lng.toFixed(6)+', Adresse: '+ address;
                'Latitude: ' + latlng.lat.toFixed(6) + ', Longitudde: ' + latlng.lng.toFixed(6);
            document.getElementById('long').value = latlng.lng;
            document.getElementById('lat').value = latlng.lat;
            // document.getElementById('adresse').value = address;
            // document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
        }

        // Fonction pour géocoder une ville avec Nominatim
        function geocodeVille(nomVille) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nomVille + ', Ivory Coast')}`;

            $.getJSON(url, function(data) {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lon = parseFloat(data[0].lon);

                // Centrer la carte
                map.setView([lat, lon], 13);

                // Supprimer l'ancien marqueur
                if (currentMarker) {
                map.removeLayer(currentMarker);
                }

                // Ajouter un nouveau marqueur
                //currentMarker = L.marker([lat, lon]).addTo(map).bindPopup(nomVille).openPopup();
            } else {
                console.log("Ville non trouvée !");
            }
            });


        }

        function formatNumber(number) {
            return number.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }

    </script>
@endsection
