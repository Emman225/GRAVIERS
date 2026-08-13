<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Devis;
use App\Models\Ville;
use App\Models\Client;
use App\Models\BlClient;
use App\Models\Commande;
use App\Models\Location;
use App\Models\Reduction;
use App\Models\DetailDevis;
use App\Models\TvaCommande;
use Illuminate\Http\Request;
use App\Models\Configuration;
use App\Services\CalculMontant;
use App\Models\AdresseLivraison;
use App\Models\CoutLivraison;
// Casse EXACTE du fichier app/Models/PrixPersonnalise.php (obligatoire sous Linux).
use App\Models\PrixPersonnalise;
use App\Models\Produit;
use App\Models\RetourProduit;
use App\Models\DetailCommande;
use App\Mail\EnvoieCommandeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\PreuveOperationBanque;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\DemandeAnnulationCommande;
use Illuminate\Validation\ValidationException;

class CommandeController extends Controller
{
    public function listeCommande(Request $request)
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
                $retour->data = [
                    'commande' => Commande::liste($client->id),
                    'location' => Location::liste($client->id),
                ];
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function listeDevis(Request $request)
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
                // « statut » facultatif : absent, on renvoie les devis encore
                // ouverts, comme l'ont toujours fait les applications installées.
                // L'application récente demande le statut 2 pour l'historique des
                // devis transformés en commande.
                $devs = Devis::liste($client->id, $request->statut);
                foreach ($devs as $d) {
                    $d->date_devis = $d->created_at->format("d/m/Y H:i:s");
                }
                $retour->data = $devs;
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function enregistrerDevis(Request $request)
    {
        Request()->validate([
            "access" => "required",
            "type" => "required",
            "libelle" => "required",
            "montantHt" => "required",
            "montantTva" => "required",
            "meFaireLivre" => "required",
            "lignes" => "required | array",
            "total" => "required",
            "typeLivraison" => "required",
            "service" => "required",
            "long" => "required",
            "lat" => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                // Même garde qu'à l'enregistrement d'une commande : tous les produits
                // doivent exister. Elle manquait ici, si bien qu'un identifiant erroné
                // faisait remonter la violation de clé étrangère TELLE QUELLE jusqu'à
                // l'écran du client — nom de la base, de la table et de la contrainte
                // compris. Illisible pour lui, et inutilement bavard.
                if ($refus = $this->refusProduitsInconnus($request->lignes)) {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = $refus;
                    return response()->json($retour);
                }

                $client = Client::lireSurUser($user->id);
                $ville = Ville::lire($user->ville_id);

                $devis = new Devis();
                $devis->numero = Help::genererNumeroUnique('devis');
                $devis->client_id = $client->id;
                $devis->montant = $request->total;
                $devis->libelle = $request->libelle;
                $devis->statut = Help::$STATUT_ACTIF;
                $devis->tva = $request->montantTva;
                $devis->cout_livraison = ($request->meFaireLivre == true || $request->meFaireLivre == 1) ? $request->coutLivraison : 0;
                $devis->cout_reduction = $request->coutReduction;
                $devis->montant_ht = $request->montantHt;
                $devis->mode_paiement_id = $request->moyenPaiement;
                $devis->type_livraison_id = $request->typeLivraison;
                $devis->adresse_livraison_id = $request->adresseLivraison;
                $devis->date_livraison = $request->dateLivraison;
                $devis->service = is_numeric($request->service) ? $request->service : ($request->service == 'VENTE' ? 1 : 2);
                if ($devis->save()) {
                    // Le coût de livraison TOTAL du devis (client-envoyé depuis le résumé,
                    // désormais aligné sur le web) est réparti proportionnellement à la
                    // quantité sur chaque ligne — pour que la somme des lignes = le total.
                    $livTotal = ($request->meFaireLivre == true || $request->meFaireLivre == 1) ? (float) $request->coutLivraison : 0;
                    $qteTotaleDevis = 0;
                    foreach ($request->lignes as $l) { $qteTotaleDevis += (float) $l[('qte')]; }
                    foreach ($request->lignes as $l) {

                        $cl = ($qteTotaleDevis > 0) ? ((float) $l[('qte')] / $qteTotaleDevis) * $livTotal : 0;

                        $ligne = new DetailDevis();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->devis_id = $devis->id;
                        $ligne->qte = $l['qte'];
                        $ligne->prix = $l['prix'];
                        $ligne->statut = Help::$STATUT_ACTIF;
                        $ligne->cout_livraison = $cl;
                        $ligne->debut_location = $l['dateDebut'];
                        $ligne->fin_location = $l['dateDeFin'];
                        $ligne->nbre_jour_location = $l['nbreJours'];
                        $ligne->save();
                    }
                }
                $retour->code = 200;
                $retour->message = 'Votre devis a été enregistré avec succès';
                DB::commit();
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
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
     * Montant DÉFINITIF calculé par le serveur, sans rien enregistrer.
     *
     * L'application interroge ce point d'entrée depuis l'écran de résumé, avec
     * exactement les données qu'elle s'apprête à envoyer. Elle affiche ensuite le
     * montant renvoyé : le client ne peut donc plus être prélevé d'une somme
     * différente de celle qu'il a vue. Le calcul est celui de CalculMontant, le
     * même que celui appliqué à l'enregistrement — aucune divergence possible.
     */
    public function verifierMontant(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type'   => "required",
            'lignes' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user  = User::lire($idUsr);

            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);

                $calcul = CalculMontant::pour($user, $client, [
                    'lignes'       => $request->lignes,
                    'adresse'      => $request->adresse,
                    'meFaireLivre' => $request->meFaireLivre,
                    'long'         => $request->long,
                    'lat'          => $request->lat,
                    'reduction'    => $request->reduction,
                    'pointUtilise' => $request->pointUtilise,
                ], $request->service == Help::$LOCATION || $request->estLocation == true);

                $retour->code = 200;
                $retour->data = [
                    'montant_ht'   => round($calcul['ht']),
                    'montant_tva'  => round($calcul['tva']),
                    'livraison'    => round($calcul['livraison']),
                    'remise'       => round($calcul['remise']),
                    'total'        => $calcul['total'],
                ];
                $retour->message = 'ok';
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function resumeCommande(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'total' => "required",
            'remise' => "required",
            'montantTva' => "required",
            'long' => "required",
            'lat' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $ville = Ville::lire($user->ville_id);

                // MÊME calcul que le web : UN SEUL coût de livraison pour toute la
                // commande (km × prixKm), réparti proportionnellement à la quantité sur
                // chaque ligne — au lieu d'additionner un coût par ligne (qui sur-comptait
                // pour les commandes multi-produits).
                $montant = 0;
                $lignes = $request->lignes;
                $qteTotale = 0;
                foreach ($lignes as $l) { $qteTotale += (float) $l[('qte')]; }
                if ($qteTotale > 0) {
                    $montant = CoutLivraison::calculer($request->long, $request->lat, $ville->region_id, $qteTotale);
                    foreach ($lignes as $key => $l) {
                        $lignes[$key]['livraison'] = ((float) $l[('qte')] / $qteTotale) * $montant;
                    }
                }

                $totalTTC = $request->total;
                $newTotal = $totalTTC + $montant;

                $retour->code = 200;
                $retour->data = [
                    "montant" => $montant,
                    "lignes" => $lignes,
                ];
                $retour->message = "Vous êtes sur le point de valider une commande d'une total de " .
                    Help::formatNombre($newTotal, true) . "\n\nMontant TTC: " . Help::formatNombre($totalTTC, true)
                    . " \nCout de livraison: " . Help::formatNombre($montant, true) . "\n\nVoulez-vous finaliser la commande ?";

                DB::commit();
            } else {
                $retour->code = 404;
                $retour->message = 'Impossible de récupérer l\'utilisateur';
            }
        } catch (ValidationException $e) {
            // Ce bloc sera prioritaire pour les erreurs de validation
            $retour->code = 501;
            $retour->message = collect($e->errors())->flatten()->implode(" \n ");
        } catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite à la ligne ' . $th->getLine() . ' code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    /**
     * Message de refus si une ligne désigne un produit qui n'existe pas.
     *
     * La contrainte de clé étrangère de detail_devis / detail_commande rejette
     * l'insertion, mais son message est du SQL brut — il nomme la base, la table
     * et la contrainte. Affiché tel quel dans l'application, il n'apprend rien au
     * client et en dit trop à tout le monde.
     *
     * @return string|null  null si toutes les lignes désignent un produit connu
     */
    private function refusProduitsInconnus($lignes): ?string
    {
        $ids = collect($lignes)->pluck('produit_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return null;
        }

        if (Produit::whereIn('id', $ids)->count() >= $ids->count()) {
            return null;
        }

        return "Un ou plusieurs produits de votre panier ne sont plus disponibles. "
            . "Veuillez actualiser votre catalogue (fermez et rouvrez l'application) "
            . "puis réessayer.";
    }

    /**
     * Message de refus si la commande dépasse le crédit disponible du client.
     *
     * Porté du site (ClientController::refusPlafondCredit) : mêmes conditions,
     * mêmes chiffres, même formulation. Un client doit lire la même explication
     * qu'il commande depuis le site ou depuis l'application.
     *
     * @return string|null  null si la commande passe
     */
    private function refusPlafondCredit(?Client $client, float $montantCommande): ?string
    {
        if (!$client || !$client->client_a_terme) {
            return null;
        }

        $disponible = $client->plafondDisponible();

        if ($disponible === null || $montantCommande <= $disponible) {
            return null;
        }

        $format = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';

        return sprintf(
            "Cette commande de %s dépasse votre crédit disponible. "
            . "Plafond accordé : %s. Déjà engagé : %s. Reste disponible : %s. "
            . "Réglez une facture en cours ou réduisez votre commande pour continuer.",
            $format($montantCommande),
            $format((float) $client->plafond_credit),
            $format($client->encoursCredit()),
            $format($disponible)
        );
    }

    /**
     * La même commande vient-elle d'être enregistrée ?
     *
     * L'application poste sa commande : un double appui, un retour réseau tardif
     * ou une reprise automatique rejouent la requête. Deux commandes réelles
     * naissaient alors d'un seul achat.
     *
     * On rapproche sur le client, le montant et le nombre de lignes, dans une
     * fenêtre de deux minutes. Assez court pour qu'une commande volontairement
     * identique passée plus tard reste possible — un client peut légitimement
     * recommander la même chose — et assez large pour couvrir une reprise réseau.
     */
    private function commandeIdentiqueRecente(Client $client, float $total, int $nbLignes): ?Commande
    {
        $recentes = Commande::where('client_id', $client->id)
            ->where('etat_commande', '!=', 'ANNULEE')
            ->where('created_at', '>=', now()->subMinutes(2))
            ->orderByDesc('id')
            ->get();

        foreach ($recentes as $commande) {
            $memeMontant = abs((float) $commande->montant_total - $total) < 1;
            $memNombreDeLignes = $commande->detailCommande->count() === $nbLignes;

            if ($memeMontant && $memNombreDeLignes) {
                return $commande;
            }
        }

        return null;
    }

    public function enregistrerCommande(Request $request)
    {
        //   'adresse': addr.id,
        //   'mode_paiement': mode,
        //   'moyen_paiement': mp.id,
        //   'note': data[2],
        //   'date_livraison': data[3],
        //   'type_livraison': tl.id,
        //   'numero_bc': data[5],
        //   'bc_file': bcByte == null ? null : base64Encode(bcByte),
        //   'total': getTotalAmount() + coutLivraison,
        //   "lignes": lignes,
        //   "reduction": reduction.id,
        //   "remise": coutReduction,
        //   "montantTva": montantTva,
        //   "montantLivraison": coutLivraison,
        //   "meFaireLivre": meFaireLivre,
        //   "pointUtilise": utiliserPoint == true ? nombrePoint : 0,
        //   'long': position?.longitude ?? 0,
        //   'lat': position?.latitude ?? 0,
        //   'banque': data[8],
        //   'numCompte': data[9],
        //   'refOperation': data[10],
        //   'dateOperation': data[11],
        //   'fichierVir': virByte == null ? null : base64Encode(virByte),
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'mode_paiement' => "required",
            'lignes' => "required",
            'total' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            $config = Configuration::find(1);
            if ($user->id > 0) {

                // Garde : tous les produits du panier doivent encore exister (l'app peut
                // avoir un catalogue en cache périmé -> id de produit disparu). Sinon la
                // contrainte de clé étrangère fait planter avec un message SQL brut (500).
                // On renvoie plutôt un message clair et on annule proprement.
                if ($refus = $this->refusProduitsInconnus($request->lignes)) {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = $refus;
                    return response()->json($retour);
                }

                $client = Client::lireSurUser($user->id);

                // =====================================================================
                // MONTANTS CALCULÉS PAR LE SERVEUR
                // ---------------------------------------------------------------------
                // Prix unitaires, TVA, remise et coût de livraison sont recalculés à
                // partir du catalogue, des prix personnalisés du client, de la
                // configuration et de l'ADRESSE DE LIVRAISON — jamais repris de
                // l'application. Le calcul vit dans App\Services\CalculMontant, utilisé
                // aussi par « verifier-montant » : le client voit donc AVANT de valider
                // exactement le montant qui lui sera prélevé.
                // Le serveur corrige, il ne refuse pas : tout écart est journalisé.
                // =====================================================================
                $calcul = CalculMontant::pour($user, $client, [
                    'lignes'       => $request->lignes,
                    'adresse'      => $request->adresse,
                    'meFaireLivre' => $request->meFaireLivre,
                    'long'         => $request->long,
                    'lat'          => $request->lat,
                    'reduction'    => $request->reduction,
                    'pointUtilise' => $request->pointUtilise,
                ], false);

                $lignes           = $calcul['lignes'];
                $montantLivraison = $calcul['livraison'];
                $tvaServeur       = $calcul['tva'];
                $remiseServeur    = $calcul['remise'];
                $totalServeur     = $calcul['total'];
                $pointsUtilises   = $calcul['points_utilises'];
                $reductionValide  = $calcul['reduction_valide'];

                if (abs($totalServeur - (float) $request->total) > 1 || !empty($calcul['ecarts'])) {
                    \Log::warning('Commande mobile — montants recalculés par le serveur', [
                        'client_id'        => $client->id,
                        'total_envoye'     => (float) $request->total,
                        'total_applique'   => $totalServeur,
                        'tva_envoyee'      => (float) ($request->montantTva ?? 0),
                        'tva_appliquee'    => $tvaServeur,
                        'remise_envoyee'   => (float) ($request->remise ?? 0),
                        'remise_appliquee' => $remiseServeur,
                        'ecarts'           => $calcul['ecarts'],
                    ]);
                }
                // PLAFOND DE CRÉDIT — même règle que sur le site.
                //
                // Un client à terme n'est JAMAIS envoyé à la passerelle depuis
                // l'application (voir plus bas : « if ($client->client_a_terme ==
                // false) ») : toutes ses commandes mobiles sont donc prises à crédit,
                // sans exception. La limite s'applique à chacune.
                //
                // Elle n'existait que côté web : un client à terme atteignant son
                // plafond n'avait qu'à ouvrir l'application pour continuer à commander.
                // Une limite contournable en changeant de canal n'est pas une limite.
                if ($refus = $this->refusPlafondCredit($client, $totalServeur)) {
                    DB::rollBack();
                    $retour->code = 403;
                    $retour->message = $refus;
                    return response()->json($retour);
                }

                // GARDE ANTI-DOUBLON : une commande identique vient-elle d'être
                // enregistrée ? Un double appui, ou un réseau qui fait rejouer la
                // requête, créait deux commandes réelles pour un seul achat — le
                // client était engagé deux fois, et le gestionnaire préparait deux
                // livraisons.
                if ($existante = $this->commandeIdentiqueRecente($client, $totalServeur, count($lignes))) {
                    DB::commit();
                    $retour->code = 200;
                    $retour->data = ['commande' => $existante->id, 'numero' => $existante->numero];
                    $retour->message = 'Votre commande a déjà été enregistrée.';
                    return response()->json($retour);
                }

                // Devis d'origine, quand la commande vient d'un devis repris dans le
                // panier. On le relit en base plutôt que de faire confiance à l'appel :
                // il doit exister, appartenir à CE client, et ne pas être déjà clos.
                $devisOrigine = $request->devis_id
                    ? Devis::where('id', $request->devis_id)
                        ->where('client_id', $client->id)
                        ->where('statut', Help::$STATUT_ACTIF)
                        ->first()
                    : null;

                $commande = new Commande();
                // Numéro vérifié dans devis ET commande : voir Help::genererNumeroCommande.
                $commande->numero = Help::genererNumeroCommande();
                // Le lien vers le devis était forcé à null : rien ne reliait la commande
                // à son devis, et celui-ci restait « en attente » indéfiniment dans
                // l'application. Le site, lui, fait ce rattachement depuis toujours.
                $commande->devis_id = $devisOrigine?->id;
                $commande->client_id = $client->id;
                $commande->adresse_livraison_id = $request->adresse;
                // NULL si aucun moyen en ligne choisi (ex. paiement en agence : le mobile
                // envoie 0) : la FK vers mode_paiement refuse 0 -> 500 à la création.
                $commande->mode_paiement_id = $request->moyen_paiement > 0 ? $request->moyen_paiement : null;
                $commande->date_commande = date("Y-m-d H:i:s");
                // Montant recalculé par le serveur (voir le bloc « CONTRÔLE SERVEUR
                // DES MONTANTS » ci-dessus), jamais celui envoyé par l'application.
                $commande->montant_total = $totalServeur;
                // Commande à payer EN LIGNE (mobile « En ligne » = mode 1, montant sous
                // le plafond passerelle) : créée « EN ATTENTE DE PAIEMENT » pour NE PAS
                // apparaître dans la file de traitement du gestionnaire tant que le
                // paiement n'est pas confirmé. Le callback la passe à « EN ATTENTE ».
                // (Parité avec le web ; mêmes conditions que le déclenchement du paiement
                //  en ligne plus bas : mode_paiement == 1 && total <= 2 000 000.)
                $commandePaieEnLigne = ($request->mode_paiement == 1 && $totalServeur <= 2000000);
                $commande->etat_commande = $commandePaieEnLigne ? Help::$COMMANDE_EN_ATTENTE_PAIEMENT : Help::$COMMANDE_EN_ATTENTE;
                $commande->statut = Help::$STATUT_ACTIF;
                $commande->note = $request->note;
                $commande->date_livraison = $request->date_livraison;
                $commande->type_livraison_id = $request->type_livraison;
                $commande->cout_livraison_client = $montantLivraison;
                $commande->est_livrable = $request->meFaireLivre;
                // Remise recalculée par le serveur (code promo revérifié + points
                // plafonnés au solde réel), pas celle annoncée par l'application.
                $commande->remise = $remiseServeur;

                // Le code promo n'est consommé QUE s'il a passé les contrôles serveur :
                // un code expiré ou déjà utilisé n'est plus « brûlé » au passage.
                if ($reductionValide) {
                    $reductionValide->est_utilise = true;
                    $reductionValide->statut = Help::$STATUT_INACTIF;
                    $reductionValide->save();
                }

                //On vas retirer les points utilisé du client
                if ($pointsUtilises > 0) {
                    // Solde plafonné plus haut : un client ne peut plus dépenser plus de
                    // points qu'il n'en possède (le solde devenait négatif).
                    $client->point -= $pointsUtilises;
                    $client->save();
                }

                if ($commande->save()) {

                    // Le devis est clos une fois la commande enregistrée. statut = 2 est
                    // la valeur que le site emploie déjà (ClientController), et la liste
                    // renvoyée à l'application ne retient que les devis en statut ACTIF :
                    // le devis transformé disparaît donc de « Mes devis enregistrés »,
                    // comme il disparaît de l'espace client du site.
                    if ($devisOrigine) {
                        $devisOrigine->statut = Help::$STATUT_INACTIF;
                        $devisOrigine->save();
                    }

                    // 'numero_bc'
                    // 'bc_file'
                    if ($request->numero_bc != '' && $request->bc_file != '') {
                        $bc = new BlClient();
                        $bc->numero = $request->numero_bc;
                        $bc->client_id = $client->id;
                        $bc->commande_id = $commande->id;
                        $bc->montant = $commande->montant_total;
                        $bc_file = $request->bc_file;
                        if ($bc_file != '' && $bc_file != 'null') {
                            $storedFilePath = "lesBons/$commande->id.png";
                            Storage::disk("principal")->put($storedFilePath, base64_decode($bc_file));
                            $bc->fichier = $storedFilePath;
                        }
                        $bc->statut = Help::$STATUT_ACTIF;
                        $bc->save();
                    }

                    // La ligne de TVA est créée SYSTÉMATIQUEMENT, à 0 si le client n'est
                    // pas assujetti. L'ancien « if (montantTva > 0) » laissait les
                    // commandes mobiles sans ligne tva_commande, contrairement aux
                    // commandes du site : c'est ce qui faisait tomber la page « Mon
                    // compte » en erreur 500 côté web.
                    $mtva = new TvaCommande();
                    $mtva->client_id = $client->id;
                    $mtva->commande_id = $commande->id;
                    // TVA recalculée par le serveur (taux de la configuration appliqué au
                    // HT réel) et non celle annoncée par l'application.
                    $mtva->montant = max(0, round($tvaServeur));
                    $mtva->type_affaire = Help::$VENTE;
                    $mtva->statut = Help::$STATUT_ACTIF;
                    $mtva->save();

                    foreach ($lignes as $l) {
                        $ligne = new DetailCommande();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->commande_id = $commande->id;
                        $ligne->qte = $l['qte'];
                        $ligne->prix = $l['prix'];
                        $ligne->statut = Help::$STATUT_ACTIF;
                        $ligne->cout_livraison = ($request->meFaireLivre == 1 || $request->meFaireLivre == true) ? $l['livraison'] : 0;
                        $ligne->save();
                    }

                    //On vas payé l'apporteur d'aff
                    // if ($client->parrain_id > 0) {
                    //     $this->payerApporteurAffaire($client->parrain_id, $commande->id, $commande->montant_total, Help::$VENTE);
                    // }

                    $retour->code = 200;
                    $retour->message = 'Commande effectuée avec succès nous vous contacterons dans quelque instant';

                    $ret = array();
                    // Le paiement en ligne repose sur le MODE CHOISI, plus sur le statut
                    // du client. Il était ici sauté pour tout client à terme : celui qui
                    // choisissait « En ligne » voyait sa commande enregistrée sans qu'aucune
                    // passerelle ne s'ouvre, alors que la commande, elle, était bien marquée
                    // « EN ATTENTE DE PAIEMENT » quelques lignes plus haut — donc en attente
                    // d'un règlement que rien n'avait initié.
                    //
                    // $commandePaieEnLigne est la MÊME variable qui a fixé cet état : les deux
                    // décisions ne peuvent plus diverger. Elle s'appuie sur le total calculé
                    // par le serveur, quand la condition remplacée testait montant_total, dont
                    // le sens diffère entre le web (HT) et le mobile (net).
                    if ($commandePaieEnLigne) {
                        //Paiement en ligne
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

                        $ret = PaiementController::initierPaiement(
                            [
                                'code_paiement' => $codePaiement,
                                // 'credential_id' => "",
                                'nom_usager' => $leNom,
                                'prenom_usager' => $lePrenom,
                                'telephone' => $client->contact1,
                                'email' => $user->email,
                                'libelle_article' => "Paiement IMLOD",
                                'quantite' => 1,
                                // Montant réellement prélevé : celui calculé par le
                                // serveur. C'est le point central de la faille :
                                // l'application pouvait sinon faire payer 100 F une
                                // commande de 1 000 000 F.
                                'montant' => ceil($totalServeur),
                                'lib_order' => "Paiement commande de produit IMLOD",
                                'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                            ],
                            $commande->numero,
                            $codePaiement,
                            $client,
                            // Montant enregistré dans le paiement : celui du serveur.
                            $totalServeur,
                            $request->mode_paiement,
                            $commande->id,
                            Help::$COMMANDE
                        );
                        if ($ret['code'] == 200) {
                            $retour->code = 201;
                            $retour->message = $ret['message'];
                        } else {
                            $retour->code = $ret['code'];
                            $retour->message = $ret['message'];
                        }
                    } else if ($request->mode_paiement == 2) {
                        //Paiement par virement
                        $preuve = new PreuveOperationBanque();
                        $preuve->client_id = $client->id;
                        $preuve->commande_id = $commande->id;
                        $preuve->reference = $request->refOperation;
                        $preuve->num_compte = $request->numCompte;
                        $preuve->banque = $request->banque;
                        $preuve->date_operation = $request->dateOperation;
                        $preuve->service = Help::$COMMANDE;

                        $storedFilePath = "preuveVirement/COM-$commande->id-1.png";
                        Storage::disk("principal")->put($storedFilePath, base64_decode($request->fichierVir));
                        $preuve->fichier = $storedFilePath;

                        $preuve->note_supp = $request->note;
                        $preuve->statut = Help::$STATUT_INACTIF;
                        $preuve->save();
                    }

                    DB::commit();

                    // Email de confirmation APRÈS commit et NON bloquant : un timeout
                    // SMTP (LWS) ne doit jamais faire échouer/annuler la commande déjà
                    // enregistrée par le client. cf. règle projet « emails non bloquants ».
                    try {
                        $com = Commande::lire($commande->id);
                        $lis = DetailCommande::liste(null, $commande->id);
                        Mail::to($user->email)->send(new EnvoieCommandeMail($com, $lis, $request->montantTva, $client->nom . ' ' . $client->prenom, $client->email, $client->contact1, Help::$VENTE));
                    } catch (\Throwable $mailEx) {
                        Log::error('Email commande non envoyé (commande ' . $commande->id . '): ' . $mailEx->getMessage());
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
        } catch (\Throwable $th) {
            DB::rollBack();
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite à la ligne ' . $th->getLine() . ' code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function detailsCommande(Request $request, $id)
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
                $commande = Commande::lire($id);

                // IDOR : ne renvoyer la commande que si elle appartient au client
                // authentifié. Sans ce contrôle, un client pouvait lire montant /
                // adresse / note de la commande d'un autre en passant un id arbitraire.
                if (!$commande || $commande->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 404;
                    $retour->message = 'Commande introuvable';
                    return response()->json($retour);
                }

                $retour->data = [
                    'client_a_terme' => $client->client_a_terme == true ? true : false,
                    'commande' => $commande,
                    'lignes' => DetailCommande::liste(null, $id, $client->id),
                ];
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function detailsDevis(Request $request, $id)
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
                $retour->data = DetailDevis::liste($client->id, $id);
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
        } catch (\Throwable $th) {
            $retour->code = 500;
            $retour->message = 'Une erreur s\'est produite code: 500 ' . $th->getMessage();
        }

        return response()->json($retour);
    }

    public function supprimerDevis(Request $request, $id)
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
                $devis = Devis::lire($id);

                // IDOR : sans ce contrôle, un client pouvait supprimer le devis d'un
                // autre client en passant un id arbitraire — le devis disparaissait de
                // l'application de la victime sans explication.
                if (!$devis || $devis->id <= 0 || $devis->client_id != $client->id) {
                    $retour->code = 404;
                    $retour->message = 'Devis introuvable';
                    return response()->json($retour);
                }

                $devis->statut = Help::$STATUT_INACTIF;
                $devis->save();

                $dets = DetailDevis::liste(null, $id);
                foreach ($dets as $d) {
                    $d->statut = Help::$STATUT_INACTIF;
                    $d->save();
                }

                $retour->code = 200;
                $retour->message = "Devis supprimé avec succès";
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

    public function annulerCommande(Request $request, $id)
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

                $commande = Commande::lire($id);
                $client = Client::lireSurUser($user->id);

                // PROPRIÉTÉ : la commande doit exister ET appartenir au client qui
                // appelle. Sans ce contrôle, n'importe quel utilisateur connecté
                // pouvait annuler la commande d'un autre en devinant son id — et
                // l'écran location de l'app (branché par erreur sur cet endpoint)
                // annulait la COMMANDE portant le même id que la location.
                if ($commande->id <= 0 || $client->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette commande est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                // GARDE-FOUS : l'annulation DIRECTE n'est autorisée que si la
                // commande n'est ni payée ni en cours de traitement/livraison.
                // Sinon, on transforme automatiquement en DEMANDE d'annulation
                // que l'admin devra approuver (remboursement/retour à gérer).
                $paye = \App\Models\LignePaiement::where('service', Help::$COMMANDE)
                    ->where('service_id', $commande->id)
                    ->where('statut', 1)
                    ->sum('montant');

                $detailIds = DetailCommande::where('commande_id', $commande->id)->pluck('id');
                $enTraitement = $commande->etat_commande === 'TERMINEE'
                    || ($detailIds->isNotEmpty() && \App\Models\Livraison::whereIn('detail_commande_id', $detailIds)
                            ->where('provenance', Help::$COMMANDE)->exists());

                if ($paye > 0 || $enTraitement) {
                    $demande = DemandeAnnulationCommande::lireSurCle($client->id, $commande->id, Help::$VENTE);
                    if ($demande->id <= 0) {
                        $demande = new DemandeAnnulationCommande();
                        $demande->client_id = $client->id;
                        $demande->user_id = $user->id; // colonne NOT NULL
                        $demande->commande_id = $commande->id;
                        $demande->motif = "Annulation demandée depuis le mobile (commande "
                            . ($paye > 0 ? "déjà payée" : "en cours de traitement") . ")";
                        $demande->est_traite = false;
                        $demande->statut = Help::$STATUT_ACTIF;
                        $demande->type_affaire = Help::$VENTE;
                        $demande->save();
                    }
                    $retour->code = 200;
                    $retour->message = $paye > 0
                        ? "Cette commande a déjà été payée : une demande d'annulation a été transmise à notre équipe (le remboursement sera traité après validation)."
                        : "Cette commande est déjà en cours de traitement : une demande d'annulation a été transmise à notre équipe pour validation.";
                } else {
                    $commande->etat_commande = 'ANNULEE';
                    $commande->statut = Help::$STATUT_INACTIF;
                    $commande->save();

                    $dets = DetailCommande::liste(null, $id);
                    foreach ($dets as $d) {
                        $d->statut = Help::$STATUT_INACTIF;
                        $d->save();
                    }

                    $retour->code = 200;
                    $retour->message = "Commande annulée avec succès";
                }
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

    public function demandeAnnulerCommande(Request $request, $id)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'motif' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);

                // PROPRIÉTÉ : même contrôle que annulerCommande — la demande ne peut
                // viser qu'une commande du client appelant.
                $commande = Commande::lire($id);
                if ($commande->id <= 0 || $client->id <= 0 || $commande->client_id != $client->id) {
                    $retour->code = 403;
                    $retour->message = "Cette commande est introuvable ou ne vous appartient pas.";
                    return response()->json($retour);
                }

                $demande = DemandeAnnulationCommande::lireSurCle($client->id, $id, Help::$VENTE);
                if ($demande->id <= 0) {
                    $demande = new DemandeAnnulationCommande();
                    $demande->client_id = $client->id;
                    $demande->user_id = $user->id; // colonne NOT NULL : sans elle, erreur 1364 -> la demande n'était JAMAIS enregistrée
                    $demande->commande_id = $id;
                    $demande->motif = $request->motif;
                    $demande->est_traite = false;
                    $demande->statut = Help::$STATUT_ACTIF;
                    $demande->type_affaire = Help::$VENTE;
                    $demande->save();
                    $retour->code = 200;
                    $retour->message = "Demande d'annulation de commande envoyée avec succès";
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez déjà une demande d\'annulation en cours de traitement pour cette commande';
                }
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

    public function verifierCodePromo(Request $request)
    {
        Request()->validate([
            // 'access' => "required",
            // 'type' => "required",
            'code' => "required",
        ]);
        $retour = new Retour();

        try {
            $reduction = Reduction::lireCode($request->code);
            if ($reduction->id > 0) {
                // Mêmes règles que le web (ClientController::appliquerCodePromo) : sans ces
                // contrôles le mobile ne testait QUE l'existence du code -> un code déjà
                // utilisé, désactivé ou expiré restait indéfiniment acceptable.
                $aujourdhui = date('Y-m-d');
                $motifRefus = null;

                if ($reduction->est_utilise == 1) {
                    $motifRefus = 'Ce code promo a déjà été utilisé.';
                } elseif (isset($reduction->statut) && $reduction->statut == 0) {
                    $motifRefus = "Ce code promo n'est plus actif.";
                } elseif (!empty($reduction->debut) && $aujourdhui < \Carbon\Carbon::parse($reduction->debut)->format('Y-m-d')) {
                    $motifRefus = "Ce code promo n'est pas encore valide (à partir du "
                        . \Carbon\Carbon::parse($reduction->debut)->format('d-m-Y') . ').';
                } elseif (!empty($reduction->fin) && $aujourdhui > \Carbon\Carbon::parse($reduction->fin)->format('Y-m-d')) {
                    $motifRefus = 'Ce code promo a expiré le '
                        . \Carbon\Carbon::parse($reduction->fin)->format('d-m-Y') . '.';
                }

                if ($motifRefus !== null) {
                    $retour->code = 405;
                    $retour->message = $motifRefus;
                } else {
                    $retour->data = $reduction;
                    $retour->code = 200;
                    $retour->message = "Code promo valide";
                }
            } else {
                $retour->code = 405;
                $retour->message = 'Code promo invalide';
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

    public function demandeRetourProduit(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'motif' => "required",
            'idLigne' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {
                $client = Client::lireSurUser($user->id);
                $ok = false;

                foreach ($request->idLigne as $r) {
                    $retourProd = RetourProduit::lireSurDetailCommande($r, $client->id);
                    if ($retourProd->id <= 0) {
                        $retourProd = new RetourProduit();
                        $retourProd->motif = $request->motif;
                        $retourProd->client_id = $client->id;
                        $retourProd->detail_commande_id = $r;
                        $retourProd->statut = Help::$STATUT_ACTIF;
                        $retourProd->date_retour = date('Y-m-d');
                        $retourProd->save();
                        $ok = true;
                    }
                }

                if ($ok == true) {
                    $retour->code = 200;
                    $retour->message = "Demande de retour produit enregistré avec succès";
                } else {
                    $retour->code = 405;
                    $retour->message = 'Vous avez une demande de retour pour ce produit en cours de traitement';
                }
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
}
