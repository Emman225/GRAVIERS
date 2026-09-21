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
                    // Les locations, avec l'état de leur livraison (10/09/2026) :
                    // « Livrée le … », « Retirée le … », « En livraison » — la
                    // location elle-même reste EN COURS jusqu'au retour du matériel.
                    'location' => Location::liste($client->id)->each(function ($l) {
                        $etat = Location::etatLivraison($l);
                        $l->etat_livraison_code    = $etat['code'] ?? null;
                        $l->etat_livraison_libelle = $etat['libelle'] ?? null;
                    }),
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

                // LE NUMÉRO DE BON DE COMMANDE INTERNE (09/09/2026) : obligatoire pour
                // une ENTREPRISE qui demande un devis de VENTE, comme pour la commande ;
                // l'application l'exige déjà à l'écran, ce contrôle-ci fait foi. Il est
                // figé sur le devis et reporté devant chaque désignation.
                $estVente = is_numeric($request->service) ? (int) $request->service === 1 : $request->service == 'VENTE';
                if ($client->type_client == Help::$ENTREPRISE && $estVente
                    && trim((string) $request->numero_bc) === '') {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = "Le numéro de bon de commande interne est obligatoire pour une entreprise : "
                        . "il sera reporté sur votre devis.";
                    return response()->json($retour);
                }

                $devis = new Devis();
                $devis->numero = Help::genererNumeroUnique('devis');
                $devis->client_id = $client->id;
                $devis->numero_bon_commande = trim((string) $request->numero_bc) ?: null;
                $devis->montant = $request->total;
                $devis->libelle = $request->libelle;
                $devis->statut = Help::$STATUT_ACTIF;
                $devis->tva = $request->montantTva;
                $devis->cout_livraison = ($request->meFaireLivre == true || $request->meFaireLivre == 1) ? $request->coutLivraison : 0;
                // TVA sur le transport (point 5), au taux du client, figée sur le devis.
                // TVA du transport propre au client (10/09/2026).
                $devis->tva_transport = Help::tvaTransportPour($client, (float) $devis->cout_livraison);
                // AIRSI figé sur le devis : HT net de remise + TVA.
                $devis->airsi = Help::airsiPour($client,
                    max(0, (float) $request->montantHt - (float) ($request->coutReduction ?? 0)) + (float) $request->montantTva,
                    max(0, (float) $request->montantHt - (float) ($request->coutReduction ?? 0)), Help::tauxTvaClient($client) / 100,
                    (float) $devis->cout_livraison, (float) $devis->tva_transport > 0 ? Help::tauxTvaTransportClient($client) / 100 : 0.0);
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

                    // Prix arrêtés par le SERVEUR, comme pour une commande : prix
                    // personnalisé du client s'il en a un, sinon prix catalogue.
                    // On reprenait ici le prix envoyé par l'application — un devis
                    // se transforme ensuite en commande, et ce prix aurait suivi
                    // jusqu'à la facture.
                    $prixPersoDevis = PrixPersonnalise::listeSurClient($client->id);
                    $catalogueDevis = Produit::prixCatalogue(
                        array_map(fn ($l) => $l['produit_id'] ?? null, $request->lignes)
                    );

                    foreach ($request->lignes as $l) {

                        $cl = ($qteTotaleDevis > 0) ? ((float) $l[('qte')] / $qteTotaleDevis) * $livTotal : 0;

                        $idProduitDevis = (int) ($l['produit_id'] ?? 0);
                        $prixDevis = (float) ($prixPersoDevis[$idProduitDevis]
                            ?? ($catalogueDevis[$idProduitDevis] ?? 0));

                        $ligne = new DetailDevis();
                        $ligne->produit_id = $l['produit_id'];
                        $ligne->devis_id = $devis->id;
                        $ligne->qte = $l['qte'];
                        $ligne->prix = $prixDevis;
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
                    'montant_ht'            => round($calcul['ht']),
                    'montant_tva'           => round($calcul['tva']),
                    'livraison'             => round($calcul['livraison']),
                    // TVA sur le transport (point 5) : l'application l'affiche
                    // sur une ligne à part, elle est déjà comprise dans `total`.
                    'montant_tva_transport' => round($calcul['tva_transport'] ?? 0),
                    // AIRSI (10/09/2026) : ligne « autres taxes », déjà comprise dans `total`.
                    'montant_airsi'         => round($calcul['airsi'] ?? 0),
                    'remise'                => round($calcul['remise']),
                    'total'                 => $calcul['total'],
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

                // LE BON DE COMMANDE D'UNE ENTREPRISE EST OBLIGATOIRE (07/09/2026).
                //
                // L'application l'exige déjà à l'écran ; ce contrôle-ci fait foi.
                // Il porte sur la VENTE — ce point d'entrée n'enregistre que des
                // ventes, les locations passent par LocationController.
                if ($client->type_client == Help::$ENTREPRISE
                    && trim((string) $request->numero_bc) === '') {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = "Le numéro de bon de commande est obligatoire pour une entreprise : "
                        . "il sera reporté sur votre facture.";
                    return response()->json($retour);
                }

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

                // LE PLAFOND DU PAIEMENT EN LIGNE — AVANT TOUTE ÉCRITURE.
                //
                // Demander « En ligne » au-dessus du plafond ne refusait rien :
                // la passerelle était simplement SAUTÉE et la commande entrait
                // dans la file du gestionnaire comme si elle devait être réglée
                // au comptoir — sans qu'un franc soit encaissé et sans que
                // l'application puisse le dire au client.
                //
                // L'application filtre déjà son menu au-dessus du plafond ; ce
                // refus protège les versions plus anciennes et tout appel qui
                // ne passerait pas par elle. Elle sait l'afficher : elle montre
                // `message` dès que `code` n'est pas 200.
                if ($request->mode_paiement == 1
                    && \App\Support\PlafondPaiementEnLigne::depasse($totalServeur)) {
                    DB::rollBack();
                    $retour->code = 400;
                    $retour->message = \App\Support\PlafondPaiementEnLigne::refus();
                    return response()->json($retour);
                }

                // UNE COMMANDE SANS ARTICLE N'EST PAS UNE COMMANDE.
                //
                // Une commande enregistrée avec son montant et AUCUNE ligne
                // s'affiche partout — espace client, application, listes du
                // gestionnaire — sans que personne puisse dire ce qui a été
                // acheté : son écran de détail s'ouvre blanc et son bon
                // s'imprime vide. On refuse plutôt que d'enregistrer une somme
                // sans contrepartie.
                if (empty($lignes)) {
                    DB::rollBack();
                    \Log::error('Commande mobile refusée : panier illisible', [
                        'client_id'     => $client->id,
                        'lignes_recues' => is_array($request->lignes)
                            ? count($request->lignes)
                            : gettype($request->lignes),
                        'total_envoye'  => (float) $request->total,
                    ]);
                    $retour->code = 400;
                    $retour->message = "Votre panier n'a pas pu être lu. Fermez et "
                        . "rouvrez l'application, reprenez vos articles, puis validez "
                        . "de nouveau.";
                    return response()->json($retour);
                }

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
                // Une avance disponible couvre d'abord la commande : seul le
                // reliquat engage le crédit (point 19, réponse Q2 du 07/09/2026).
                // Seul le « Paiement en agence » (mode 3) consomme l'avance.
                $montantACredit = (int) $request->mode_paiement === 3
                    ? max(0, $totalServeur - \App\Services\Avances::soldeDisponible($client))
                    : $totalServeur;
                if ($refus = $this->refusPlafondCredit($client, $montantACredit)) {
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
                // Le plafond a été opposé au client plus haut, avant toute
                // écriture : ici il ne reste que le mode choisi. Le seuil était
                // recopié dans les deux endroits, et la règle finissait par
                // diverger d'un point d'entrée à l'autre.
                $commandePaieEnLigne = ($request->mode_paiement == 1);
                $commande->etat_commande = $commandePaieEnLigne ? Help::$COMMANDE_EN_ATTENTE_PAIEMENT : Help::$COMMANDE_EN_ATTENTE;
                $commande->statut = Help::$STATUT_ACTIF;
                $commande->note = $request->note;
                $commande->date_livraison = $request->date_livraison;
                $commande->type_livraison_id = $request->type_livraison;
                $commande->cout_livraison_client = $montantLivraison;
                // TVA sur le transport (point 5), figée avec la commande.
                $commande->tva_transport = (float) ($calcul['tva_transport'] ?? 0);
                // AIRSI figé sur la commande (10/09/2026).
                $commande->airsi = (float) ($calcul['airsi'] ?? 0);
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
                    //
                    // MAIS SEULEMENT QUAND LA COMMANDE TIENT.
                    //
                    // Le devis était clos dès l'enregistrement, y compris quand
                    // la commande partait « EN ATTENTE DE PAIEMENT » : un client
                    // qui annulait son règlement sur la passerelle laissait
                    // derrière lui un devis annoncé « Commandé » alors que rien
                    // n'avait été encaissé. Même défaut que sur le site,
                    // constaté le 04/09/2026 sur le devis n° 429808.
                    //
                    // PaiementController le clôt à la confirmation du paiement.
                    if ($devisOrigine && !$commandePaieEnLigne) {
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

                    // CE QUI EST ENREGISTRÉ DOIT CORRESPONDRE À CE QUI A ÉTÉ ACHETÉ.
                    //
                    // On est encore dans la transaction : une ligne manquante
                    // annule TOUTE la commande, plutôt que d'en laisser une
                    // incomplète — le client la croirait passée.
                    $posees = DetailCommande::where('commande_id', $commande->id)->count();
                    if ($posees !== count($lignes)) {
                        DB::rollBack();
                        \Log::error('Commande mobile annulée : lignes incomplètes', [
                            'commande_id' => $commande->id,
                            'attendues'   => count($lignes),
                            'enregistrees'=> $posees,
                        ]);
                        $retour->code = 500;
                        $retour->message = "Votre commande n'a pas pu être enregistrée "
                            . "entièrement. Rien n'a été retenu : merci de la repasser.";
                        return response()->json($retour);
                    }

                    //On vas payé l'apporteur d'aff
                    // if ($client->parrain_id > 0) {
                    //     $this->payerApporteurAffaire($client->parrain_id, $commande->id, $commande->montant_total, Help::$VENTE);
                    // }

                    $retour->code = 200;
                    $retour->message = 'Commande effectuée avec succès nous vous contacterons dans quelque instant';

                    // LA PROFORMA PART AU CLIENT (lot 81, 15/09/2026) : le site produit
                    // le PDF et l'envoie (jeton interne). Une commande à payer en ligne
                    // recevra sa facture au paiement confirmé. Jamais bloquant.
                    if (!$commandePaieEnLigne) {
                        \App\Services\DocumentCommandeDistant::envoyerApresLaReponse($commande);
                    }

                    // AVANCE DU CLIENT (point 19, 07/09/2026) : une commande réglée
                    // « en agence » (mode 3) s'impute d'elle-même sur les avances
                    // disponibles, du dépôt le plus ancien au plus récent. Même
                    // règle que le site. Un mode en ligne n'y touche pas (Q5).
                    if ((int) $request->mode_paiement === 3) {
                        $imputationAvance = \App\Services\Avances::imputerSurCommande($commande, $client);
                        $retour->avance_imputee = $imputationAvance['impute'];
                        $retour->reste_a_regler = $imputationAvance['reste'];
                        $retour->message .= \App\Services\Avances::messageImputation($imputationAvance);
                    }

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
                                'libelle_article' => "Paiement DALAKOUN",
                                'quantite' => 1,
                                // Montant réellement prélevé : celui calculé par le
                                // serveur. C'est le point central de la faille :
                                // l'application pouvait sinon faire payer 100 F une
                                // commande de 1 000 000 F.
                                'montant' => ceil($totalServeur),
                                'lib_order' => "Paiement commande de produit DALAKOUN",
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
                        Mail::to($user->email)->send(new EnvoieCommandeMail($com, $lis, $request->montantTva, $client->display_name, $client->email, $client->contact1, Help::$VENTE));
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

                // LES CODES DE CHAQUE LIGNE (point 17, complété le 08/09/2026).
                //
                // Le client remet le code de livraison au livreur et le bon
                // d'enlèvement au fournisseur (ou au fournisseur seul, s'il
                // retire lui-même). L'application ne les montrait que dans la
                // liste des livraisons ; le détail de la commande les porte
                // désormais, ligne par ligne, pour les courses acceptées.
                $lignes = DetailCommande::liste(null, $id, $client->id, true);
                foreach ($lignes as $ligne) {
                    $ligne->codes = DB::table('livraison')
                        ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
                        ->where('livraison.detail_commande_id', $ligne->id)
                        ->where('livraison.accepte', 1)
                        ->whereNull('livraison.deleted_at')
                        ->orderBy('livraison.id')
                        ->get([
                            'livraison.numero as code_livraison',
                            'enlevement.code_enleve as code_enlevement',
                        ]);
                }

                $retour->data = [
                    'client_a_terme' => $client->client_a_terme == true ? true : false,
                    'commande' => $commande,
                    // Ecran de LECTURE : les lignes d'une commande annulee restent visibles.
                    'lignes' => $lignes,
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

                // MÊME RÈGLE QUE LE SITE (10/09/2026) : un devis se supprime tant
                // qu'il est en attente — ni transformé en commande (statut 2), ni
                // rattaché à une commande encore vivante. Une commande abandonnée
                // sur la passerelle ne retient pas le devis.
                if ((int) $devis->statut !== (int) Help::$STATUT_ACTIF) {
                    $retour->code = 400;
                    $retour->message = "Ce devis a déjà été transformé en commande : il ne peut plus être supprimé.";
                    return response()->json($retour);
                }
                $commandeVivante = Commande::where('devis_id', $devis->id)
                    ->whereNotIn('etat_commande', ['ANNULEE', Help::$COMMANDE_EN_ATTENTE_PAIEMENT])
                    ->orderByDesc('id')->first();
                if ($commandeVivante) {
                    $retour->code = 400;
                    $retour->message = "Ce devis est rattaché à la commande n° {$commandeVivante->numero} : il ne peut plus être supprimé.";
                    return response()->json($retour);
                }

                // MÊME ARCHIVAGE QUE LE SITE (Devis::supprimer) : statut inactif
                // ET soft delete. Sans le soft delete, Help::$STATUT_INACTIF (2)
                // étant aussi le statut des devis passés en commande, un devis
                // supprimé depuis l'application réapparaissait dans « Historique ».
                $devis->statut = Help::$STATUT_INACTIF;
                $devis->save();

                $dets = DetailDevis::liste(null, $id);
                foreach ($dets as $d) {
                    $d->statut = Help::$STATUT_INACTIF;
                    $d->save();
                }
                $devis->delete();

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
