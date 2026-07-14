<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use App\Models\Livreur;
use App\Models\Commande;
use App\Models\Location;
use App\Models\Vehicule;
use App\Models\CodeReset;
use App\Models\Livraison;
use App\Models\ModePaiement;
use App\Models\TypeVehicule;
use App\Models\UniteProduit;
use Illuminate\Http\Request;
use App\Models\TypeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailLocation;
use App\Models\DemandePaiement;
use App\Mail\CodeInscriptionMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LivreurController extends Controller
{
    public function connexion(Request $request)
    {

        Request()->validate([
            'login' => "required",
            'password' => "required",
        ]);

        $retour = new Retour();

        try {

            $login = $request->login;
            $password = $request->password;

            $user = User::lireSurLogin($login, Help::$USER_LIVREUR);

            if ($user->id > 0 && $user->type_user_id == Help::$USER_LIVREUR) {

                if ($user->statut == Help::$STATUT_ACTIF) {
                    if (Help::HashVerifier($password, $user->password)) {
                        $retour->code = 200;
                        $retour->token = Crypt::encryptString($user->id);
                        $retour->type = $user->type_user_id;
                        $retour->nom = $user->nom_prenoms;
                        $retour->photo = Help::urlFichier($user->photo);
                        $retour->email = $user->email;
                        $retour->livreur = Livreur::lireSurUser($user->id);
                        $retour->configs = [
                            'mode_paiements' => ModePaiement::liste(),
                            'type_livraisons' => TypeLivraison::liste(),
                            'unites' => UniteProduit::liste(),
                            'pays' => Pays::liste(),
                            'villes' => Ville::liste(),
                            'url_fichier' => "",
                        ];
                        $retour->message = "Login successful";
                    } else {
                        $retour->code = 406;
                        $retour->message = 'Login ou mot de passe incorrecte code: 406';
                    }
                } else {
                    $retour->code = 405;
                    $retour->message = "Votre compte est inactif. Veuillez contacter l'administrateur";
                }
            } else {
                $retour->code = 404;
                $retour->message = 'Login ou mot de passe incorrecte code: 404';
            }
        }catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Login ou mot de passe incorrecte code: 404 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function renvoyerOtp(Request $request)
    {

        Request()->validate([
            'access' => "required",
            'type' => "required",
            'niveau' => "required",
        ]);

        $retour = new Retour();

        $niveau = $request->niveau;

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $codeReset = CodeReset::lireSurUser($user->id, $niveau == 1 ? Help::$CODE_CONNEXION : Help::$CODE_PASS_OUBLIE, false);
                if ($codeReset->id > 0) {

                    $message = "";
                    if ($niveau == 1) {
                        $message = "Bonjour $user->nom_prenoms, Votre code de connexion sur mon gravier est: $codeReset->code. Veuillez le saisir pour vous connecté. Ce code est disponible pour la journée du " . date("d/m/Y");
                    } else {
                        $message = "Bonjour $user->nom_prenoms, Votre code de confirmation pour reinitialiser votre mot de passe sur mon gravier est: $codeReset->code. Veuillez le saisir pour finaliser l'opération.";
                    }
                    // The email sending is done using the to method on the Mail facade
                    Mail::to($user->email)->send(new CodeInscriptionMail($user->nom_prenoms, $codeReset->code, $message));
                    $retour->code = 200;
                    $retour->message = 'Le code a bien été renvoyé sur votre mail';
                } else {
                    $retour->code = 404;
                    $retour->message = 'Aucun code trouvé';
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

    public function verifierOtp(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'otp' => "required",
            'niveau' => "required",
        ]);

        $retour = new Retour();
        $niveau = $request->niveau;


        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                // Anti brute-force : OTP à 4 chiffres -> 5 tentatives / 15 min / compte.
                $rlKey = 'otp-verify-livreur:' . $user->id;
                if (RateLimiter::tooManyAttempts($rlKey, 5)) {
                    $seconds = RateLimiter::availableIn($rlKey);
                    $retour->code = 429;
                    $retour->message = 'Trop de tentatives. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).';
                    return response()->json($retour);
                }

                $codeReset = CodeReset::lireSurUser($user->id, $niveau == 1 ? Help::$CODE_CONNEXION : Help::$CODE_PASS_OUBLIE, false);
                if ($codeReset->id > 0) {

                    // Expiration réellement vérifiée.
                    if (!empty($codeReset->expiration_date) && strtotime($codeReset->expiration_date) < time()) {
                        $retour->code = 410;
                        $retour->message = 'Code OTP expiré, veuillez en demander un nouveau';
                        return response()->json($retour);
                    }

                    if ($codeReset->code == $request->otp) {

                        RateLimiter::clear($rlKey);

                        if ($niveau == 2) {
                            $codeReset->utilise = true;
                            $codeReset->save();
                        }

                        $retour->code = 200;
                        $retour->message = 'Ok';
                    } else {
                        RateLimiter::hit($rlKey, 900);

                        $retour->code = 405;
                        $retour->message = 'Code OTP incorrecte';
                    }
                } else {
                    $retour->code = 404;
                    $retour->message = 'Aucun code trouvé';
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

    public function demandeReinititPass(Request $request)
    {
        Request()->validate([
            'email' => "required|email",
        ]);

        $retour = new Retour();

        try {
            $email = $request->email;
            $user = User::verifierUser($email, $email);
            if ($user->id > 0 && $user->type_user_id == Help::$USER_LIVREUR) {

                if ($user->statut == Help::$STATUT_ACTIF) {

                    $code = CodeReset::lireSurUser($user->id, Help::$CODE_PASS_OUBLIE, false);
                    if ($code->id <= 0) {
                        $code->code = Help::ChaineAleatoireNombre(4);
                        $code->email = $email;
                        $code->user_id = $user->id;
                        $code->type_code = Help::$CODE_PASS_OUBLIE;
                        $code->expiration_date = date("Y-m-d H:i:s", strtotime("+30 minutes")); // OTP courte durée (anti brute-force)
                        $code->utilise = false;
                        $code->save();
                    }

                    // The email sending is done using the to method on the Mail facade
                    $message = "Bonjour $user->nom_prenoms, Votre code de confirmation pour reinitialiser votre mot de passe sur mon gravier est: $code->code. Veuillez le saisir sur l'application pour finaliser le processus.";
                    Mail::to($user->email)->send(new CodeInscriptionMail($user->nom_prenoms, $code->code, $message));

                    $retour->code = 200;
                    $retour->token = Crypt::encryptString($user->id);
                    $retour->type = $user->type_user_id;
                    $retour->message = 'Le code a bien été renvoyé sur votre mail';
                } else {
                    $retour->code = 405;
                    $retour->message = "Votre compte est inactif";
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
            $retour->message = $th->getMessage();
        }

        return response()->json($retour);
    }

    public function listeVehicule(Request $request)
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
                $livreur = Livreur::lireSurUser($user->id);
                $retour->data = [
                    "vehicules" => Vehicule::liste($livreur->id),
                    "types" => TypeVehicule::liste(),
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

    public function enregistrerVehicule(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            "id" => "nullable",
            "typeVehicule" => "required",
            "immatriculation" => "required",
            "nom" => "required",
            "description" => "nullable",
            "capacite" => "required",
            "marque" => "required",
            "modele" => "required",
            "disponible" => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $livreur = Livreur::lireSurUser($user->id);

                $vehicule = new Vehicule();
                $id = $request->id ?? 0;
                if ($id > 0) {
                    $vehicule = Vehicule::lire($id);
                }
                $vehicule->immatriculation = $request->immatriculation;
                $vehicule->nom = $request->nom;
                $vehicule->description = $request->descriptoion;
                $vehicule->type_vehicule_id = $request->typeVehicule;
                $vehicule->livreur_id = $livreur->id;
                $vehicule->statut = Help::$STATUT_ACTIF;
                $vehicule->disponible = $request->disponible;
                $vehicule->capacite = $request->capacite;
                $vehicule->marque = $request->marque;
                $vehicule->modele = $request->modele;
                $vehicule->save();

                $retour->data = $vehicule;
                $retour->code = 200;
                $retour->message = 'Vehicule enregistré avec succès';
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

    public function accepterLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'idLivraison' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $livraison = Livraison::lire($request->idLivraison);
                $livraison->accepte = 1;
                $livraison->etat_livraison = Help::$LIVRAISON_EN_TRAITEMENT;
                $livraison->date_accord = date("Y-m-d H:i:s");
                $livraison->save();

                DB::commit();

                $retour->code = 200;
                $retour->message = "Livraison acceptée avec succès";
                $retour->data = $livraison;
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

    public function refuserLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'idLivraison' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $livraison = Livraison::lire($request->idLivraison);
                $livraison->accepte = 3;
                $livraison->save();

                DB::commit();

                $retour->code = 200;
                $retour->message = "Vous avez refusé la livraison";
                $retour->data = $livraison;
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

    public function enregistrerFinLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'idLivraison' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                // Verrou pessimiste + garde d'idempotence : un double tap ou un
                // retry réseau ne doit PAS créditer le livreur deux fois ni
                // incrémenter qte_livree en double. On relit la livraison FOR UPDATE
                // et on sort si elle est déjà LIVREE.
                $livraison = Livraison::where('id', $request->idLivraison)->lockForUpdate()->first();
                if (!$livraison || $livraison->id <= 0) {
                    DB::rollBack();
                    $retour->code = 404;
                    $retour->message = 'Livraison introuvable';
                    return response()->json($retour);
                }
                if ($livraison->etat_livraison == Help::$LIVRAISON_LIVREE) {
                    DB::commit();
                    $retour->code = 200;
                    $retour->message = "Livraison déjà enregistrée comme livrée";
                    $retour->data = $livraison;
                    return response()->json($retour);
                }

                $livraison->etat_livraison = Help::$LIVRAISON_LIVREE;
                $livraison->date_livraison = date("Y-m-d H:i:s");
                $livraison->note_livreur = $request->note;
                $livraison->save();

                $livreur = Livreur::lire($livraison->livreur_id);
                $livreur->solde += $livraison->cout_livraison;
                $livreur->save();

                switch ($livraison->provenance) {
                    case 'COMMANDE':
                        $det = DetailCommande::lire($livraison->detail_commande_id);

                        // Point 10 — La commande passe en TERMINEE UNIQUEMENT lorsque la dernière
                        // action (la dernière livraison) est exécutée. On vérifie que toutes les
                        // lignes ont leur quantité totalement livrée AVANT de marquer TERMINEE,
                        // sinon on reste en EN TRAITEMENT (pour refléter une livraison partielle).
                        $com = Commande::lire($det->commande_id);

                        // Met à jour qte_livree de la ligne courante
                        $det->qte_livree = (float) ($det->qte_livree ?? 0) + (float) $livraison->qte;
                        $det->save();

                        $details = DetailCommande::where('commande_id', $com->id)->get();
                        $toutLivre = true;
                        foreach ($details as $d) {
                            if ((float) ($d->qte_livree ?? 0) < (float) $d->qte) {
                                $toutLivre = false;
                                break;
                            }
                        }

                        $com->etat_commande = $toutLivre
                            ? Help::$COMMANDE_TERMINE
                            : Help::$COMMANDE_EN_TRAITEMENT;
                        $com->save();
                        break;
                    case 'LOCATION':
                        // La LIVRAISON du matériel ne TERMINE pas la location : le matériel
                        // est remis au client, la location est EN COURS. Le passage à TERMINE
                        // se fait au RETOUR du matériel (écran admin « Retour »).
                        $det = DetailLocation::lire($livraison->detail_commande_id);
                        $loc = Location::lire($det->location_id);
                        $loc->etat_location = Help::$LOCATION_EN_COURS;
                        $loc->save();
                        break;
                }

                DB::commit();

                $retour->code = 200;
                $retour->message = "Fin de livraison enregistrée avec succès votre solde à été crédité";
                $retour->data = $livraison;
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

    public function listerDemandePaiement(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $retour->code = 200;
                $retour->message = "Ok";
                $dems = DemandePaiement::liste($user->id);
                foreach ($dems as $d) {
                    $d->date_demande = $d->created_at->format('d/m/Y H:i:s');
                }
                $retour->data = $dems;
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

    public function enregistrerDemandePaiement(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'montant' => "required",
            'mode' => "required",
            'compte' => "required",
        ]);
        $retour = new Retour();
        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                if ($request->id > 0) {
                    $demande = DemandePaiement::lire($request->id);
                    $demande->montant = $request->montant;
                    $demande->mode_paiement_id = $request->mode;
                    $demande->numero_compte = $request->compte;
                    $demande->save();
                    DB::commit();
                    $retour->code = 200;
                    $retour->message = "Demande de paiement enregistrée avec succès";
                } else {
                    $livreur = Livreur::lireSurUser($user->id);
                    if ($livreur->id > 0 && $livreur->solde >= $request->montant) {
                        $demande = DemandePaiement::verifierNonPaye($user->id);
                        if ($demande->id > 0) {
                            $retour->code = 406;
                            $retour->message = "vous avez déjà une demande en attente de paiement";
                        } else {
                            $demande = new DemandePaiement();
                            $demande->montant = $request->montant;
                            $demande->mode_paiement_id = $request->mode;
                            $demande->numero_compte = $request->compte;
                            $demande->user_id = $user->id;
                            $demande->paye = false;
                            $demande->statut = Help::$STATUT_ACTIF;
                            $demande->save();

                            $livreur->solde -= $request->montant;
                            $livreur->save();

                            DB::commit();

                            $retour->code = 200;
                            $retour->message = "Demande de paiement effectuée avec succès";
                        }
                    } else {
                        $retour->code = 406;
                        $retour->message = "votre solde de " . Help::formatNombre($livreur->solde, true) . "est insuffisant pour effectuer une demande de " . Help::formatNombre($request->montant, true);
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
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }

    public function homeLivreur(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
        ]);
        $retour = new Retour();
        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $livreur = Livreur::lireSurUser($user->id);
                $retour->code = 200;
                $retour->message = "ok";

                $dems = DemandePaiement::listeEffectue($user->id);
                foreach ($dems as $d) {
                    $d->date_demande = $d->created_at->format('d/m/Y H:i:s');
                }

                $retour->data = [
                    "livreur" => $livreur,
                    "stats" => Livraison::statLivreur($livreur->id),
                    "paiements" => $dems,
                ];
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

    /**
     * Mise à jour de la position GPS du livreur (appelée régulièrement par l'app mobile).
     * Inputs : access (token chiffré), latitude, longitude, [disponible: bool optionnel]
     */
    public function majPosition(Request $request)
    {
        $request->validate([
            'access' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $retour = new Retour();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id <= 0) {
                $retour->code = 404;
                $retour->message = "Utilisateur introuvable";
                return response()->json($retour);
            }
            $livreur = Livreur::lireSurUser($user->id);
            if ($livreur->id <= 0) {
                $retour->code = 404;
                $retour->message = "Aucun livreur lié à ce compte";
                return response()->json($retour);
            }

            $livreur->latitude = (string) $request->latitude;
            $livreur->longitude = (string) $request->longitude;
            $livreur->derniere_position_at = now();
            if ($request->has('disponible')) {
                $livreur->disponible = (bool) $request->disponible;
            }
            $livreur->save();

            $retour->code = 200;
            $retour->message = 'Position mise à jour';
            $retour->data = [
                'latitude' => $livreur->latitude,
                'longitude' => $livreur->longitude,
                'derniere_position_at' => $livreur->derniere_position_at,
            ];
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Erreur : ' . $th->getMessage();
        }
        return response()->json($retour);
    }

    /**
     * Retourne le livreur disponible le plus proche d'un point GPS donné,
     * + la distance en km + le coût de livraison estimé (distance × cout_liv_fixe).
     * Inputs : latitude, longitude (du point de livraison).
     */
    public function plusProche(Request $request)
    {
        $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $retour = new Retour();
        try {
            $latPt = (float) $request->latitude;
            $lonPt = (float) $request->longitude;

            // On filtre sur les livreurs ACTIFS, DISPONIBLES, avec une position connue.
            $livreurs = Livreur::where('statut', Help::$STATUT_ACTIF)
                ->where('disponible', 1)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('user')
                ->get();

            if ($livreurs->isEmpty()) {
                $retour->code = 404;
                $retour->message = "Aucun livreur disponible avec position connue.";
                return response()->json($retour);
            }

            $meilleur = null;
            $distanceMin = null;
            foreach ($livreurs as $l) {
                $d = Livreur::distanceKm($l->latitude, $l->longitude, $latPt, $lonPt);
                if ($d === null) continue;
                if ($distanceMin === null || $d < $distanceMin) {
                    $distanceMin = $d;
                    $meilleur = $l;
                }
            }

            if (!$meilleur) {
                $retour->code = 404;
                $retour->message = "Aucun livreur géolocalisable trouvé.";
                return response()->json($retour);
            }

            // Coût livraison = distance × taux paramétré dans Configuration (cout_liv_fixe).
            // Fallback : si non défini, utilise prixKm, sinon 0.
            $config = \App\Models\Configuration::first();
            $taux = (float) ($config?->cout_liv_fixe ?? $config?->prixKm ?? 0);
            $cout = round($distanceMin * $taux, 0);

            // Application d'un cout minimum si paramétré
            $coutMin = (float) ($config?->cout_livraison_min ?? 0);
            if ($coutMin > 0 && $cout < $coutMin) {
                $cout = $coutMin;
            }

            $retour->code = 200;
            $retour->message = "Livreur le plus proche trouvé.";
            $retour->data = [
                'livreur' => [
                    'id' => $meilleur->id,
                    'nom_prenoms' => $meilleur->user?->nom_prenoms,
                    'contact' => $meilleur->user?->contact,
                    'latitude' => $meilleur->latitude,
                    'longitude' => $meilleur->longitude,
                ],
                'distance_km' => round($distanceMin, 2),
                'taux_par_km' => $taux,
                'cout_livraison' => $cout,
            ];
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Erreur : ' . $th->getMessage();
        }
        return response()->json($retour);
    }
}
