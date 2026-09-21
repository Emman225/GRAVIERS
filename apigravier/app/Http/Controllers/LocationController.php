<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Ville;
use App\Models\Client;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\Reduction;
// Casse EXACTE des fichiers de modeles (obligatoire sous Linux).
use App\Models\PrixPersonnalise;
use App\Models\Produit;
use App\Models\TvaCommande;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Services\CalculMontant;
use App\Models\AdresseLivraison;
use App\Models\CoutLivraison;
use App\Models\IntervalPoint;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Mail\EnvoieCommandeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\PreuveOperationBanque;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\DemandeAnnulationCommande;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function listeLocation(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);
                $locations = Location::liste($client->id);
                // Où en est le matériel (10/09/2026) : « Livrée le … », « Retirée le … »,
                // « En livraison » — la location, elle, reste EN COURS jusqu'au retour.
                foreach ($locations as $l) {
                    $etat = Location::etatLivraison($l);
                    $l->etat_livraison_code    = $etat['code'] ?? null;
                    $l->etat_livraison_libelle = $etat['libelle'] ?? null;
                }
                $retour->data = $locations;
                $retour->code = 200;
                $retour->message = 'ok';
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    /**
     * Message de refus si la location dépasse le crédit disponible du client.
     *
     * Même règle, mêmes chiffres et même formulation que pour une commande
     * (CommandeController::refusPlafondCredit) : le client doit lire la même
     * explication quel que soit ce qu'il réserve.
     *
     * @return string|null  null si la location passe
     */
    private function refusPlafondCredit(?Client $client, float $montant): ?string
    {
        if (!$client || !$client->client_a_terme) {
            return null;
        }

        $disponible = $client->plafondDisponible();

        if ($disponible === null || $montant <= $disponible) {
            return null;
        }

        $format = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';

        return sprintf(
            "Cette location de %s dépasse votre crédit disponible. "
            . "Plafond accordé : %s. Déjà engagé : %s. Reste disponible : %s. "
            . "Réglez une facture en cours ou réduisez votre location pour continuer.",
            $format($montant),
            $format((float) $client->plafond_credit),
            $format($client->encoursCredit()),
            $format($disponible)
        );
    }

    public function enregistrerLocation(Request $request)
    {
        //   'adresse': addr.id,
        //   'mode_paiement': mode,
        //   'moyen_paiement': mp.id,
        //   'note': data[2],
        //   'date_livraison': data[3],
        //   'type_livraison': tl.id,
        //   'numero_bc': data[5],
        //   'bc_file': bcByte == null ? null : base64Encode(bcByte),
        //   'total': getTotalAmount() + coutLivraison,
        //   "lignes": lignes,
        //   "reduction": reduction.id,
        //   "remise": coutReduction,
        //   "montantTva": montantTva,
        //   "montantLivraison": coutLivraison,
        //   "meFaireLivre": meFaireLivre,
        //   "pointUtilise": utiliserPoint == true ? nombrePoint : 0,
        //   'long': position?.longitude ?? 0,
        //   'lat': position?.latitude ?? 0,
        //   'banque': data[8],
        //   'numCompte': data[9],
        //   'refOperation': data[10],
        //   'dateOperation': data[11],
        //   'fichierVir': virByte == null ? null : base64Encode(virByte),
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'mode_paiement' => "required",
            'lignes' => "required",
            'total' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            $config = Configuration::find(1);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);

                // =====================================================================
                // MONTANTS CALCULÉS PAR LE SERVEUR
                // ---------------------------------------------------------------------
                // Prix unitaires, TVA, remise et coût de livraison sont recalculés à
                // partir du catalogue, des prix personnalisés du client, de la
                // configuration et de l'ADRESSE DE LIVRAISON — jamais repris de
                // l'application. Le calcul vit dans App\Services\CalculMontant, utilisé
                // aussi par « verifier-montant » : le client voit donc AVANT de valider
                // exactement le montant qui lui sera prélevé.
                // Le serveur corrige, il ne refuse pas : tout écart est journalisé.
                // =====================================================================
                $calcul = CalculMontant::pour($user, $client, [
                    'lignes'       => $request->lignes,
                    'adresse'      => $request->adresse,
                    'meFaireLivre' => $request->meFaireLivre,
                    'long'         => $request->long,
                    'lat'          => $request->lat,
                    'reduction'    => $request->reduction,
                    'pointUtilise' => $request->pointUtilise,
                ], true);

                $lignes           = $calcul['lignes'];
                $montantLivraison = $calcul['livraison'];
                $tvaServeur       = $calcul['tva'];
                $remiseServeur    = $calcul['remise'];
                $totalServeur     = $calcul['total'];
                $pointsUtilises   = $calcul['points_utilises'];
                $reductionValide  = $calcul['reduction_valide'];

                if (abs($totalServeur - (float) $request->total) > 1 || !empty($calcul['ecarts'])) {
                    \Log::warning('Location mobile — montants recalculés par le serveur', [
                        'client_id'        => $client->id,
                        'total_envoye'     => (float) $request->total,
                        'total_applique'   => $totalServeur,
                        'tva_envoyee'      => (float) ($request->montantTva ?? 0),
                        'tva_appliquee'    => $tvaServeur,
                        'remise_envoyee'   => (float) ($request->remise ?? 0),
                        'remise_appliquee' => $remiseServeur,
                        'ecarts'           => $calcul['ecarts'],
                    ]);
                }
                // PLAFOND DE CRÉDIT — une location l'engage comme une commande : le
                // client repart avec le matériel et paiera plus tard. La règle ne
                // portait que sur les commandes, si bien qu'une location de 260 000
                // FCFA passait sur un plafond de 5 000, puis restait invisible dans
                // l'encours — le client pouvait ensuite commander comme si rien
                // n'était dû.
                //
                // Une avance disponible couvre d'abord la location : seul le
                // reliquat engage le crédit — même règle que la commande. Seul le
                // « Paiement en agence » (mode 3) consomme l'avance.
                $montantACredit = (int) $request->mode_paiement === 3
                    ? max(0, $totalServeur - \App\Services\Avances::soldeDisponible($client))
                    : $totalServeur;
                if ($refus = $this->refusPlafondCredit($client, $montantACredit)) {
                    DB::rollBack();
                    $retour->code = 403;
                    $retour->message = $refus;
                    return response()->json($retour);
                }

                // LE PLAFOND DU PAIEMENT EN LIGNE — AVANT TOUTE ÉCRITURE.
                //
                // Le seuil vivait plus bas, recopié à la main, et il ne
                // refusait rien : la passerelle était sautée et la location
                // s'enregistrait comme si elle devait être réglée au comptoir,
                // sans qu'un franc soit encaissé ni que l'application puisse
                // le dire au client. Même correction que sur le site.
                if ($request->mode_paiement == 1
                    && \App\Support\PlafondPaiementEnLigne::depasse($totalServeur)) {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = \App\Support\PlafondPaiementEnLigne::refus();
                    return response()->json($retour);
                }

                // LE BON DE COMMANDE D'UNE ENTREPRISE EST OBLIGATOIRE, POUR UNE LOCATION
                // AUSSI (09/09/2026) — même règle que la vente (CommandeController).
                // L'application l'exige déjà à l'écran ; ce contrôle-ci fait foi.
                if ($client->type_client == Help::$ENTREPRISE
                    && trim((string) $request->numero_bc) === '') {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = "Le numéro de bon de commande interne est obligatoire pour une entreprise : "
                        . "il sera reporté sur votre bon de location.";
                    return response()->json($retour);
                }

                $location = new Location();
                $location->numero = Help::genererNumeroUnique('location');
                $location->client_id = $client->id;
                // Numéro figé sur la location, reporté devant chaque désignation.
                $location->numero_bon_commande = trim((string) $request->numero_bc) ?: null;
                $location->adresse_livraison_id = $request->adresse;
                // NULL si aucun moyen en ligne choisi (ex. paiement en agence : le mobile
                // envoie 0) : la FK vers mode_paiement refuse 0 -> 500 à la création.
                $location->mode_paiement_id = $request->moyen_paiement > 0 ? $request->moyen_paiement : null;
                $location->date_location = date("Y-m-d H:i:s");
                // Montant recalcule par le serveur (bloc « CONTROLE SERVEUR DES
                // MONTANTS » ci-dessus), jamais celui envoye par l'application.
                $location->montant_total = $totalServeur;
                $location->etat_location = Help::$LOCATION_EN_ATTENTE;
                $location->statut = Help::$STATUT_ACTIF;
                $location->note = $request->note;
                $location->cout_livraison_client = $montantLivraison;
                // TVA sur le transport (point 5), figée avec la location.
                $location->tva_transport = (float) ($calcul['tva_transport'] ?? 0);
                // AIRSI figé sur la location (10/09/2026).
                $location->airsi = (float) ($calcul['airsi'] ?? 0);
                // Choix de livraison du client (l'app l'envoie déjà, il n'était simplement
                // pas enregistré) : sans lui, une location « Retrait sur place » était
                // INVALIDABLE côté gestionnaire, qui exige un livreur. Même normalisation
                // que la commande (l'app envoie un booléen JSON).
                $location->est_livrable = ($request->meFaireLivre == 1 || $request->meFaireLivre == true) ? 1 : 0;
                // Remise recalculee par le serveur (code promo reverifie, points
                // plafonnes au solde reel).
                $location->remise = $remiseServeur;
                if ($reductionValide) {
                    $reduction = $reductionValide;
                    // est_utilise est CE QUI rend le code invalide (badge « Code invalide »
                    // de creation-de-code-promo, et refus dans la vérification du code).
                    // Sans lui, un code promo consommé par une location restait réutilisable
                    // à l'infini. Parité avec CommandeController::enregistrerCommande.
                    $reduction->est_utilise = true;
                    $reduction->statut = Help::$STATUT_INACTIF;
                    $reduction->save();
                }

                //On vas retirer les points utilisé du client
                if ($pointsUtilises > 0) {
                    $client->point -= $pointsUtilises;
                    $client->save();
                }

                if ($location->save()) {
                    // La pièce jointe du bon rejoint bl_client, rattachée à la LOCATION
                    // (09/09/2026) — même rangement que pour une vente.
                    if (trim((string) $request->numero_bc) !== '' && $request->bc_file != '' && $request->bc_file != 'null') {
                        $bc = new \App\Models\BlClient();
                        $bc->numero = trim((string) $request->numero_bc);
                        $bc->client_id = $client->id;
                        $bc->location_id = $location->id;
                        $bc->montant = $location->montant_total;
                        $storedFilePath = "lesBons/location-$location->id.png";
                        Storage::disk("principal")->put($storedFilePath, base64_decode($request->bc_file));
                        $bc->fichier = $storedFilePath;
                        $bc->statut = Help::$STATUT_ACTIF;
                        $bc->save();
                    }

                    // Ligne de TVA créée systématiquement (0 si non assujetti), pour que
                    // toutes les locations aient la même structure que celles du site.
                    {
                        $mtva = new TvaCommande();
                        $mtva->client_id = $client->id;
                        $mtva->commande_id = $location->id;
                        // TVA recalculee par le serveur.
                        $mtva->montant = max(0, round($tvaServeur));
                        $mtva->type_affaire = Help::$LOCATION;
                        $mtva->statut = Help::$STATUT_ACTIF;
                        $mtva->save();
                    }

                    // ITÉRER $lignes (la copie enrichie de la clé 'livraison' plus haut),
                    // PAS $request->lignes : l'original n'a pas la clé -> "Undefined array
                    // key 'livraison'" dès que meFaireLivre=1. (Bug dormant : avant la
                    // suppression de la FK tva_commande, le flux plantait plus tôt.)
                    foreach ($lignes as $l) {
                        $ligne = new DetailLocation();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->location_id = $location->id;
                        $ligne->qte = $l['qte'];
                        // LE TOTAL DE LA LIGNE, PAS LE PRIX D'UNE JOURNÉE.
                        //
                        // CalculMontant garde dans $l['prix'] le prix UNITAIRE
                        // journalier et calcule le HT à part (prix x qte x jours).
                        // On recopiait ce prix unitaire tel quel dans
                        // detail_location.prix, colonne qui porte le TOTAL de la
                        // ligne — c'est la convention du site, et celle dont
                        // vivent la facture et le montant réclamé au client.
                        //
                        // Une bétonnière à 22 000/jour louée 2 jours s'écrivait
                        // donc 22 000 : la facture U260000000002 annonçait
                        // 22 000 de HT là où le client devait 44 000, tandis que
                        // la TVA, calculée sur le vrai HT, valait bien 7 920.
                        // L'écart se voyait à l'oeil nu sur le document.
                        $ligne->prix = (float) $l['prix']
                            * (float) $l['qte']
                            * max(1, (float) ($l['nbreJours'] ?? 1));
                        $ligne->debut = $l['debut'];
                        $ligne->fin = $l['fin'];
                        $ligne->nombre_jour = $l['nbreJours'];
                        $ligne->cout_livraison = ($request->meFaireLivre == 1 || $request->meFaireLivre == true) ? ($l['livraison'] ?? 0) : 0;
                        $ligne->etat_location = Help::$LOCATION_EN_ATTENTE;
                        $ligne->statut = Help::$STATUT_ACTIF;
                        $ligne->save();
                    }

                    $retour->code = 200;
                    $retour->message = 'Commande effectuée avec succès nous vous contacterons dans quelque instant';

                    // AVANCE DU CLIENT (10/09/2026) : une location réglée « en
                    // agence » (mode 3) s'impute d'elle-même sur les avances
                    // disponibles, comme une commande. Un mode en ligne n'y
                    // touche pas.
                    if ((int) $request->mode_paiement === 3) {
                        $imputationAvance = \App\Services\Avances::imputerSurLocation($location, $client);
                        $retour->avance_imputee = $imputationAvance['impute'];
                        $retour->reste_a_regler = $imputationAvance['reste'];
                        $retour->message .= \App\Services\Avances::messageImputation($imputationAvance, Help::$LOCATION);
                    }

                    $ret = array();
                    // Même correction que pour les commandes : le paiement en ligne
                    // repose sur le MODE CHOISI, plus sur le statut du client. Les deux
                    // passent par le MÊME écran de l'application ; le client à terme qui
                    // choisissait « En ligne » pour une location voyait celle-ci
                    // enregistrée sans qu'aucune passerelle ne s'ouvre.
                    // Le plafond a été opposé au client plus haut, avant toute
                    // écriture : ici il ne reste que le mode choisi.
                    if ($request->mode_paiement == 1) {
                        //Paiement en ligne
                        $codePaiement = Help::getCommandeNo();
                        $nomPrenoms = $client->nom;
                        $arrNoms = explode(" ", $nomPrenoms);
                        $leNom = "";
                        $lePrenom = "";
                        if (count($arrNoms) >= 2) {
                            $leNom = $arrNoms[0];
                            $lePrenom = $arrNoms[1];
                        } else {
                            $leNom = $arrNoms[0];
                            $lePrenom = $arrNoms[0];
                        }
                        $ret = PaiementController::initierPaiement(
                            [
                                'code_paiement' => $codePaiement,
                                // 'credential_id' => "",
                                'nom_usager' => $leNom,
                                'prenom_usager' => $lePrenom,
                                'telephone' => $client->contact1,
                                'email' => $user->email,
                                'libelle_article' => "Paiement DALAKOUN",
                                'quantite' => 1,
                                // Montant reellement preleve : celui du serveur.
                                'montant' => ceil($totalServeur),
                                'lib_order' => "Paiement location de produit DALAKOUN",
                                'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                            ],
                            $location->numero,
                            $codePaiement,
                            $client,
                            $totalServeur,
                            $request->mode_paiement,
                            $location->id,
                            Help::$LOCATION
                        );
                        if ($ret['code'] == 200) {
                            $retour->code = 201;
                            $retour->message = $ret['message'];
                        } else {
                            $retour->code = $ret['code'];
                            $retour->message = $ret['message'];
                        }
                    } else if ($request->mode_paiement == 2) {
                        //Paiement par virement
                        $preuve = new PreuveOperationBanque();
                        $preuve->client_id = $client->id;
                        $preuve->commande_id = $location->id;
                        $preuve->reference = $request->refOperation;
                        $preuve->num_compte = $request->numCompte;
                        $preuve->banque = $request->banque;
                        $preuve->date_operation = $request->dateOperation;
                        $preuve->service = Help::$LOCATION;

                        $storedFilePath = "preuveVirement/LOC-$location->id-1.png";
                        Storage::disk("principal")->put($storedFilePath, base64_decode($request->fichierVir));
                        $preuve->fichier = $storedFilePath;

                        $preuve->note_supp = $request->note;
                        $preuve->statut = Help::$STATUT_INACTIF;
                        $preuve->save();
                    }
                }

                DB::commit();

                // Email de confirmation APRÈS commit et NON bloquant : un timeout
                // SMTP (LWS) ne doit jamais faire échouer/annuler la location déjà
                // enregistrée par le client. cf. règle projet « emails non bloquants ».
                if (isset($location) && $location->id > 0) {
                    try {
                        $loc = Location::lire($location->id);
                        $lis = DetailLocation::liste(null, $location->id);
                        Mail::to($user->email)->send(new EnvoieCommandeMail($loc, $lis, $request->montantTva, $client->display_name, $client->email, $client->contact1, Help::$LOCATION));
                    } catch (\Throwable $mailEx) {
                        Log::error('Email location non envoyé (location ' . $location->id . '): ' . $mailEx->getMessage());
                    }
                }
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite à la ligne ' . $th->getLine() . ' code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function detailsLocation(Request $request, $id)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);
                $location = Location::lire($id);

                // IDOR : ne renvoyer la location que si elle appartient au client
                // authentifié (même garde que detailsCommande). Seules les LIGNES
                // étaient filtrées : montant, adresse de livraison, note et mode de
                // paiement d'un autre client restaient lisibles avec un id arbitraire.
                if (!$location || $location->id <= 0 || $location->client_id != $client->id) {
                    $retour->code = 404;
                    $retour->message = 'Location introuvable';
                    return response()->json($retour);
                }

                // LES CODES DE LA LOCATION (10/09/2026), comme pour une commande :
                // le client remet le code de livraison au livreur, ou le bon
                // d'enlèvement au fournisseur quand il retire lui-même. Une course
                // de location est une ligne de `livraison` de provenance LOCATION
                // dont `detail_commande_id` porte l'id d'une LIGNE de la location
                // (detail_location) — c'est ainsi que le site la crée.
                $retraitSurPlace = (int) $location->est_livrable !== 1 && !$location->adresse_livraison_id;
                $lignesLocation = DB::table('detail_location')->where('location_id', $location->id)->pluck('id');
                $codes = DB::table('livraison')
                    ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
                    ->where('livraison.provenance', Help::$LOCATION)
                    ->whereIn('livraison.detail_commande_id', $lignesLocation)
                    ->where('livraison.accepte', 1)
                    ->whereNull('livraison.deleted_at')
                    ->orderBy('livraison.id')
                    ->get([
                        'livraison.numero as code_livraison',
                        'enlevement.code_enleve as code_enlevement',
                    ]);

                $retour->data = [
                    'client_a_terme' => $client->client_a_terme == true ? true : false,
                    'location' => $location,
                    // Ecran de LECTURE : les lignes d'une location annulee restent visibles.
                    'lignes' => DetailLocation::liste(null, $id, $client->id, true),
                    'retrait_sur_place' => $retraitSurPlace,
                    'codes' => $codes,
                    // Où en est le matériel (10/09/2026).
                    'etat_livraison' => Location::etatLivraison($location),
                ];
                $retour->code = 200;
                $retour->message = 'ok';
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function annulerLocation(Request $request, $id)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $location = Location::lire($id);
                $client = Client::lireSurUser($user->id);

                // PROPRIÉTÉ : la location doit exister ET appartenir au client appelant.
                if ($location->id <= 0 || $client->id <= 0 || $location->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette location est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                // GARDE-FOUS (parité annulerCommande) : l'annulation DIRECTE n'est
                // autorisée que si la location n'est ni payée ni démarrée (matériel
                // sorti / livraison créée). Sinon, on transforme automatiquement en
                // DEMANDE d'annulation que l'admin devra approuver.
                $paye = LignePaiement::where('service', Help::$LOCATION)
                    ->where('service_id', $location->id)
                    ->where('statut', 1)
                    ->sum('montant');

                $detailIds = DetailLocation::where('location_id', $location->id)->pluck('id');
                // NB : pour une livraison de LOCATION, detail_commande_id porte l'id
                // du detail_location (cf. création côté web validerLocation).
                $enTraitement = in_array($location->etat_location, [Help::$LOCATION_EN_COURS, Help::$LOCATION_TERMINE])
                    || ($detailIds->isNotEmpty() && Livraison::whereIn('detail_commande_id', $detailIds)
                            ->where('provenance', Help::$LOCATION)->exists());

                if ($paye > 0 || $enTraitement) {
                    $demande = DemandeAnnulationCommande::lireSurCle($client->id, $location->id, Help::$LOCATION);
                    if ($demande->id <= 0) {
                        $demande = new DemandeAnnulationCommande();
                        $demande->client_id = $client->id;
                        $demande->user_id = $user->id; // colonne NOT NULL
                        $demande->commande_id = $location->id;
                        $demande->motif = "Annulation demandée depuis le mobile (location "
                            . ($paye > 0 ? "déjà payée" : "en cours de traitement") . ")";
                        $demande->est_traite = false;
                        $demande->type_affaire = Help::$LOCATION;
                        $demande->statut = Help::$STATUT_ACTIF;
                        $demande->save();
                    }
                    $retour->code = 200;
                    $retour->message = $paye > 0
                        ? "Cette location a déjà été payée : une demande d'annulation a été transmise à notre équipe (le remboursement sera traité après validation)."
                        : "Cette location est déjà en cours de traitement : une demande d'annulation a été transmise à notre équipe pour validation.";
                } else {
                    // MÊMES effets que l'approbation d'une demande côté web
                    // (DemandeAnnulationController::traiter, branche LOCATION).
                    $location->etat_location = 'ANNULEE';
                    $location->save();
                    // Libère les lignes de matériel réservées.
                    DetailLocation::where('location_id', $location->id)
                        ->update(['etat_location' => Help::$LOCATION_EN_ATTENTE, 'statut' => Help::$STATUT_INACTIF]);

                    $retour->code = 200;
                    $retour->message = "Location annulée avec succès";
                }
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function demandeAnnulerLocation(Request $request, $id)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'motif' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);

                // PROPRIÉTÉ : la demande ne peut viser qu'une location du client appelant.
                $location = Location::lire($id);
                if ($location->id <= 0 || $client->id <= 0 || $location->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette location est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                $demande = DemandeAnnulationCommande::lireSurCle($client->id, $id, Help::$LOCATION);
                if ($demande->id <= 0) {
                    $demande = new DemandeAnnulationCommande();
                    $demande->client_id = $client->id;
                    $demande->user_id = $user->id; // colonne NOT NULL : sans elle, erreur 1364 -> la demande n'était JAMAIS enregistrée
                    $demande->commande_id = $id;
                    $demande->motif = $request->motif;
                    $demande->est_traite = false;
                    $demande->type_affaire = Help::$LOCATION;
                    $demande->statut = Help::$STATUT_ACTIF;
                    $demande->save();
                    $retour->code = 200;
                    $retour->message = "Demande d'annulation de location envoyée avec succès";
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez déjà une demande d\'annulation en cours de traitement pour cette location';
                }
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }
}
