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
        .dl-page { background: #edf2f9; padding: 32px 0 64px; overflow-x: hidden; }

        .dl-entete { max-width: 900px; margin: 0 auto 28px; text-align: center; }
        /* Lot 109 bis : « Ville » et « Rechercher le lieu… » (prise en charge et destination)
           prennent toute la largeur — main.css plafonne les listes Select2 à 155 px
           (.custom_select .select2-container) et le géocodeur Leaflet pose un champ de 246 px
           dans un bloc qui se rétrécit à son contenu. */
        .dl-carte .custom_select { width: 100%; }
        .dl-carte .custom_select .select2-container,
        .dl-carte .select2-container { width: 100% !important; max-width: none !important; }
        .dl-carte .select2-container--default .select2-selection--single {
            height: 50px; border: 1.5px solid #bacde6; border-radius: 12px; padding: 0 12px;
        }
        .dl-carte .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 48px; padding-left: 4px; color: #131a2b; font-size: 15px;
        }
        .dl-carte .select2-container--default .select2-selection--single .select2-selection__arrow { height: 48px; right: 12px; }
        .dl-recherche .leaflet-control-geocoder { width: 100% !important; max-width: none !important; display: block; float: none; margin: 0; }
        .dl-recherche .leaflet-control-geocoder-form { display: block !important; width: 100%; }
        /* Le bouton-loupe du plugin (26 px) se retrouvait seul au-dessus du champ : le champ dessine déjà sa loupe. */
        .dl-recherche .leaflet-control-geocoder-icon { display: none !important; }
        /* Lot 109 : colonnes du tableau de marchandise réparties sur toute la largeur. */
        @media (min-width: 768px) {
            .dl-tableau th:nth-child(1), .dl-tableau td:nth-child(1) { width: 28%; }
            .dl-tableau th:nth-child(2), .dl-tableau td:nth-child(2) { width: 40%; }
            .dl-tableau th:nth-child(3), .dl-tableau td:nth-child(3) { width: 12%; }
            .dl-tableau th:nth-child(4), .dl-tableau td:nth-child(4) { width: 15%; }
            .dl-tableau td .form-control, .dl-tableau td .form-select { width: 100%; min-width: 0; }
        }
        .dl-entete h1 {
            font-size: 32px; font-weight: 700; color: #131a2b;
            margin-bottom: 14px; letter-spacing: -.5px;
        }
        .dl-entete h1::after {
            content: ''; display: block;
            width: 64px; height: 4px; margin: 12px auto 0;
            border-radius: 2px;
            background: linear-gradient(90deg, #23326e, #1b58a5);
        }
        .dl-entete p { color: #56637a; font-size: 16px; margin: 0 auto; max-width: 640px; }

        .dl-carte {
            background: #fff;
            border: 1.2px solid #bacde6;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(19, 26, 43, .06),
                        0 8px 24px rgba(19, 26, 43, .07);
            padding: 28px 28px 24px;
            margin-bottom: 22px;
        }

        /* Titre d'étape : le numéro dit combien il en reste. */
        .dl-etape { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .dl-etape__num {
            flex: 0 0 auto;
            width: 38px; height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #23326e, #1b58a5);
            color: #fff;
            font-weight: 700; font-size: 15px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 2px 6px rgba(35, 50, 110, .28);
        }
        .dl-etape__titre { margin: 0; font-size: 19px; font-weight: 700; color: #131a2b; }
        .dl-etape__aide { margin: 2px 0 0; font-size: 13.5px; color: #8794ab; }

        /* Champs : le noir pur du thème d'origine écrasait la page. */
        .dl-carte .form-control,
        .dl-carte .form-select,
        .dl-carte select.form-control {
            border: 1px solid #d4e1f0 !important;
            border-radius: 8px;
            min-height: 46px;
            font-size: 15px;
            box-shadow: none;
        }
        .dl-carte textarea.form-control { min-height: 92px; height: auto; }
        .dl-carte .form-control:focus,
        .dl-carte .form-select:focus {
            border-color: #23326e !important;
            box-shadow: 0 0 0 3px rgba(35, 50, 110, .12);
        }
        .dl-libelle { display: block; font-size: 13.5px; font-weight: 600; color: #56637a; margin-bottom: 6px; }

        /* Tableau des marchandises */
        .dl-tableau { margin: 0; }
        .dl-tableau > thead th {
            background: #23326e; color: #fff;
            font-size: 13.5px; font-weight: 600;
            border: 0; padding: 12px 10px; white-space: nowrap;
        }
        .dl-tableau > thead th:first-child { border-top-left-radius: 8px; }
        .dl-tableau > thead th:last-child { border-top-right-radius: 8px; }
        .dl-tableau > tbody > tr > td { border-color: #d4e1f0; padding: 10px; vertical-align: top; }
        .dl-tableau .btn-danger {
            width: 38px; height: 38px; padding: 0;
            border-radius: 8px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; line-height: 1;
        }

        .dl-ajouter {
            background: #e6eef9; color: #23326e;
            border: 1px dashed #bacde6; border-radius: 8px;
            font-weight: 600; padding: 10px 18px;
        }
        .dl-ajouter:hover { background: #dbe7f6; color: #182248; }

        /* ===================================================================
           RECHERCHE D'ADRESSE

           Le champ mesurait 44px, sans repère visuel, et la LISTE DES
           SUGGESTIONS n'avait AUCUN style : elle sortait telle que le plugin la
           produit — texte brut, sans séparation, sans survol, sans bordure —
           posée par-dessus la carte. C'était le point le plus utilisé de la
           page, et le moins soigné.

           Le bloc reste posé en absolu par-dessus la carte pour que les
           suggestions la recouvrent. Il portait « left: 10px » AVEC
           « width: 100% » : sa largeur partait donc du bord gauche du parent et
           débordait de 10px, et le formulaire du géocodeur, non contraint,
           poussait l'ensemble à 135px hors de l'écran. On borne par la droite.
           =================================================================== */
        .dl-recherche { position: relative; min-height: 66px; margin-bottom: 14px; }
        .dl-recherche > div {
            position: absolute; top: 0; left: 0; right: 0;
            width: auto !important;
            z-index: 999;
        }
        .dl-recherche .leaflet-control-geocoder {
            max-width: 100%; box-sizing: border-box;
            background: transparent; border: 0; box-shadow: none;
        }
        .dl-recherche .leaflet-control-geocoder-form { position: relative; }

        /* Loupe dessinée en fond : le plugin ne prévoit pas d'emplacement pour
           un pictogramme, et ajouter un élément casserait son balisage. */
        /* Le « !important » n'est pas un raccourci : « myStyle.css » impose
           bordure, rayon, marge interne et corps de texte sur TOUT
           « form input[type=text] » du site avec !important, et
           « premium-client.css » y ajoute un « background » raccourci — qui
           efface au passage l'image de la loupe. Sans cela, aucune des lignes
           ci-dessous ne s'applique : le champ reprend l'aspect commun, et la
           marge de 48px reservee au pictogramme disparait avec lui.
           La portee reste limitee a « .dl-recherche » : rien d'autre sur le
           site n'est touche. */
        .dl-recherche .leaflet-control-geocoder-form input {
            width: 100% !important; box-sizing: border-box;
            min-height: 60px !important;
            padding: 12px 16px 12px 50px !important;
            font-size: 16px !important; color: #131a2b !important;
            background-color: #fff !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238794ab' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M20 20l-3.5-3.5'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: 16px center !important;
            background-size: 22px 22px !important;
            border: 1.5px solid #bacde6 !important;
            border-radius: 12px !important;
            box-shadow: 0 1px 3px rgba(19, 26, 43, .06) !important;
            transition: border-color .15s, box-shadow .15s;
        }
        .dl-recherche .leaflet-control-geocoder-form input::placeholder { color: #8794ab; }
        .dl-recherche .leaflet-control-geocoder-form input:focus {
            outline: none;
            border-color: #23326e !important;
            box-shadow: 0 0 0 4px rgba(35, 50, 110, .14) !important;
        }

        /* La liste des suggestions — l'élément qui n'avait aucun style. */
        .dl-recherche .leaflet-control-geocoder-alternatives {
            max-width: 100%;
            margin: 8px 0 0;
            padding: 6px;
            list-style: none;
            background: #fff;
            border: 1px solid #d4e1f0;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(19, 26, 43, .14);
            max-height: 320px; overflow-y: auto;
        }
        .dl-recherche .leaflet-control-geocoder-alternatives li { border: 0; }
        .dl-recherche .leaflet-control-geocoder-alternatives li a {
            display: block;
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 14.5px; line-height: 1.4;
            color: #131a2b; text-decoration: none;
            white-space: normal;
        }
        .dl-recherche .leaflet-control-geocoder-alternatives li a:hover,
        .dl-recherche .leaflet-control-geocoder-alternatives li.leaflet-control-geocoder-selected a {
            background: #e6eef9; color: #23326e;
        }
        /* Le plugin met le nom du lieu en <span class="…-address"> quand
           l'adresse détaillée est demandée : on distingue le lieu de sa
           localité, au lieu d'une seule ligne indifférenciée. */
        .dl-recherche .leaflet-control-geocoder-address-detail { color: #56637a; font-size: 13px; }

        /* « Aucun lieu trouvé » : c'était du texte nu, sans cadre. */
        .dl-recherche .leaflet-control-geocoder-error {
            display: block; margin: 8px 0 0;
            padding: 12px 14px;
            background: #fbf2e2; border: 1px solid rgba(183, 121, 31, .25);
            border-radius: 10px;
            font-size: 14px; color: #8a5c14;
        }

        /* Indicateur d'attente du plugin, pour qu'il ne reste pas collé au bord. */
        .dl-recherche .leaflet-control-geocoder-throbber .leaflet-control-geocoder-form input {
            background-image: none;
        }

        /* Sélecteur porté par l'identifiant : myStyle.css impose
           « #map { height: 500px; width: 70%; margin: auto } » à l'échelle du
           site. Une simple classe ne peut pas l'emporter — le balisage d'origine
           s'en sortait par un style en ligne. On ne touche pas au fichier
           global, d'autres pages s'appuient dessus. */
        #map.dl-carteleaflet,
        #map1.dl-carteleaflet {
            height: 420px; width: 100%; margin: 0;
            border: 1px solid #d4e1f0; border-radius: 10px;
            overflow: hidden; background: #e4edf7;
        }
        .dl-coordonnees { font-size: 13px; color: #8794ab; margin-top: 8px; }

        .dl-envoyer {
            width: 100%; padding: 16px 20px;
            font-size: 16.5px; font-weight: 700; border-radius: 12px;
            border: 0;
            background: linear-gradient(135deg, #23326e, #1b58a5) !important;
            box-shadow: 0 6px 18px rgba(35, 50, 110, .28) !important;
            border-radius: 12px !important;
            color: #fff !important;
        }
        .dl-envoyer:hover { background: linear-gradient(135deg, #182248, #17498a) !important; }

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
                border: 1px solid #d4e1f0; border-radius: 10px;
                padding: 6px 10px 10px; margin-bottom: 14px; background: #f7faff;
            }
            .dl-tableau > tbody > tr > td { border: 0; padding: 8px 0; }
            .dl-tableau > tbody > tr > td::before {
                content: attr(data-libelle);
                display: block;
                font-size: 12.5px; font-weight: 600; color: #56637a;
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
                {{-- Lot 109 : toute la largeur du conteneur (col-lg-10 laissait 20 % de vide). --}}
                <div class="col-lg-12">

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
                                    {{-- Le délai toléré (lot 81, 15/09/2026). --}}
                                    <small class="text-muted d-block mt-1">{{ \Help::mentionDelaiLivraison() }}</small>
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

                            <label class="dl-libelle">Rechercher le lieu de prise en charge <span class="champ-obligatoire" style="color:#d9534f;font-weight:bold;margin-left:2px;">*</span></label>
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

                            <label class="dl-libelle">Rechercher le lieu de destination <span class="champ-obligatoire" style="color:#d9534f;font-weight:bold;margin-left:2px;">*</span></label>
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
        var geocoder = creerRechercheLieu({
            title: 'Rechercher le lieu de prise en charge',
            placeholder: 'Où récupérons-nous la marchandise ?',
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

        // REFERMER LA LISTE UNE FOIS LE LIEU CHOISI, PUIS LA ROUVRIR.
        //
        // Deux défauts corrigés ici :
        //
        //  1. Le masquage visait « document.querySelector(...) », c'est-à-dire
        //     la PREMIÈRE liste de la page — celle du départ — quelle que soit
        //     la barre qui venait d'être utilisée. Choisir une destination
        //     refermait donc la liste du départ. On se limite au conteneur de
        //     cette barre-ci.
        //
        //  2. La réouverture n'écoutait que « startgeocode », l'événement
        //     d'une recherche VALIDÉE. Depuis que les suggestions apparaissent
        //     à la frappe, c'est « startsuggest » qui est émis : sans lui, la
        //     liste refermée après un premier choix ne serait jamais rouverte,
        //     et le client ne verrait plus aucune proposition ensuite.
        function listeDesSuggestions() {
            return geocoder.getContainer()
                           .querySelector('.leaflet-control-geocoder-alternatives');
        }

        geocoder.on('markgeocode', function () {
            var liste = listeDesSuggestions();
            if (liste) {
                liste.style.display = 'none';
            }
        });

        geocoder.on('startgeocode startsuggest', function () {
            var liste = listeDesSuggestions();
            if (liste) {
                liste.style.display = 'block';
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

    const geocoder1 = creerRechercheLieu({
        title: 'Rechercher le lieu de destination',
        placeholder: 'Où livrons-nous la marchandise ?',
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

    // Même traitement que pour la barre de départ : la liste des suggestions
    // restait ouverte par-dessus la carte de destination une fois le lieu
    // choisi, alors qu'elle se refermait sur l'autre carte.
    function listeDesSuggestions1() {
        return geocoder1.getContainer()
                        .querySelector('.leaflet-control-geocoder-alternatives');
    }

    geocoder1.on('markgeocode', () => {
        const liste = listeDesSuggestions1();
        if (liste) { liste.style.display = 'none'; }
    });

    geocoder1.on('startgeocode startsuggest', () => {
        const liste = listeDesSuggestions1();
        if (liste) { liste.style.display = 'block'; }
    });

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
