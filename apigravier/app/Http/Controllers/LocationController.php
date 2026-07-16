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
use App\Models\TvaCommande;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\IntervalPoint;
use App\Models\DetailLocation;
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
                $retour->data = Location::liste($client->id);
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

                // MÊME calcul que le web : UN SEUL coût de livraison (km × prixKm)
                // réparti proportionnellement à la quantité sur chaque ligne.
                $montantLivraison = 0;
                $lignes = $request->lignes;
                if ($request->meFaireLivre == 1 || $request->meFaireLivre == true) {
                    $ville = Ville::lire($user->ville_id);
                    $qteTotale = 0;
                    foreach ($lignes as $l) { $qteTotale += (float) $l[('qte')]; }
                    if ($qteTotale > 0) {
                        $montantLivraison = CoutLivraison::calculer($request->long, $request->lat, $ville->region_id, $qteTotale);
                        foreach ($lignes as $key => $l) {
                            $lignes[$key]['livraison'] = ((float) $l[('qte')] / $qteTotale) * $montantLivraison;
                        }
                    }
                }

                $client = Client::lireSurUser($user->id);
                $location = new Location();
                $location->numero = Help::genererNumeroUnique('location');
                $location->client_id = $client->id;
                $location->adresse_livraison_id = $request->adresse;
                // NULL si aucun moyen en ligne choisi (ex. paiement en agence : le mobile
                // envoie 0) : la FK vers mode_paiement refuse 0 -> 500 à la création.
                $location->mode_paiement_id = $request->moyen_paiement > 0 ? $request->moyen_paiement : null;
                $location->date_location = date("Y-m-d H:i:s");
                $location->montant_total = $request->total;
                $location->etat_location = Help::$LOCATION_EN_ATTENTE;
                $location->statut = Help::$STATUT_ACTIF;
                $location->note = $request->note;
                $location->cout_livraison_client = $montantLivraison;
                // Choix de livraison du client (l'app l'envoie déjà, il n'était simplement
                // pas enregistré) : sans lui, une location « Retrait sur place » était
                // INVALIDABLE côté gestionnaire, qui exige un livreur. Même normalisation
                // que la commande (l'app envoie un booléen JSON).
                $location->est_livrable = ($request->meFaireLivre == 1 || $request->meFaireLivre == true) ? 1 : 0;
                $location->remise = $request->remise;
                if ($request->reduction > 0) {
                    $reduction = Reduction::lire($request->reduction);
                    // est_utilise est CE QUI rend le code invalide (badge « Code invalide »
                    // de creation-de-code-promo, et refus dans la vérification du code).
                    // Sans lui, un code promo consommé par une location restait réutilisable
                    // à l'infini. Parité avec CommandeController::enregistrerCommande.
                    $reduction->est_utilise = true;
                    $reduction->statut = Help::$STATUT_INACTIF;
                    $reduction->save();
                }

                //On vas retirer les points utilisé du client
                if ($request->pointUtilise > 0) {
                    $client->point -= $request->pointUtilise;
                    $client->save();
                }

                if ($location->save()) {

                    if ($request->montantTva > 0) {
                        $mtva = new TvaCommande();
                        $mtva->client_id = $client->id;
                        $mtva->commande_id = $location->id;
                        $mtva->montant = $request->montantTva;
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
                        $ligne->prix = $l['prix'];
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

                    $ret = array();
                    if ($client->client_a_terme == false) {
                        if ($location->montant_total <= 2000000 && $request->mode_paiement == 1) {
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
                                    'libelle_article' => "Paiement IMLOD",
                                    'quantite' => 1,
                                    'montant' => ceil($request->total),
                                    'lib_order' => "Paiement location de produit IMLOD",
                                    'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                    'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                                ],
                                $location->numero,
                                $codePaiement,
                                $client,
                                $request->total,
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
                }

                DB::commit();

                // Email de confirmation APRÈS commit et NON bloquant : un timeout
                // SMTP (LWS) ne doit jamais faire échouer/annuler la location déjà
                // enregistrée par le client. cf. règle projet « emails non bloquants ».
                if (isset($location) && $location->id > 0) {
                    try {
                        $loc = Location::lire($location->id);
                        $lis = DetailLocation::liste(null, $location->id);
                        Mail::to($user->email)->send(new EnvoieCommandeMail($loc, $lis, $request->montantTva, $client->nom . ' ' . $client->prenom, $client->email, $client->contact1, Help::$LOCATION));
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
                $retour->data = [
                    'client_a_terme' => $client->client_a_terme == true ? true : false,
                    'location' => Location::lire($id),
                    'lignes' => DetailLocation::liste(null, $id, $client->id),
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

                $demande = DemandeAnnulationCommande::lireSurCle($client->id, $id);
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
