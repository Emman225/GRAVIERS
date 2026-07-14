@extends('client.main')
@section('title','Ajoutez une adresse')
@section('content')
    <main class="main">
        <div class="page-header breadcrumb-wrap">
            <div class="container">
                <div class="breadcrumb">
                    <a href="{{route('client.index')}}" rel="nofollow"><i class="fi-rs-home mr-5"></i>Accueil</a>
                    <span></span> Boutique
                    <span></span> Adresse
                </div>
            </div>
        </div>



        {{-- @dd(Cart::content()) --}}
        <div class="container mb-80 mt-50">
            <div class="row">
                <div class="col-lg-8 mb-40">
                    <h1 class="heading-2 mb-10">Demande de livraison</h1>

                </div>
            </div>
            <div class="container">

                    <a href="{{route('client.monCompte')}}" class="btn btn-primary">Demandes de livraison en cours</a>

            </div>
            <div class="row">
                <div class="col-lg-7">
                    <div class="row mb-50">

                        <div class="col-lg-6">

                        </div>
                    </div>
                    <div class="row">
                        {{-- <h4 class="mb-30">Ajoutez votre adresse</h4> --}}
                        <form method="post" action="{{route('client.recapLivraison')}}">
                            @csrf
                            <div class="row shipping_calculator">

                                <div class="container" >
                                    <input type="text" style="margin-top: 3rem;" name="produit" class="form-control" placeholder="Nom du produit">
                                    <textarea name="description" class="form-control " style="margin-top: 3rem; height: 200px" id="" placeholder="Description" cols="30" rows="10"></textarea>
                                    {{-- <input type="text" style="margin-top: 3rem;" name="description" class="form-control" placeholder="Description"> --}}
                                </div>
                                <div class="mb-3">
                                    <div class="row gx-2 mt-3">
                                        <div class="col-8"><input class="form-control" name="qte" placeholder="Quantité" type="text" /></div>
                                        <select class="form-control select-active" name="unite">
                                            <option value="">Unité</option>
                                            @foreach ($unites as $unite )
                                                <option value="{{$unite->abreviation}}"> {{$unite->libelle}} </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="custom_select mb-5" style="margin-top: 3rem;">
                                    <select class="form-control select-active" name="paiement">
                                        <option value="">Choisissez un mode de paiement...</option>
                                        @foreach ($paiements as $paiement)
                                            <option value="{{$paiement->description}}">{{$paiement->description}}</option>
                                        @endforeach
                                    </select>
                                <div class="custom_select mb-5" style="margin-top: 3rem;">
                                    <select class="form-control select-active" name="type_livraison">
                                        <option value="">Choisissez le type de livraison souhaité...</option>
                                        @foreach ($types_livraison as $type_livraison)
                                            <option value="{{$type_livraison->libelle}}">{{$type_livraison->libelle}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="container">
                                    <label for="" >Date de livraison souhaitée</label>
                                    <input type="date" class="form-control" name="date">
                                </div>
                                {{-- <input type="text" name="poids" class="form-control" placeholder="Poids du vehicule souhaité (en tonne)"> --}}

                                <div class="container" style="margin-top: 3rem;">
                                    <div class="custom_select mb-5" style="margin-top: 3rem;">
                                        <select class="form-control select-active" name="ville">
                                            <option value="">Selectionnez une ville...</option>
                                            @foreach ($villes as $ville)
                                                <option value="{{$ville->id}}">{{$ville->nom}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="text" class=" mb-5 mt-5 form-control" name="affichage" placeholder="Lieu de pris en charge" value="">
                                    <p for="" class="text-center h5">Veuillez préciser sur la carte</p>
                                    <div id="map" style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>
                                    <div class="text-center" id="coordinates"></div>
                                    <div class="text-center" id="latlng"></div>
                                    <input type="hidden" name="long"  id="long"><br><br>
                                    <input type="hidden" name="lat"  id="lat">
                                </div>

                                <div class="container" style="margin-top: 3rem;">
                                    <div class="custom_select mb-5" style="margin-top: 3rem;">
                                        <select class="form-control select-active" name="ville1">
                                            <option value="">Selectionnez une ville...</option>
                                            @foreach ($villes as $ville)
                                                <option value="{{$ville->id}}">{{$ville->nom}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="text" class=" mb-5 mt-5 form-control" name="affichage1" placeholder="Lieu de destination" value="">
                                    <p for="" class="text-center h5">Veuillez préciser sur la carte</p>
                                    <div id="map1" style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>

                                    <div class="text-center" id="coordinates1"></div>

                                    <div class="text-center" id="latlng1"></div>
                                    <input type="hidden" name="long1"  id="long1"><br><br>
                                    <input type="hidden" name="lat1"  id="lat1">
                                    <input type="hidden" name="km" id="km">
                                </div>
                            </div>
                                <button type="submit" class="btn btn-fill-out btn-block mb-30">Voir le récapitulatif</button> {{-- Ce bouton s'affiche si le client veut passer une commande --}}
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
<script>


    var map = L.map('map').setView([5.320357, -4.016107], 13);
    var marker, pt1

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
            pt1 = latlng;


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

    // FIN CARTE 1
</script>
<script>
    var map1 = L.map('map1').setView([5.320357, -4.016107], 13);
    var marker1,pt2;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'

    }).addTo(map1);

    var geocoder1 = L.Control.geocoder({
        defaultMarkGeocode: false
    }).addTo(map1);

    function updateMarkerPosition1(latlng, address = null) {
        if (marker1) {
            marker1.setLatLng(latlng);
        } else {
            marker1 = L.marker(latlng).addTo(map1);
        }
        map1.setView(latlng, 13);

        document.getElementById('coordinates1').innerHTML =
            'Latitude: ' + latlng.lat.toFixed(6) + ', Longitude: ' + latlng.lng.toFixed(6);
            document.getElementById('long1').value =latlng.lng  ;
            document.getElementById('lat1').value = latlng.lat;
            pt2 = latlng;
            document.getElementById('km').value = (Math.trunc(pt1.distanceTo(pt2))/1000)*5000;






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

    geocoder1.on('markgeocode', function(e) {
        var latlng1 = e.geocode.center;
        updateMarkerPosition1(latlng1, e.geocode.name);
    });

    map1.on('click', function(e) {
        updateMarkerPosition1(e.latlng);
    });
</script>


@endsection
