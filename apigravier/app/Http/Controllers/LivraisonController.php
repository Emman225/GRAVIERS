<?php

namespace App\Http\Controllers;

use Help;
use Retour;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Livraison;
use App\Models\ModePaiement;
use Illuminate\Http\Request;
use App\Models\TvaCommande;
use App\Models\CoutLivraison;
use App\Models\Configuration;
use App\Models\TypeLivraison;
use App\Models\DetailCommande;
use App\Models\DetailLocation;
use App\Models\AdresseLivraison;
use App\Models\DemandeLivraison;
use App\Models\DetailsLivraison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class LivraisonController extends Controller
{
    public function listeLivraison(Request $request)
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
                if ($user->type_user_id == Help::$USER_CLIENT) {
                    $client = Client::lireSurUser($user->id);
                    $retour->data = Livraison::liste(null, $client->id);
                } else if ($user->type_user_id == Help::$USER_LIVREUR) {
                    $livreur = Livreur::lireSurUser($user->id);
                    $retour->data = Livraison::liste($livreur->id);
                }
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

    public function listeDemandeLivraison(Request $request)
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
                $retour->data = DemandeLivraison::liste($client->id);
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

    public function detailsLivraison(Request $request, $id)
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
                $livraison = Livraison::lire($id);

                // IMPORTANT : un modèle Eloquent VIDE (lire() qui ne trouve rien) se
                // sérialise en "[]" (tableau) et non "{}". Côté mobile, fromJson attend
                // un objet -> reçoit une liste -> exception -> écran en erreur/blocage.
                // On renvoie donc null quand la ligne n'existe pas (ex. livraison sans
                // detail_livraison_id), ce que le mobile gère déjà (test != null).
                $ligneLivraison = $livraison->detail_livraison_id
                    ? DetailsLivraison::lire($livraison->detail_livraison_id)
                    : null;
                if (!($ligneLivraison->id ?? null)) {
                    $ligneLivraison = null;
                }

                if ($livraison->provenance == Help::$LIVRAISON) {
                    // DEMANDE DE LIVRAISON : la marchandise appartient au client, elle
                    // n'existe pas au catalogue. Il n'y a donc ni produit ni ligne de
                    // commande — la désignation et l'unité sont saisies en texte libre
                    // sur detail_livraison.
                    //
                    // Ce cas n'était pas prévu : on lisait quand même une ligne de
                    // COMMANDE, sur un detail_commande_id qui ne pointe sur rien.
                    // L'application livreur recevait donc une ligne vide et affichait
                    // « Article: null » et « Qte à livrer: 1.0 null ».
                    //
                    // On lui rend les mêmes CLÉS que pour une vente (nom, unite, qte...)
                    // afin que les applications DÉJÀ INSTALLÉES affichent correctement,
                    // sans nouvelle version à distribuer.
                    // « qte » ET « prix » sont OBLIGATOIRES : l'application les lit avec
                    // double.parse(json[...].toString()), sans protection. Une clé
                    // absente donnerait double.parse('null') -> exception, et l'écran
                    // tomberait en erreur au lieu de s'afficher. Le prix n'a pas de sens
                    // pour du transport — la marchandise n'est pas vendue — d'où 0.
                    $ligneCommande = $ligneLivraison ? (object) [
                        'id'             => $ligneLivraison->id,
                        'qte'            => (float) ($ligneLivraison->qte ?? 0),
                        'prix'           => 0,
                        'nom'            => $ligneLivraison->nom_produit,
                        'unite'          => $ligneLivraison->unite,
                        'reference'      => $ligneLivraison->numero ?? null,
                        'description'    => $ligneLivraison->nom_produit,
                        'etat_livraison' => $ligneLivraison->etat_livraison,
                        'statut'         => $ligneLivraison->statut,
                    ] : null;
                } else {
                    $ligneCommande = $livraison->provenance == Help::$LOCATION
                        ? DetailLocation::lire($livraison->detail_commande_id)
                        : DetailCommande::lire($livraison->detail_commande_id);
                    if (!($ligneCommande->id ?? null)) {
                        $ligneCommande = null;
                    }
                }

                $retour->data = [
                    "ligneCommande"  => $ligneCommande,
                    "ligneLivraison" => $ligneLivraison,
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

    public function detailsDemandeLivraison(Request $request, $id)
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
                $lignes = DetailsLivraison::liste(null, $id);
                // LE CODE DE LIVRAISON DE CHAQUE LIGNE (10/09/2026), comme sur le
                // détail d'une commande : le client le remet au livreur. Une
                // demande de livraison ne produit pas de bon d'enlèvement. Une
                // course se rattache à sa ligne par livraison.detail_livraison_id.
                foreach ($lignes as $ligne) {
                    $ligne->codes = \Illuminate\Support\Facades\DB::table('livraison')
                        ->where('detail_livraison_id', $ligne->id)
                        ->where('provenance', Help::$LIVRAISON)
                        ->where('accepte', 1)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get(['numero as code_livraison'])
                        ->map(fn ($c) => ['code_livraison' => $c->code_livraison, 'code_enlevement' => null]);
                }
                $retour->data = $lignes;
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

    public function resumeDemandeLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'distance' => "required",
            'demande' => "required",
        ]);
        $retour = new Retour();

        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $montant = 0;
                foreach ($request->lignes as $l) {
                    $cout = CoutLivraison::lireSurCle($l['unite_id'], $l['qte'], $request->distance);
                    if ($cout->id > 0) {
                        $montant += $cout->prix_km;
                    }
                }

                // LA TVA SUR LE TRANSPORT EST UNE OPTION, désactivée par défaut.
                //
                // Le réglage se pose sur le site (Paramètres → Livraison) et vaut
                // pour LES DEUX CANAUX. C'est tout l'enjeu : une même course doit
                // coûter le même prix depuis le site et depuis le téléphone —
                // l'écart de 3 600 F entre les deux avait déjà fait l'objet d'un
                // arbitrage, il n'est pas question de le rouvrir par une option
                // que le mobile ignorerait.
                $montantTva = self::tvaSurTransport($montant);

                $depart = AdresseLivraison::lire($request->demande['adresseDepart']);
                $destination = AdresseLivraison::lire($request->demande['adresseDestination']);
                $typeLiv = TypeLivraison::lire($request->demande['typeLivraison']);
                $mode = ModePaiement::lire($request->demande['modePaiement']);
                $date = Help::formatterDate($request->demande['dateLivraison'], "Y-m-d", "d-m-Y");

                $retour->data = [
                    "distance" => $request->distance,
                    // Ce que le client paiera réellement. Sans TVA — le cas par
                    // défaut — c'est exactement le tarif de la grille.
                    "montant" => $montant + $montantTva,
                    "montant_ht" => $montant,
                    "tva" => $montantTva,
                    "depart" => $depart->affichage,
                    "destination" => $destination->affichage,
                    "type_livraison" => $typeLiv->libelle,
                    "mode_paiement" => $mode->libelle,
                    "date_livraison" => $date
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

    /**
     * LA TVA D'UNE COURSE, SELON L'OPTION.
     *
     * Le transport se facture hors taxe par défaut. Le responsable peut activer
     * la TVA depuis le site (Paramètres → Livraison) ; le réglage vaut alors
     * pour les demandes faites depuis le site COMME depuis l'application, sans
     * quoi la même course coûterait deux prix selon le canal.
     *
     * Le taux vient de la configuration, comme sur le site — le champ historique
     * `applique_tva` du client n'est pas consulté, la règle en vigueur étant que
     * la taxe s'applique à toutes les factures.
     */
    private static function tvaSurTransport(float $montantHt): float
    {
        $conf = Configuration::first();

        if ((int) ($conf->tva_transport ?? 0) !== 1) {
            return 0;
        }

        $taux = ($conf->tva === null || $conf->tva === '') ? 18 : (float) $conf->tva;

        return round($montantHt * $taux / 100);
    }

    /**
     * Message de refus si la demande de livraison depasse le credit disponible.
     *
     * MEME REGLE, MEMES CHIFFRES ET MEME FORMULATION que pour une commande
     * (CommandeController) et une location (LocationController) : le client
     * doit lire la meme explication quel que soit ce qu'il demande.
     *
     * Ce controle manquait ICI, et seulement ici. Une demande de livraison
     * engage pourtant le credit au meme titre : le camion part, le transport
     * est rendu, et le client a terme paiera plus tard. `encoursCredit()` la
     * compte d'ailleurs deja dans ce qui est du — elle amputait le plafond des
     * achats suivants sans jamais etre bornee elle-meme.
     *
     * @return string|null  null si la demande passe
     */
    private function refusPlafondCredit(?Client $client, float $montant): ?string
    {
        if (!$client || !$client->client_a_terme) {
            return null;
        }

        $disponible = $client->plafondDisponible();

        if ($disponible === null || $montant <= $disponible) {
            return null;
        }

        $format = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';

        return sprintf(
            "Cette demande de livraison de %s dépasse votre crédit disponible. "
            . "Plafond accordé : %s. Déjà engagé : %s. Reste disponible : %s. "
            . "Réglez une facture en cours ou réduisez votre demande pour continuer.",
            $format($montant),
            $format((float) $client->plafond_credit),
            $format($client->encoursCredit()),
            $format($disponible)
        );
    }

    public function enregistrerDemandeLivraison(Request $request)
    {
        Request()->validate([
            'access' => "required",
            'type' => "required",
            'lignes' => "required",
            'distance' => "required",
            'demande' => "required",
            'libelle' => "required",
        ]);
        $retour = new Retour();

        DB::beginTransaction();
        try {
            $idUsr = Crypt::decryptString($request->access);
            $user = User::lire($idUsr);
            if ($user->id > 0) {

                $client = Client::lireSurUser($user->id);

                // Les deux adresses étaient recopiées telles quelles depuis la
                // requête. Absentes, elles s'écrivaient en NULL — la colonne
                // l'autorise — et la demande partait sans lieu de prise en
                // charge ni destination : inexploitable par le gestionnaire, et
                // jusqu'à cette semaine capable de faire tomber sa page entière.
                //
                // On vérifie aussi qu'elles APPARTIENNENT au client qui
                // commande : rien n'empêchait sinon d'envoyer l'identifiant de
                // l'adresse d'un autre client, dont l'écran de traitement
                // afficherait alors le domicile.
                //
                // L'application, elle, ne propose que les adresses du client et
                // refuse déjà un champ vide : ce contrôle ne change donc rien au
                // parcours normal, il ferme la porte à ce qui le contourne.
                $priseEnCharge = AdresseLivraison::lire($request->demande['adresseDepart'] ?? null);
                $destination   = AdresseLivraison::lire($request->demande['adresseDestination'] ?? null);

                $adressesValides = $priseEnCharge->id > 0
                    && $destination->id > 0
                    && (int) $priseEnCharge->client_id === (int) $client->id
                    && (int) $destination->client_id === (int) $client->id;

                $montant = 0;
                $cleLaisse = [];
                foreach ($request->lignes as $key => $l) {
                    $cout = CoutLivraison::lireSurCle($l['unite_id'], $l['qte'], $request->distance);
                    if ($cout->id > 0) {
                        $montant += $cout->prix_km;
                    } else {
                        array_push($cleLaisse, $key);
                    }
                }

                // Même règle qu'au devis, recalculée ici : le montant qui engage
                // le client ne se déduit pas de ce que l'application a affiché.
                // TVA du transport propre au client (10/09/2026).
                $montantTva = Help::tvaTransportPour($client, (float) $montant);
                // AIRSI : sur le transport TTC ; le net à payer le comprend.
                $montantAirsi = Help::airsiPour($client, (float) $montant + $montantTva,
                    (float) $montant, Help::tauxTvaTransportClient($client) / 100);
                $montantAPayer = $montant + $montantTva + $montantAirsi;
                // Ce qui engage le crédit : le dû, moins l'avance disponible si
                // la demande se règle en agence (10/09/2026).
                $montantACredit = self::montantACredit($request, $client, (float) $montantAPayer);

                if (count($cleLaisse) == count($request->lignes)) {
                    $retour->code = 405;
                    $retour->message = "Contenu vide ou aucun cout de livraison défini";
                } else if (!$adressesValides) {
                    $retour->code = 405;
                    $retour->message = "Le lieu de prise en charge ou la destination est introuvable. Veuillez les sélectionner à nouveau.";
                } else if ($refus = $this->refusPlafondCredit($client, $montantACredit)) {
                    // Le montant retenu est celui qui ENGAGE le client — taxe
                    // comprise —, et il est recalcule ici : ce que l'application
                    // a affiche ne fait pas foi. Une avance disponible couvre
                    // d'abord une demande réglée en agence : seul le reliquat
                    // engage le crédit (même règle que la commande).
                    $retour->code = 405;
                    $retour->message = $refus;
                } else if (($modeDemande = ModePaiement::lire($request->demande['modePaiement'] ?? 0))
                    && $modeDemande->en_ligne == 1
                    && \App\Support\PlafondPaiementEnLigne::depasse($montantAPayer)) {
                    // LE PLAFOND DU PAIEMENT EN LIGNE — AVANT TOUTE ÉCRITURE.
                    //
                    // Le seuil vivait plus bas, écrit à la main, et il ne
                    // refusait rien : la passerelle était sautée et la demande
                    // s'enregistrait sans qu'un franc soit encaissé ni que le
                    // client soit prévenu. Il testait de surcroît
                    // « < 2 000 000 », quand la commande et la location
                    // testaient « <= » : le montant exact de deux millions
                    // était refusé ici et accepté ailleurs.
                    $retour->code = 400;
                    $retour->message = \App\Support\PlafondPaiementEnLigne::refus();
                } else {
                    $demande = new DemandeLivraison();
                    $demande->numero = Help::genererNumeroUnique('demande_livraison');
                    $demande->libelle = $request->libelle;
                    $demande->description = $request->demande['note'];
                    $demande->client_id = $client->id;
                    $demande->adresse_livraison_pec_id = $request->demande['adresseDepart'];
                    $demande->adresse_livraison_dest_id = $request->demande['adresseDestination'];
                    $demande->montantTotal = $montant;
                    $demande->etat_commande = Help::$COMMANDE_EN_ATTENTE;
                    $demande->date_livraison = $request->demande['dateLivraison'];
                    $demande->remise = 0;
                    // AIRSI figé sur la demande (10/09/2026).
                    $demande->airsi = $montantAirsi;
                    $demande->statut = Help::$STATUT_ACTIF;
                    $demande->mode_paiement_id = $request->demande['modePaiement'];
                    $demande->type_livraison_id = $request->demande['typeLivraison'];
                    $demande->save();

                    // `montantTotal` reste le HORS TAXE, comme sur le site : la
                    // taxe vit dans tva_commande, et c'est la facture qui les
                    // additionne. Le filtre type_affaire est vital — une
                    // commande, une location et une demande peuvent porter le
                    // même identifiant dans cette table.
                    if ($montantTva > 0) {
                        $mtva = new TvaCommande();
                        $mtva->client_id = $client->id;
                        $mtva->commande_id = $demande->id;
                        $mtva->montant = $montantTva;
                        $mtva->type_affaire = Help::$LIVRAISON;
                        $mtva->statut = Help::$STATUT_ACTIF;
                        $mtva->save();
                    }

                    foreach ($request->lignes as $key => $l) {
                        if (!in_array($key, $cleLaisse)) {
                            $ligne = new DetailsLivraison();
                            $ligne->nom_produit = $l['produit'];
                            $ligne->qte = $l['qte'];
                            $ligne->unite = $l['unite'];
                            $ligne->description = "";
                            $ligne->demande_livraison_id = $demande->id;
                            $ligne->etat_livraison = Help::$LIVRAISON_EN_ATTENTE;
                            $ligne->statut = Help::$STATUT_ACTIF;
                            $ligne->unite_produit_id = $l['unite_id'];
                            $ligne->save();
                        }
                    }

                    //On vas payé l'apporteur d'aff
                    // if ($client->parrain_id > 0) {
                    //     $this->payerApporteurAffaire($client->parrain_id, $demande->id, $demande->montant_total, Help::$LIVRAISON);
                    // }

                    $retour->code = 200;
                    $retour->message = "Votre demande de livraison a bien été prise en compte.\n Nous vous contacterons très bientôt.";

                    // $paiement = new Paiement();
                    // $paiement->client_id = $client->id;
                    // $paiement->devis_id = 0;
                    // $paiement->service_id = $demande->id;
                    // $paiement->service = Help::$LIVRAISON;
                    // $paiement->code = $demande->numero;
                    // $paiement->libelle = "Paiement demande de livraison DALAKOUN";
                    // $paiement->montant_total = $montant;
                    // $paiement->montant_restant = 0;
                    // $paiement->statut = Help::$STATUT_INACTIF;
                    // $paiement->save();

                    $ret = array();
                    // Le paiement en ligne suit le MODE CHOISI. Deux défauts se
                    // superposaient ici : le client à terme en était exclu quoi qu'il
                    // choisisse — sa demande s'enregistrait sans qu'aucun règlement ne
                    // parte — et, à l'inverse, le mode n'était jamais consulté pour les
                    // autres : la passerelle s'ouvrait même sur « Paiement en agence ».
                    //
                    // Même règle que le site et que les commandes : instrument en ligne
                    // (en_ligne = 1) et montant sous le plafond de la passerelle.
                    $modeLiv = ModePaiement::lire($request->demande['modePaiement'] ?? 0);

                    // Le client qui a choisi le guichet doit savoir ce qu'on
                    // attend de lui. Le message générique — « nous vous
                    // contacterons » — laissait croire que rien n'était dû, alors
                    // que la course se règle au comptoir avant l'enlèvement.
                    if (self::regleeEnAgence($modeLiv)) {
                        // AVANCE DU CLIENT (10/09/2026) : une demande réglée « en
                        // agence » s'impute d'elle-même sur les avances
                        // disponibles, comme une commande. Le montant à
                        // présenter au guichet est ce qui reste après l'avance.
                        $imputationAvance = \App\Services\Avances::imputerSurDemandeLivraison($demande, $client);
                        $resteGuichet = ($imputationAvance['impute'] ?? 0) >= 1
                            ? (float) $imputationAvance['reste']
                            : (float) $montantAPayer;

                        $retour->message = "Votre demande de livraison a bien été prise en compte.
";
                        if ($resteGuichet >= 1) {
                            $retour->message .= "Présentez le numéro " . $demande->numero . " à nos guichets pour régler "
                                . Help::formatNombre($resteGuichet, true) . ".";
                        }
                        if (($imputationAvance['impute'] ?? 0) >= 1) {
                            $retour->avance_imputee = $imputationAvance['impute'];
                            $retour->reste_a_regler = $imputationAvance['reste'];
                            $retour->message .= \App\Services\Avances::messageImputation($imputationAvance, Help::$LIVRAISON);
                        }
                    }

                    // Le plafond a ete oppose au client plus haut, avant toute
                    // ecriture : ici il ne reste que le caractere du mode.
                    if ($modeLiv->en_ligne == 1) {
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
                                // TTC : on encaisse ce que le client doit, taxe
                                // comprise le cas échéant.
                                'montant' => ceil($montantAPayer),
                                'lib_order' => "Paiement demande de livraison DALAKOUN",
                                'Url_Retour' => Help::urlPaiement(route("ouvreApp", ['codePaiement' => $codePaiement])),
                                'Url_Callback' => Help::urlPaiement(route('callBackPaiement')),
                            ],
                            $demande->numero,
                            $codePaiement,
                            $client,
                            $montantAPayer,
                            $demande->mode_paiement_id,
                            $demande->id,
                            Help::$LIVRAISON
                        );
                        if ($ret['code'] == 200) {
                            $retour->code = 201;
                            $retour->message = $ret['message'];
                        } else {
                            $retour->code = $ret['code'];
                            $retour->message = $ret['message'];
                        }
                    }

                    DB::commit();
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
    /** Le mode choisi est-il le règlement au guichet (« Paiement en agence ») ? */
    private static function regleeEnAgence($mode): bool
    {
        // MÊME RÈGLE QUE LE SITE (11/09/2026) : tout mode HORS LIGNE se règle au
        // guichet — Espèces, Chèque, Virement, Carte, « Paiement en agence ».
        // Le test portait sur le mot « agence » dans le libellé : une demande
        // réglée « Espèces » depuis l'application n'imputait pas l'avance du
        // client et ne lui disait pas quoi présenter au guichet.
        return $mode && $mode->id > 0 && (int) ($mode->en_ligne ?? 0) === 0;
    }

    /**
     * Ce qui engage le crédit du client : le dû, moins l'avance disponible si
     * la demande se règle en agence (seul ce mode consomme l'avance).
     */
    private static function montantACredit(Request $request, $client, float $montantAPayer): float
    {
        $mode = ModePaiement::lire($request->demande['modePaiement'] ?? 0);

        return self::regleeEnAgence($mode)
            ? max(0, $montantAPayer - \App\Services\Avances::soldeDisponible($client))
            : $montantAPayer;
    }
}
