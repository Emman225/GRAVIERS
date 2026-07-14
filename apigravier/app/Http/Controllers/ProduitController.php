<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\Like;
use App\Models\User;
use App\Models\Client;
use App\Models\Produit;
use App\Models\NoteProduit;
use App\Models\ImageProduit;
use App\Models\PrixPersonnalise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ProduitController extends Controller
{
    public function listeProduit(Request $request)
    {
        $categories = $request->categories ?? [];
        $produits = $request->produits ?? [];
        $montants = $request->montants ?? [];
        $search = $request->search ?? null;

        $result = Produit::liste($search, 3000, $categories, $produits, $montants);

        // Ajouter les prix personnalisés si le client est connecté
        if ($request->has('access') && $request->access) {
            try {
                $idUsr = Crypt::decryptString($request->access);
                $user = User::lire($idUsr);
                if ($user->id > 0) {
                    $client = Client::lireSurUser($user->id);
                    if ($client && $client->id > 0) {
                        $prixPerso = PrixPersonnalise::listeSurClient($client->id);
                        if (!empty($prixPerso)) {
                            foreach ($result as $produit) {
                                if (isset($prixPerso[$produit->id])) {
                                    $produit->prix_personnalise = $prixPerso[$produit->id];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently ignore - continue without personalized prices
            }
        }

        return $result;
    }

    public function ajouterRetirerListeSouhait(Request $request, $id)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'niveau' => "required",
        ]);

        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);

                if ($request->niveau == 1) {
                    //Ajout
                    $like = Like::lireCle($client->id, $id);
                    if ($like->id > 0) {
                        $retour->code = 500;
                        $retour->message = 'Le produit existe déjà dans votre liste de souhait';
                    } else {
                        $like->produit_id = $id;
                        $like->client_id = $client->id;
                        $like->save();
                        $retour->code = 200;
                        $retour->message = 'Ajouté avec succès a votre liste de souhait';
                    }
                } else if ($request->niveau == 2) {
                    //Suppression
                    $like = Like::lireCle($client->id, $id);
                    if ($like->id > 0) {
                        $like->delete();
                        $prods = Like::liste($client->id);
                        $prixPerso = PrixPersonnalise::listeSurClient($client->id);
                        foreach ($prods as $key => $p) {
                            $prods[$key]->images = ImageProduit::liste($p->id);
                            if (isset($prixPerso[$p->id])) {
                                $prods[$key]->prix_personnalise = $prixPerso[$p->id];
                            }
                        }
                        $retour->data = $prods;
                        $retour->code = 200;
                        $retour->message = 'Retiré avec succès a votre liste de souhait';
                    } else {
                        $retour->code = 500;
                        $retour->message = 'Le produit n\'existe pas dans votre liste de souhait';
                    }
                } else {
                    //Listing
                    $prods = Like::liste($client->id);
                    $prixPerso = PrixPersonnalise::listeSurClient($client->id);
                    foreach ($prods as $key => $p) {
                        $prods[$key]->images = ImageProduit::liste($p->id);
                        if (isset($prixPerso[$p->id])) {
                            $prods[$key]->prix_personnalise = $prixPerso[$p->id];
                        }
                    }
                    $retour->data = $prods;
                    $retour->code = 200;
                    $retour->message = 'Ok';
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

    public function listeProduitSurCategorie(Request $request)
    {
        $categories = $request->categories ?? [];
        $produits = $request->produits ?? [];
        $montants = $request->montants ?? [];

        $result = Produit::listeSurCategorie(null, 3000, $categories, $produits, $montants);

        // Ajouter les prix personnalisés si le client est connecté
        if ($request->has('access') && $request->access) {
            try {
                $idUsr = Crypt::decryptString($request->access);
                $user = User::lire($idUsr);
                if ($user->id > 0) {
                    $client = Client::lireSurUser($user->id);
                    if ($client && $client->id > 0) {
                        $prixPerso = PrixPersonnalise::listeSurClient($client->id);
                        if (!empty($prixPerso)) {
                            foreach ($result as $produit) {
                                if (isset($prixPerso[$produit->id])) {
                                    $produit->prix_personnalise = $prixPerso[$produit->id];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Continue without personalized prices
            }
        }

        return $result;
    }

    public function enregistrerNoteProduit(Request $request)
    {

        Request()->validate([
            'access' => "required",
            'type' => "required",
            'note' => "required",
            'avis' => "required",
            'produit_id' => "required",
        ]);

        $retour = new Retour();

        try {

            $note = $request->note;
            $avisCh = $request->avis;
            $produit_id = $request->produit_id;

            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);
                $ok = false;

                foreach ($produit_id as $p) {
                    $avis = NoteProduit::lireCle($p, $client->id);
                    if ($avis->id <= 0) {

                        $meilleur = false;
                        $produit = Produit::lire($p);
                        if ($note > $produit->meilleur_note) {
                            $produit->meilleur_note = $note;
                            $produit->save();
                            $meilleur = true;
                        } else if ($note == $produit->meilleur_note) {
                            $meilleur = true;
                        }

                        $avis = new NoteProduit();
                        $avis->produit_id = $p;
                        $avis->client_id = $client->id;
                        $avis->avis = $avisCh;
                        $avis->note = $note;
                        $avis->statut = $meilleur == true ? Help::$STATUT_ACTIF : Help::$STATUT_INACTIF;
                        $avis->save();

                        $ok = true;
                    } else {
                        $ok = false;
                    }
                }

                if ($ok == true) {
                    $retour->code = 200;
                    $retour->message = 'Votre avis a été enregistré avec succès';
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez déjà donné votre avis sur ce produit';
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
}
