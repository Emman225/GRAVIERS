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

                    // SÉCURITÉ : cette route est publique et le POST est falsifiable
                    // (un attaquant connaissant un codePaiement peut envoyer code=200).
                    // On ne se fie donc pas au code annoncé : on confirme le statut
                    // serveur→serveur auprès de PaySecure avant d'agir.
                    if (!empty(config('paysecure.status_url'))) {
                        $statutVerifie = self::interrogerStatutPaiement($request->codePaiement);
                        if ($statutVerifie === 'paye') {
                            $request->merge(['code' => 200]);
                        } else if ($statutVerifie === 'echec') {
                            $request->merge(['code' => 400]);
                        } else {
                            // Statut indéterminé (PENDING, passerelle injoignable...) :
                            // on ne solde ni n'annule rien ; la vérification pull
                            // (verifierPaiement) régularisera.
                            Help::ecrireLog(
                                "callBackPaiement",
                                "CallBack paiement non appliqué",
                                "Code " . $request->codePaiement . " : statut PaySecure indéterminé, aucune action",
                                0
                            );
                            return response()->json(['code' => 202, 'message' => 'Statut non confirmé, aucune action']);
                        }
                    } else {
                        Help::ecrireLog(
                            "callBackPaiement",
                            "CallBack paiement non vérifié",
                            "PAYSECURE_STATUS_URL absente : callback accepté sans vérification serveur→serveur (code " . $request->codePaiement . ")",
                            0
                        );
                    }

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

                            // Un échec de paiement de FACTURE n'annule que la TENTATIVE
                            // de paiement : la commande/location sous-jacente a déjà été
                            // facturée (souvent livrée) et ne doit surtout pas être
                            // désactivée. Le switch ci-dessous ne concerne que les
                            // paiements initiés À LA CRÉATION du service.
                            $paiement = Paiement::lire($l->paiement_id);
                            if ($paiement->facture_id > 0) {
                                $paiement->statut = 3; //Annulé (tentative)
                                $paiement->save();
                                continue;
                            }

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

    /**
     * Interroge PaySecure (serveur→serveur) sur le statut réel d'un paiement.
     * Retourne 'paye', 'echec' ou 'inconnu' (PENDING, non configuré, injoignable).
     * Même logique que PaiementEnLigne::interrogerStatutPaiement côté web.
     */
    private static function interrogerStatutPaiement($codePaiement)
    {
        $statusUrl = config('paysecure.status_url');
        if (empty($statusUrl)) {
            return 'inconnu';
        }

        try {
            $reponse = Http::withHeaders([
                'ApiKey'       => config('paysecure.api_key'),
                'MerchantId'   => config('paysecure.merchant_id'),
                'Content-Type' => 'application/json',
            ])->post($statusUrl, [
                'codePaiement' => $codePaiement,
            ]);

            if ($reponse->status() != 200) {
                return 'inconnu';
            }

            $json = $reponse->json();

            // Format PaySecure (POST /api/airtime/status/transact) :
            // { "code": 200, "message": "...", "payments": { "state": "PENDDING|CANCEL|...", ... } }
            // Le "code" racine peut valoir 200 alors que le paiement est encore "PENDDING" :
            // on se fie donc à payments.state (et non au seul code).
            $state = strtoupper((string) ($json['payments']['state'] ?? $json['payments']['status'] ?? ''));

            $etatsPayes = ['SUCCESS', 'SUCCESSFUL', 'SUCCESSFULL', 'SUCCESSFULLY', 'SUCCES', 'PAID', 'DONE', 'COMPLETED', 'COMPLETE', 'EFFECTUE', 'EFFECTUEE', 'VALIDATED', 'VALIDE', 'APPROVED', 'PAYE'];
            $etatsEchoues = ['CANCEL', 'CANCELLED', 'CANCELED', 'FAILED', 'FAIL', 'ECHEC', 'ECHOUE', 'REJECTED', 'REJET', 'ERROR', 'EXPIRED'];

            if (in_array($state, $etatsPayes, true)) {
                return 'paye';
            }
            if (in_array($state, $etatsEchoues, true)) {
                return 'echec';
            }
            return 'inconnu';
        } catch (\Throwable $th) {
            Help::ecrireLog('interrogerStatutPaiement', 'error', "code: $codePaiement cause: " . $th->getMessage(), 0);
            return 'inconnu';
        }
    }

    /**
     * Vérification (pull) du statut d'un paiement — filet de sécurité si le
     * callback s'est perdu ou n'a pas pu être confirmé. Si PaySecure confirme
     * le paiement, on rejoue le traitement du callback (qui re-vérifie lui-même).
     */
    public function verifierPaiement(Request $request)
    {
        Request()->validate(['codePaiement' => 'required|string']);
        $code = $request->codePaiement;

        $lignes = LignePaiement::listeSurCode($code);
        if (count($lignes) === 0) {
            return response()->json(['code' => 404, 'statut' => 'introuvable', 'message' => 'Paiement introuvable']);
        }

        $dejaRegle = true;
        foreach ($lignes as $l) {
            if ($l->statut != Help::$STATUT_ACTIF) { $dejaRegle = false; break; }
        }
        if ($dejaRegle) {
            return response()->json(['code' => 200, 'statut' => 'paye', 'message' => 'Paiement déjà confirmé']);
        }

        $statut = self::interrogerStatutPaiement($code);
        if ($statut === 'paye') {
            $this->callBackPaiement(new Request(['codePaiement' => $code, 'code' => 200]));

            // On ne répond "payé" que si la régularisation a RÉELLEMENT été
            // appliquée en base : callBackPaiement re-vérifie lui-même le statut
            // auprès de PaySecure et peut n'avoir rien fait (échec transitoire du
            // 2e appel). Répondre 200 sur la seule foi du 1er appel ferait sortir
            // l'app de son état "en attente" alors que rien n'est soldé.
            $lignesApres = LignePaiement::listeSurCode($code);
            $regle = count($lignesApres) > 0;
            foreach ($lignesApres as $l) {
                if ($l->statut != Help::$STATUT_ACTIF) { $regle = false; break; }
            }
            if ($regle) {
                return response()->json(['code' => 200, 'statut' => 'paye', 'message' => 'Paiement confirmé']);
            }
            return response()->json(['code' => 202, 'statut' => 'en_attente', 'message' => 'Paiement confirmé par la passerelle, régularisation en cours. Veuillez réessayer.']);
        }
        if ($statut === 'echec') {
            return response()->json(['code' => 402, 'statut' => 'echec', 'message' => 'Paiement non abouti']);
        }

        return response()->json(['code' => 202, 'statut' => 'en_attente', 'message' => 'Statut indéterminé']);
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
                    // Signature : ($paramInit, $numero, $codePaiement, $client, $montantTotal, ...).
                    // L'ancien appel omettait $numero : $client arrivait dans $codePaiement
                    // et 0 dans $client -> branche factures inutilisable.
                    $codePaiement,
                    $codePaiement,
                    $client,
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
                        // Paiement de FACTURES : valable pour TOUT client (à terme ou BE).
                        // Avant, seul le client à terme était traité : un client BE
                        // obtenait une URL de paiement mais AUCUN Paiement/LignePaiement
                        // n'était enregistré -> le callback ne trouvait rien à solder
                        // (argent encaissé, factures jamais réglées).
                        $leMontant = $montantTotal;
                        foreach ($factures as $f) {
                            $paiement = Paiement::lireSurFacture($f->id);
                            $paiement->client_id = $client->id;
                            $paiement->devis_id = null;
                            // facture_id explicite : lireSurFacture peut renvoyer un
                            // Paiement NEUF (aucun existant) ; sans ce champ, le callback
                            // ne marquait jamais la facture comme payée.
                            $paiement->facture_id = $f->id;
                            $paiement->service_id = $f->service_id;
                            $paiement->service = $f->service;
                            $paiement->code = $f->numero;
                            $paiement->libelle = "Paiement de facture N° ".$f->numero;
                            // Le dû du paiement = le montant de SA facture, pas le total
                            // du panier : avec l'ancien montant_total = total global, un
                            // paiement multi-factures n'était JAMAIS soldé au callback
                            // (restant = total global − ligne de la facture > 0).
                            $paiement->montant_total = $f->montant;
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
