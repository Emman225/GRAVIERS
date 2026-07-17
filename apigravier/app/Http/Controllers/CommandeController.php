<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Devis;
use App\Models\Ville;
use App\Models\Client;
use App\Models\BlClient;
use App\Models\Commande;
use App\Models\Location;
use App\Models\Reduction;
use App\Models\DetailDevis;
use App\Models\TvaCommande;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\Produit;
use App\Models\RetourProduit;
use App\Models\DetailCommande;
use App\Mail\EnvoieCommandeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\PreuveOperationBanque;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\DemandeAnnulationCommande;
use Illuminate\Validation\ValidationException;

class CommandeController extends Controller
{
    public function listeCommande(Request $request)
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
                    'commande' => Commande::liste($client->id),
                    'location' => Location::liste($client->id),
                ];
                $retour->code = 200;
                $retour->message = 'ok';
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

    public function listeDevis(Request $request)
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
                $devs = Devis::liste($client->id);
                foreach ($devs as $d) {
                    $d->date_devis = $d->created_at->format("d/m/Y H:i:s");
                }
                $retour->data = $devs;
                $retour->code = 200;
                $retour->message = 'ok';
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

    public function enregistrerDevis(Request $request)
    {
        Request()->validate([
            "access" => "required",
            "type" => "required",
            "libelle" => "required",
            "montantHt" => "required",
            "montantTva" => "required",
            "meFaireLivre" => "required",
            "lignes" => "required | array",
            "total" => "required",
            "typeLivraison" => "required",
            "service" => "required",
            "long" => "required",
            "lat" => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);
                $ville = Ville::lire($user->ville_id);

                $devis = new Devis();
                $devis->numero = Help::genererNumeroUnique('devis');
                $devis->client_id = $client->id;
                $devis->montant = $request->total;
                $devis->libelle = $request->libelle;
                $devis->statut = Help::$STATUT_ACTIF;
                $devis->tva = $request->montantTva;
                $devis->cout_livraison = ($request->meFaireLivre == true || $request->meFaireLivre == 1) ? $request->coutLivraison : 0;
                $devis->cout_reduction = $request->coutReduction;
                $devis->montant_ht = $request->montantHt;
                $devis->mode_paiement_id = $request->moyenPaiement;
                $devis->type_livraison_id = $request->typeLivraison;
                $devis->adresse_livraison_id = $request->adresseLivraison;
                $devis->date_livraison = $request->dateLivraison;
                $devis->service = is_numeric($request->service) ? $request->service : ($request->service == 'VENTE' ? 1 : 2);
                if ($devis->save()) {
                    // Le coût de livraison TOTAL du devis (client-envoyé depuis le résumé,
                    // désormais aligné sur le web) est réparti proportionnellement à la
                    // quantité sur chaque ligne — pour que la somme des lignes = le total.
                    $livTotal = ($request->meFaireLivre == true || $request->meFaireLivre == 1) ? (float) $request->coutLivraison : 0;
                    $qteTotaleDevis = 0;
                    foreach ($request->lignes as $l) { $qteTotaleDevis += (float) $l[('qte')]; }
                    foreach ($request->lignes as $l) {

                        $cl = ($qteTotaleDevis > 0) ? ((float) $l[('qte')] / $qteTotaleDevis) * $livTotal : 0;

                        $ligne = new DetailDevis();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->devis_id = $devis->id;
                        $ligne->qte = $l['qte'];
                        $ligne->prix = $l['prix'];
                        $ligne->statut = Help::$STATUT_ACTIF;
                        $ligne->cout_livraison = $cl;
                        $ligne->debut_location = $l['dateDebut'];
                        $ligne->fin_location = $l['dateDeFin'];
                        $ligne->nbre_jour_location = $l['nbreJours'];
                        $ligne->save();
                    }
                }
                $retour->code = 200;
                $retour->message = 'Votre devis a été enregistré avec succès';
                DB::commit();
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
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

    public function resumeCommande(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'total' => "required",
            'remise' => "required",
            'montantTva' => "required",
            'long' => "required",
            'lat' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $ville = Ville::lire($user->ville_id);

                // MÊME calcul que le web : UN SEUL coût de livraison pour toute la
                // commande (km × prixKm), réparti proportionnellement à la quantité sur
                // chaque ligne — au lieu d'additionner un coût par ligne (qui sur-comptait
                // pour les commandes multi-produits).
                $montant = 0;
                $lignes = $request->lignes;
                $qteTotale = 0;
                foreach ($lignes as $l) { $qteTotale += (float) $l[('qte')]; }
                if ($qteTotale > 0) {
                    $montant = CoutLivraison::calculer($request->long, $request->lat, $ville->region_id, $qteTotale);
                    foreach ($lignes as $key => $l) {
                        $lignes[$key]['livraison'] = ((float) $l[('qte')] / $qteTotale) * $montant;
                    }
                }

                $totalTTC = $request->total;
                $newTotal = $totalTTC + $montant;

                $retour->code = 200;
                $retour->data = [
                    "montant" => $montant,
                    "lignes" => $lignes,
                ];
                $retour->message = "Vous êtes sur le point de valider une commande d'une total de " .
                    Help::formatNombre($newTotal, true) . "\n\nMontant TTC: " . Help::formatNombre($totalTTC, true)
                    . " \nCout de livraison: " . Help::formatNombre($montant, true) . "\n\nVoulez-vous finaliser la commande ?";

                DB::commit();
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
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

    public function enregistrerCommande(Request $request)
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

                // Garde : tous les produits du panier doivent encore exister (l'app peut
                // avoir un catalogue en cache périmé -> id de produit disparu). Sinon la
                // contrainte de clé étrangère fait planter avec un message SQL brut (500).
                // On renvoie plutôt un message clair et on annule proprement.
                $idsProduits = collect($request->lignes)->pluck('produit_id')->filter()->unique()->values();
                $existants   = Produit::whereIn('id', $idsProduits)->pluck('id');
                if ($idsProduits->count() > 0 && $existants->count() < $idsProduits->count()) {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = "Un ou plusieurs produits de votre panier ne sont plus disponibles. Veuillez actualiser votre catalogue (fermez et rouvrez l'application) puis réessayer.";
                    return response()->json($retour);
                }

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
                $commande = new Commande();
                $commande->numero = Help::genererNumeroUnique('commande');
                $commande->devis_id = null;
                $commande->client_id = $client->id;
                $commande->adresse_livraison_id = $request->adresse;
                // NULL si aucun moyen en ligne choisi (ex. paiement en agence : le mobile
                // envoie 0) : la FK vers mode_paiement refuse 0 -> 500 à la création.
                $commande->mode_paiement_id = $request->moyen_paiement > 0 ? $request->moyen_paiement : null;
                $commande->date_commande = date("Y-m-d H:i:s");
                $commande->montant_total = $request->total;
                // Commande à payer EN LIGNE (mobile « En ligne » = mode 1, montant sous
                // le plafond passerelle) : créée « EN ATTENTE DE PAIEMENT » pour NE PAS
                // apparaître dans la file de traitement du gestionnaire tant que le
                // paiement n'est pas confirmé. Le callback la passe à « EN ATTENTE ».
                // (Parité avec le web ; mêmes conditions que le déclenchement du paiement
                //  en ligne plus bas : mode_paiement == 1 && total <= 2 000 000.)
                $commandePaieEnLigne = ($request->mode_paiement == 1 && $request->total <= 2000000);
                $commande->etat_commande = $commandePaieEnLigne ? Help::$COMMANDE_EN_ATTENTE_PAIEMENT : Help::$COMMANDE_EN_ATTENTE;
                $commande->statut = Help::$STATUT_ACTIF;
                $commande->note = $request->note;
                $commande->date_livraison = $request->date_livraison;
                $commande->type_livraison_id = $request->type_livraison;
                $commande->cout_livraison_client = $montantLivraison;
                $commande->est_livrable = $request->meFaireLivre;
                $commande->remise = $request->remise;

                if ($request->reduction > 0) {
                    $reduction = Reduction::lire($request->reduction);
                    $reduction->est_utilise = true;
                    $reduction->statut = Help::$STATUT_INACTIF;
                    $reduction->save();
                }

                //On vas retirer les points utilisé du client
                if ($request->pointUtilise > 0) {
                    $client->point -= $request->pointUtilise;
                    $client->save();
                }

                if ($commande->save()) {
                    // 'numero_bc'
                    // 'bc_file'
                    if ($request->numero_bc != '' && $request->bc_file != '') {
                        $bc = new BlClient();
                        $bc->numero = $request->numero_bc;
                        $bc->client_id = $client->id;
                        $bc->commande_id = $commande->id;
                        $bc->montant = $commande->montant_total;
                        $bc_file = $request->bc_file;
                        if ($bc_file != '' && $bc_file != 'null') {
                            $storedFilePath = "lesBons/$commande->id.png";
                            Storage::disk("principal")->put($storedFilePath, base64_decode($bc_file));
                            $bc->fichier = $storedFilePath;
                        }
                        $bc->statut = Help::$STATUT_ACTIF;
                        $bc->save();
                    }

                    if ($request->montantTva > 0) {
                        $mtva = new TvaCommande();
                        $mtva->client_id = $client->id;
                        $mtva->commande_id = $commande->id;
                        $mtva->montant = $request->montantTva;
                        $mtva->type_affaire = Help::$VENTE;
                        $mtva->statut = Help::$STATUT_ACTIF;
                        $mtva->save();
                    }

                    foreach ($lignes as $l) {
                        $ligne = new DetailCommande();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->commande_id = $commande->id;
                        $ligne->qte = $l['qte'];
                        $ligne->prix = $l['prix'];
                        $ligne->statut = Help::$STATUT_ACTIF;
                        $ligne->cout_livraison = ($request->meFaireLivre == 1 || $request->meFaireLivre == true) ? $l['livraison'] : 0;
                        $ligne->save();
                    }

                    //On vas payé l'apporteur d'aff
                    // if ($client->parrain_id > 0) {
                    //     $this->payerApporteurAffaire($client->parrain_id, $commande->id, $commande->montant_total, Help::$VENTE);
                    // }

                    $retour->code = 200;
                    $retour->message = 'Commande effectuée avec succès nous vous contacterons dans quelque instant';

                    $ret = array();
                    if ($client->client_a_terme == false) {
                        if ($commande->montant_total <= 2000000 && $request->mode_paiement == 1) {
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
                                    'lib_order' => "Paiement commande de produit IMLOD",
                                    'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                    'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                                ],
                                $commande->numero,
                                $codePaiement,
                                $client,
                                $request->total,
                                $request->mode_paiement,
                                $commande->id,
                                Help::$COMMANDE
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
                            $preuve->commande_id = $commande->id;
                            $preuve->reference = $request->refOperation;
                            $preuve->num_compte = $request->numCompte;
                            $preuve->banque = $request->banque;
                            $preuve->date_operation = $request->dateOperation;
                            $preuve->service = Help::$COMMANDE;

                            $storedFilePath = "preuveVirement/COM-$commande->id-1.png";
                            Storage::disk("principal")->put($storedFilePath, base64_decode($request->fichierVir));
                            $preuve->fichier = $storedFilePath;

                            $preuve->note_supp = $request->note;
                            $preuve->statut = Help::$STATUT_INACTIF;
                            $preuve->save();
                        }
                    }

                    DB::commit();

                    // Email de confirmation APRÈS commit et NON bloquant : un timeout
                    // SMTP (LWS) ne doit jamais faire échouer/annuler la commande déjà
                    // enregistrée par le client. cf. règle projet « emails non bloquants ».
                    try {
                        $com = Commande::lire($commande->id);
                        $lis = DetailCommande::liste(null, $commande->id);
                        Mail::to($user->email)->send(new EnvoieCommandeMail($com, $lis, $request->montantTva, $client->nom . ' ' . $client->prenom, $client->email, $client->contact1, Help::$VENTE));
                    } catch (\Throwable $mailEx) {
                        Log::error('Email commande non envoyé (commande ' . $commande->id . '): ' . $mailEx->getMessage());
                    }
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
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite à la ligne ' . $th->getLine() . ' code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function detailsCommande(Request $request, $id)
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
                $commande = Commande::lire($id);

                // IDOR : ne renvoyer la commande que si elle appartient au client
                // authentifié. Sans ce contrôle, un client pouvait lire montant /
                // adresse / note de la commande d'un autre en passant un id arbitraire.
                if (!$commande || $commande->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 404;
                    $retour->message = 'Commande introuvable';
                    return response()->json($retour);
                }

                $retour->data = [
                    'client_a_terme' => $client->client_a_terme == true ? true : false,
                    'commande' => $commande,
                    'lignes' => DetailCommande::liste(null, $id, $client->id),
                ];
                $retour->code = 200;
                $retour->message = 'ok';
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

    public function detailsDevis(Request $request, $id)
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
                $retour->data = DetailDevis::liste($client->id, $id);
                $retour->code = 200;
                $retour->message = 'ok';
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

    public function supprimerDevis(Request $request, $id)
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

                $devis = Devis::lire($id);
                $devis->statut = Help::$STATUT_INACTIF;
                $devis->save();

                $dets = DetailDevis::liste(null, $id);
                foreach ($dets as $d) {
                    $d->statut = Help::$STATUT_INACTIF;
                    $d->save();
                }

                $retour->code = 200;
                $retour->message = "Devis supprimé avec succès";
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

    public function annulerCommande(Request $request, $id)
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

                $commande = Commande::lire($id);
                $client = Client::lireSurUser($user->id);

                // PROPRIÉTÉ : la commande doit exister ET appartenir au client qui
                // appelle. Sans ce contrôle, n'importe quel utilisateur connecté
                // pouvait annuler la commande d'un autre en devinant son id — et
                // l'écran location de l'app (branché par erreur sur cet endpoint)
                // annulait la COMMANDE portant le même id que la location.
                if ($commande->id <= 0 || $client->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette commande est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                // GARDE-FOUS : l'annulation DIRECTE n'est autorisée que si la
                // commande n'est ni payée ni en cours de traitement/livraison.
                // Sinon, on transforme automatiquement en DEMANDE d'annulation
                // que l'admin devra approuver (remboursement/retour à gérer).
                $paye = \App\Models\LignePaiement::where('service', Help::$COMMANDE)
                    ->where('service_id', $commande->id)
                    ->where('statut', 1)
                    ->sum('montant');

                $detailIds = DetailCommande::where('commande_id', $commande->id)->pluck('id');
                $enTraitement = $commande->etat_commande === 'TERMINEE'
                    || ($detailIds->isNotEmpty() && \App\Models\Livraison::whereIn('detail_commande_id', $detailIds)
                            ->where('provenance', Help::$COMMANDE)->exists());

                if ($paye > 0 || $enTraitement) {
                    $demande = DemandeAnnulationCommande::lireSurCle($client->id, $commande->id, Help::$VENTE);
                    if ($demande->id <= 0) {
                        $demande = new DemandeAnnulationCommande();
                        $demande->client_id = $client->id;
                        $demande->user_id = $user->id; // colonne NOT NULL
                        $demande->commande_id = $commande->id;
                        $demande->motif = "Annulation demandée depuis le mobile (commande "
                            . ($paye > 0 ? "déjà payée" : "en cours de traitement") . ")";
                        $demande->est_traite = false;
                        $demande->statut = Help::$STATUT_ACTIF;
                        $demande->type_affaire = Help::$VENTE;
                        $demande->save();
                    }
                    $retour->code = 200;
                    $retour->message = $paye > 0
                        ? "Cette commande a déjà été payée : une demande d'annulation a été transmise à notre équipe (le remboursement sera traité après validation)."
                        : "Cette commande est déjà en cours de traitement : une demande d'annulation a été transmise à notre équipe pour validation.";
                } else {
                    $commande->etat_commande = 'ANNULEE';
                    $commande->statut = Help::$STATUT_INACTIF;
                    $commande->save();

                    $dets = DetailCommande::liste(null, $id);
                    foreach ($dets as $d) {
                        $d->statut = Help::$STATUT_INACTIF;
                        $d->save();
                    }

                    $retour->code = 200;
                    $retour->message = "Commande annulée avec succès";
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

    public function demandeAnnulerCommande(Request $request, $id)
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

                // PROPRIÉTÉ : même contrôle que annulerCommande — la demande ne peut
                // viser qu'une commande du client appelant.
                $commande = Commande::lire($id);
                if ($commande->id <= 0 || $client->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette commande est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                $demande = DemandeAnnulationCommande::lireSurCle($client->id, $id, Help::$VENTE);
                if ($demande->id <= 0) {
                    $demande = new DemandeAnnulationCommande();
                    $demande->client_id = $client->id;
                    $demande->user_id = $user->id; // colonne NOT NULL : sans elle, erreur 1364 -> la demande n'était JAMAIS enregistrée
                    $demande->commande_id = $id;
                    $demande->motif = $request->motif;
                    $demande->est_traite = false;
                    $demande->statut = Help::$STATUT_ACTIF;
                    $demande->type_affaire = Help::$VENTE;
                    $demande->save();
                    $retour->code = 200;
                    $retour->message = "Demande d'annulation de commande envoyée avec succès";
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez déjà une demande d\'annulation en cours de traitement pour cette commande';
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

    public function verifierCodePromo(Request $request)
    {
        Request()->validate([
            // 'access' => "required",
            // 'type' => "required",
            'code' => "required",
        ]);
        $retour = new Retour();

        try {
            $reduction = Reduction::lireCode($request->code);
            if ($reduction->id > 0) {
                // Mêmes règles que le web (ClientController::appliquerCodePromo) : sans ces
                // contrôles le mobile ne testait QUE l'existence du code -> un code déjà
                // utilisé, désactivé ou expiré restait indéfiniment acceptable.
                $aujourdhui = date('Y-m-d');
                $motifRefus = null;

                if ($reduction->est_utilise == 1) {
                    $motifRefus = 'Ce code promo a déjà été utilisé.';
                } elseif (isset($reduction->statut) && $reduction->statut == 0) {
                    $motifRefus = "Ce code promo n'est plus actif.";
                } elseif (!empty($reduction->debut) && $aujourdhui < \Carbon\Carbon::parse($reduction->debut)->format('Y-m-d')) {
                    $motifRefus = "Ce code promo n'est pas encore valide (à partir du "
                        . \Carbon\Carbon::parse($reduction->debut)->format('d-m-Y') . ').';
                } elseif (!empty($reduction->fin) && $aujourdhui > \Carbon\Carbon::parse($reduction->fin)->format('Y-m-d')) {
                    $motifRefus = 'Ce code promo a expiré le '
                        . \Carbon\Carbon::parse($reduction->fin)->format('d-m-Y') . '.';
                }

                if ($motifRefus !== null) {
                    $retour->code = 405;
                    $retour->message = $motifRefus;
                } else {
                    $retour->data = $reduction;
                    $retour->code = 200;
                    $retour->message = "Code promo valide";
                }
            } else {
                $retour->code = 405;
                $retour->message = 'Code promo invalide';
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

    public function demandeRetourProduit(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'motif' => "required",
            'idLigne' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);
                $ok = false;

                foreach ($request->idLigne as $r) {
                    $retourProd = RetourProduit::lireSurDetailCommande($r, $client->id);
                    if ($retourProd->id <= 0) {
                        $retourProd = new RetourProduit();
                        $retourProd->motif = $request->motif;
                        $retourProd->client_id = $client->id;
                        $retourProd->detail_commande_id = $r;
                        $retourProd->statut = Help::$STATUT_ACTIF;
                        $retourProd->date_retour = date('Y-m-d');
                        $retourProd->save();
                        $ok = true;
                    }
                }

                if ($ok == true) {
                    $retour->code = 200;
                    $retour->message = "Demande de retour produit enregistré avec succès";
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez une demande de retour pour ce produit en cours de traitement';
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
}
