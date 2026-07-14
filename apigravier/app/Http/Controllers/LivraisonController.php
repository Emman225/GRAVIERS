<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Livraison;
use App\Models\ModePaiement;
use Illuminate\Http\Request;
use App\Models\CoutLivraison;
use App\Models\TypeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailLocation;
use App\Models\AdresseLivraison;
use App\Models\DemandeLivraison;
use App\Models\DetailsLivraison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class LivraisonController extends Controller
{
    public function listeLivraison(Request $request)
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
                if ($user->type_user_id == Help::$USER_CLIENT) {
                    $client = Client::lireSurUser($user->id);
                    $retour->data = Livraison::liste(null, $client->id);
                } else if ($user->type_user_id == Help::$USER_LIVREUR) {
                    $livreur = Livreur::lireSurUser($user->id);
                    $retour->data = Livraison::liste($livreur->id);
                }
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

    public function listeDemandeLivraison(Request $request)
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
                $retour->data = DemandeLivraison::liste($client->id);
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

    public function detailsLivraison(Request $request, $id)
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
                $livraison = Livraison::lire($id);

                // IMPORTANT : un modèle Eloquent VIDE (lire() qui ne trouve rien) se
                // sérialise en "[]" (tableau) et non "{}". Côté mobile, fromJson attend
                // un objet -> reçoit une liste -> exception -> écran en erreur/blocage.
                // On renvoie donc null quand la ligne n'existe pas (ex. livraison sans
                // detail_livraison_id), ce que le mobile gère déjà (test != null).
                $ligneCommande = $livraison->provenance == Help::$LOCATION
                    ? DetailLocation::lire($livraison->detail_commande_id)
                    : DetailCommande::lire($livraison->detail_commande_id);
                if (!($ligneCommande->id ?? null)) {
                    $ligneCommande = null;
                }

                $ligneLivraison = $livraison->detail_livraison_id
                    ? DetailsLivraison::lire($livraison->detail_livraison_id)
                    : null;
                if (!($ligneLivraison->id ?? null)) {
                    $ligneLivraison = null;
                }

                $retour->data = [
                    "ligneCommande"  => $ligneCommande,
                    "ligneLivraison" => $ligneLivraison,
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

    public function detailsDemandeLivraison(Request $request, $id)
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
                $retour->data = DetailsLivraison::liste(null, $id);
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

    public function resumeDemandeLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'distance' => "required",
            'demande' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $montant = 0;
                foreach ($request->lignes as $l) {
                    $cout = CoutLivraison::lireSurCle($l['unite_id'], $l['qte'], $request->distance);
                    if ($cout->id > 0) {
                        $montant += $cout->prix_km;
                    }
                }

                $depart = AdresseLivraison::lire($request->demande['adresseDepart']);
                $destination = AdresseLivraison::lire($request->demande['adresseDestination']);
                $typeLiv = TypeLivraison::lire($request->demande['typeLivraison']);
                $mode = ModePaiement::lire($request->demande['modePaiement']);
                $date = Help::formatterDate($request->demande['dateLivraison'], "Y-m-d", "d-m-Y");

                $retour->data = [
                    "distance" => $request->distance,
                    "montant" => $montant,
                    "depart" => $depart->affichage,
                    "destination" => $destination->affichage,
                    "type_livraison" => $typeLiv->libelle,
                    "mode_paiement" => $mode->libelle,
                    "date_livraison" => $date
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

    public function enregistrerDemandeLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'distance' => "required",
            'demande' => "required",
            'libelle' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);

                $montant = 0;
                $cleLaisse = [];
                foreach ($request->lignes as $key => $l) {
                    $cout = CoutLivraison::lireSurCle($l['unite_id'], $l['qte'], $request->distance);
                    if ($cout->id > 0) {
                        $montant += $cout->prix_km;
                    } else {
                        array_push($cleLaisse, $key);
                    }
                }

                if (count($cleLaisse) == count($request->lignes)) {
                    $retour->code = 405;
                    $retour->message = "Contenu vide ou aucun cout de livraison défini";
                } else {
                    $demande = new DemandeLivraison();
                    $demande->numero = Help::genererNumeroUnique('demande_livraison');
                    $demande->libelle = $request->libelle;
                    $demande->description = $request->demande['note'];
                    $demande->client_id = $client->id;
                    $demande->adresse_livraison_pec_id = $request->demande['adresseDepart'];
                    $demande->adresse_livraison_dest_id = $request->demande['adresseDestination'];
                    $demande->montantTotal = $montant;
                    $demande->etat_commande = Help::$COMMANDE_EN_ATTENTE;
                    $demande->date_livraison = $request->demande['dateLivraison'];
                    $demande->remise = 0;
                    $demande->statut = Help::$STATUT_ACTIF;
                    $demande->mode_paiement_id = $request->demande['modePaiement'];
                    $demande->type_livraison_id = $request->demande['typeLivraison'];
                    $demande->save();

                    foreach ($request->lignes as $key => $l) {
                        if (!in_array($key, $cleLaisse)) {
                            $ligne = new DetailsLivraison();
                            $ligne->nom_produit = $l['produit'];
                            $ligne->qte = $l['qte'];
                            $ligne->unite = $l['unite'];
                            $ligne->description = "";
                            $ligne->demande_livraison_id = $demande->id;
                            $ligne->etat_livraison = Help::$LIVRAISON_EN_ATTENTE;
                            $ligne->statut = Help::$STATUT_ACTIF;
                            $ligne->unite_produit_id = $l['unite_id'];
                            $ligne->save();
                        }
                    }

                    //On vas payé l'apporteur d'aff
                    // if ($client->parrain_id > 0) {
                    //     $this->payerApporteurAffaire($client->parrain_id, $demande->id, $demande->montant_total, Help::$LIVRAISON);
                    // }

                    $retour->code = 200;
                    $retour->message = "Votre demande de livraison a bien été prise en compte.\n Nous vous contacterons très bientôt.";

                    // $paiement = new Paiement();
                    // $paiement->client_id = $client->id;
                    // $paiement->devis_id = 0;
                    // $paiement->service_id = $demande->id;
                    // $paiement->service = Help::$LIVRAISON;
                    // $paiement->code = $demande->numero;
                    // $paiement->libelle = "Paiement demande de livraison IMLOD";
                    // $paiement->montant_total = $montant;
                    // $paiement->montant_restant = 0;
                    // $paiement->statut = Help::$STATUT_INACTIF;
                    // $paiement->save();

                    $ret = array();
                    if ($client->client_a_terme == false) {
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
                                'montant' => ceil($montant),
                                'lib_order' => "Paiement demande de livraison IMLOD",
                                'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                            ],
                            $demande->numero,
                            $codePaiement,
                            $client,
                            $montant,
                            $demande->mode_paiement_id,
                            $demande->id,
                            Help::$LIVRAISON
                        );
                        if ($ret['code'] == 200) {
                            $retour->code = 201;
                            $retour->message = $ret['message'];
                        } else {
                            $retour->code = $ret['code'];
                            $retour->message = $ret['message'];
                        }
                    }

                    DB::commit();
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
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }
}
