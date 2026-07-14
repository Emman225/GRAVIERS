<?php

namespace App\Http\Controllers;

use Retour;
use App\Models\Pays;
use App\Models\User;
use App\Models\Ville;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Banniere;
use App\Models\TypeUser;
use App\Models\Categorie;
use App\Models\ImageProduit;
use App\Models\ModePaiement;
use App\Models\UniteProduit;
use App\Models\PrixPersonnalise;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Models\TypeLivraison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    public function chargerParametres(Request $request)
    {
        $prods = Produit::liste(null, 10);
        foreach ($prods as $key => $p) {
            $prods[$key]->images = ImageProduit::liste($p->id);
        }

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
                            foreach ($prods as $produit) {
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

        $retour = [
            'categories' => Categorie::liste(),
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
        return response()->json($retour);
    }

    /**
     * Endpoint pour fournir la configuration FNE aux applications mobiles
     */
    public function configurationFne(Request $request)
    {
        try {
            $config = Configuration::first();

            return response()->json([
                'code' => 200,
                'data' => [
                    'raison_sociale'    => $config->raison_sociale ?? '',
                    'ncc'               => $config->ncc ?? '',
                    'regime_imposition' => $config->regime_imposition ?? '',
                    'centre_impots'     => $config->centre_impots ?? '',
                    'rccm'              => $config->rccm ?? '',
                    'ref_bancaires'     => $config->ref_bancaires ?? '',
                    'cnps'              => $config->cnps ?? '',
                    'capital_social'    => $config->capital_social ?? '',
                    'adresse_siege'     => $config->adresse_siege ?? '',
                    'telephone'         => $config->telephone ?? '',
                    'email_entreprise'  => $config->email_entreprise ?? '',
                    'nom_etablissement' => $config->nom_etablissement ?? '',
                    'nom_pdv'           => $config->nom_pdv ?? '',
                    'tva'               => $config->tva ?? 0,
                    'devise'            => $config->devise ?? 'FCFA',
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 500,
                'message' => 'Erreur lors du chargement de la configuration FNE',
            ]);
        }
    }

    public function recupererMontantPoint(Request $request)
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
                $config = Configuration::find(1);
                $retour->tva = $client->applique_tva == true ? $config->tva : 0;
                $retour->nombrePoint = $client->point;
                $retour->montantPoint = $config->montant_point;
                $retour->devise = $config->devise;
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
}
