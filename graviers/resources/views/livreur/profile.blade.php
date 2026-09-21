@extends('layout.main')
@section('title','Profile livreur')
@section('contenu')

{{-- @dump($fournisseur) --}}


            {{-- Plus de « z-index: -2 » : cette couche négative plaçait tout
                 l'en-tête SOUS les vignettes positionnées au-dessus, et le
                 clic sur « Retour » ouvrait l'image de la pièce d'identité
                 au lieu de revenir en arrière. --}}
            <section class="content-main">
                <div class="content-header">
                    <a href="javascript:history.back()" class="btn btn-light"><i class="material-icons md-arrow_back"></i> Retour</a>
                </div>
                <div class="card mb-4">
                    <div class="card-header bg-brand" style="height: 150px"></div>
                    <div class="card-body">
                        @php
                            $logoFallback = asset(config('constantes.logo'));
                            $hasRecto = $livreur->piece_recto && $livreur->piece_recto !== 'image.png';
                            $hasVerso = $livreur->piece_verso && $livreur->piece_verso !== 'image.png';
                        @endphp
                        <div class="row">
                            @if ($hasRecto)
                                <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                                    <div class="img-thumbnail shadow w-100 bg-white position-relative text-center" style="height: 190px; width: 200px; margin-top: -120px">
                                        <a href="{{ route('show.livreurPiece', ['livreur' => $livreur->id, 'type' => 'recto', 'mode' => 'inline']) }}" target="_blank" rel="noopener">
                                            <img src="{{ route('show.livreurPiece', ['livreur' => $livreur->id, 'type' => 'recto', 'mode' => 'inline']) }}"
                                                 class="center-xy img-fluid"
                                                 loading="lazy" decoding="async"
                                                 style="max-height: 180px; max-width: 190px; object-fit: contain;"
                                                 alt="Pièce d'identité (Recto)"
                                                 onerror="this.onerror=null; this.src='{{ $logoFallback }}'; this.style.opacity='0.5';" />
                                        </a>
                                    </div>
                                    <p class="text-center small text-muted mt-2 mb-0">Pièce CNI - Recto</p>
                                </div>
                            @endif

                            @if ($hasVerso)
                                <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                                    <div class="img-thumbnail shadow w-100 bg-white position-relative text-center" style="height: 190px; width: 200px; margin-top: -120px">
                                        <a href="{{ route('show.livreurPiece', ['livreur' => $livreur->id, 'type' => 'verso', 'mode' => 'inline']) }}" target="_blank" rel="noopener">
                                            <img src="{{ route('show.livreurPiece', ['livreur' => $livreur->id, 'type' => 'verso', 'mode' => 'inline']) }}"
                                                 class="center-xy img-fluid"
                                                 loading="lazy" decoding="async"
                                                 style="max-height: 180px; max-width: 190px; object-fit: contain;"
                                                 alt="Pièce d'identité (Verso)"
                                                 onerror="this.onerror=null; this.src='{{ $logoFallback }}'; this.style.opacity='0.5';" />
                                        </a>
                                    </div>
                                    <p class="text-center small text-muted mt-2 mb-0">Pièce CNI - Verso</p>
                                </div>
                            @endif

                            @if (!$hasRecto && !$hasVerso)
                                {{-- Aucune pièce CNI : afficher uniquement un avatar par défaut --}}
                                <div class="col-xl col-lg flex-grow-0" style="flex-basis: 230px">
                                    <div class="img-thumbnail shadow w-100 bg-white position-relative text-center" style="height: 190px; width: 200px; margin-top: -120px">
                                        <img src="{{ $logoFallback }}" class="center-xy img-fluid" alt="Avatar" style="opacity: 0.4;" />
                                    </div>
                                    <p class="text-center small text-muted mt-2 mb-0">Aucune pièce CNI enregistrée</p>
                                </div>
                            @endif

                            <!--  col.// -->
                            <div class="col-xl col-lg">
                                <h3> {{$livreur->user?->nom_prenoms}} </h3>
                                <p>{{$livreur->user?->login}}, {{$livreur->user?->email}} </p>
                            </div>
                            <!--  col.// -->

                            <!--  col.// -->
                        </div>
                        <!-- card-body.// -->
                        <hr class="my-4" />
                        <div class="row g-4">
                            <div class="col-md-12 col-lg-4 col-xl-2">
                                <article class="box">
                                    <p class="mb-0 text-muted">Total livraison:</p>
                                    <h5 class="text-success"> {{$livreur->livraisons->where('etat_livraison', 'LIVREE')->count()}} </h5>
                                    {{-- <p class="mb-0 text-muted">Revenue:</p>
                                    <h5 class="text-success mb-0">{{number_format($livreur->livraisons->count() * $livreur->prix_livraison,'0','',' ')}} fcfa</h5> --}}
                                </article>
                            </div>
                            <!--  col.// -->
                            <div class="col-sm-4 col-lg-4 col-xl-3">
                                <h6>Contacts</h6>
                                <p>
                                    {{$livreur->user?->contact}} <br />
                                </p>
                            </div>
                            <!--  col.// -->
                            <div class="col-sm-4 col-lg-4 col-xl-3">
                                <h6>Numero CNI </h6>
                                <p>
                                    {{$livreur->num_piece_identite}} <br />

                                </p>
                                <form action="{{ route('show.modifierZoneLivreur', $livreur) }}" method="post">
                                    @csrf
                                    <label class="mb-1" for="zone_intervention"><h6 class="d-inline">Zone d'intervention</h6></label>
                                    <input type="text" name="zone_intervention" id="zone_intervention"
                                           class="form-control mb-2" style="border: 1px solid black"
                                           value="{{ $livreur->zone_intervention }}"
                                           placeholder="Ex : Yopougon, Abobo...">
                                    <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                                </form>
                            </div>
                            <div class="col-sm-4 col-lg-4 col-xl-3 bg-light">
                                {{-- La grille prime sur le mode de tarification ci-dessous :
                                     quand une tranche couvre la course, c est elle qui
                                     fixe le montant. Le mode reste le repli. --}}
                                @php $nbTranches = $livreur->tranchesRenseignees(); @endphp
                                <div class="mb-3">
                                    <label class="mb-1"><h6 class="d-inline">Grille de facturation</h6></label>
                                    <p class="small mb-2">
                                        @if ($nbTranches > 0)
                                            <span class="badge bg-success">{{ $nbTranches }} tranche(s)</span>
                                            @if ($livreur->part_grille)
                                                <span class="text-muted">— part livreur {{ rtrim(rtrim(number_format($livreur->part_grille, 2, ',', ' '), '0'), ',') }} %</span>
                                            @endif
                                        @else
                                            <span class="badge bg-secondary">Aucune grille</span>
                                            <span class="text-muted">— le tarif ci-dessous s'applique</span>
                                        @endif
                                    </p>
                                    <a href="{{ route('show.grilleLivreur', $livreur) }}" class="btn btn-sm btn-outline-primary">
                                        Ouvrir la grille
                                    </a>
                                </div>

                                {{-- L ECRAN MONTRAIT DEUX TARIFICATIONS SANS DIRE LAQUELLE GAGNE.
                                     La grille prime ; le mode ci-dessous ne sert que lorsque aucune
                                     tranche ne couvre la course. Sans cette phrase, on peut croire
                                     que modifier le fixe ou le prix au km change la remuneration
                                     des courses deja couvertes — ce qui n est pas le cas. --}}
                                @if ($nbTranches > 0)
                                    <div class="alert alert-info py-2 px-3 small mb-3">
                                        <strong>La grille prime.</strong> Le mode ci-dessous ne s'applique
                                        que si <u>aucune tranche ne couvre la course</u> : unité sans grille,
                                        ou quantité / distance hors des bornes.
                                    </div>
                                @endif

                                <form action="{{route('show.modifierPrixLivraison',$livreur)}}" method="post" class="d-flex">
                                    @csrf
                                    <div class="">
                                        <label for="mode_tarification" class="mb-1">Mode de tarification</label>
                                        <select name="mode_tarification" id="mode_tarification" class="form-control w-75" style="border: 1px solid black">
                                            <option value="base" {{ ($livreur->mode_tarification ?? 'base') == 'base' ? 'selected' : '' }}>Tarif de base</option>
                                            <option value="km" {{ ($livreur->mode_tarification ?? 'base') == 'km' ? 'selected' : '' }}>Tarif par KM</option>
                                            <option value="mixte" {{ ($livreur->mode_tarification ?? 'base') == 'mixte' ? 'selected' : '' }}>Fixe + KM</option>
                                        </select>

                                        <div id="bloc_tarif_base" class="mt-2">
                                            <label class="mb-1">Tarif de base (en fcfa) </label>
                                            <input type="number" name="montant" class="form-control w-75" value="{{$livreur->cout_livraison}}" style="border: 1px solid black">
                                        </div>

                                        {{-- Mode « Fixe + KM » : la part fixe vit dans tarif_forfait_base,
                                             distincte de cout_livraison pour que basculer d'un mode à
                                             l'autre ne détruise pas le forfait de l'autre. --}}
                                        <div id="bloc_tarif_fixe" class="mt-2">
                                            <label class="mb-1">Part fixe par course (en fcfa) </label>
                                            <input type="number" name="tarif_forfait_base" class="form-control w-75" value="{{$livreur->tarif_forfait_base}}" style="border: 1px solid black">
                                        </div>

                                        <div id="bloc_tarif_km" class="mt-2">
                                            <label class="mb-1">Tarif par KM (en fcfa) </label>
                                            <input type="number" name="tarif_km" class="form-control w-75" value="{{$livreur->tarif_km}}" style="border: 1px solid black">
                                        </div>

                                        <input type="text" name="motif" class="form-control w-75 mt-1"
                                            placeholder="Motif (facultatif)" maxlength="255">
                                        <div class="d-flex mt-1">
                                            <button class="btn btn-primary me-2" type="submit">Modifier</button>
                                            <button type="button" class="btn btn-outline-secondary btn-open-historique-prix"
                                                data-bs-toggle="modal" data-bs-target="#modalHistoriquePrix"
                                                title="Historique des modifications">
                                                <i class="material-icons md-history align-middle"></i>
                                                Historique ({{ $historiquesPrix->count() }})
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <script>
                                    (function () {
                                        var sel = document.getElementById('mode_tarification');
                                        var blocBase = document.getElementById('bloc_tarif_base');
                                        var blocFixe = document.getElementById('bloc_tarif_fixe');
                                        var blocKm = document.getElementById('bloc_tarif_km');
                                        function toggle() {
                                            if (!sel) return;
                                            // Trois modes : forfait seul, kilomètre seul, ou les deux.
                                            var m = sel.value;
                                            blocBase.style.display = (m === 'base') ? 'block' : 'none';
                                            blocFixe.style.display = (m === 'mixte') ? 'block' : 'none';
                                            blocKm.style.display   = (m === 'km' || m === 'mixte') ? 'block' : 'none';
                                        }
                                        if (sel) { sel.addEventListener('change', toggle); toggle(); }
                                    })();
                                </script>
                            </div>
                            <!--  col.// -->
                            {{-- <div class="col-sm-6 col-xl-4 text-xl-end">
                                <map class="mapbox position-relative d-inline-block">
                                    <img src="" class="rounded2" height="120" alt="map" />
                                    <span class="map-pin" style="top: 50px; left: 100px"></span>
                                    <button class="btn btn-sm btn-brand position-absolute bottom-0 end-0 mb-15 mr-15 font-xs">Large</button>
                                </map>
                            </div> --}}
                            <!--  col.// -->
                        </div>
                        <!--  row.// -->
                    </div>
                    <!--  card-body.// -->
                    {{-- ass="card mb-4"> --}}
                    @if(!$livraisons->isEmpty())
            <div class="card-body">
                {{-- Le nom du livreur dans le titre (08/09/2026) : un export ou une
                     impression de ce tableau dit de qui il s'agit. --}}
                <h3 class="card-title">Liste des bons d'enlèvement assignés à {{ $livreur->user?->nom_prenoms ?? $livreur->nom_prenoms }}</h3>
                <div class="row">
                    <div class="card-body">
                        {{-- Les trois exports, comme sur les autres listes (08/09/2026). --}}
                        <x-export-buttons table-id="tableLivraisonsLivreur"
                                          filename="bons-enlevement-livreur-{{ $livreur->id }}"
                                          title="Bons d'enlèvement assignés à {{ $livreur->user?->nom_prenoms ?? $livreur->nom_prenoms }}" />
                        <div class="table-responsive">
                            <table class="table table-hover text-center" id="tableLivraisonsLivreur">
                                <thead>
                                    <tr>
                                        <th class="text-center">Code commande</th>
                                        <th class="text-center">Produit</th>
                                        <th class="text-center" >Quantité</th>
                                        <th class="text-center">Fournisseur</th>
                                        <th class="text-center">Date</th>

                                        {{-- <th class="text-end">Action</th> --}}
                                        {{-- <th class="text-end">Action</th> --}}
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach ($livraisons as $livraison)
                                        <tr>
                                            <td>{{ $livraison->enlevement?->code_enleve}}</td>
                                            <td>{{ $livraison->enlevement?->produit?->nom }}</td>
                                            <td class="text-center"><b> {{ $livraison->enlevement?->qte }}  </b></td>
                                            <td class="text-center"><b> {{ $livraison->enlevement?->fournisseur?->nom_prenoms }}  </b></td>
                                            <td class="text-center">
                                                {{ $livraison->date_livraison }}
                                            {{-- <a href="" class="btn btn-success rounded font-sm">Accepter</a>
                                            <a href="" class="btn btn-danger rounded font-sm">Refuser</a> --}}
                                        </td>
                                        </tr>
                                        @endforeach

                                </tbody>
                            </table>
                        </div>
                        <!-- table-responsive //end -->
                    </div>

                    <!-- col.// -->
                </div>
                <!-- row.// -->
            </div>
            @else
            <h1 class="text-center p-3">Aucun bon assigné pour l'instant !!</h1>
            @endif
            <!--  card-body.// -->

            {{-- ============================================================
                 Section "Véhicules" du livreur
                 ============================================================ --}}
            <div class="card-body">
                <h3 class="card-title">Véhicules du livreur {{ $livreur->user?->nom_prenoms ?? $livreur->nom_prenoms }}</h3>
                <x-export-buttons table-id="tableVehiculesLivreur"
                                  filename="vehicules-livreur-{{ $livreur->id }}"
                                  title="Véhicules du livreur {{ $livreur->user?->nom_prenoms ?? $livreur->nom_prenoms }}" />
                <div class="table-responsive">
                    <table class="table table-hover text-center vehicules-livreur" id="tableVehiculesLivreur">
                        <thead style="background-color: #1c57a3; color: white;">
                            <tr>
                                <th class="text-center">Immatriculation</th>
                                <th class="text-center">Nom</th>
                                <th class="text-center">Type</th>
                                <th class="text-center">Marque</th>
                                <th class="text-center">Modèle</th>
                                <th class="text-center">Capacité</th>
                                <th class="text-center">Disponibilité</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($livreur->vehicules as $vehicule)
                                <tr>
                                    <td>{{ $vehicule->immatriculation }}</td>
                                    <td>{{ $vehicule->nom }}</td>
                                    <td>{{ $vehicule->type?->libelle ?? '-' }}</td>
                                    <td>{{ $vehicule->marque ?? '-' }}</td>
                                    <td>{{ $vehicule->modele ?? '-' }}</td>
                                    <td>{{ $vehicule->capacite ?? '-' }}</td>
                                    <td>
                                        @if ($vehicule->disponible)
                                            <span class="badge bg-success">Disponible</span>
                                        @else
                                            <span class="badge bg-secondary">Indisponible</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-muted">Aucun véhicule enregistré pour ce livreur.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Ajout d'un véhicule par l'admin / le gestionnaire (point 8) --}}
                <h5 class="mt-3">Ajouter un véhicule</h5>
                <form action="{{ route('show.ajoutVehiculeLivreur', $livreur->id) }}" method="post" class="row g-2">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label mb-0">Immatriculation</label>
                        <input type="text" name="matricule" class="form-control" required maxlength="15">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0">Nom du véhicule</label>
                        <input type="text" name="nom" class="form-control" required maxlength="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0">Type</label>
                        <select name="type_vehicule" class="form-control" required>
                            <option value="">Choisir...</option>
                            @foreach (($typesVehicule ?? []) as $tv)
                                <option value="{{ $tv->id }}">{{ $tv->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0">Marque</label>
                        <input type="text" name="marque" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0">Modèle</label>
                        <input type="text" name="modele" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-0">Capacité (T)</label>
                        <input type="number" step="0.1" min="0.1" name="capacite" class="form-control" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                    </div>
                </form>
                    </div>{{-- card-body --}}
                </div>{{-- card --}}
            </section>

            {{-- LA FENÊTRE EST HORS DE LA CARTE (08/09/2026, cause enfin établie sur
                 la page servie). La carte et la section n'étaient jamais refermées :
                 la fenêtre, écrite après, devenait un descendant de la carte. Or le
                 thème applique « transform: translateY(-2px) » à toute carte survolée
                 (main.css, .card:hover) : un élément transformé devient le repère des
                 positions fixes, et la fenêtre — fond gris compris — se retrouvait
                 confinée dans la carte (overflow: hidden), dansait au gré du curseur
                 et ne redevenait normale que lorsque le curseur quittait la page. --}}
            <!--  end section véhicules -->

    {{-- ==================================================================
         OVERLAY 100% CUSTOM (sans aucune classe Bootstrap modal/fade/show)
         pour éviter tout conflit avec BS4 / BS5 chargés simultanément.
         ================================================================== --}}
    <style>
        .hp-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 99999;
            overflow-y: auto;
            padding: 30px 15px;
        }
        .hp-overlay.is-open { display: block; }
        /* LE CADRE N'A PAS À S'ANIMER (08/09/2026). Le thème applique
           « transition: all » à tout : le cadre s'ouvrait avec une largeur
           nulle, le fond gris couvrait l'écran, et le contenu n'était peint
           qu'au prochain rafraîchissement (déplacement du curseur hors de la
           page). Aucune transition, une couche d'affichage à part. */
        .hp-overlay, .hp-overlay * { transition: none !important; animation: none !important; }
        .hp-dialog {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 900px;
            margin: 30px auto;
            overflow: hidden;
            font-family: inherit;
            position: relative;
            transform: translateZ(0);
            will-change: transform;
        }
        .hp-header {
            background: #1c57a3;
            color: #fff;
            padding: 12px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .hp-header h5,
        .hp-overlay .hp-header h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #ffffff !important;
        }
        .hp-close-x {
            background: transparent;
            border: 0;
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
        }
        .hp-body { padding: 18px; }
        .hp-footer {
            padding: 12px 18px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        .hp-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .hp-table th, .hp-table td { border: 1px solid #ddd; padding: 6px 8px; vertical-align: middle; }
        .hp-table thead { background: #f0f4fa; }
        .hp-table .center { text-align: center; }
        .hp-table .right { text-align: right; }
        .hp-up { color: #28a745; }
        .hp-down { color: #dc3545; }
        .hp-muted { color: #888; }
        .hp-empty { text-align: center; color: #888; margin: 20px 0; }
        .hp-btn-close {
            background: #6c757d;
            color: #fff;
            border: 0;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .hp-btn-close:hover { background: #5a6268; }
    </style>

    {{-- LA FENÊTRE STANDARD DU SITE (08/09/2026). Le cadre maison qui la
         précédait — un calque fixe écrit dans une carte du thème — restait gris
         et ne se peignait qu'au prochain rafraîchissement de l'écran. Les
         fenêtres Bootstrap des guichets, elles, s'affichent partout : c'est
         la même qui sert ici. --}}
    {{-- SANS « fade » (08/09/2026) : l'animation d'ouverture (glissement + fondu)
         laissait sur certains postes deux images fantômes de la fenêtre, l'une
         à sa position de départ, l'autre à l'arrivée, et la page en dessous
         restait délavée tant que l'écran n'était pas rafraîchi. La fenêtre
         apparaît d'un coup. --}}
    <div class="modal" id="modalHistoriquePrix" tabindex="-1" aria-labelledby="modalHistoriquePrixLabel" aria-hidden="true"
         style="transition: none;">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalHistoriquePrixLabel">
                        <i class="material-icons md-history"></i>
                        Historique du prix de livraison — {{ $livreur->user?->nom_prenoms ?? $livreur->nom_prenoms }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">

                @if ($historiquesPrix->isEmpty())
                    <p class="hp-empty">Aucune modification enregistrée pour ce livreur.</p>
                @else
                    <table class="hp-table">
                        <thead>
                            <tr>
                                <th class="center">Date</th>
                                <th class="right">Ancien prix</th>
                                <th class="right">Nouveau prix</th>
                                <th class="right">Variation</th>
                                <th>Modifié par</th>
                                <th>Motif</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historiquesPrix as $h)
                                @php $delta = $h->nouveau_prix - $h->ancien_prix; @endphp
                                <tr>
                                    <td class="center">{{ \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i:s') }}</td>
                                    <td class="right">{{ number_format($h->ancien_prix, 0, ',', ' ') }} fcfa</td>
                                    <td class="right"><strong>{{ number_format($h->nouveau_prix, 0, ',', ' ') }} fcfa</strong></td>
                                    <td class="right">
                                        @if ($delta > 0)
                                            <span class="hp-up">+{{ number_format($delta, 0, ',', ' ') }}</span>
                                        @elseif ($delta < 0)
                                            <span class="hp-down">{{ number_format($delta, 0, ',', ' ') }}</span>
                                        @else
                                            <span class="hp-muted">0</span>
                                        @endif
                                    </td>
                                    <td>{{ $h->user?->nom_prenoms ?? 'Système' }}</td>
                                    <td>{{ $h->motif ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="hp-muted" style="font-size:12px; margin-top:8px;">
                        {{ $historiquesPrix->count() }} modification(s) — historique limité aux 50 dernières entrées.
                    </p>
                @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('jsParts')
    <script src="{{ asset('backend/plugins/DataTables/datatables.min.js') }}"></script>
    <script type="text/javascript">
        $(function() {
            var lang = { url: '{{ asset('backend/plugins/DataTables/i18n/fr-FR.json') }}' };

            // N'initialiser DataTables que sur les tables qui contiennent de vraies
            // lignes de données (pas une ligne vide avec colspan).
            function safeInit(selector) {
                var $t = $(selector);
                if (!$t.length) return;
                if ($t.find('tbody tr td[colspan]').length > 0) return; // état vide
                if ($t.find('tbody tr').length === 0) return;
                $t.DataTable({ columnDefs: [{ targets: '_all', defaultContent: '-' }], language: lang, order: [] });
            }

            safeInit('#tableLivraisonsLivreur');
            safeInit('#tableVehiculesLivreur');
        });

        // La fenêtre « Historique » est une fenêtre Bootstrap standard (08/09/2026).
        // Par sûreté, elle est rattachée directement à <body> : aucun ancêtre
        // transformé (carte survolée, animation) ne peut plus la capturer.
        document.addEventListener('DOMContentLoaded', function () {
            var fenetre = document.getElementById('modalHistoriquePrix');
            if (fenetre && fenetre.parentNode !== document.body) {
                document.body.appendChild(fenetre);
            }
        });
    </script>
@endsection
