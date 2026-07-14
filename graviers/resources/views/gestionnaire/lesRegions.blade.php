@extends('layout.main')
@section('title', 'Gestion des régions')

@php
    use Carbon\Carbon;
    $totalRegions = $regions->count();
@endphp

@section('contenu')
    {{-- ===== HEADER WELCOME ===== --}}
    <div class="dash-welcome mb-4">
        <div class="dash-welcome-content">
            <div>
                <h2 class="dash-welcome-title">
                    Gestion des <span class="dash-welcome-name">Régions</span> 🗺️
                </h2>
                <p class="dash-welcome-subtitle">
                    Référentiel des régions desservies — {{ Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </p>
            </div>
        </div>
        <div class="dash-welcome-decoration"></div>
    </div>

    @if (session('saved'))
        <div class="alert alert-success">{{ session('saved') }}</div>
    @endif

    {{-- ===== KPI MINI STRIP ===== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-12">
            <div class="kpi-card kpi-card-primary">
                <div class="kpi-card-icon"><i class="material-icons md-public"></i></div>
                <div class="kpi-card-body">
                    <div class="kpi-card-label">Total régions</div>
                    <div class="kpi-card-value">{{ $totalRegions }}</div>
                </div>
                <div class="kpi-card-shape"></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        {{-- ===== FORMULAIRE ===== --}}
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-add_location text-primary"></i>
                        {{ $laRegion->id ? 'Modifier la région' : 'Nouvelle région' }}
                    </h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Nom de la région <span class="text-danger">*</span></label>
                            <input class="form-control" value="{{ $laRegion->nom }}" name="nom" type="text" />
                            @error('nom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Adresse complète <span class="text-danger">*</span></label>
                            <div style="position: relative; margin-bottom: 3rem;">
                                <div id="search-container" style="position: absolute; top: 10px; left: 10px; height: 100px; width: 100%; margin-bottom: 3rem; z-index: 9999"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div style="margin-top: 3rem;">
                                <p class="h6 mb-2">Veuillez préciser sur la carte</p>
                                <div id="map" style="height: 300px; width: 100%; margin: auto; background: #cecece; border-radius: 6px;"></div>
                            </div>
                            @error('long')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
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

        {{-- ===== TABLEAU ===== --}}
        <div class="col-lg-6">
            <div class="card dash-card">
                <div class="card-header dash-card-header">
                    <h5 class="dash-card-title">
                        <i class="material-icons md-list text-primary"></i>
                        Régions enregistrées
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table dash-table align-middle mb-0" id="listeRegions">
                            <thead>
                                <tr>
                                    <th>Région</th>
                                    <th>Adresse</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($regions as $r)
                                    <tr>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:10px;">
                                                <div class="dash-counter-icon dash-counter-icon-primary" style="width:34px;height:34px;font-size:16px;">
                                                    <i class="material-icons md-public"></i>
                                                </div>
                                                <strong>{{ $r->nom }}</strong>
                                            </div>
                                        </td>
                                        <td><small class="text-muted">{{ $r->description ?: '-' }}</small></td>
                                        <td class="text-end">
                                            <div class="dropdown">
                                                <a href="#" data-bs-toggle="dropdown" class="btn btn-light rounded btn-sm font-sm">
                                                    <i class="material-icons md-more_horiz"></i> Actions
                                                </a>
                                                <div class="dropdown-menu">
                                                    <a href="{{ route('show.modifierRegion', $r) }}" class="dropdown-item">
                                                        <i class="material-icons md-edit"></i> Modifier
                                                    </a>
                                                    <a href="{{ route('show.supprimerRegion', $r) }}"
                                                       class="dropdown-item text-danger"
                                                       data-confirm-msg="Voulez-vous vraiment supprimer la région {{ $r->nom }} ?">
                                                        <i class="material-icons md-delete"></i> Supprimer
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Aucune région enregistrée.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('cssParts')
    <link rel="stylesheet" href="{{ asset('backend/plugins/DataTables/datatables.min.css') }}">
@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function () {
            var $table = $('#listeRegions');
            if ($table.find('tbody tr').length > 0 &&
                $table.find('tbody tr td[colspan]').length === 0) {
                $table.DataTable({
                    columnDefs: [{ targets: '_all', defaultContent: '-' }],
                    language: { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' },
                    order: [[0, 'asc']],
                });
            }
        });
    </script>
    <script>
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
        geocoder.onAdd(map);
        geocoderContainer.appendChild(geocoder.getContainer());

        var searchInput = document.querySelector('.leaflet-control-geocoder input');
        if (searchInput) {
            searchInput.id = 'afficheAdresse';
            searchInput.name = 'adresse_geo';
            searchInput.value = '<?php echo $laRegion->description; ?>';
            searchInput.style.width = '500px';
            searchInput.style.height = '50px';
        }

        geocoder.on('markgeocode', function (e) {
            var resultsContainer = document.querySelector('.leaflet-control-geocoder-alternatives');
            if (resultsContainer) {
                resultsContainer.style.display = 'none';
            }
        });

        geocoder.on('startgeocode', function () {
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

            document.getElementById('afficheAdresse').value = address;
            if (document.getElementById('long')) document.getElementById('long').value = latlng.lng;
            if (document.getElementById('lat')) document.getElementById('lat').value = latlng.lat;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({
                    lat: latlng.lat,
                    lng: latlng.lng,
                    address: address
                })
            }).catch(error => console.error('Error:', error));
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
    </script>
@endsection
