<?php

namespace App\Http\Controllers;

use Retour;
use App\Models\User;
use App\Models\Ville;
use App\Models\Client;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\AdresseLivraison;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class AdresseController extends Controller
{
    public function chargerAdresses(Request $request)
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
                $retour->code = 200;
                $retour->message = 'ok';
                $config = Configuration::find(1);
                $retour->tva = ($client->applique_tva == true && $config) ? $config->tva : 0;
                $retour->nombrePoint = $client->point;
                $retour->montantPoint = $config ? $config->montant_point : 0;
                $retour->data = AdresseLivraison::liste($client->id);
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

    public function editerAdresse(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'adresse' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $retour->code = 200;
                $retour->message = 'ok';
                $adresse = $request->adresse;
                $client = Client::lireSurUser($user->id);

                $adds = AdresseLivraison::liste($client->id);
                if (count($adds) <= 0) {
                    $adresse['defaut'] = true;
                } else {
                    $adresse['defaut'] = false;
                }
                $adresse['statut'] = 1;
                $adresse['client_id'] = $client->id;
                $retour->data = AdresseLivraison::enregistrer($adresse);
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur '.$idUsr;
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

    public function listeVilles(Request $request)
    {
        $retour = new Retour();
        try {
            $retour->code = 200;
            $retour->message = 'ok';
            $retour->data = Ville::liste($request->pays_id);
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

}
