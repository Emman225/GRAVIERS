<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use App\Models\Enlevement;
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
use App\Models\DemandeLivraison;
use App\Models\DetailsLivraison;
use App\Mail\CodeInscriptionMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

                    // hash_equals : comparaison stricte et à temps constant. On
                    // normalise les deux valeurs à 4 chiffres (str_pad) car la colonne
                    // `code` est un entier : un OTP « 0123 » est stocké « 123 », alors
                    // que l'email envoie « 0123 » -> sans padding, hash_equals échouerait
                    // sur la différence de longueur (~10 % des codes, ceux à zéro en tête).
                    if (hash_equals(
                        str_pad((string) $codeReset->code, 4, '0', STR_PAD_LEFT),
                        str_pad((string) $request->otp, 4, '0', STR_PAD_LEFT)
                    )) {

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

                // AUTORISATION : seul le livreur ASSIGNÉ à la livraison peut
                // l'accepter (même garde que enregistrerFinLivraison).
                $livreurAuth = Livreur::lireSurUser($user->id);
                if ($livreurAuth->id <= 0) {
                    DB::rollBack();
                    $retour->code = 403;
                    $retour->message = 'Action réservée à un livreur';
                    return response()->json($retour);
                }

                $livraison = Livraison::lire($request->idLivraison);
                if ($livraison->livreur_id != $livreurAuth->id) {
                    // Couvre aussi la livraison inexistante (livreur_id null) :
                    // 404 pour ne pas servir d'oracle d'énumération.
                    DB::rollBack();
                    $retour->code = 404;
                    $retour->message = 'Livraison introuvable';
                    return response()->json($retour);
                }
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

                // AUTORISATION : seul le livreur ASSIGNÉ à la livraison peut
                // la refuser (même garde que enregistrerFinLivraison).
                $livreurAuth = Livreur::lireSurUser($user->id);
                if ($livreurAuth->id <= 0) {
                    DB::rollBack();
                    $retour->code = 403;
                    $retour->message = 'Action réservée à un livreur';
                    return response()->json($retour);
                }

                $livraison = Livraison::lire($request->idLivraison);
                if ($livraison->livreur_id != $livreurAuth->id) {
                    // Couvre aussi la livraison inexistante (livreur_id null) :
                    // 404 pour ne pas servir d'oracle d'énumération.
                    DB::rollBack();
                    $retour->code = 404;
                    $retour->message = 'Livraison introuvable';
                    return response()->json($retour);
                }
                $livraison->accepte = 3;
                $livraison->save();

                // REFUS : LIBERER CE QUE L'AFFECTATION AVAIT RESERVE.
                //
                // Le refus ne faisait qu'ecrire `accepte = 3`. Le camion, mis
                // indisponible a l'affectation, le restait pour toujours : il
                // sortait du parc sans avoir rien transporte.
                //
                // La ligne de la demande, elle, repasse « EN ATTENTE » des lors
                // qu'aucune course active ne la couvre plus — c'est cet etat que
                // le back-office lit pour proposer une nouvelle affectation.
                if ($livraison->vehicule_id) {
                    DB::table('vehicule')
                        ->where('id', $livraison->vehicule_id)
                        ->update(['disponible' => 1]);
                }

                // PROVENANCE COMMANDE : la ligne de commande repasse « EN
                // ATTENTE » des lors qu'aucune course active ne la couvre plus.
                // Sans quoi elle restait « EN TRAITEMENT », et les ecrans qui
                // s'appuient sur cet etat la croyaient prise en charge.
                //
                // On ne touche a detail_commande QUE pour une vente : sur une
                // location, `detail_commande_id` porte l'id d'un detail_location,
                // et ecrire dedans corromprait une ligne de commande etrangere.
                if ($livraison->provenance === 'COMMANDE' && $livraison->detail_commande_id) {
                    $encoreCouverte = DB::table('livraison')
                        ->where('detail_commande_id', $livraison->detail_commande_id)
                        ->where('provenance', 'COMMANDE')
                        ->where('accepte', '!=', 3)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$encoreCouverte) {
                        DB::table('detail_commande')
                            ->where('id', $livraison->detail_commande_id)
                            ->update(['etat_livraison' => Help::$LIVRAISON_EN_ATTENTE]);
                    }
                }

                // PROVENANCE LOCATION : LA LOCATION REDEVIENT AFFECTABLE.
                //
                // Le refus ne touchait rien du côté location. Or l'écran qui
                // propose une affectation ne liste que les locations « EN
                // ATTENTE » : restée « EN COURS », la location DISPARAISSAIT de
                // la liste, sans que rien ne signale pourquoi. Le gestionnaire
                // n'avait plus aucun moyen de la confier à un autre livreur.
                //
                // On ne détache la location que si PLUS AUCUNE course active ne
                // la couvre : une location à plusieurs matériels peut n'avoir
                // qu'une de ses courses refusée, et les autres continuent.
                if ($livraison->provenance === Help::$LOCATION && $livraison->detail_commande_id) {
                    // Sur une location, `detail_commande_id` porte l'id du
                    // detail_location — d'où le détour pour retrouver la
                    // location elle-même.
                    $locationId = DB::table('detail_location')
                        ->where('id', $livraison->detail_commande_id)
                        ->value('location_id');

                    if ($locationId) {
                        $detailsDeLaLocation = DB::table('detail_location')
                            ->where('location_id', $locationId)
                            ->pluck('id');

                        $encoreCouverte = DB::table('livraison')
                            ->whereIn('detail_commande_id', $detailsDeLaLocation)
                            ->where('provenance', Help::$LOCATION)
                            ->where('accepte', '!=', 3)
                            ->whereNull('deleted_at')
                            ->exists();

                        if (!$encoreCouverte) {
                            // Le livreur et le véhicule sont détachés en même
                            // temps que l'état : les laisser renseignés ferait
                            // croire, sur la fiche, que la course est toujours
                            // confiée à celui qui vient de la refuser.
                            DB::table('location')
                                ->where('id', $locationId)
                                ->update([
                                    'etat_location' => Help::$LOCATION_EN_ATTENTE,
                                    'livreur_id'    => null,
                                    'vehicule_id'   => null,
                                ]);
                        }
                    }
                }

                if ($livraison->detail_livraison_id) {
                    $detail = DB::table('detail_livraison')
                        ->where('id', $livraison->detail_livraison_id)
                        ->first();

                    if ($detail) {
                        // Les courses REFUSEES ne comptent pas : elles n'ont
                        // rien transporte, elles ne consomment donc rien.
                        $affectee = (float) DB::table('livraison')
                            ->where('detail_livraison_id', $detail->id)
                            ->where('accepte', '!=', 3)
                            ->whereNull('deleted_at')
                            ->sum('qte');

                        if ($affectee < (float) $detail->qte) {
                            DB::table('detail_livraison')
                                ->where('id', $detail->id)
                                ->update(['etat_livraison' => Help::$LIVRAISON_EN_ATTENTE]);
                        }
                    }
                }

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

                // AUTORISATION : seul le livreur ASSIGNÉ à la livraison peut la
                // clôturer. Sans ce contrôle, n'importe quel porteur d'un access
                // valide (client, apporteur...) pouvait marquer n'importe quelle
                // livraison comme livrée et créditer le solde du livreur assigné.
                $livreurAuth = Livreur::lireSurUser($user->id);
                if ($livreurAuth->id <= 0) {
                    DB::rollBack();
                    $retour->code = 403;
                    $retour->message = 'Action réservée à un livreur';
                    return response()->json($retour);
                }

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
                if ($livraison->livreur_id != $livreurAuth->id) {
                    // 404 (et non 403) : ne pas confirmer à un tiers l'existence
                    // de la livraison d'un autre livreur.
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

                // LE FOURNISSEUR DOIT AVOIR SERVI LE BON.
                //
                // Rien ne le vérifiait : une course pouvait être clôturée — et le
                // livreur crédité — alors que le bon d'enlèvement n'avait jamais
                // été servi. La vente n'entrait alors dans AUCUN chiffre
                // d'affaires (« CA détaillé », « CA par famille » et le
                // récapitulatif des ventes ne comptent que les bons portant une
                // `fournisseur_validation`), et le fournisseur n'était dû de
                // rien alors que sa marchandise était partie.
                //
                // Le contrôle ne s'applique QUE s'il existe un bon.
                //
                // MISE À JOUR DU 28/08/2026 : les LOCATIONS en produisent
                // désormais un, comme les ventes — le livreur doit pouvoir
                // prouver au fournisseur qu'il est autorisé à retirer le
                // matériel. Elles passent donc sous ce contrôle : une location
                // ne se clôture plus tant que le fournisseur n'a pas validé son
                // bon. C'est voulu, et c'est la même règle que pour une vente.
                //
                // Les demandes de livraison, elles, n'ont toujours aucun bon :
                // le client transporte SA marchandise, aucun fournisseur n'est
                // concerné. Le contrôle les laisse passer.
                $bon = Enlevement::where('livraison_id', $livraison->id)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->first();

                if ($bon && empty($bon->fournisseur_validation)) {
                    DB::rollBack();
                    $retour->code = 409;
                    $retour->message = "Le fournisseur n'a pas encore validé le bon d'enlèvement "
                        . ($bon->code_enleve ? '(' . $bon->code_enleve . ') ' : '')
                        . ": la livraison ne peut pas être clôturée. Demandez-lui de valider son bon.";
                    return response()->json($retour);
                }

                $livraison->etat_livraison = Help::$LIVRAISON_LIVREE;
                $livraison->date_livraison = date("Y-m-d H:i:s");
                // La date de livraison effective, imprimée sur le bon de livraison (lot 84).
                if (\Illuminate\Support\Facades\Schema::hasColumn('livraison', 'date_livree')) {
                    $livraison->date_livree = date("Y-m-d H:i:s");
                }
                $livraison->note_livreur = $request->note;
                $livraison->save();
                // Le bon de livraison part au client entreprise par le site (lot 84,
                // 15/09/2026), après la réponse, jamais bloquant.
                \App\Services\BonDeLivraisonDistant::envoyerApresLaReponse($livraison);

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

                        // CE QUE LE FOURNISSEUR A SERVI, ET NON CE QUI A ÉTÉ DEMANDÉ.
                        //
                        // On ajoutait `livraison->qte`, la quantité demandée. Un
                        // enlèvement partiel — 5 t servies sur 15 — créditait le
                        // client de 15 et la commande passait TERMINEE alors
                        // qu'il restait 10 t à servir. Même défaut que sur le
                        // site, corrigé de la même façon.
                        $det->qte_livree = min(
                            (float) $det->qte,
                            (float) ($det->qte_livree ?? 0) + $livraison->quantiteRemise()
                        );

                        // L'ÉTAT de la ligne n'était pas mis à jour ici, alors que
                        // le site le fait dans son propre écran de validation
                        // (LivreurController::validationLivraison). Une commande
                        // clôturée depuis l'application du livreur restait donc en
                        // « EN COURS LIVRAISON » au niveau de ses lignes, même
                        // affichée TERMINEE au niveau de la commande.
                        //
                        // Conséquence visible : la page « Retour de produit » du
                        // client ne liste que les lignes en LIVREE. Une commande
                        // pourtant livrée n'y apparaissait jamais, et le client ne
                        // pouvait pas demander de retour. Seules les commandes
                        // retirées sur place y figuraient, celles-là étant passées
                        // en LIVREE par SellerController.
                        $det->etat_livraison = ((float) $det->qte_livree >= (float) $det->qte)
                            ? Help::$LIVRAISON_LIVREE
                            : Help::$LIVRAISON_EN_COURS;

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
                        // La ligne livrée suit (10/09/2026) : l'application l'affiche.
                        if ($det->id > 0 && $det->etat_location === Help::$LOCATION_EN_ATTENTE) {
                            $det->etat_location = Help::$LOCATION_EN_COURS;
                            $det->save();
                        }
                        break;

                    case 'LIVRAISON':
                        // DEMANDE DE LIVRAISON — ce cas MANQUAIT.
                        //
                        // La livraison passait bien à LIVREE, mais rien ne
                        // remontait ensuite : la ligne de la demande restait
                        // « EN TRAITEMENT » et la demande elle-même n'était
                        // jamais close. Le gestionnaire la voyait indéfiniment
                        // dans « Demande en attente », et le client dans son
                        // compte, alors que la marchandise était livrée.
                        //
                        // Même règle que le site : une demande est TERMINEE quand
                        // la totalité des quantités demandées a été livrée, et
                        // reste EN TRAITEMENT tant qu'il subsiste un reliquat.
                        $ligne = DetailsLivraison::find($livraison->detail_livraison_id);

                        if ($ligne) {
                            $idsLignes = DetailsLivraison::where('demande_livraison_id', $ligne->demande_livraison_id)
                                ->pluck('id');

                            // Cette ligne-ci est-elle entièrement servie ?
                            $livreePourLaLigne = (float) Livraison::where('detail_livraison_id', $ligne->id)
                                ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
                                ->sum('qte');

                            if ($livreePourLaLigne >= (float) $ligne->qte) {
                                $ligne->etat_livraison = Help::$LIVRAISON_LIVREE;
                                $ligne->save();
                            }

                            // Et la demande entière ?
                            $qteADemander = (float) DetailsLivraison::whereIn('id', $idsLignes)->sum('qte');
                            $qteLivree    = (float) Livraison::whereIn('detail_livraison_id', $idsLignes)
                                ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
                                ->sum('qte');

                            $demande = DemandeLivraison::find($ligne->demande_livraison_id);
                            if ($demande) {
                                // « >= » et non « == » : un arrondi sur des
                                // quantités décimales ne doit pas empêcher la
                                // clôture d'une demande pourtant servie.
                                $demande->etat_commande = ($qteLivree >= $qteADemander)
                                    ? Help::$COMMANDE_TERMINE
                                    : Help::$COMMANDE_EN_TRAITEMENT;
                                $demande->save();
                            }
                        }
                        break;
                }

                // Le véhicule redevient disponible dès qu'il n'a plus aucune
                // course en cours. Il était mis à 0 à l'affectation sans jamais
                // être libéré : après sa première course, il disparaissait
                // définitivement de la liste proposée au gestionnaire, qui se
                // retrouvait sans véhicule alors que les livreurs avaient fini.
                Vehicule::libererSiPlusAucuneCourse($livraison->vehicule_id);

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

    /**
     * Ajoute à la liste des demandes les RÈGLEMENTS saisis au back-office.
     *
     * L'entreprise paie le livreur par deux chemins : la demande qu'il envoie
     * depuis l'application, et le règlement qu'un administrateur enregistre
     * sur une de ses courses. Seul le premier remontait ici : son historique
     * ne retombait pas sur ce qu'il avait réellement reçu.
     *
     * Les règlements issus d'une demande sont EXCLUS : ils sont déjà dans la
     * liste, sous leur demande. Les garder ferait apparaître le même versement
     * deux fois.
     *
     * Les lignes prennent la forme d'une demande déjà payée : l'application
     * les affiche sans modification, et aucune nouvelle version n'est requise.
     */
    private function ajouterReglementsBackOffice($demandes, $livreur, $user)
    {
        if (!$livreur || $livreur->id <= 0) {
            return $demandes;
        }

        $regles = DB::table('paiement_livreur')
            ->leftJoin('mode_paiement', 'mode_paiement.id', '=', 'paiement_livreur.mode_paiement_id')
            ->where('paiement_livreur.livreur_id', $livreur->id)
            ->where('paiement_livreur.statut', 1)   // validés uniquement
            ->whereNull('paiement_livreur.deleted_at')
            // La colonne vient d'une migration du back-office : si elle manque,
            // on s'en passe plutôt que de casser l'écran du livreur.
            ->when(
                Schema::hasColumn('paiement_livreur', 'demande_paiement_id'),
                fn ($q) => $q->whereNull('paiement_livreur.demande_paiement_id')
            )
            ->orderByDesc('paiement_livreur.date_paiement')
            ->select([
                'paiement_livreur.id',
                'paiement_livreur.montant',
                'paiement_livreur.mode_paiement_id',
                'paiement_livreur.date_paiement',
                'paiement_livreur.reference',
                'paiement_livreur.created_at',
                'mode_paiement.libelle as mode_paiement',
            ])
            // Point 20 (09/09/2026) : l'état du circuit « À payer → Effectuée »
            // du règlement, quand la colonne existe (migration du back-office).
            ->when(
                Schema::hasColumn('paiement_livreur', 'etat_reglement'),
                fn ($q) => $q->addSelect('paiement_livreur.etat_reglement')
            )
            ->get();

        foreach ($regles as $r) {
            $demandes->push((object) [
                'id'               => $r->id,
                'montant'          => (float) $r->montant,
                'mode_paiement_id' => $r->mode_paiement_id,
                'mode_paiement'    => $r->mode_paiement ?? 'Paiement en agence',
                // Un règlement saisi au back-office n'a pas de numéro de
                // compte : on envoie la référence de la transaction si l'agent
                // l'a saisie, sinon RIEN — l'application masque alors la ligne
                // au lieu d'afficher « Compte: null ».
                'numero_compte'    => $r->reference ?: '',
                'user_id'          => $user->id,
                'user_valide_id'   => null,
                'user_valide2_id'  => null,
                'date_validation'  => $r->date_paiement,
                'paye'             => 1,
                // L'application lit etat_reglement : EFFECTUEE → « Effectué »,
                // autre valeur → « Validé — paiement en cours », vide → « Payé: OUI ».
                'etat_reglement'   => $r->etat_reglement ?? null,
                'statut'           => Help::$STATUT_ACTIF,
                'deleted_at'       => null,
                'created_at'       => $r->created_at,
                'updated_at'       => $r->created_at,
                'date_demande'     => $r->date_paiement
                    ? date('d/m/Y', strtotime($r->date_paiement))
                    : '',
            ]);
        }

        return $demandes;
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

                // Les courses réglées depuis le back-office ne sont pas dans
                // demande_paiement : on les ajoute (voir ajouterReglementsBackOffice).
                $dems = $this->ajouterReglementsBackOffice($dems, Livreur::lireSurUser($user->id), $user);

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
                            // Le solde est débité ci-dessous (réservation) : la 2e validation
                            // admin ne doit PAS re-débiter (cf. web valideDemande). Sans ce
                            // flag, l'heuristique historique re-débitait CHAQUE demande
                            // acceptée -> solde livreur à 0 au lieu du reliquat.
                            $demande->solde_debite_initiation = 1;
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

                $dems = $this->ajouterReglementsBackOffice($dems, $livreur, $user);

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
