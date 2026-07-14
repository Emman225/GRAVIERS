<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Apporteur;
use App\Models\CodeReset;
use App\Models\ModePaiement;
use App\Models\UniteProduit;
use Illuminate\Http\Request;
use App\Models\TypeLivraison;
use App\Models\DemandePaiement;
use App\Mail\CodeInscriptionMail;
use App\Mail\InscriptionEffectueeMail;
use Illuminate\Support\Facades\DB;
use App\Models\CommissionApporteur;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ApporteurController extends Controller
{
    public function chargerParametres()
    {
        $retour = [
            'pays' => Pays::liste(),
            'villes' => Ville::liste(),
            'mode_paiements' => ModePaiement::listePourApporteur(),
        ];
        return response()->json($retour);
    }

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

            $user = User::lireSurLogin($login, Help::$USER_APPORTEUR);

            if ($user->id > 0 && $user->type_user_id == Help::$USER_APPORTEUR) {

                if ($user->statut == Help::$STATUT_ACTIF) {
                    if (Help::HashVerifier($password, $user->password)) {
                        $retour->code = 200;
                        $retour->token = Crypt::encryptString($user->id);
                        $retour->type = $user->type_user_id;
                        $retour->nom = $user->nom_prenoms;
                        $retour->photo = Help::urlFichier($user->photo);
                        $retour->email = $user->email;
                        $retour->apporteur = Apporteur::lireSurUser($user->id);
                        $retour->configs = [
                            'mode_paiements' => ModePaiement::liste(),
                            'type_livraisons' => TypeLivraison::liste(),
                            'unites' => UniteProduit::liste(),
                            'pays' => Pays::liste(),
                            'villes' => Ville::liste(),
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
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Login ou mot de passe incorrecte code: 404 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function inscription(Request $request)
    {

        $valid = Validator::make($request->input(),[
            'nom_prenoms' => "required",
            'email' => "required|email|unique:users,email",
            'contact' => "required|unique:users,contact",
            'password' => "required",
            'pays_id' => "required",
            'ville_id' => "required",
            'recto' => "required",
            'verso' => "required",
            'numero_piece' => "required",
            'mode_paiement_id' => "required|exists:mode_paiement,id",
        ],[
            'email.unique' => "Cette adresse email est déjà utilisée.",
            'contact.unique' => "Ce numéro de téléphone est déjà utilisé.",
            'mode_paiement_id.exists' => "Le mode de paiement sélectionné est invalide.",
        ]);

        $retour = new Retour();

        if ($valid->fails()) {
            $retour->code = 501;
            $retour->message = collect($valid->errors())->flatten()->implode(" \n ");
            return response()->json($retour);
        }

        try {
            DB::beginTransaction();

            $nom_prenoms = $request->nom_prenoms;
            $email = $request->email;
            $contact = $request->contact;
            $password = $request->password;
            $pays_id = $request->pays_id;
            $ville_id = $request->ville_id;

            $user = User::verifierUser($email, $email);
            if ($user->id <= 0) {

                $recto = "";
                if ($request->recto != '') {
                    $recto = "piecesApporteurs/recto-$email.png";
                    Storage::disk("principal")->put($recto, base64_decode($request->recto));
                }
                $verso = "";
                if ($request->verso != '') {
                    $verso = "piecesApporteurs/verso-$email.png";
                    Storage::disk("principal")->put($verso, base64_decode($request->verso));
                }

                $user = new User();
                $user->nom_prenoms = $nom_prenoms;
                $user->email = $email;
                $user->contact = $contact;
                $user->login = $email;
                $user->password = Help::HashPassword($password);
                $user->type_user_id = Help::$USER_APPORTEUR;
                $user->statut = Help::$STATUT_INACTIF;
                $user->pays_id = $pays_id;
                $user->ville_id = $ville_id;
                $user->save();

                $arrNoms = explode(" ", $nom_prenoms);
                $leNom = "";
                if (count($arrNoms) >= 2) {
                    $leNom = $arrNoms[0];
                } else {
                    $leNom = $arrNoms[0];
                }
                $apporteur = new Apporteur();
                $apporteur->user_id = $user->id;
                $apporteur->code = $leNom . strtoupper(Help::ChaineAleatoire(4));
                $apporteur->solde = 0;
                $apporteur->statut = Help::$STATUT_INACTIF;
                $apporteur->pourcentage = 1;
                $apporteur->piece_recto = $recto;
                $apporteur->piece_verso = $verso;
                $apporteur->numero_piece = $request->numero_piece;
                $apporteur->mode_paiement_id = $request->mode_paiement_id;
                $apporteur->save();

                $code = CodeReset::lireSurUser($user->id, Help::$CODE_INSCRIPTION, false);
                if ($code->id <= 0) {
                    $code->code = Help::ChaineAleatoireNombre(4);
                    $code->email = $email;
                    $code->user_id = $user->id;
                    $code->type_code = Help::$CODE_INSCRIPTION;
                    $code->expiration_date = date("Y-m-d H:i:s", strtotime("+30 minutes")); // OTP courte durée (anti brute-force)
                    $code->utilise = false;
                    $code->save();
                }

                // The email sending is done using the to method on the Mail facade
                $message = "Bonjour $nom_prenoms, Votre code de confirmation pour vous inscrire sur mon gravier est: $code->code. Veuillez le saisir pour finaliser votre inscription d'apporteur d'affaire.";
                try {
                    Mail::to($email)->send(new CodeInscriptionMail($nom_prenoms, $code->code, $message));
                } catch (\Throwable $mailEx) {
                    \Log::error('Erreur envoi email inscription: ' . $mailEx->getMessage());
                }

                $retour->code = 200;
                $retour->message = "Sign up successful";
                $retour->token = Crypt::encryptString($user->id);
                $retour->type = $user->type_user_id;
                $retour->nom = $user->nom_prenoms;
                $retour->photo = Help::urlFichier($user->photo);
                $retour->email = $user->email;
                $retour->apporteur = Apporteur::lireSurUser($user->id);
                $retour->configs = [
                    'mode_paiements' => ModePaiement::liste(),
                    'type_livraisons' => TypeLivraison::liste(),
                    'unites' => UniteProduit::liste(),
                    'pays' => Pays::liste(),
                    'villes' => Ville::liste(),
                ];
                DB::commit();
            }else{
                $retour->code = 501;
                $retour->message = 'Vous ne pouvez pas vous inscrire avec cette adresse email';
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
            DB::rollBack();
            \Log::error('Erreur inscription apporteur: ' . $th->getMessage());
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite. Veuillez réessayer plus tard.';
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
                $codeReset = CodeReset::lireSurUser($user->id, $niveau == 1 ? Help::$CODE_INSCRIPTION : Help::$CODE_PASS_OUBLIE, false);
                if ($codeReset->id > 0) {

                    $message = "";
                    if ($niveau == 1) {
                        $message = "Bonjour $user->nom_prenoms, Votre code de confirmation pour vous inscrire sur mon gravier est: $codeReset->code. Veuillez le saisir pour finaliser votre inscription d'apporteur d'affaire.";
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
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
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
                $rlKey = 'otp-verify-apporteur:' . $user->id;
                if (RateLimiter::tooManyAttempts($rlKey, 5)) {
                    $seconds = RateLimiter::availableIn($rlKey);
                    $retour->code = 429;
                    $retour->message = 'Trop de tentatives. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).';
                    return response()->json($retour);
                }

                $codeReset = CodeReset::lireSurUser($user->id, $niveau == 1 ? Help::$CODE_INSCRIPTION: Help::$CODE_PASS_OUBLIE, false);
                if ($codeReset->id > 0) {

                    // Expiration réellement vérifiée.
                    if (!empty($codeReset->expiration_date) && strtotime($codeReset->expiration_date) < time()) {
                        $retour->code = 410;
                        $retour->message = 'Code OTP expiré, veuillez en demander un nouveau';
                        return response()->json($retour);
                    }

                    if ($codeReset->code == $request->otp) {

                        RateLimiter::clear($rlKey);

                        if ($niveau == 1) {
                            //On active le compte de l'apporteur
                            $apporteur = Apporteur::lireSurUser($user->id);
                            $apporteur->statut = Help::$STATUT_ACTIF;
                            $apporteur->save();

                            $user->statut = Help::$STATUT_ACTIF;
                            $user->save();

                            Mail::to($user->email)->send(new InscriptionEffectueeMail($user->nom_prenoms));

                        }

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
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
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
            if ($user->id > 0 && $user->type_user_id == Help::$USER_APPORTEUR) {

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
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = $th->getMessage();
        }

        return response()->json($retour);
    }

    public function listeFilleule(Request $request)
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
                $apporteur = Apporteur::lireSurUser($user->id);
                $retour->data = Client::listeFilleule($apporteur->id);
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
        }catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }

    public function listeCommission(Request $request)
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
                $apporteur = Apporteur::lireSurUser($user->id);
                $retour->data = CommissionApporteur::liste($apporteur->id);
                $retour->code = 200;
                $retour->message = 'ok' . $apporteur->id;
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
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
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
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
                    $apporteur = Apporteur::lireSurUser($user->id);
                    if ($apporteur->id > 0 && $apporteur->solde >= $request->montant) {
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
                            DB::commit();
                            $retour->code = 200;
                            $retour->message = "Demande de paiement effectuée avec succès";
                        }
                    } else {
                        $retour->code = 406;
                        $retour->message = "votre solde de " . Help::formatNombre($apporteur->solde, true) . "est insuffisant pour effectuer une demande de " . Help::formatNombre($request->montant, true);
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
        }catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }

    public function homeApporteur(Request $request)
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

                $apporteur = Apporteur::lireSurUser($user->id);
                $retour->code = 200;
                $retour->message = "ok";

                $dems = DemandePaiement::listeEffectue($user->id);
                foreach ($dems as $d) {
                    $d->date_demande = $d->created_at->format('d/m/Y H:i:s');
                }

                $retour->apporteur = $apporteur;
                $retour->data = [
                    "stats" => Apporteur::statPaiement($apporteur->id),
                    "paiements" => $dems,
                ];
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }

    public function listePaiementFilleule(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'filleule_id' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $retour->code = 200;
                $retour->message = "ok";
                $retour->data = Paiement::liste(null, $request->filleule_id, [Help::$STATUT_ACTIF, Help::$STATUT_INACTIF]);
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        }catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }
        return response()->json($retour);
    }
}
