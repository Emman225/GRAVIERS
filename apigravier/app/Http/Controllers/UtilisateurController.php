<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Banniere;
use App\Models\TypeUser;
use App\Models\Apporteur;
use App\Models\Categorie;
use App\Models\CodeReset;
use App\Models\ImageProduit;
use App\Models\ModePaiement;
use App\Models\UniteProduit;
use Illuminate\Http\Request;
use App\Models\TypeLivraison;
use App\Mail\CodeInscriptionMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use App\Mail\InscriptionEffectueeMail;
use Illuminate\Support\Facades\Storage;
use App\Models\DemandeCompteClientATerme;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UtilisateurController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($type = null)
    {
        return User::liste($type);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->validation();
        return User::enregistrer($request->input());
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return $user;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->validation();
        $usr = $request->input();
        $usr['id'] = $user->id;
        return User::enregistrer($usr);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        return User::supprimer($user);
    }

    private function validation()
    {
        Request()->validate([
            'nom_prenoms' => "required",
            'email' => "required",
            'contact' => "required",
            'login' => "required",
            'password' => "required",
            'adresse' => "required",
            'pays_id' => "required",
            'ville_id' => "required",
            'type_user_id' => "required",
        ]);
    }

    public function inscription(Request $request)
    {
        $valid = Validator::make($request->input(), [
            'nom_prenoms' => "required",
            'email' => "required",
            'contact' => "required",
            'password' => "required",
            'type_client' => "required",
            'pays_id'=> "required",
            'ville_id'=> "required",
        ]);

        $retour = new Retour();

        if ($valid->fails()) {
            $retour->code = 501;
            $retour->message = collect($valid->errors())->flatten()->implode(" \n ");
        }

        DB::beginTransaction();
        try {

            $email = $request->email;
            $password = $request->password;
            $nom_prenoms = $request->nom_prenoms;
            $contact = $request->contact;
            $type_client = $request->type_client;
            $code_parain = $request->code_parain;
            $rccm = $request->rccm;
            $ncc = $request->ncc;
            $pays_id = $request->pays_id;
            $ville_id = $request->ville_id;

            // Upload DFE et Registre de commerce pour entreprise
            $dfePath = null;
            $rcPath = null;
            if ($type_client == 2) {
                if ($request->hasFile('dfe')) {
                    $dfePath = $request->file('dfe')->store('documents_entreprise', 'public');
                }
                if ($request->hasFile('registre_commerce')) {
                    $rcPath = $request->file('registre_commerce')->store('documents_entreprise', 'public');
                }
            }


            $pass = true;
            $parrain = new Apporteur();
            if ($code_parain != '' && $code_parain != null) {
                $parrain = Apporteur::lireSurCode($code_parain);
                if ($parrain->id > 0) {
                    $pass = true;
                }else{
                    $pass = false;
                }
            }

            if ($pass == true) {
                $user = User::verifierUser($email, $email);
                if ($user->id <= 0) {
                    $user = new User();
                    $user->nom_prenoms = $nom_prenoms;
                    $user->email = $email;
                    $user->contact = $contact;
                    $user->login = $email;
                    $user->password = Help::HashPassword($password);
                    $user->type_user_id = Help::$USER_CLIENT;
                    $user->statut = Help::$STATUT_INACTIF;
                    $user->pays_id = $pays_id;
                    $user->ville_id = $ville_id;
                    $user->save();

                    $arrNoms = explode(" ", $nom_prenoms);
                    $leNom = "";
                    $lePrenom = "";
                    if (count($arrNoms) >= 2) {
                        $leNom = $arrNoms[0];
                        $lePrenom = $arrNoms[1];
                    } else {
                        $leNom = $arrNoms[0];
                        $lePrenom = $arrNoms[0];
                    }
                    $client = new Client();
                    $client->user_id = $user->id;
                    $client->nom = $leNom;
                    $client->prenom = $lePrenom;
                    $client->email = $email;
                    $client->contact1 = $contact;
                    $client->contact2 = "";
                    $client->code_parrain = $code_parain;
                    $client->parrain_id = $parrain->id;
                    $client->rccm_clt = $rccm;
                    $client->ncc_clt = $ncc;
                    $client->dfe = $dfePath;
                    $client->registre_commerce = $rcPath;
                    $client->type_client = $type_client == 1 ? Help::$PARTICULIER : Help::$ENTREPRISE;
                    $client->statut = Help::$STATUT_INACTIF;
                    $client->save();

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

                    $prods = Produit::liste(null, 10);
                    foreach ($prods as $key => $p) {
                        $prods[$key]->images = ImageProduit::liste($p->id);
                    }
                    $retour->code = 200;
                    $retour->token = Crypt::encryptString($user->id);
                    $retour->type = $user->type_user_id;
                    $retour->nom = $user->nom_prenoms;
                    $retour->message = "SignUp successful";
                    $retour->configs = [
                        'categories' => Categorie::liste(5),
                        'bannieres' => Banniere::liste(),
                        'produits' => $prods,
                        'mode_paiements' => ModePaiement::liste(),
                        'type_livraisons' => TypeLivraison::liste(),
                        'unites' => UniteProduit::liste(),
                        'pays' => Pays::liste(),
                        'villes' => Ville::liste(),
                        'type_users' => TypeUser::liste(),
                        'regions' => DB::table('regions')->get(),
                        'url_fichier' => "",
                    ];
                    DB::commit();
                    try {
                    // The email sending is done using the to method on the Mail facade
                    $message = "Bonjour $nom_prenoms, Votre code de confirmation pour vous inscrire sur mon gravier est: $code->code. Veuillez le saisir pour finaliser votre inscription.";
                    Mail::to($email)->send(new CodeInscriptionMail($nom_prenoms, $code->code, $message));
                    } catch (\Throwable $mailEx) {
                        // L'inscription réussit même si le mail échoue, mais on trace
                        // désormais l'erreur pour pouvoir diagnostiquer les non-réceptions.
                        \Log::error("Echec envoi OTP inscription à $email : " . $mailEx->getMessage());
                    }
                } else {
                    if ($user->statut == Help::$STATUT_INACTIF && $user->type_user_id == Help::$USER_CLIENT) {
                        $client = Client::lireSurUser($user->id);
                        if ($client->id > 0 && $client->statut == Help::$STATUT_INACTIF) {

                            $code = CodeReset::lireSurUser($user->id, Help::$CODE_INSCRIPTION, false);

                            $prods = Produit::liste(null, 10);
                            foreach ($prods as $key => $p) {
                                $prods[$key]->images = ImageProduit::liste($p->id);
                            }
                            $retour->code = 200;
                            $retour->token = Crypt::encryptString($user->id);
                            $retour->type = $user->type_user_id;
                            $retour->nom = $user->nom_prenoms;
                            $retour->message = "SignUp successful";
                            $retour->configs = [
                                'categories' => Categorie::liste(5),
                                'bannieres' => Banniere::liste(),
                                'produits' => $prods,
                                'mode_paiements' => ModePaiement::liste(),
                                'type_livraisons' => TypeLivraison::liste(),
                                'unites' => UniteProduit::liste(),
                                'pays' => Pays::liste(),
                                'villes' => Ville::liste(),
                                'type_users' => TypeUser::liste(),
                                'regions' => DB::table('regions')->get(),
                                'url_fichier' => "",
                            ];
                            DB::commit();
                        try {
                            // The email sending is done using the to method on the Mail facade
                            $message = "Bonjour $nom_prenoms, Votre code de confirmation pour vous inscrire sur mon gravier est: $code->code. Veuillez le saisir pour finaliser votre inscription.";
                            Mail::to($email)->send(new CodeInscriptionMail($nom_prenoms, $code->code, $message));
                        } catch (\Throwable $mailEx) {
                            // Email failed silently - registration succeeded
                        }
                        }
                    }else{
                        $retour->code = 406;
                        $retour->message = 'Vous ne pouvez pas vous inscrire avec cette adresse email';
                    }
                }
            } else {
                DB::rollBack();
                $retour->code = 405;
                $retour->message = 'Le code parain que vous avez saisi est incorrecte retirez le si vous n\'en avez pas';
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

            $user = User::lireSurLogin($login, Help::$USER_CLIENT);

            if ($user->id > 0 && $user->type_user_id == Help::$USER_CLIENT) {
                if ($user->statut == Help::$STATUT_ACTIF) {
                    if (Help::HashVerifier($password, $user->password)) {

                        $prods = Produit::liste(null, 10);
                        foreach ($prods as $key => $p) {
                            $prods[$key]->images = ImageProduit::liste($p->id);
                        }

                        $client = Client::lireSurUser($user->id);

                        $retour->code = 200;
                        $retour->token = Crypt::encryptString($user->id);
                        $retour->type = $user->type_user_id;
                        $retour->nom = $user->nom_prenoms;
                        $retour->photo = Help::urlFichier($user->photo);
                        $retour->code_parrain = $client->type_client;
                        $retour->cat = $client->client_a_terme;
                        $retour->configs = [
                            'categories' => Categorie::liste(5),
                            'bannieres' => Banniere::liste(),
                            'produits' => $prods,
                            'mode_paiements' => ModePaiement::liste(),
                            'type_livraisons' => TypeLivraison::liste(),
                            'unites' => UniteProduit::liste(),
                            'pays' => Pays::liste(),
                            'villes' => Ville::liste(),
                            'type_users' => TypeUser::liste(),
                            'regions' => DB::table('regions')->get(),
                            'url_fichier' => "",
                        ];
                        $retour->message = "Login successful";
                    } else {
                        $retour->code = 405;
                        $retour->message = 'Login ou mot de passe incorrecte code: 405';
                    }
                } else if ($user->statut == Help::$STATUT_INACTIF) {
                    $retour->code = 406;
                    $retour->message = 'Votre compte est bloqué';
                } else if ($user->statut == Help::$STATUT_DEMANDE_SUP) {
                    $retour->code = 407;
                    $retour->message = 'Vos avez demandé la suppression de votre compte';
                } else {
                    $retour->code = 408;
                    $retour->message = 'Votre compte est supprimé';
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

    public function modifierPass(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'new' => "required",
            'niveau' => "required",
        ]);

        $retour = new Retour();


        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                if ($request->niveau == 1) {
                    if (Help::HashVerifier($request->old, $user->password)) {
                        $user->password = Help::HashPassword($request->new);
                        $user->save();
                        $retour->code = 200;
                        $retour->message = 'Votre mot de passe a été mis à jour avec succès';
                    } else {
                        $retour->code = 405;
                        $retour->message = "Votre mot de passe actuel est incorrecte";
                    }
                } else {
                    $user->password = Help::HashPassword($request->new);
                    $user->save();
                    $retour->code = 200;
                    $retour->message = 'Votre mot de passe a été mis à jour avec succès';
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
                        $message = "Bonjour $user->nom_prenoms, Votre code de confirmation pour vous inscrire sur mon gravier est: $codeReset->code. Veuillez le saisir pour finaliser votre inscription.";
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

                // Anti brute-force : l'OTP ne fait que 4 chiffres (10 000 combinaisons).
                // On limite à 5 tentatives par compte et par fenêtre de 15 min, en plus
                // du throttle IP global. Sans ça, la prise de compte était réaliste.
                $rlKey = 'otp-verify:' . $user->id;
                if (RateLimiter::tooManyAttempts($rlKey, 5)) {
                    $seconds = RateLimiter::availableIn($rlKey);
                    $retour->code = 429;
                    $retour->message = 'Trop de tentatives. Réessayez dans ' . ceil($seconds / 60) . ' minute(s).';
                    return response()->json($retour);
                }

                $codeReset = CodeReset::lireSurUser($user->id, $niveau == 1 ? Help::$CODE_INSCRIPTION : Help::$CODE_PASS_OUBLIE, false);
                if ($codeReset->id > 0) {

                    // Expiration RÉELLEMENT vérifiée (la colonne existait mais n'était
                    // jamais contrôlée -> un OTP restait valable indéfiniment).
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

                        // Tentative réussie : on réinitialise le compteur.
                        RateLimiter::clear($rlKey);

                        if ($niveau == 1) {
                            //On active le compte du client
                            $user->statut = Help::$STATUT_ACTIF;
                            $user->save();

                            $client = Client::lireSurUser($user->id);
                            $client->statut = Help::$STATUT_ACTIF;
                            $client->save();

                            Mail::to($user->email)->send(new InscriptionEffectueeMail($user->nom_prenoms));

                        }

                        $codeReset->utilise = true;
                        $codeReset->save();

                        $retour->code = 200;
                        $retour->message = 'Ok';
                    } else {
                        // Tentative échouée : on incrémente le compteur (décroissance 15 min).
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

    public function infosUtilisateur(Request $request)
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
                $retour->code = 200;
                $user->photo = Help::urlFichier($user->photo);
                $retour->data = $user;
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

    public function editProfil(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'nom_prenoms' => "required",
            'contact' => "required",
            'pays_id' => "required",
            'ville_id' => "required",
            'adresse' => "required",
        ]);

        $retour = new Retour();

        try {

            $nom_prenoms = $request->nom_prenoms;
            $contact = $request->contact;
            $pays_id = $request->pays_id;
            $ville_id = $request->ville_id;
            $adresse = $request->adresse;
            $photo = $request->photo;

            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $user->nom_prenoms = $nom_prenoms;
                $user->contact = $contact;
                $user->pays_id = $pays_id;
                $user->ville_id = $ville_id;
                $user->adresse = $adresse;
                if ($photo != '' && $photo != 'null') {
                    $storedFilePath = "imageUser/$user->id.png";
                    Storage::disk("principal")->put($storedFilePath, base64_decode($photo));
                    $user->photo = $storedFilePath;
                }
                $user->save();

                $client = Client::lireSurUser($user->id);
                $client->nom = $nom_prenoms;
                $client->contact1 = $contact;
                $client->save();

                $user->photo = Help::urlFichier($user->photo);

                $retour->code = 200;
                $retour->data = $user;
                $retour->message = 'Vos informations on été mis à jour avec succès';
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

    public function supprimerCompteClient(Request $request)
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
                $user->statut = Help::$STATUT_DEMANDE_SUP;
                $user->save();
                $retour->code = 200;
                $retour->message = 'Demande de suppression de compte en cours de traitement';
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

    public function demandeClientATerme(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'objet' => "required",
            'description' => "required",
        ]);

        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);

                if ($client->client_a_terme == true) {
                    $retour->code = 405;
                    $retour->message = "Vous êtes déjà un client a terme";
                } else {
                    $demande = DemandeCompteClientATerme::lireSurClient($client->id);
                    if ($demande->id <= 0) {
                        // Documents justificatifs (mêmes clés que le formulaire web :
                        // rccm / bilan / piece_id / autre), envoyés en base64 par le mobile.
                        // Stockés sur le disque principal ; le web les retrouve via
                        // Client::resolveStoragePath (qui regarde aussi côté apigravier).
                        $docsPaths = [];
                        $extAutorisees = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                        $documents = $request->documents;
                        if (is_array($documents)) {
                            foreach (['rccm', 'bilan', 'piece_id', 'autre'] as $key) {
                                $doc = $documents[$key] ?? null;
                                if (is_array($doc) && !empty($doc['fichier'])) {
                                    $ext = strtolower((string) ($doc['extension'] ?? 'pdf'));
                                    if (!in_array($ext, $extAutorisees)) {
                                        $ext = 'pdf';
                                    }
                                    $contenu = base64_decode((string) $doc['fichier'], true);
                                    if ($contenu !== false && strlen($contenu) > 0) {
                                        $chemin = "demandes_client_terme/DCT-{$client->id}-{$key}-" . time() . ".{$ext}";
                                        Storage::disk('principal')->put($chemin, $contenu);
                                        $docsPaths[$key] = $chemin;
                                    }
                                }
                            }
                        }

                        $demande = new DemandeCompteClientATerme();
                        $demande->objet = $request->objet;
                        $demande->description = $request->description;
                        $demande->documents_path = !empty($docsPaths) ? json_encode($docsPaths) : null;
                        $demande->client_id = $client->id;
                        $demande->approuve = false;
                        $demande->user_id = $user->id;
                        $demande->statut = Help::$STATUT_ACTIF;
                        $demande->save();
                        $retour->code = 200;
                        $retour->message = "Votre demande a bien été envoyer. Vous serai notifier par mail du statut de votre demande.";
                    } else {
                        $retour->code = 406;
                        $retour->message = "Vous avez déjà une demande en attente effectué le " . $demande->created_at->format('d/m/Y à H:i:s');
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
            $retour->code = 500;
            $retour->message = $th->getMessage();
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
            $user = User::lireSurLogin($email);
            if ($user->id > 0 && $user->type_user_id == Help::$USER_CLIENT) {

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

                    $prods = Produit::liste(null, 10);
                    foreach ($prods as $key => $p) {
                        $prods[$key]->images = ImageProduit::liste($p->id);
                    }

                    $retour->code = 200;
                    $retour->token = Crypt::encryptString($user->id);
                    $retour->type = $user->type_user_id;
                    $retour->nom = $user->nom_prenoms;
                    $retour->photo = Help::urlFichier($user->photo);
                    $retour->code_parrain = "";
                    $retour->configs = [
                        'categories' => Categorie::liste(5),
                        'bannieres' => Banniere::liste(),
                        'produits' => $prods,
                        'mode_paiements' => ModePaiement::liste(),
                        'type_livraisons' => TypeLivraison::liste(),
                        'unites' => UniteProduit::liste(),
                        'pays' => Pays::liste(),
                        'villes' => Ville::liste(),
                        'type_users' => TypeUser::liste(),
                        'regions' => DB::table('regions')->get(),
                        'url_fichier' => "",
                    ];
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
            $retour->message = $th->getMessage()."\n".json_encode($th);
        }

        return response()->json($retour);
    }
}
