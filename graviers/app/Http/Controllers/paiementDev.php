<?php

namespace App\Http\Controllers;

use App\Mail\emailCommande;
use App\Models\Apporteur;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\DemandeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailLivraison;
use App\Models\DetailLocation;
use App\Models\DetailsLivraison;
use App\Models\LignePaiement;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\User;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Retour;

class paiementDev extends Controller
{
    //

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
                        Help::ecrireLog(
                            "bien payé",
                            "CallBack paiement error 3",
                            "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                            0
                        );
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

                                $parrain_id = $ligne->parrain_id;
                                $idService = $ligne->service_id;
                                $service = $ligne->service;

                                if ($parrain_id > 0 && $idService > 0 && $service != "") {
                                    $this->payerApporteurAffaire($parrain_id, $idService, $paiement->montant_total, $service);
                                }
                            }
                            $paiement->save();
                        }

                        foreach ($arrService as $s) {
                            switch ($s['service']) {
                                case Help::$COMMANDE:

                                    $commande = Commande::lire($s['service_id']);
                                    Help::ecrireLog(
                                        "callBackPaiement",
                                        "CallBack paiement error 5",
                                        "Données: " . json_encode($request->input()) . " Cause: " . $commande,
                                        0
                                    );


                                    // Mail::send(new emailCommande(
                                    //     $commande->client->user->email,
                                    //     $commande->client->nom.' '.$commande->client->prenom,
                                    //     $commande,
                                    //     $commande->montant_total,
                                    //     0,
                                    //     0));
                                    break;
                                case Help::$LOCATION:
                                    $jour = [];
                                    $location = Location::lire($s['service_id']);
                                    foreach ($location->detailLocation as $d) {
                                        array_push($jour, $d->nombre_jour);
                                    }

                                    // Mail::send(new emailLocation($$location->client->user,
                                    //     $location,
                                    //     0,
                                    //     0,
                                    //     $jour,
                                    //     $location->montant_total
                                    // ));

                                    break;
                                case Help::$LIVRAISON:

                                    break;
                                default:
                                    break;
                            }
                        }

                        // Envoie de mail

                    } else {

                        Help::ecrireLog(
                            "!achevé",
                            "- ",
                            "Données: " . $lignePaiements . " autre :" . Paiement::lire($lignePaiements->first()->paiement_id),
                            0
                        );
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

                            Help::ecrireLog(
                                "pour tester",
                                "pour tester",
                                "Données: " . $l . "||| paiement_id: " . $l->paiement_id . " paiement: " . Paiement::lire($l->paiement_id),
                                0
                            );
                        }
                    }
                    Help::ecrireLog(
                        "ligne trouvé",
                        "CallBack paiement error 3",
                        "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                        0
                    );
                } else {
                    Help::ecrireLog(
                        "callBackPaiement",
                        "CallBack paiement error 3",
                        "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                        0
                    );
                }
                Help::ecrireLog(
                    "code trouvé",
                    "CallBack paiement error 3",
                    "Données: " . json_encode($request->input()) . " Cause: Ligne Paiement introuvable",
                    0
                );
            } else {
                Help::ecrireLog(
                    "callBackPaiement",
                    "CallBack paiement error 2",
                    "Données: " . json_encode($request->input()) . " Cause: Code de paiement introuvable",
                    0
                );
            }
        } catch (\Throwable $th) {
            Help::ecrireLog(
                "callBackPaiement",
                "error 1",
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

    public static function initierPaiement($paramInit, $numero, $codePaiement, $client, $montantTotal, $modePaiement, $idService, $service, $factures = null)
    {
        if ($idService != null && $service != null) {
            if ($client->client_a_terme == false) {
                //Client BE
                $paiement = new Paiement();
                $paiement->client_id = $client->id;
                $paiement->devis_id = 0;
                $paiement->service_id = $idService;
                $paiement->service = Help::$COMMANDE;
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
                    $paiement->devis_id = 0;
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
                    $paiement->devis_id = 0;
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
        $r['message'] = route('client.index');


        return $r;
    }


    private function payerApporteurAffaire($idApporteur, $montantTotal, $idService, $typeService)
    {
        $apporteur = Apporteur::lire($idApporteur);

        $com = new CommissionApporteur();
        $com->apporteur_id = $idApporteur;
        $com->commande_id = $idService;
        $com->type_affaire = $typeService;
        $com->montant = $montantTotal * ($apporteur->pourcentage / 100);
        $com->statut = 1;
        $com->save();

        $apporteur->solde += $com->montant;
        $apporteur->save();
    }
}
