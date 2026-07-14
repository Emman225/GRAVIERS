@extends('client.main')
@section('title', "Modifier l'adresse de livraison")
@section('content')
    <main class="main modif-adresse-main">
        @include('client.navMobile')

        {{-- ===== HERO ===== --}}
        <section class="modif-adresse-hero">
            <div class="modif-adresse-hero__inner">
                <span class="modif-adresse-hero__chip"><i class="fi-rs-marker"></i> Modification</span>
                <h1 class="modif-adresse-hero__title">Modifier l'adresse de livraison</h1>
                <p class="modif-adresse-hero__subtitle">
                    Commande N°<strong>{{ $commande->numero }}</strong> — précisez la nouvelle adresse sur la carte.
                </p>
            </div>
        </section>

        <div class="container mb-80 mt-30">
            <div class="row">
                <div class="col-lg-12">
                    @if ($commande->etat_commande == "EN ATTENTE")
                        <div class="modif-adresse-card">
                            <div class="modif-adresse-card__header">
                                <h5 class="modif-adresse-card__title">
                                    <i class="fi-rs-marker"></i> Nouvelle adresse de livraison
                                </h5>
                                <span class="modif-adresse-card__state">{{ $commande->etat_commande }}</span>
                            </div>
                            <div class="modif-adresse-card__body">
                                <form method="post">
                                    @csrf
                                    <div class="row shipping_calculator g-3">
                                        <div class="col-md-6">
                                            <label class="modif-adresse-field-label">
                                                <i class="fi-rs-marker"></i> Ville
                                            </label>
                                            <div class="custom_select">
                                                <select required class="form-control select-active modif-adresse-select" name="ville">
                                                    <option value="">Sélectionnez une ville...</option>
                                                    @foreach ($villes as $ville)
                                                        <option @selected($ville->id == $commande->adresseLivraison->ville_id) value="{{ $ville->id }}">{{ $ville->nom }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="modif-adresse-field-label">
                                                <i class="fi-rs-home"></i> Lieu précis
                                            </label>
                                            <input type="text" required value="{{ $commande->adresseLivraison->affichage }}"
                                                   class="form-control modif-adresse-input" name="affichage"
                                                   placeholder="Entrez votre lieu de livraison">
                                        </div>

                                        <div class="col-12 mt-4">
                                            <label class="modif-adresse-field-label">
                                                <i class="fi-rs-marker"></i> Veuillez préciser sur la carte
                                            </label>
                                            <div class="modif-adresse-map-wrap">
                                                <div id="map" style="height: 500px; width: 100%; margin: auto; background: #1c57a3"></div>
                                            </div>
                                            <div id="coordinates" class="modif-adresse-coords"></div>
                                        </div>

                                        <input required type="hidden" value="{{ $commande->adresseLivraison->longitude }}" name="long" id="long">
                                        <input required type="hidden" value="{{ $commande->adresseLivraison->latitude }}" name="lat" id="lat">
                                    </div>

                                    <div class="modif-adresse-actions">
                                        <a href="javascript:history.back()" class="modif-adresse-cancel">
                                            <i class="fi-rs-arrow-left"></i> Annuler
                                        </a>
                                        <button type="submit" class="modif-adresse-submit">
                                            <i class="fi-rs-check"></i> Enregistrer la modification
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="modif-adresse-blocked">
                            <div class="modif-adresse-blocked__icon"><i class="fi-rs-lock"></i></div>
                            <h3 class="modif-adresse-blocked__title">Modification impossible</h3>
                            <p class="modif-adresse-blocked__text">
                                Cette commande est en cours de traitement. Vous ne pouvez plus modifier l'adresse de livraison en ligne.
                                <br>Pour plus d'aide, rendez-vous dans l'une de nos agences.
                            </p>
                            <a href="{{ route('client.monCompte') }}" class="modif-adresse-blocked__btn">
                                <i class="fi-rs-arrow-left"></i> Retour à mon compte
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <style>
        .modif-adresse-hero {
            position: relative;
            background: linear-gradient(135deg, #0a2540 0%, #134380 60%, #c2410c 100%);
            color: #fff;
            padding: 40px 20px 44px;
            overflow: hidden;
            isolation: isolate;
        }
        .modif-adresse-hero::after {
            content: "";
            position: absolute; inset: 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(251, 146, 60, 0.30), transparent 55%),
                radial-gradient(circle at 15% 85%, rgba(28, 87, 163, 0.4), transparent 50%);
            z-index: -1;
        }
        .modif-adresse-hero__inner { max-width: 1140px; margin: 0 auto; text-align: center; }
        .modif-adresse-hero__chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .modif-adresse-hero__chip i { color: #fbbf24; font-size: 14px; }
        .modif-adresse-hero__title,
        h1.modif-adresse-hero__title {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0 0 6px;
            color: #ffffff !important;
            text-shadow: 0 2px 18px rgba(0,0,0,0.35);
        }
        .modif-adresse-hero__subtitle { margin: 0; color: rgba(255,255,255,0.92); font-size: 0.92rem; }
        .modif-adresse-hero__subtitle strong { color: #fbbf24; }

        .modif-adresse-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,0.05);
        }
        .modif-adresse-card__header {
            padding: 16px 22px;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(to right, #f8fafc, #ffffff);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        .modif-adresse-card__title {
            display: flex; align-items: center; gap: 10px;
            font-size: 1rem;
            font-weight: 700;
            color: #0a2540;
            margin: 0;
        }
        .modif-adresse-card__title i { color: #1c57a3; font-size: 18px; }
        .modif-adresse-card__state {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 700;
            text-transform: uppercase;
            background: #fef3c7;
            color: #92400e;
        }
        .modif-adresse-card__body { padding: 24px; }

        .modif-adresse-field-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            color: #374151;
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .modif-adresse-field-label i { color: #1c57a3; font-size: 14px; }
        .modif-adresse-input,
        .modif-adresse-select {
            display: block;
            width: 100%;
            padding: 11px 14px !important;
            border: 1.5px solid #e5e7eb !important;
            border-radius: 10px !important;
            background: #ffffff !important;
            font-size: 0.92rem !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            height: auto !important;
        }
        .modif-adresse-input:focus,
        .modif-adresse-select:focus {
            border-color: #ea580c !important;
            box-shadow: 0 0 0 3px rgba(234,88,12,0.12) !important;
            outline: none !important;
        }

        .modif-adresse-map-wrap #map {
            border-radius: 14px !important;
            border: 1.5px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15,23,42,0.08);
        }
        .modif-adresse-coords {
            font-size: 0.82rem;
            color: #6b7280;
            background: #f9fafb;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 10px;
        }
        .modif-adresse-coords:empty { display: none; }

        .modif-adresse-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        .modif-adresse-cancel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6b7280 !important;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            transition: all 0.15s ease;
        }
        .modif-adresse-cancel:hover { color: #1c57a3 !important; background: #f3f4f6; }
        .modif-adresse-submit {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 22px;
            background: linear-gradient(135deg, #fb923c 0%, #ea580c 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.92rem;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(234,88,12,0.32);
            transition: all 0.18s ease;
        }
        .modif-adresse-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(234,88,12,0.42);
            background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%);
        }
        .modif-adresse-submit i { font-size: 14px; }

        /* État bloqué */
        .modif-adresse-blocked {
            max-width: 520px;
            margin: 30px auto 0;
            padding: 50px 30px;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 4px 14px rgba(15,23,42,0.05);
        }
        .modif-adresse-blocked__icon {
            width: 90px; height: 90px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #f59e0b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
        }
        .modif-adresse-blocked__title { color: #0a2540; font-weight: 700; font-size: 1.3rem; margin: 0 0 8px; }
        .modif-adresse-blocked__text { color: #6b7280; font-size: 0.95rem; line-height: 1.6; margin: 0 0 22px; }
        .modif-adresse-blocked__btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, #1c57a3, #134380);
            color: #ffffff !important;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(28,87,163,0.30);
            transition: all 0.18s ease;
        }
        .modif-adresse-blocked__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(28,87,163,0.42);
        }

        @media (max-width: 575px) {
            .modif-adresse-hero { padding: 30px 16px 36px; }
            .modif-adresse-hero__title { font-size: 1.4rem; }
            .modif-adresse-actions { flex-direction: column-reverse; }
            .modif-adresse-cancel, .modif-adresse-submit { width: 100%; justify-content: center; }
        }
    </style>
@endsection
@section('jspart')
    <script>
        var map = L.map('map').setView([5.320357, -4.016107], 13);
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
            document.getElementById('long').value = latlng.lng;
            document.getElementById('lat').value = latlng.lat;

            // Envoyer les données au serveur
            fetch('{{ route('welcome') }}', {
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
@endsection
