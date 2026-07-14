<?php

namespace App\Http\Controllers;

use App\Models\Apporteur;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\DemandeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailLivraison;
use App\Models\DetailLocation;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\User;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Retour;

class PaiementEnLigne extends Controller
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

                        $arrService = [];
                        $arr = [];

                        foreach ($lignePaiements as $ligne) {

                            $ligne->reference = $request->referencePaiement;
                            $ligne->moyen_paiement = $request->moyenPaiement;
                            $ligne->date_paiement = date("Y-m-d H:i:s");
                            $ligne->statut = 1;
                            $ligne->save();

                            $montantPaye = 0;
                            $lignes = LignePaiement::liste($ligne->paiement_id);
                            foreach ($lignes as $l) {
                                if ($l->statut == Help::$STATUT_ACTIF) {
                                    $montantPaye += $l->montant;
                                }
                            }

                            $arr = [];
                            $arr['service'] = $ligne->service;
                            $arr['service_id'] = $ligne->service_id;
                            array_push($arrService, $arr);


                            $paiement = Paiement::lire($ligne->paiement_id);
                            $paiement->montant_restant = $paiement->montant_total - $montantPaye;
                            if ($paiement->montant_restant <= 0) {

                                $paiement->statut = 1;

                                $idService = $ligne->service_id;
                                $service = $ligne->service;

                                // La commission de l'apporteur est créditée à partir du
                                // client de ce paiement (via son code parrain), une seule
                                // fois, lorsque le paiement est totalement réglé.
                                if ($idService > 0 && $service != "") {
                                    $this->payerApporteurAffaire($paiement->client_id, $idService, $paiement->montant_total, $service);
                                }
                            }
                            $paiement->save();

                            // LOCATION : la location n'est créée qu'ICI, à la confirmation du
                            // paiement, à partir du brouillon stocké sur le paiement
                            // (paiement.donnees_service). Idempotent : si une location est déjà
                            // liée (ancien flux), creerDepuisPaiement la renvoie sans rien recréer.
                            if ($ligne->service == Help::$LOCATION) {
                                $loc = Location::creerDepuisPaiement($paiement);
                                if ($loc && $loc->id) {
                                    $loc->statut = ($paiement->montant_restant <= 0) ? 3 : 2;
                                    $loc->save();
                                }
                            }
                        }

                        foreach ($arrService as $s) {
                            switch ($s['service']) {
                                case Help::$COMMANDE:

                                    $commande = Commande::lire($s['service_id']);
                                    if ($commande && $commande->client) {
                                        $image = base_path('public/frontend/assets/imgs/logo/omer 1.png');
                                        Help::envoyerDocumentPdf(
                                            $commande->client->nom . ' ' . $commande->client->prenom,
                                            $commande->client->user->email,
                                            'Facture',
                                            $commande->numero ?? $commande->id,
                                            'document.factureCommande',
                                            ['commande' => $commande, 'image' => $image],
                                            'Facture_' . ($commande->numero ?? $commande->id) . '.pdf'
                                        );
                                    }
                                    break;
                                case Help::$LOCATION:
                                    $location = Location::lire($s['service_id']);
                                    if ($location && $location->client) {
                                        $image = base_path('public/frontend/assets/imgs/logo/omer 1.png');
                                        Help::envoyerDocumentPdf(
                                            $location->client->nom . ' ' . $location->client->prenom,
                                            $location->client->user->email,
                                            'Facture Location',
                                            $location->numero ?? $location->id,
                                            'document.factureCommande',
                                            ['commande' => $location, 'image' => $image],
                                            'Facture_Location_' . ($location->numero ?? $location->id) . '.pdf'
                                        );
                                    }
                                    break;
                                case Help::$LIVRAISON:

                                    break;
                                default:
                                    break;
                            }
                        }

                        // Envoie de mail

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
                                    break;
                                case Help::$LIVRAISON:
                                    $liv = DemandeLivraison::lire($l->service_id);
                                    $liv->statut = Help::$STATUT_INACTIF;
                                    $liv->save();

                                    $dets = DetailLivraison::liste(null, $liv->id);
                                    foreach ($dets as $d) {
                                        $d->statut = Help::$STATUT_INACTIF;
                                        $d->save();
                                    }
                                    break;
                                default:
                                    break;
                            }

                            $paiement = Paiement::find($l->paiement_id);
                            // $paiement = Paiement::lire($l->paiement_id);
                            $paiement->statut = 3;
                            $paiement->update();
                        }
                    }
                } else {
                    Help::ecrireLog(
                        "callBackPaiement",
                        "CallBack paiement error 2",
                        "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                        0
                    );
                }
            } else {
                Help::ecrireLog(
                    "callBackPaiement",
                    "CallBack paiement error 1",
                    "Données: " . json_encode($request->input()) . " Cause: Code de paiement introuvable",
                    0
                );
            }
        } catch (\Throwable $th) {
            Help::ecrireLog(
                "callBackPaiement",
                "error",
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
            'statut' => "required|array",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $leStatut = null;
                if (empty($request->statut)) {
                    $leStatut = null;
                } else {
                    $leStatut = $request->statut;
                }
                $client = Client::lireSurUser($user->id);
                $pais = Paiement::liste(null, $client->id, $leStatut);
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

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
                $paiements = Paiement::lireSurIds($request->ids);

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
                foreach ($paiements as $p) {
                    $total += $p->montant_restant;
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
                        'Url_Retour' => route("ouvreApp", ['codePaiement' => $codePaiement]),
                        'Url_Callback' => route('callBackPaiement'),
                    ],
                    $codePaiement,
                    $client,
                    0,
                    $total,
                    $request->modePaiement,
                    null,
                    null,
                    $paiements
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
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

    public static function initierPaiement($paramInit, $numero, $codePaiement, $client, $montantTotal, $modePaiement, $idService, $service, $factures = null, $donneesService = null)
    {

        $r = array();
        $reponse = Http::withHeaders([
            'ApiKey' => config('paysecure.api_key'),
            'MerchantId' => config('paysecure.merchant_id'),
            'Content-Type' => 'application/json',
        ])
            ->post(config('paysecure.url'), $paramInit);
        $ResJSON = $reponse->json();

        if ($reponse->status() == 200) {
            if ($ResJSON['code'] == 200) {

                if (!empty($ResJSON['url'])) {
                    // Comparaison STRICTE (!==) : le flux location "créée après paiement"
                    // passe service_id = 0 (la location n'existe pas encore). Avec l'ancien
                    // `!= null` (lâche), 0 == null -> le Paiement n'était JAMAIS enregistré :
                    // le client payait chez PaySecure mais aucune trace en base -> retour
                    // "code introuvable" (500) et callback sans effet.
                    if ($idService !== null && $service != null) {
                        if ($client->client_a_terme == false) {
                            //Client BE
                            $paiement = new Paiement();
                            $paiement->client_id = $client->id;
                            $paiement->devis_id = null;
                            $paiement->service_id = $idService;
                            // Bug : le service était codé en dur à COMMANDE, donc un paiement
                            // en ligne de LOCATION était enregistré comme COMMANDE (paiement.service),
                            // alors que la ligne portait le bon service. On utilise le paramètre reçu.
                            $paiement->service = $service;
                            // Brouillon de location : la Location n'est créée qu'après confirmation
                            // du paiement (callback / retour), à partir de ces données.
                            $paiement->donnees_service = $donneesService;
                            $paiement->code = $codePaiement;
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
                        } else {
                            $leMontant = $montantTotal;
                            foreach ($factures as $f) {
                                $paiement = Paiement::lireSurFacture($f->id);
                                $paiement->client_id = $client->id;
                                $paiement->devis_id = null;
                                $paiement->service_id = $f->service_id;
                                $paiement->service = $f->service;
                                $paiement->code = $codePaiement;
                                $paiement->libelle = "Paiement de facture N° " . $f->numero;
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
                                $paiement->code = $codePaiement;
                                $paiement->libelle = "Paiement de facture N° " . $f->numero;
                                $paiement->montant_total = $montantTotal;
                                $paiement->montant_restant = 0;
                                $paiement->statut = Help::$STATUT_INACTIF;
                                $paiement->save();

                                $ligne = new LignePaiement();
                                $ligne->service_id = $f->service_id;
                                $ligne->service = $codePaiement;
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

    // public static function initierPaiement($paramInit, $codePaiement, $client, $idPaiement, $montantTotal, $modePaiement, $idService, $service, $paiements = null)
    // {
    //     $r = array();
    //     $reponse = Http::withHeaders([
    //         'ApiKey' => 'shk_GTMxalgPgIa1AhO2zAXUZZv7Yebsan8LbOXQ',
    //         'MerchantId' => 'h4dyks8y5a',
    //         'Content-Type'  => 'application/json',
    //     ])
    //         ->post('https://rest-airtime.paysecurehub.com/api/payhub-ws/build-away', $paramInit);
    //     $ResJSON = $reponse->json();

    //     if ($reponse->status() == 200) {
    //         if ($ResJSON['code'] == 200) {
    //             if (!empty($ResJSON['url'])) {

    //                 if ($idService != null && $service != null) {
    //                     $ligne = new LignePaiement();
    //                     $ligne->service_id = $idService;
    //                     $ligne->service = $service;
    //                     $ligne->paiement_id = $idPaiement;
    //                     $ligne->mode_paiement_id = $modePaiement;
    //                     $ligne->date_paiement = date("Y-m-d H:i:s");
    //                     $ligne->montant = $montantTotal;
    //                     $ligne->statut = Help::$STATUT_INACTIF;
    //                     $ligne->code_paiement = $codePaiement;
    //                     $ligne->save();
    //                 } else {
    //                     $leMontant = $montantTotal;
    //                     foreach ($paiements as $p) {
    //                         $ligne = new LignePaiement();
    //                         $ligne->service_id = $p->service_id;
    //                         $ligne->service = $p->service;
    //                         $ligne->paiement_id = $p->id;
    //                         $ligne->mode_paiement_id = $modePaiement;
    //                         $ligne->date_paiement = date("Y-m-d H:i:s");
    //                         $ligne->statut = Help::$STATUT_INACTIF;
    //                         $ligne->code_paiement = $codePaiement;
    //                         if ($leMontant - $p->montant_total >= 0) {
    //                             $ligne->montant = $p->montant_total;
    //                             $ligne->save();
    //                             $leMontant -= $p->montant_total;
    //                         } else {
    //                             $ligne->montant = $leMontant;
    //                             $ligne->save();
    //                             $leMontant = 0;
    //                         }
    //                     }
    //                 }
    //                 $r['code'] = 200;
    //                 $r['message'] = $ResJSON['url'];
    //             } else {
    //                 $r['code'] = $ResJSON['code'];
    //                 $r['message'] = self::messageBrut($ResJSON['message']);
    //                 Help::ecrireLog(
    //                     "initierPaiement",
    //                     "Initiation paiement error",
    //                     "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
    //                     $client->user_id
    //                 );
    //             }
    //         } else {
    //             $r['code'] = $ResJSON['code'];
    //             $r['message'] = self::messageBrut($ResJSON['message']);
    //             Help::ecrireLog(
    //                 "initierPaiement",
    //                 "Initiation paiement error",
    //                 "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
    //                 $client->user_id
    //             );
    //         }
    //     } else {
    //         $mess = 'Une erreur inattendue s\'est produite, verifier que vous avez accès à internet, ' .
    //             'puis reéssayer. erreur ' . $reponse->status();
    //         $r['code'] = $reponse->status();
    //         $r['message'] = $mess;
    //         Help::ecrireLog(
    //             "initierPaiement",
    //             "Initiation paiement error",
    //             "Données: " . json_encode($paramInit) . " Cause: " . json_encode($ResJSON),
    //             $client->user_id
    //         );
    //     }
    //     return $r;
    // }

    private function payerApporteurAffaire($clientId, $idService, $montantTotal, $typeService)
    {
        // On retrouve l'apporteur via le code parrain du client de la commande.
        $client = Client::find($clientId);
        if (!$client || empty($client->code_parrain)) {
            return;
        }

        $apporteur = Apporteur::where('code', $client->code_parrain)->first();
        if (!$apporteur) {
            return;
        }

        // type_affaire est un enum('LOCATION','VENTE') côté commission_apporteur.
        $typeAffaire = ($typeService === Help::$LOCATION) ? 'LOCATION' : 'VENTE';

        // Évite tout double crédit si le callback est rejoué.
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

    /**
     * Applique le règlement d'un paiement (passe les lignes et le paiement à "payé",
     * crédite la commission apporteur si totalement réglé, envoie la facture).
     *
     * Logique unique réutilisée par : le callback PaySecure (chemin normal),
     * la vérification de statut (polling) et la confirmation manuelle admin.
     * Idempotent : les lignes déjà réglées ne sont pas retraitées et la facture
     * n'est ré-envoyée que si au moins une ligne vient d'être réglée.
     *
     * @return bool true si quelque chose a été réglé, false sinon.
     */
    public function marquerPaiementEffectue($codePaiement, $reference = null, $moyen = null)
    {
        $lignePaiements = LignePaiement::listeSurCode($codePaiement);
        if (count($lignePaiements) === 0) {
            return false;
        }

        $arrService = [];
        $auMoinsUneReglee = false;

        foreach ($lignePaiements as $ligne) {

            // On ne retraite pas une ligne déjà validée (idempotence).
            if ($ligne->statut != Help::$STATUT_ACTIF) {
                if ($reference !== null) $ligne->reference = $reference;
                if ($moyen !== null)     $ligne->moyen_paiement = $moyen;
                $ligne->date_paiement = date("Y-m-d H:i:s");
                $ligne->statut = 1;
                $ligne->save();
                $auMoinsUneReglee = true;
            }

            $montantPaye = 0;
            $lignes = LignePaiement::liste($ligne->paiement_id);
            foreach ($lignes as $l) {
                if ($l->statut == Help::$STATUT_ACTIF) {
                    $montantPaye += $l->montant;
                }
            }

            $arrService[] = ['service' => $ligne->service, 'service_id' => $ligne->service_id];

            $paiement = Paiement::lire($ligne->paiement_id);
            $paiement->montant_restant = $paiement->montant_total - $montantPaye;
            if ($paiement->montant_restant <= 0) {
                $paiement->statut = 1;
                if ($ligne->service_id > 0 && $ligne->service != "") {
                    // Anti-doublon géré dans payerApporteurAffaire().
                    $this->payerApporteurAffaire($paiement->client_id, $ligne->service_id, $paiement->montant_total, $ligne->service);
                }
            }
            $paiement->save();
        }

        // Envoi des factures uniquement si un règlement vient d'avoir lieu.
        // Non bloquant : un échec d'envoi de facture ne doit pas annuler le règlement.
        if ($auMoinsUneReglee) {
            try {
            foreach ($arrService as $s) {
                switch ($s['service']) {
                    case Help::$COMMANDE:
                        $commande = Commande::lire($s['service_id']);
                        if ($commande && $commande->client) {
                            $image = base_path('public/frontend/assets/imgs/logo/omer 1.png');
                            Help::envoyerDocumentPdf(
                                $commande->client->nom . ' ' . $commande->client->prenom,
                                $commande->client->user->email,
                                'Facture',
                                $commande->numero ?? $commande->id,
                                'document.factureCommande',
                                ['commande' => $commande, 'image' => $image],
                                'Facture_' . ($commande->numero ?? $commande->id) . '.pdf'
                            );
                        }
                        break;
                    case Help::$LOCATION:
                        $location = Location::lire($s['service_id']);
                        if ($location && $location->client) {
                            $image = base_path('public/frontend/assets/imgs/logo/omer 1.png');
                            Help::envoyerDocumentPdf(
                                $location->client->nom . ' ' . $location->client->prenom,
                                $location->client->user->email,
                                'Facture Location',
                                $location->numero ?? $location->id,
                                'document.factureCommande',
                                ['commande' => $location, 'image' => $image],
                                'Facture_Location_' . ($location->numero ?? $location->id) . '.pdf'
                            );
                        }
                        break;
                    default:
                        break;
                }
            }
            } catch (\Throwable $e) {
                \Log::error('Envoi de facture (règlement paiement) échoué : ' . $e->getMessage());
            }
        }

        return $auMoinsUneReglee;
    }

    /**
     * Interroge la passerelle PaySecure pour connaître le statut réel d'un paiement.
     *
     * BRANCHEMENT : renseigner PAYSECURE_STATUS_URL dans le .env avec l'endpoint
     * de consultation fourni par PaySecure, puis ADAPTER le bloc de parsing ci-dessous
     * au format réel de la réponse (champ + valeur indiquant "payé"). Tant que la
     * variable n'est pas renseignée, la méthode renvoie 'inconnu' (aucun appel réseau).
     *
     * @return string 'paye' | 'echec' | 'inconnu'
     */
    public function interrogerStatutPaiement($codePaiement)
    {
        $statusUrl = config('paysecure.status_url');
        if (empty($statusUrl)) {
            // Endpoint non configuré : on ne peut pas savoir automatiquement.
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
            // on se fie donc à payments.state (et non au seul code) pour éviter les faux positifs.
            $state = strtoupper((string) ($json['payments']['state'] ?? $json['payments']['status'] ?? ''));

            $etatsPayes = ['SUCCESS', 'SUCCESSFUL', 'SUCCESSFULL', 'SUCCESSFULLY', 'SUCCES', 'PAID', 'DONE', 'COMPLETED', 'COMPLETE', 'EFFECTUE', 'EFFECTUEE', 'VALIDATED', 'VALIDE', 'APPROVED', 'PAYE'];
            $etatsEchoues = ['CANCEL', 'CANCELLED', 'CANCELED', 'FAILED', 'FAIL', 'ECHEC', 'ECHOUE', 'REJECTED', 'REJET', 'ERROR', 'EXPIRED'];

            if (in_array($state, $etatsPayes, true)) {
                return 'paye';
            }
            if (in_array($state, $etatsEchoues, true)) {
                return 'echec';
            }
            // PENDDING, vide ou état inconnu => on ne régularise pas (on attend).
            return 'inconnu';
        } catch (\Throwable $th) {
            Help::ecrireLog('interrogerStatutPaiement', 'error', "code: $codePaiement cause: " . $th->getMessage(), 0);
            return 'inconnu';
        }
    }

    /**
     * Vérifie (pull) le statut d'un paiement et le régularise s'il est payé.
     * Utilisable par l'écran d'attente de l'app/web ou par la reprise planifiée.
     */
    public function verifierPaiement(Request $request)
    {
        Request()->validate(['codePaiement' => 'required|string']);
        $code = $request->codePaiement;

        $lignes = LignePaiement::listeSurCode($code);
        if (count($lignes) === 0) {
            return response()->json(['code' => 404, 'statut' => 'introuvable', 'message' => 'Paiement introuvable']);
        }

        // Déjà réglé ?
        $dejaRegle = true;
        foreach ($lignes as $l) {
            if ($l->statut != Help::$STATUT_ACTIF) { $dejaRegle = false; break; }
        }
        if ($dejaRegle) {
            return response()->json(['code' => 200, 'statut' => 'paye', 'message' => 'Paiement déjà confirmé']);
        }

        $statut = $this->interrogerStatutPaiement($code);
        if ($statut === 'paye') {
            $this->marquerPaiementEffectue($code);
            return response()->json(['code' => 200, 'statut' => 'paye', 'message' => 'Paiement confirmé']);
        }
        if ($statut === 'echec') {
            return response()->json(['code' => 402, 'statut' => 'echec', 'message' => 'Paiement non abouti']);
        }

        return response()->json([
            'code' => 202,
            'statut' => 'en_attente',
            'message' => "Statut indéterminé (vérification automatique indisponible). Une confirmation manuelle peut être nécessaire.",
        ]);
    }

    /**
     * Confirmation manuelle par un admin/gestionnaire : régularise un paiement
     * dont le client a prouvé le débit, sans dépendre de la passerelle.
     * Fonctionne même en environnement de développement (127.0.0.1).
     */
    public function confirmerPaiementManuel(Request $request)
    {
        Request()->validate(['codePaiement' => 'required|string']);

        $user = $request->user();
        $rolesAutorises = [Help::$USER_SA, Help::$USER_ADMIN, Help::$USER_GESTIONNAIRE];
        if (!$user || !in_array($user->type_user_id, $rolesAutorises)) {
            return back()->with('error', "Action réservée à un administrateur ou gestionnaire.");
        }

        $regle = $this->marquerPaiementEffectue(
            $request->codePaiement,
            $request->reference,
            $request->moyen_paiement ?? 'Confirmation manuelle'
        );

        if (!$regle) {
            return back()->with('error', "Paiement introuvable ou déjà confirmé.");
        }

        Help::ecrireLog('confirmerPaiementManuel', 'info', "Paiement {$request->codePaiement} confirmé manuellement par user {$user->id}", $user->id);
        return back()->with('success', 'Paiement confirmé manuellement.');
    }

    /**
     * Reprend les paiements restés "en attente" (statut INACTIF) depuis un délai
     * et tente de les régulariser via la passerelle. Appelée par la commande planifiée
     * paiements:reprendre-en-attente. N'a d'effet automatique que si PAYSECURE_STATUS_URL
     * est configuré.
     *
     * @return array compteurs [verifies, confirmes]
     */
    public function reprendrePaiementsEnAttente(int $minutesMin = 5, int $limit = 100): array
    {
        $verifies = 0;
        $confirmes = 0;

        $paiements = Paiement::where('statut', Help::$STATUT_INACTIF)
            ->whereNotNull('code')
            ->where('created_at', '<=', date('Y-m-d H:i:s', strtotime("-{$minutesMin} minutes")))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($paiements as $p) {
            $verifies++;
            if ($this->interrogerStatutPaiement($p->code) === 'paye') {
                if ($this->marquerPaiementEffectue($p->code)) {
                    $confirmes++;
                }
            }
        }

        return ['verifies' => $verifies, 'confirmes' => $confirmes];
    }
}
