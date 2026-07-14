<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Commande;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\Apporteur;
use App\Models\TvaCommande;
use Illuminate\Http\Request;
use App\Models\IntervalPoint;
use App\Models\LignePaiement;
use App\Models\DetailCommande;
use App\Models\DetailLocation;
use App\Models\DemandeLivraison;
use App\Models\DetailsLivraison;
use App\Models\CommissionApporteur;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use App\Mail\EnvoieCommandeMail;

class PaiementController extends Controller
{
    public function callBackPaiement(Request $request)
    {

        try {

            // ValeurRetour		est une Structure
            //     cleretour	est une chaîne
            //     code		est une entier
            //     codePaiement est une chaîne
            //     moyenPaiement  est une chaîne
            //     numTel est une chaîne
            //     Details est un tableau de stdetail
            // FIN

            // stdetail est une Structure
            //     Credentiel			est une chaîne
            //     datePaiement		est une chaîne
            //     HeurePaiement		est une chaîne
            //     sMessage			est une chaîne
            //     referenceePaiement	est une chaîne
            // FIN

            // "cleretour":"EFB.3747350",
            // "code":200,"
            // "datePaiement":"2023-08-08",
            // "HeurePaiement":"11:00:39",
            // "referencePaiement":"6921531359215765",
            // "montant":200,
            // "benefice":194,
            // "codePaiement":"BYBgPfVfFKbbYThIQO3Z",
            // "service_id":29,
            // "moyenPaiement":"Orange Money",
            // "no_transation":"0757411021",
            // "numTel":"0757411021",
            // "p_last_wallet_amount":11,
            // "p_new_wallet_amount":205,

            if (isset($request->codePaiement)) {

                $lignePaiements = LignePaiement::listeSurCode($request->codePaiement);

                if (count($lignePaiements) > 0) {

                    if ($request->code == 200) {
                        //Effectué
                        $parrain_id = 0;
                        $idService = 0;
                        $service = "";
                        foreach ($lignePaiements as $ligne) {
                            //On met a jour la ligne de paiement actuelle
                            $ligne->reference = $request->referencePaiement;
                            $ligne->moyen_paiement = $request->moyenPaiement;
                            $ligne->date_paiement = date("Y-m-d H:i:s");
                            $ligne->statut = 1;
                            $ligne->save();

                            //On vas calculer le montant total payé
                            $montantPaye = 0;
                            $lignes = LignePaiement::liste($ligne->paiement_id);
                            foreach ($lignes as $l) {
                                if ($l->statut == Help::$STATUT_ACTIF) {
                                    $montantPaye += $l->montant;
                                }
                            }

                            //On met a jour le paiement
                            $paiement = Paiement::lire($ligne->paiement_id);
                            $paiement->montant_restant = $paiement->montant_total - $montantPaye;
                            if ($paiement->montant_restant <= 0) {
                                //Paiement soldé

                                $paiement->statut = 1;

                                // Le parrain se retrouve via le CLIENT du paiement : la table
                                // ligne_paiement n'a PAS de colonne parrain_id/client_id, donc
                                // $ligne->parrain_id était toujours NULL -> aucune commission
                                // apporteur n'était jamais créée pour les paiements mobiles.
                                $clientPayeur = Client::lire($paiement->client_id);
                                $parrain_id = (int) ($clientPayeur->parrain_id ?? 0);
                                $idService = $ligne->service_id;
                                $service = $ligne->service;
                                if ($parrain_id > 0 && $idService > 0 && $service != "") {
                                    $this->payerApporteurAffaire($parrain_id, $paiement->montant_total, $idService,$service);
                                }

                                if ($paiement->facture_id > 0) {
                                    //On marque la facture comme payé
                                    $facture = Facture::lire($paiement->facture_id);
                                    $facture->statut = Help::$STATUT_ACTIF;
                                    $facture->save();
                                }

                                //On vas rajouter les point de la new commande
                                // (client du paiement : $ligne->client_id n'existe pas)
                                // lireIntPointSurMontant() -> first() renvoie NULL si la table
                                // interval_point est vide ou sans palier correspondant :
                                // $intervalPoint->id plantait alors le callback AVANT
                                // $paiement->save() -> paiement jamais soldé (statut resté 2),
                                // commission et points jamais attribués.
                                $intervalPoint = IntervalPoint::lireIntPointSurMontant($paiement->montant_total);
                                if ($intervalPoint && $intervalPoint->id > 0 && $clientPayeur && $clientPayeur->id > 0) {
                                    $clientPayeur->point += $intervalPoint->nombre_point;
                                    $clientPayeur->save();
                                }
                            }
                            $paiement->save();

                            // Statut de paiement du SERVICE (même convention que le callback
                            // web PaiementEnLigne) : 3 = soldé, 2 = partiellement payé.
                            // Sans cette mise à jour, les pages web qui lisent location.statut /
                            // commande.statut affichaient "Aucun paiement effectué" pour tout
                            // paiement passé par le mobile.
                            if ($ligne->service == Help::$LOCATION) {
                                $locSvc = Location::lire($ligne->service_id);
                                if ($locSvc && $locSvc->id > 0) {
                                    $locSvc->statut = ($paiement->montant_restant <= 0) ? 3 : 2;
                                    $locSvc->save();
                                }
                            } else if ($ligne->service == Help::$COMMANDE) {
                                $comSvc = Commande::lire($ligne->service_id);
                                if ($comSvc && $comSvc->id > 0) {
                                    $comSvc->statut = ($paiement->montant_restant <= 0) ? 3 : 2;
                                    $comSvc->save();
                                }
                            }

                            // Envoi email de confirmation de paiement
                            try {
                                if ($ligne->service == Help::$COMMANDE) {
                                    $commande = Commande::lire($ligne->service_id);
                                    if ($commande && $commande->id > 0) {
                                        $client = Client::lire($commande->client_id);
                                        $user = User::lire($client->user_id);
                                        $details = DetailCommande::liste(null, $commande->id);
                                        $tva = TvaCommande::lireSurCommande($commande->id, Help::$VENTE);
                                        Mail::to($user->email)->send(new EnvoieCommandeMail(
                                            $commande, $details, $tva->montant ?? 0,
                                            $user->nom_prenoms, $user->email, $user->contact, Help::$VENTE
                                        ));
                                    }
                                }
                            } catch (\Exception $e) {
                                Log::error('Erreur envoi email confirmation paiement: ' . $e->getMessage());
                            }
                        }
                    } else {
                        foreach ($lignePaiements as $l) {
                            $l->statut = 3; //Annulé
                            $l->save();

                            switch ($l->service) {
                                case Help::$COMMANDE:
                                    $com = Commande::lire($l->service_id);
                                    $com->statut = Help::$STATUT_INACTIF;
                                    $com->save();

                                    $dets = DetailCommande::liste(null, $com->id);
                                    foreach ($dets as $d) {
                                        $d->statut = Help::$STATUT_INACTIF;
                                        $d->save();
                                    }

                                    $mtva = TvaCommande::lireSurCommande($com->id, Help::$VENTE);
                                    if ($mtva->id > 0) {
                                        $mtva->statut = Help::$STATUT_INACTIF;
                                        $mtva->save();
                                    }

                                    break;
                                case Help::$LOCATION:
                                    $loc = Location::lire($l->service_id);
                                    $loc->statut = Help::$STATUT_INACTIF;
                                    $loc->save();

                                    $dets = DetailLocation::liste(null, $loc->id);
                                    foreach ($dets as $d) {
                                        $d->statut = Help::$STATUT_INACTIF;
                                        $d->save();
                                    }

                                    $mtva = TvaCommande::lireSurCommande($loc->id, Help::$LOCATION);
                                    if ($mtva->id > 0) {
                                        $mtva->statut = Help::$STATUT_INACTIF;
                                        $mtva->save();
                                    }

                                    break;
                                case Help::$LIVRAISON:
                                    $liv = DemandeLivraison::lire($l->service_id);
                                    $liv->statut = Help::$STATUT_INACTIF;
                                    $liv->save();

                                    $dets = DetailsLivraison::liste(null, $liv->id);
                                    foreach ($dets as $d) {
                                        $d->statut = Help::$STATUT_INACTIF;
                                        $d->save();
                                    }

                                    // $mtva = TvaCommande::lireSurCommande($liv->id, Help::$LIVRAISON);
                                    // if ($mtva->id > 0) {
                                    //     $mtva->statut = Help::$STATUT_INACTIF;
                                    //     $mtva->save();
                                    // }

                                    break;
                                default:
                                    break;
                            }

                            //modifier le statut du paiement = 3 --> Annulé
                            $paiement = Paiement::lire($l->paiement_id);
                            $paiement->statut = 3; //Annulé
                            $paiement->save();
                        }
                    }
                } else {
                    Help::ecrireLog(
                        "callBackPaiement",
                        "CallBack paiement error 3",
                        "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                        0
                    );
                }
            } else {
                Help::ecrireLog(
                    "callBackPaiement",
                    "CallBack paiement error 2",
                    "Données: " . json_encode($request->input()) . " Cause: Code de paiement introuvable",
                    0
                );
            }
        }catch (\Throwable $th) {
            Help::ecrireLog(
                "callBackPaiement",
                "CallBack paiement error 1",
                "Données: " . json_encode($request->input()) . " Cause: " . $th->getMessage(),
                0
            );
        }
    }

    public function ouvreApp($codePaiement)
    {
        $lignes = LignePaiement::listeSurCode($codePaiement);
        $statut = 0;
        foreach ($lignes as $l) {
            $statut = $l->statut;
        }
        $url = "gravier://ouvrir?page=retour_commande&code=$codePaiement&statut=$statut";
        return redirect()->to($url);
    }

    public function listePaiement(Request $request)
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
                $pais = Paiement::liste(null, $client->id, [Help::$STATUT_ACTIF]);
                foreach ($pais as $d) {
                    $d->date_paiement = $d->created_at->format("d/m/Y H:i:s");
                }
                $retour->data = $pais;
                $retour->code = 200;
                $retour->message = 'ok' . json_encode($client);
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

    public function listeFacture(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'statut' => "required|array",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $leStatut = [Help::$STATUT_ACTIF, Help::$STATUT_INACTIF];
                // if (!empty($request->statut)) {
                //     $leStatut = $request->statut;
                // }
                $client = Client::lireSurUser($user->id);
                $pais = Facture::liste(null, null, $client->id, $leStatut);
                foreach ($pais as $d) {
                    $d->date_paiement = $d->created_at->format("d/m/Y H:i:s");
                }
                $retour->data = $pais;
                $retour->code = 200;
                $retour->message = 'ok' . json_encode($client);
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

    public function listeLignePaiementSurCode(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'codePaiement' => "nullable",
            'idPaiement' => "nullable",
            'niveau' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $pais = [];
                if ($request->niveau == 1) {
                    $pais = LignePaiement::listeSurCode($request->codePaiement);
                    foreach ($pais as $d) {
                        $d->date_paiement = Help::formatterDate($d->date_paiement, "Y-m-d H:i:s", "d/m/Y H:i:s");
                    }
                } else {
                    $pais = LignePaiement::listeSurIdPaiement($request->idPaiement);
                    foreach ($pais as $d) {
                        $d->date_paiement = Help::formatterDate($d->date_paiement, "Y-m-d H:i:s", "d/m/Y H:i:s");
                    }
                }
                $retour->data = $pais;
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

    // public function obtenirLienPaiement(Request $request)
    // {
    //     Request()->validate([
    //         'access' => "required",
    //         'type' => "required",
    //         'ids' => "required",
    //         'modePaiement' => "required",
    //     ]);

    //     $retour = new Retour();

    //     try {
    //         $idUsr = Crypt::decryptString($request->access);
    //         $user = User::lire($idUsr);
    //         if ($user->id > 0) {

    //             $client = Client::lireSurUser($user->id);
    //             $paiements = Paiement::lireSurIds($request->ids);

    //             $codePaiement = Help::getCommandeNo();
    //             $nomPrenoms = $client->nom;
    //             $arrNoms = explode(" ", $nomPrenoms);
    //             $leNom = "";
    //             $lePrenom = "";
    //             if (count($arrNoms) >= 2) {
    //                 $leNom = $arrNoms[0];
    //                 $lePrenom = $arrNoms[1];
    //             } else {
    //                 $leNom = $arrNoms[0];
    //                 $lePrenom = $arrNoms[0];
    //             }

    //             $total = 0;
    //             foreach ($paiements as $p) {
    //                 $total += $p->montant_restant;
    //             }

    //             $ret = PaiementController::initierPaiement(
    //                 [
    //                     'code_paiement' => $codePaiement,
    //                     'nom_usager' => $leNom,
    //                     'prenom_usager' => $lePrenom,
    //                     'telephone' => $client->contact1,
    //                     'email' => $user->email,
    //                     'libelle_article' => "Paiement IMLOD",
    //                     'quantite' => 1,
    //                     'montant' => $total,
    //                     'lib_order' => "Paiement de facture IMLOD",
    //                     'Url_Retour' => route("ouvreApp", ['codePaiement' => $codePaiement]),
    //                     'Url_Callback' => route('callBackPaiement'),
    //                 ],
    //                 $codePaiement,
    //                 $client,
    //                 0,
    //                 $total,
    //                 $request->modePaiement,
    //                 null,
    //                 null,
    //                 $paiements
    //             );
    //             if ($ret['code'] == 200) {
    //                 $retour->code = 200;
    //                 $retour->message = $ret['message'];
    //             } else {
    //                 $retour->code = $ret['code'];
    //                 $retour->message = $ret['message'];
    //             }
    //         } else {
    //             $retour->code = 404;
    //             $retour->message = 'Impossible de récupérer l\'utilisateur';
    //         }
    //     }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
        //     $retour->code = 501;
        //     $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        // } catch (\Throwable $th) {
    //         $retour->code = 500;
    //         $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
    //     }

    //     return response()->json($retour);
    // }

    public function obtenirLienPaiement(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'ids' => "required",
            'modePaiement' => "required",
        ]);

        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);
                $factures = Facture::lireSurIds($request->ids);

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

                $total = 0;
                foreach ($factures as $p) {
                    $total += $p->montant;
                }

                $ret = PaiementController::initierPaiement(
                    [
                        'code_paiement' => $codePaiement,
                        'nom_usager' => $leNom,
                        'prenom_usager' => $lePrenom,
                        'telephone' => $client->contact1,
                        'email' => $user->email,
                        'libelle_article' => "Paiement IMLOD",
                        'quantite' => 1,
                        'montant' => $total,
                        'lib_order' => "Paiement de facture IMLOD",
                        'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                        'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                    ],
                    $codePaiement,
                    $client,
                    0,
                    $total,
                    $request->modePaiement,
                    null,
                    null,
                    $factures
                );
                if ($ret['code'] == 200) {
                    $retour->code = 200;
                    $retour->message = $ret['message'];
                } else {
                    $retour->code = $ret['code'];
                    $retour->message = $ret['message'];
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

    public static function initierPaiement($paramInit, $numero, $codePaiement, $client, $montantTotal, $modePaiement, $idService, $service, $factures = null)
    {
        $r = array();
        $reponse = Http::withHeaders([
            'ApiKey' => config('paysecure.api_key'),
            'MerchantId' => config('paysecure.merchant_id'),
            'Content-Type'  => 'application/json',
        ])
            ->post(config('paysecure.url'), $paramInit);
        $ResJSON = $reponse->json();

        if ($reponse->status() == 200) {
            if ($ResJSON['code'] == 200) {
                if (!empty($ResJSON['url'])) {
                    if ($idService != null && $service != null) {
                        if ($client->client_a_terme == false) {
                            //Client BE
                            $paiement = new Paiement();
                            $paiement->client_id = $client->id;
                            $paiement->devis_id = null;
                            $paiement->service_id = $idService;
                            $paiement->service = Help::$COMMANDE;
                            $paiement->code = $numero;
                            $paiement->libelle = $paramInit['lib_order'];
                            $paiement->montant_total = $montantTotal;
                            $paiement->montant_restant = 0;
                            $paiement->statut = Help::$STATUT_INACTIF;
                            $paiement->save();

                            $ligne = new LignePaiement();
                            $ligne->service_id = $idService;
                            $ligne->service = $service;
                            $ligne->paiement_id = $paiement->id;
                            $ligne->mode_paiement_id = $modePaiement;
                            $ligne->date_paiement = date("Y-m-d H:i:s");
                            $ligne->montant = $montantTotal;
                            $ligne->statut = Help::$STATUT_INACTIF;
                            $ligne->code_paiement = $codePaiement;
                            $ligne->save();
                        }
                    } else {
                        if ($client->client_a_terme == true) {
                            //Client a terme
                            $leMontant = $montantTotal;
                            foreach ($factures as $f) {
                                $paiement = Paiement::lireSurFacture($f->id);
                                $paiement->client_id = $client->id;
                                $paiement->devis_id = null;
                                $paiement->service_id = $f->service_id;
                                $paiement->service = $f->service;
                                $paiement->code = $f->numero;
                                $paiement->libelle = "Paiement de facture N° ".$f->numero;
                                $paiement->montant_total = $montantTotal;
                                $paiement->montant_restant = 0;
                                $paiement->statut = Help::$STATUT_INACTIF;
                                $paiement->save();

                                $ligne = new LignePaiement();
                                $ligne->service_id = $f->service_id;
                                $ligne->service = $f->service;
                                $ligne->paiement_id = $paiement->id;
                                $ligne->mode_paiement_id = $modePaiement;
                                $ligne->date_paiement = date("Y-m-d H:i:s");
                                $ligne->statut = Help::$STATUT_INACTIF;
                                $ligne->code_paiement = $codePaiement;
                                if ($leMontant - $f->montant >= 0) {
                                    $ligne->montant = $f->montant;
                                    $ligne->save();
                                    $leMontant -= $f->montant;
                                } else {
                                    $ligne->montant = $leMontant;
                                    $ligne->save();
                                    $leMontant = 0;
                                }
                            }
                        }
                    }
                    $r['code'] = 200;
                    $r['message'] = $ResJSON['url'];
                } else {
                    $r['code'] = $ResJSON['code'];
                    $r['message'] = self::messageBrut($ResJSON['message']);
                    Help::ecrireLog(
                        "initierPaiement",
                        "Initiation paiement error",
                        "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
                        $client->user_id
                    );
                }
            } else {
                $r['code'] = $ResJSON['code'];
                $r['message'] = self::messageBrut($ResJSON['message']);
                Help::ecrireLog(
                    "initierPaiement",
                    "Initiation paiement error",
                    "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
                    $client->user_id
                );
            }
        } else {
            $mess = 'Une erreur inattendue s\'est produite, verifier que vous avez accès à internet, ' .
                'puis reéssayer. erreur ' . $reponse->status();
            $r['code'] = $reponse->status();
            $r['message'] = $mess;
            Help::ecrireLog(
                "initierPaiement",
                "Initiation paiement error",
                "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
                $client->user_id
            );
        }
        return $r;
    }

    private static function messageBrut(array $tableauDeChaines)
    {
        $chainefinale = '';
        // Parcourir le tableau et afficher chaque élément
        foreach ($tableauDeChaines as $chaine) {
            $chainefinale .= $chaine . "\n";
        }
        return $chainefinale;
    }

    private function payerApporteurAffaire($idApporteur, $montantTotal, $idService, $typeService)
    {
        $apporteur = Apporteur::lire($idApporteur);
        if (!$apporteur || $apporteur->id <= 0) {
            return;
        }

        // commission_apporteur.type_affaire est un enum('LOCATION','VENTE') :
        // passer 'COMMANDE' (valeur de Help::$COMMANDE) était rejeté/vidé par MySQL.
        $typeAffaire = ($typeService == Help::$LOCATION) ? 'LOCATION' : 'VENTE';

        // Anti-doublon : le callback PaySecure peut être rejoué -> ne jamais
        // créditer deux fois la même affaire (même logique que le web).
        $existe = CommissionApporteur::where('commande_id', $idService)
            ->where('apporteur_id', $apporteur->id)
            ->where('type_affaire', $typeAffaire)
            ->exists();
        if ($existe) {
            return;
        }

        $com = new CommissionApporteur();
        $com->apporteur_id = $apporteur->id;
        $com->commande_id = $idService;
        $com->type_affaire = $typeAffaire;
        // Arrondi au franc entier : le FCFA n'a pas de décimales (ex. 10% de 118 = 11,8
        // rendait le paiement de la commission pénible côté admin).
        $com->montant = round($montantTotal * ($apporteur->pourcentage / 100));
        $com->statut = 1;
        $com->save();

        $apporteur->solde += $com->montant;
        $apporteur->save();
    }
}
