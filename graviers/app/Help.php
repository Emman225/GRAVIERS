<?php

// namespace App\Help;

use App\Models\Logs;
use App\Models\Client;
use App\Models\Facture;
use App\Models\Location;
use App\Models\Paiement;
use App\Models\Apporteur;
use App\Models\Reduction;
use Illuminate\Support\Carbon;
use App\Models\DetailLivraison;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Pagination\LengthAwarePaginator;


class Help
{
    public static $STATUT_ACTIF = 1;
    public static $STATUT_INACTIF = 2;

    /**
     * Convertit une URL locale en URL de production pour les callbacks de paiement.
     * PaySecure exige des URLs HTTPS accessibles depuis internet.
     */
    public static function urlPaiement(string $url): string
    {
        // Repli sur config('app.url') (= APP_URL) : env() renvoie null après
        // `php artisan config:cache`, ce qui ferait perdre l'override de base.
        $baseUrl = env('PAIEMENT_BASE_URL') ?: config('app.url');
        if ($baseUrl) {
            // Extraire la base de l'URL générée (ex: http://127.0.0.1:8000)
            $parsed = parse_url($url);
            $currentBase = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            $url = str_replace($currentBase, rtrim($baseUrl, '/'), $url);
        }
        return str_replace('http://', 'https://', $url);
    }


    public static $PARTICULIER = "PARTICULIER";
    public static $ENTREPRISE = "ENTREPRISE";

    public static $COMMANDE = "COMMANDE";
    public static $LIVRAISON = "LIVRAISON";

    public static $COMMANDE_EN_ATTENTE = "EN ATTENTE";
    public static $COMMANDE_EN_TRAITEMENT = "EN TRAITEMENT";
    public static $COMMANDE_TERMINE = "TERMINEE";
    // Commande créée mais dont le paiement en ligne n'est pas encore confirmé :
    // volontairement HORS de la file de traitement du gestionnaire (listeStatutCommande)
    // pour ne pas traiter une commande non payée. Passe à EN ATTENTE une fois payée.
    public static $COMMANDE_EN_ATTENTE_PAIEMENT = "EN ATTENTE DE PAIEMENT";

    public static $CLIENT_COMPTANT = "CLIENT COMPTANT";
    public static $CLIENT_BE = "CLIENT BE";
    public static $CLIENT_A_TERME = "CLIENT A TERME";

    public static $LIVRAISON_EN_ATTENTE = "EN ATTENTE";
    public static $LIVRAISON_EN_TRAITEMENT = "EN TRAITEMENT";
    public static $LIVRAISON_LIVREE = "LIVREE";
    // 4e état : marchandise servie par le fournisseur, en attente de livraison par le livreur.
    // (Doit rester en 4e position : du code écrit l'entier 4 sur la colonne ENUM = 4e valeur.)
    public static $LIVRAISON_EN_COURS = "EN COURS LIVRAISON";

    public static $BANNIERE_TOP = "TOP";
    public static $BANNIERE_FLASH = "FLASH";
    public static $BANNIERE_BOTTOM = "BOTTOM";

    public static $LOCATION = "LOCATION";
    public static $VENTE = "VENTE";

    public static $LOCATION_EN_ATTENTE = "EN ATTENTE";
    public static $LOCATION_EN_COURS = "EN COURS";
    public static $LOCATION_TERMINE = "TERMINE";

    public static $USER_SA = 1;
    public static $USER_ADMIN = 2;
    public static $USER_GESTIONNAIRE = 3;
    public static $USER_CLIENT = 4;
    public static $USER_FOURNISSEUR = 5;
    public static $USER_APPORTEUR = 6;
    public static $USER_AGENT_SAV = 7;
    public static $USER_LIVREUR = 8;

    public static $URL_BASE_FICHIER = "http://192.168.100.199/api_mon_gravier_com/public/";

    public function __construct() {}

    public static function sansAccent($string)
    {

        $a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';

        $b = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';

        $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');

        $string = strtr($string, mb_convert_encoding($a, 'ISO-8859-1', 'UTF-8'), $b);

        return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');;
    }

    public static function montantStringVersEnt($montant){
        return (int) preg_replace('/\D/', '', $montant);
    }

    public static function clientValide(){

        return (Auth::user())? Client::where('user_id',Auth::user()->id)->first() : new Client;
    }


    public static function findApporteur($code){

        if($code != null){
            $apporteur = Apporteur::where('code',$code)->first();
        }else{
            $apporteur = null;
        }
        return ($apporteur)? $apporteur->user->nom_prenoms: 'Pas de parrain';
    }

    public static function coutLivraison($longitude, $latitude, $regionID)
    {
        try {
        // Récupération des données de la région et de la configuration
        $region = DB::table('regions')->where('id', $regionID)->first();
        $conf = DB::table('configuration')->first();

        if (!$region || !$conf) {
            return [
                'km' => 0,
                'cout_livraison' => 0,
                'error' => 'Configuration ou région introuvable'
            ];
        }

        // Sécuriser les coordonnées de la région
        $regionLong = $region->long ?? 0;
        $regionLat = $region->lat ?? 0;

        if ($regionLong == 0 && $regionLat == 0) {
            return [
                'km' => 0,
                'cout_livraison' => $conf->cout_livraison_min ?? 0,
            ];
        }

        // Calcul de la distance en km
        $km = self::distance($longitude, $latitude, $regionLong, $regionLat);

        $prixKm = (float) ($conf->prixKm ?? 0);
        $coutMin = (float) ($conf->cout_livraison_min ?? 0);

        $prix = $km * $prixKm;

        if ($coutMin > 0 && $prix < $coutMin) {
            $prix = $coutMin;
        }

        $totalQte = 0;
        foreach (Cart::content() as $item) {
            $totalQte += (float) $item->qty;
        }

        // ------------------------------------------------------------------
        // GRILLE TARIFAIRE (interrupteur configuration.livraison_sur_grille).
        //
        // Le transport des ventes et des locations se chiffrait uniquement à la
        // distance : la quantité n'entrait pas dans le calcul, une tonne et
        // cent cinquante tonnes coûtaient le même prix. La grille — celle qui
        // sert déjà aux demandes de livraison — raisonne en forfaits par
        // (unité, tranche de quantité, tranche de distance), ce qui correspond
        // au coût réel : un forfait par voyage.
        //
        // Une ligne dont l'unité n'est pas tarifée RETOMBE sur la formule
        // kilométrique plutôt que de bloquer la vente, et son nom est remonté à
        // l'appelant pour être signalé. C'est un dispositif de transition : une
        // fois « php artisan grille:auditer » muet, le repli ne se déclenche
        // plus. Pour refuser la vente au lieu de retomber, il suffit de traiter
        // « lignes_sans_tarif » comme une erreur dans le contrôleur appelant.
        // ------------------------------------------------------------------
        $surGrille = (int) ($conf->livraison_sur_grille ?? 0) === 1;
        $lignesSansTarif = [];

        if ($surGrille) {
            $prixGrille = 0.0;

            foreach (Cart::content() as $item) {
                $abreviation = $item->model->unite ?? null;
                $uniteId = $abreviation
                    ? DB::table('unite_produit')->where('abreviation', $abreviation)->value('id')
                    : null;

                $forfait = null;

                if ($uniteId) {
                    $tranche = \App\Models\CoutLivraison::lireSurCle($uniteId, (float) $item->qty, $km);
                    if (($tranche->id ?? 0) > 0) {
                        $forfait = (float) $tranche->prix_km;
                    }
                }

                if ($forfait === null) {
                    // Repli : la part kilométrique de cette ligne.
                    $forfait = ($totalQte > 0) ? ((float) $item->qty / $totalQte) * $prix : 0;
                    $lignesSansTarif[] = $item->model->nom ?? ('produit #' . ($item->model->id ?? '?'));
                }

                $options = $item->options->toArray();
                $options['cout_livraison'] = $forfait;
                Cart::update($item->rowId, ['options' => $options]);

                $prixGrille += $forfait;
            }

            if (!empty($lignesSansTarif)) {
                \Log::warning('Livraison : lignes sans tarif de grille, repli kilométrique', [
                    'produits' => $lignesSansTarif,
                    'km'       => round($km, 2),
                ]);
            }

            return [
                'km'                => round($km, 2),
                'cout_livraison'    => round($prixGrille, 2),
                'lignes_sans_tarif' => $lignesSansTarif,
            ];
        }

        foreach (Cart::content() as $item) {
            $part = ($totalQte > 0)
                ? ((float) $item->qty / $totalQte) * $prix
                : 0;

            $options = $item->options->toArray();
            $options['cout_livraison'] = $part;

            Cart::update($item->rowId, [
                'options' => $options,
            ]);
        }

        return [
            'km' => round($km, 2),
            'cout_livraison' => round($prix, 2),
            'lignes_sans_tarif' => [],
        ];
        } catch (\Exception $e) {
            \Log::error('Erreur coutLivraison: ' . $e->getMessage());
            return [
                'km' => 0,
                'cout_livraison' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    public static function distance($long1, $lat1, $long2, $lat2)
    {
        $earthRadius = 6371; // en kilomètres

        // Convertir degrés → radians
        $lon1 = deg2rad($long1);
        $lat1 = deg2rad($lat1);
        $lon2 = deg2rad($long2);
        $lat2 = deg2rad($lat2);

        // Formule de Haversine
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;

        $a = pow(sin($latDelta / 2), 2) +
            cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2);

        $c = 2 * asin(sqrt($a));

        return (int) round($earthRadius * $c); // retourne une distance entière en km
    }

    /**
     * Génère un code-barres Code 39 sous forme HTML (spans inline-block).
     * Compatible DomPDF — pas de SVG, pas de dépendance externe.
     * Caractères supportés : 0-9, A-Z, espace, - . $ / + %
     */
    public static function barcode39Html(string $text, int $height = 45): string
    {
        $patterns = [
            '0'=>'nnnwwnwnn','1'=>'wnnwnnnnw','2'=>'nnwwnnnnw','3'=>'wnwwnnnnn',
            '4'=>'nnnwwnnnw','5'=>'wnnwwnnnn','6'=>'nnwwwnnnn','7'=>'nnnwnnwnw',
            '8'=>'wnnwnnwnn','9'=>'nnwwnnwnn',
            'A'=>'wnnnnwnnw','B'=>'nnwnnwnnw','C'=>'wnwnnwnnn','D'=>'nnnnwwnnw',
            'E'=>'wnnnwwnnn','F'=>'nnwnwwnnn','G'=>'nnnnnwwnw','H'=>'wnnnnwwnn',
            'I'=>'nnwnnwwnn','J'=>'nnnnwwwnn','K'=>'wnnnnnnww','L'=>'nnwnnnnww',
            'M'=>'wnwnnnnwn','N'=>'nnnnwnnww','O'=>'wnnnwnnwn','P'=>'nnwnwnnwn',
            'Q'=>'nnnnnnwww','R'=>'wnnnnnwwn','S'=>'nnwnnnwwn','T'=>'nnnnwnwwn',
            'U'=>'wwnnnnnnw','V'=>'nwwnnnnnw','W'=>'wwwnnnnnn','X'=>'nwnnwnnnw',
            'Y'=>'wwnnwnnnn','Z'=>'nwwnwnnnn',
            '-'=>'nwnnnnwnw','.'=>'wwnnnnwnn',' '=>'nwwnnnwnn','*'=>'nwnnwnwnn',
            '$'=>'nwnwnwnnn','/'=>'nwnwnnnwn','+'=>'nwnnnwnwn','%'=>'nnnwnwnwn',
        ];

        $text = strtoupper(preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/i', '', $text));
        if ($text === '') {
            return '';
        }
        $encoded = '*' . $text . '*';
        $narrow = 2; // px
        $wide = 5;   // px

        $bars = '';
        $len = strlen($encoded);
        for ($i = 0; $i < $len; $i++) {
            $c = $encoded[$i];
            if (!isset($patterns[$c])) continue;
            $pattern = $patterns[$c];
            for ($j = 0; $j < 9; $j++) {
                $w = ($pattern[$j] === 'w') ? $wide : $narrow;
                $isBar = ($j % 2 === 0);
                if ($isBar) {
                    $bars .= '<span style="display:inline-block;width:' . $w . 'px;height:' . $height . 'px;background-color:#000;vertical-align:top;"></span>';
                } else {
                    $bars .= '<span style="display:inline-block;width:' . $w . 'px;height:' . $height . 'px;vertical-align:top;"></span>';
                }
            }
            if ($i < $len - 1) {
                // gap inter-caractère (espace blanc)
                $bars .= '<span style="display:inline-block;width:' . $narrow . 'px;height:' . $height . 'px;vertical-align:top;"></span>';
            }
        }

        return '<div style="text-align:center;line-height:0;font-size:0;white-space:nowrap;">' . $bars . '</div>';
    }

    public static function isReduction($devis){

        $query = Reduction::where('devis_id', $devis->id)->where('est_utilise', false) || Reduction::where('devis_id', $devis->id);
        // $query->first();

        // dd($query);


        // return $reduction ? true : false;


    }

    public static function verificationDeCommandeTotalementTraitee($commande)
    {
        $nbrProduit = $commande->produits()->count();
        $nbProdEnleve = $commande->produits()->where('detail_commande.statut', 2)->count();

        $produitTraite = true;
        foreach($commande->detailCommande as $detail){
            $total = 0;

            foreach($detail->livraisons as $livraison){
                foreach($detail->livraisons as $livraison){
                    // if($livraison->statut == 1){

                        $total += $livraison->qte;
                    // }
                }
            }

            if($total != $detail->qte){
                return false;
            }

        }

        return true;


        // switch($nbrProduit ){
        //     case ($nbProdEnleve == 0 ) :
        //         return 0;
        //         break;
        //     case ($nbProdEnleve > 0 && $nbrProduit > $nbProdEnleve) :
        //         return 1;

        //         break;
        //     case ($nbrProduit == $nbProdEnleve) :

        //         return 2;

        //         break;
        // }
    }

    public static function listeProvenance()
    {
        return [
            Help::$COMMANDE,
            Help::$LIVRAISON,
        ];
    }

    public static function listeTypeAffaire()
    {
        return [
            Help::$LOCATION,
            Help::$VENTE,
        ];
    }

    public static function commandeHasFacture($commandes)
    {
        $facture = false;

        foreach($commandes as $commande){
            if($commande->factures->count() > 0){
                $facture = true;
            }
        }

        return $facture;


    }

    public static function ecrireLog($fn, $titre, $details, $user_id)
    {
        $log = new Logs();
        $log->fn = $fn;
        $log->titre = $titre;
        $log->details = $details;
        $log->user_id = $user_id;
        $log->save();
    }
    /**
     * Solde d'un client, en valeur BRUTE (nombre, non formaté).
     *
     * Deux lectures d'une même réalité :
     *   $admin = true  -> factures - paiements : ce que le client DOIT à l'entreprise
     *                     (sens attendu en back-office, libellé « Montant à payer »).
     *   $admin = false -> paiements - factures : positif = le client a versé plus
     *                     qu'il n'a été facturé (avoir), négatif = il reste à payer.
     *
     * Séparée de soldeClient() pour que les vues puissent tester le SIGNE et
     * choisir un libellé : « -7 fcfa » sous l'étiquette « Votre solde » ne
     * permettait pas au client de savoir de quel côté penchait le compte.
     *
     * COALESCE : sans lui, SUM() renvoie NULL quand le client n'a aucune ligne,
     * et l'arithmétique sur null déclenche un avertissement en PHP 8.
     */
    public static function soldeClientBrut($client, $admin = true): float
    {
        // Seuls les règlements CONFIRMÉS comptent.
        //
        // La condition était « statut <> 3 », qui laissait passer le statut 2 : un
        // paiement en ligne créé par la passerelle AVANT que le client ne règle. Un
        // client qui abandonnait devant Orange Money voyait donc son abandon compté
        // comme de l'argent versé — « Réglé d'avance : 216 FCFA » pour deux
        // tentatives sans suite. Côté back-office, l'effet était pire : la somme
        // MINORAIT d'autant ce que le client reste devoir.
        // Ces requêtes sont écrites à la main : la suppression logique, que
        // l'ORM applique d'office, doit donc l'être ici aussi. Sans quoi une
        // facture supprimée continue de charger le client, et un règlement
        // supprimé continue de l'alléger — dans les deux sens, le solde est faux.
        $paiement = (float) (DB::selectOne("SELECT COALESCE(SUM(li.montant), 0) AS montant
                                        FROM ligne_paiement li
                                           JOIN paiement p ON p.id = li.paiement_id
                                           JOIN client cli ON cli.id = p.client_id
                                           WHERE p.statut = ? AND li.statut = ? AND cli.id = ?
                                             AND p.deleted_at IS NULL
                                             AND li.deleted_at IS NULL ",
                                        [self::$STATUT_ACTIF, self::$STATUT_ACTIF, $client->id])->montant);

        $facture = (float) (DB::selectOne("SELECT COALESCE(SUM(fac.montant), 0) AS montant FROM facture fac
                                WHERE fac.client_id = ? AND fac.deleted_at IS NULL", [$client->id])->montant);

        return $admin ? $facture - $paiement : $paiement - $facture;
    }

    /**
     * Part de l'excédent du client qui attend seulement d'être facturée.
     *
     * Un excédent (paiements > factures) recouvre DEUX situations que rien ne
     * distinguait à l'écran, et le libellé « En attente de facturation »
     * s'appliquait aux deux :
     *
     *   - la marchandise est payée mais pas encore enlevée, donc pas encore
     *     facturée : la facture viendra, et l'excédent se résorbera seul ;
     *   - la commande est soldée et facturée, mais le client a versé plus que
     *     le montant de la facture : c'est un TROP-PERÇU, rien ne viendra le
     *     résorber. Le cas s'est produit sur la commande 849677, où la TVA
     *     était réclamée sur la remise — 443 payés pour 437 dus.
     *
     * Le calcul se fait COMMANDE PAR COMMANDE. Sur chacune :
     *
     *   excédent          = max(0, réglé − facturé)      argent versé au-delà
     *                                                     de ce qui est facturé
     *   reste à facturer  = max(0, dû − facturé)         marchandise pas encore
     *                                                     enlevée, donc à venir
     *   en attente        = min(excédent, reste à facturer)
     *
     * La borne par « reste à facturer » est indispensable : sans elle, une
     * commande entièrement facturée mais trop payée — 443 versés pour 437 dus —
     * ressortait comme « en attente de facturation », alors qu'aucune facture
     * ne viendra plus. C'est précisément le cas qu'il fallait distinguer.
     *
     * Le dû se recalcule depuis les lignes, jamais depuis montant_total : cette
     * colonne contient le HT pour une commande du site et le NET pour une
     * commande du mobile.
     *
     * Sous-requêtes corrélées plutôt que jointures : joindre à la fois les
     * lignes de paiement et les factures d'une même commande multiplierait les
     * lignes et fausserait les deux sommes.
     */
    public static function montantEnAttenteDeFacturation($client): float
    {
        $ligne = DB::selectOne(
            "SELECT COALESCE(SUM(LEAST(GREATEST(0, t.regle - t.facture),
                                      GREATEST(0, t.du    - t.facture))), 0) AS montant
               FROM (
                    SELECT COALESCE((SELECT SUM(d.prix * d.qte)
                                       FROM detail_commande d
                                      WHERE d.commande_id = c.id
                                        AND d.deleted_at IS NULL), 0)
                         + COALESCE((SELECT SUM(tc.montant)
                                       FROM tva_commande tc
                                      WHERE tc.commande_id = c.id
                                        AND tc.deleted_at IS NULL), 0)
                         + COALESCE(c.cout_livraison_client, 0)
                         - COALESCE(c.remise, 0)                             AS du,
                           COALESCE((SELECT SUM(li.montant)
                                       FROM ligne_paiement li
                                       JOIN paiement p ON p.id = li.paiement_id
                                      WHERE p.service = ? AND p.service_id = c.id
                                        AND p.statut = ? AND li.statut = ?
                                        AND p.deleted_at IS NULL AND li.deleted_at IS NULL), 0) AS regle,
                           COALESCE((SELECT SUM(f.montant)
                                       FROM facture f
                                      WHERE f.service = ? AND f.service_id = c.id
                                        AND f.deleted_at IS NULL), 0) AS facture
                      FROM commande c
                     WHERE c.client_id = ?
                       AND c.deleted_at IS NULL
                       AND c.etat_commande <> 'ANNULEE'

                    UNION ALL

                    /* Les LOCATIONS suivent exactement la même logique.
                       Elles manquaient : le solde du client compte TOUS ses
                       règlements, tous services confondus, alors que ce calcul
                       ne regardait que les commandes. Une location réglée mais
                       pas encore facturée ressortait donc en « versé en trop,
                       à votre crédit », alors qu'elle attend simplement sa
                       facture. Constaté sur un client dont les 50 180 FCFA
                       annoncés comme trop-perçus étaient en fait une avance de
                       50 000 sur une location de 90 000 en cours.

                       Le dû reprend Location::montantAPayer() : montant_total
                       porte le HT, auquel s'ajoutent la TVA et la livraison,
                       remise déduite. Le filtre type_affaire est indispensable
                       — une location et une commande peuvent porter le même
                       identifiant dans tva_commande. */
                    SELECT COALESCE(l.montant_total, 0)
                         - COALESCE(l.remise, 0)
                         + COALESCE((SELECT SUM(tc.montant)
                                       FROM tva_commande tc
                                      WHERE tc.commande_id = l.id
                                        AND tc.type_affaire = ?
                                        AND tc.deleted_at IS NULL), 0)
                         + COALESCE(l.cout_livraison_client, 0)             AS du,
                           COALESCE((SELECT SUM(li.montant)
                                       FROM ligne_paiement li
                                       JOIN paiement p ON p.id = li.paiement_id
                                      WHERE p.service = ? AND p.service_id = l.id
                                        AND p.statut = ? AND li.statut = ?
                                        AND p.deleted_at IS NULL AND li.deleted_at IS NULL), 0) AS regle,
                           COALESCE((SELECT SUM(f.montant)
                                       FROM facture f
                                      WHERE f.service = ? AND f.service_id = l.id
                                        AND f.deleted_at IS NULL), 0) AS facture
                      FROM location l
                     WHERE l.client_id = ?
                       AND l.deleted_at IS NULL
                       AND l.etat_location <> 'ANNULEE'
               ) t",
            [self::$COMMANDE, self::$STATUT_ACTIF, self::$STATUT_ACTIF, self::$COMMANDE, $client->id,
             self::$LOCATION, self::$LOCATION, self::$STATUT_ACTIF, self::$STATUT_ACTIF,
             self::$LOCATION, $client->id]
        );

        return (float) ($ligne->montant ?? 0);
    }

    public static function soldeClient($client, $admin = true){

        return self::formatNombre(self::soldeClientBrut($client, $admin), true);

    }

    public static function soldeClientOld($client, $aTerme){
        $p = 0;
        $paiementsValide = 0;
        // $config = Configuration::first();
        if($aTerme == 1){

            $totalFacture = 0 ;
            $totalPaiement = 0;

            foreach($client->commande->where('statut', 1) as $commande){

                $totalFacture += $commande->factures->sum('montant');

            }
            foreach($client->paiements->where('statut',1) as $p ){

                foreach($p->lignePaiements->where('statut', 1) as $l ){
                    $paiementsValide += $l->montant;
                }

            }

            return $totalFacture - $paiementsValide;
            $totalPaiement += $client->paiements->where('statut',1)->sum('montant_total'); ;

            // dd($totalFacture, $totalPaiement);

            return  $totalPaiement - $totalFacture;

        }else{
            //var_dump($client->commande);
            // commande.montant_total est ambigu (HT pour une commande créée sur le
            // site, NET pour une commande créée depuis l'application mobile).
            // On repasse par montantAPayer(), qui recalcule depuis les lignes.
            $solde = (float) $client->commande->where('statut',1)
                ->sum(fn ($commande) => $commande->montantAPayer());

            foreach($client->commande->where('statut',1) as $commande){

                foreach($commande->detailCommande as $detail){

                    if(!$detail->livraisons->isEmpty()){

                        foreach($detail->livraisons as $livraison){
                            if($livraison->etat_livraison == 'LIVREE'){
                                // Utiliser le prix unitaire effectivement facturé sur la ligne (detail_commande.prix)
                                // — qui contient déjà le prix personnalisé si applicable — et non le prix_moyen brut.
                                // Livraison marquée LIVREE sans bon d'enlèvement associé :
                                // la lecture directe provoquait une erreur 500 sur la page
                                // qui affiche le solde. On ignore la ligne dans ce cas.
                                $solde -= ($detail->prix * ($livraison->enlevement?->qte_servi ?? 0));
                            }
                        }
                    }
                }

                //dd()
                //tva

                if(!is_null($commande->TvaCommande)){
                    $solde -= $commande->TvaCommande->montant;
                }

               // ;


            }

            // dd($p);

            return $solde;
        }

        $montantCommandeTotal = $client->paiements->where('statut','!=', 3)->sum('montant_total');

        $totalPaye = 0;

        foreach($client->paiements as $p){

            $totalPaye += $p->lignePaiements->where('statut',1)->sum('montant');
        }


        return $totalPaye - $montantCommandeTotal;



    }

    /**
     * Ce qu'il reste a confier a un camion sur une ligne de demande.
     *
     * Les courses REFUSEES etaient comptees comme affectees : la ligne
     * s'annoncait servie alors que rien n'avait ete transporte. Le calcul est
     * desormais porte par le modele, pour que l'ecran, le controleur et la
     * liste ne puissent plus en donner trois versions.
     */
    public static function qteDetaillivraisonRestante(DetailLivraison $detail){

        return $detail->qteRestanteAAffecter();
    }

    public static function commission($montant){

        switch ($montant) {
            case ($montant >= 0 && $montant < 5000000 ):
                // dd('2,5% ',$solde + (($montant*2.5))/100);

                    return (($montant*2.5))/100;

                break;

            case ($montant >= 5000000 && $montant <= 20000000 ):
                // dd('5% ',$solde + (($montant*5))/100);

                   return (($montant*5))/100;

                break;

            case ($montant >= 20000001 ):
                // dd('7% ',$solde + (($montant*7))/100);

                    return (($montant*7))/100;

                break;
        }
    }

    public static function montantLocationRestant(Location $location){
        $montantRestant = $location->montant_total;

        // dd($location);
        // dd($montantRestant);

        if(!$location->paiements->isEmpty()){
            $dernierPaiement = $location->paiements->sortByDESC('created_at')->first();

            $montantRestant = $dernierPaiement->montant_restant;

        }

        return $montantRestant;
    }

    // pour afficher le montant précédant le montant de la commande avant la réduction dans la liste des commandes
    public static function montantInitial($remise, $montant){
        $montantInitial = ($montant)/(1-$remise/100) ;
        return $montantInitial;

    }

    public static function listeStatutLivraison()
    {
        return [
            Help::$LIVRAISON_EN_ATTENTE,
            Help::$LIVRAISON_EN_TRAITEMENT,
            Help::$LIVRAISON_LIVREE,
            Help::$LIVRAISON_EN_COURS,
        ];
    }

    public static function getCommandeNo()
    {
        // Numéro de commande court et lisible : AAMMJJ + 6 chiffres aléatoires (12 chiffres).
        // Reste purement numérique pour ne pas casser les recherches/lookups sur `numero`.
        return date('ymd') . Help::ChaineAleatoireNombre(6);
    }

    public static function getCodeParain($tel)
    {
        return "PAR-" . $tel . Help::ChaineAleatoireNombre(3);
    }

    public static function formatNombre($valeur, $monetaire = false, $devise = "fcfa")
    {
        if ($monetaire == true) return number_format($valeur, 0, ",", " ") . " $devise";
        else return number_format($valeur, 2, ",", ".");
    }

    /**
     * Largeur fixe utilisée pour tous les numéros de facture/devis affichés ou générés.
     * Utilisée pour garantir une cohérence visuelle (ex. 000123 / 045678 / 999999).
     */
    public static $NUMERO_FACTURE_WIDTH = 6;

    /**
     * Génère un numéro unique sur 6 chiffres pour une table donnée (devis, facture, ...).
     * Re-tire en cas de collision sur la colonne `numero`.
     */
    public static function genererNumeroUnique(string $table, string $colonne = 'numero', int $largeur = null): string
    {
        $largeur = $largeur ?? self::$NUMERO_FACTURE_WIDTH;
        $min = (int) str_pad('1', $largeur, '0', STR_PAD_RIGHT) / 10; // 100000 pour 6
        $max = (int) str_repeat('9', $largeur);
        $tentative = 0;
        do {
            $candidat = (string) random_int(max(100000, (int) ($max / 9)), $max);
            $candidat = str_pad($candidat, $largeur, '0', STR_PAD_LEFT);
            $existe = DB::table($table)->where($colonne, $candidat)->exists();
            $tentative++;
        } while ($existe && $tentative < 20);

        return $candidat;
    }

    /**
     * Numéro d'une commande — et du devis dont elle naîtra.
     *
     * Six chiffres, comme les commandes passées directement depuis le panier. Le
     * parcours « demande de devis » produisait au contraire un AAMMJJ suivi de 6
     * chiffres (getCommandeNo), si bien que deux commandes voisines n'affichaient
     * pas le même format selon le chemin emprunté par le client.
     *
     * Le tirage est vérifié dans devis ET dans commande. La commande reprend le
     * numéro de son devis et commande.numero porte un index UNIQUE : ne contrôler
     * que la table devis laissait passer les numéros des commandes sans devis
     * rattaché. Le devis s'enregistrait, puis la commande échouait sur l'index —
     * page blanche, commande perdue.
     */
    public static function genererNumeroCommande(int $largeur = null): string
    {
        $largeur = $largeur ?? self::$NUMERO_FACTURE_WIDTH;
        $max = (int) str_repeat('9', $largeur);
        $min = max(100000, (int) ($max / 9));

        for ($tentative = 0; $tentative < 50; $tentative++) {
            $candidat = str_pad((string) random_int($min, $max), $largeur, '0', STR_PAD_LEFT);

            $pris = DB::table('devis')->where('numero', $candidat)->exists()
                || DB::table('commande')->where('numero', $candidat)->exists();

            if (!$pris) {
                return $candidat;
            }
        }

        // Espace saturé : on élargit d'un chiffre plutôt que de renvoyer un numéro
        // déjà pris, qui ferait échouer l'insertion.
        return self::genererNumeroCommande($largeur + 1);
    }

    /**
     * Enregistre un devis en réessayant si son numéro vient d'être pris.
     *
     * genererNumeroCommande() vérifie qu'un numéro est libre, mais rien ne garantit
     * qu'il le soit encore à l'instant de l'INSERT : deux requêtes simultanées
     * peuvent tirer le même nombre et le constater libre toutes les deux. L'index
     * unique de devis.numero tranche alors correctement — mais le perdant recevait
     * une erreur SQL, donc une page blanche, pour une commande parfaitement valide.
     *
     * On retente plutôt avec un nouveau numéro. La fenêtre est étroite (il faut le
     * même tirage à la même fraction de seconde), mais elle s'ouvre avec le volume,
     * et un client n'a pas à payer le prix d'une collision qui ne le concerne pas.
     *
     * Seule une violation d'unicité est rattrapée : toute autre erreur SQL remonte
     * intacte, il n'est pas question de masquer un vrai défaut derrière une boucle.
     *
     * @param  callable(string):mixed  $creer  reçoit le numéro à utiliser
     */
    public static function creerAvecNumeroUnique(callable $creer, int $essais = 5)
    {
        for ($essai = 1; ; $essai++) {
            try {
                return $creer(self::genererNumeroCommande());
            } catch (\Illuminate\Database\QueryException $e) {
                if ($essai >= $essais || !self::estViolationUnicite($e)) {
                    throw $e;
                }
            }
        }
    }

    /** Le SQL a-t-il échoué sur un doublon de clé unique (MySQL 1062) ? */
    private static function estViolationUnicite(\Throwable $e): bool
    {
        return ($e->getCode() === '23000' || $e->getCode() === 23000)
            && (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * Formate un numéro existant pour l'afficher avec une largeur fixe (padding zéro à gauche).
     * Si le numéro contient des caractères non numériques (préfixe), il est renvoyé tel quel.
     */
    public static function formatNumeroFacture($numero, int $largeur = null): string
    {
        $largeur = $largeur ?? self::$NUMERO_FACTURE_WIDTH;
        if ($numero === null || $numero === '') return '';
        if (ctype_digit((string) $numero)) {
            return str_pad((string) $numero, $largeur, '0', STR_PAD_LEFT);
        }
        return (string) $numero;
    }

    public static function HashPassword(String $password): String
    {
        return Hash::make("@#MonGr@vier#@" . $password . "@C0m@");
    }



    public static function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();
        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }

    public static function array_sort($array, $on, $order = SORT_ASC)
    {
        $new_array = array();
        $sortable_array = array();

        if (count($array) > 0) {
            foreach ($array as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == $on) {
                            $sortable_array[$k] = $v2;
                        }
                    }
                } else {
                    $sortable_array[$k] = $v;
                }
            }

            switch ($order) {
                case SORT_ASC:
                    asort($sortable_array);
                    break;
                case SORT_DESC:
                    arsort($sortable_array);
                    break;
            }

            foreach ($sortable_array as $k => $v) {
                $new_array[$k] = $array[$k];
            }
        }

        return $new_array;
    }

    public static function HashVerifier(String $password, String $hashPassword): bool
    {
        return Hash::check("@#MonGr@vier#@" . $password . "@C0m@", $hashPassword);
    }

    public static function listeStatutCommande()
    {
        return [
            Help::$COMMANDE_EN_ATTENTE,
            Help::$COMMANDE_EN_TRAITEMENT,
            Help::$COMMANDE_TERMINE
        ];
    }

    public static function typeCompte(){

        return [
            Help::$CLIENT_COMPTANT,
            Help::$CLIENT_BE,
            Help::$CLIENT_A_TERME
        ];
    }

    public static function listeStatutLocation()
    {
        return [
            Help::$LOCATION_EN_ATTENTE,
            Help::$LOCATION_EN_COURS,
            Help::$LOCATION_TERMINE
        ];
    }
    /**
     * @params int $number
     * @return
     */
    public static function truncateToTwoDecimals($number) {
        return floor($number * 100) / 100;
    }

    public static function ChaineAleatoireNombre(int $nombreChaine)
    {
        // Stockez toutes les lettres possibles dans une chaîne.
        $str = '0123456789';
        $randomStr = '';

        // Générez un index aléatoire de 0 à la longueur de la chaîne -1.
        for ($i = 0; $i < $nombreChaine; $i++) {
            $index = rand(0, strlen($str) - 1);
            $randomStr .= $str[$index];
        }

        return $randomStr;
    }

    public static function getNumberToken(int $taille)
    {
        // Lettres majuscules + chiffres
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomStr = '';

        // Génère un caractère aléatoire à chaque itération
        for ($i = 0; $i < $taille; $i++) {
            $index = rand(0, strlen($str) - 1);
            $randomStr .= $str[$index];
        }

        return $randomStr;
    }


    public static function montantDu($client){

        $montantPaye = Help::totalPaiementClient($client);
        // Même règle : total NET recalculé depuis les lignes, jamais montant_total.
        $totalCommande = (float) $client->commande->sum(fn ($commande) => $commande->montantAPayer());

        return $totalCommande - $montantPaye;
    }

    public static function totalPaiementClient($client){

        $montantPaye = 0;

        if(!$client->paiements->isEmpty()){
            foreach($client->paiements as $paiement){
                $montantPaye += $paiement->lignePaiements?->montant;
            }
        }

        return $montantPaye;
    }

    /**
     * Valeur de la marchandise REELLEMENT enlevee sur une commande.
     *
     * Somme des lignes servies : prix de la ligne x quantite servie. Rien
     * d'autre — ni TVA, ni livraison, ni remise.
     *
     * totalEnleveSurCommande(), juste en dessous, part au contraire de
     * « TVA + livraison - remise » avant d'ajouter la marchandise. Ce decalage
     * s'annule dans la soustraction « montantAPayer - totalEnleve », qui donne
     * bien le reste a enlever ; mais affichee TELLE QUELLE dans la colonne
     * « Deja enleve », la valeur annoncait 4 900 F enleves sur une commande
     * ou rien n'avait ete retire — 900 de TVA et 4 000 de livraison.
     */
    public static function marchandiseEnleveeSurCommande($commande): float
    {
        $enleve = 0.0;

        foreach ($commande->detailCommande as $detail) {
            foreach ($detail->livraisons as $livraison) {
                $enleve += (float) $detail->prix * (float) ($livraison->enlevement?->qte_servi ?? 0);
            }
        }

        return $enleve;
    }

    public static function totalEnleveSurCommande($commande){

        $montantEnleveProduit = ($commande->TvaCommande?->montant ?? 0) + $commande->cout_livraison_client - $commande->remise;

        foreach($commande->detailCommande as $detail){

            if(!$detail->livraisons->isEmpty()){

                foreach($detail->livraisons as $livraison){
                    $montantEnleveProduit = $montantEnleveProduit + ($detail->prix * $livraison->enlevement?->qte_servi);
                    // dump($detail->prix,$livraison->enlevement->qte_servi );
                }
            }
        }

        return $montantEnleveProduit != 0 ?  $montantEnleveProduit : 0;
    }

    public static function totalEnleveParClient($client){
        $montantTotalEnleve = 0;

        if(!$client->commande->isEmpty()){

            foreach($client->commande as $commande){

                $montantTotalEnleve += Help::totalEnleveSurCommande($commande);

            }
        }

        return $montantTotalEnleve != 0 ?  $montantTotalEnleve : 0;
    }


    public static function ChaineAleatoire(int $nombreChaine)
    {
        // Stockez toutes les lettres possibles dans une chaîne.
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomStr = '';

        // Générez un index aléatoire de 0 à la longueur de la chaîne -1.
        for ($i = 0; $i < $nombreChaine; $i++) {
            $index = rand(0, strlen($str) - 1);
            $randomStr .= $str[$index];
        }

        return $randomStr;
    }

    public static function NombreCommancantParzero(int $LeNombre, int $Taille = 0)
    {
        $length = 0;
        ($Taille > 0) ? $length = $Taille : $length = 6;
        $char = 0;
        $type = 'd';
        $format = "%{$char}{$length}{$type}"; // or "$010d";

        //store to a variable
        $newFormat = sprintf($format, $LeNombre);
        return $newFormat;
    }

    public function paginate($items, $total, $perPage = 5, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items,  $total, $perPage, $page, $options);
    }

    public static function rechercheParCle($tableaux, $cle, $valeur)
    {
        foreach ($tableaux as $object) {
            // dd($object->{$cle});
            if ($object->{$cle} == $valeur) {
                return $object;
            }
        }
        return null;
    }

    public static function dateViewToDB($dateString)
    {
        $myDateTime = DateTime::createFromFormat('d/m/Y H:i', $dateString);
        return $myDateTime->format('Y-m-d H:i');
    }

    public static function setElementToSession($key, $value)
    {
        if (session()->has($key)) {
            session()->forget($key);
        }
        session()->put($key, $value);
    }

    public static function getElementToSession($key)
    {
        if (session()->has($key)) {
            return session()->get($key);
        }
        return null;
    }
    public static function totatEnlevementUnProduit($commandeId, $produitId){

        // NULLIF(qte_servi, 0) : un qte_servi à 0 (décimale détruite par l'ancien type
        // INTEGER de la colonne, ou saisie aberrante) est traité comme « non renseigné »
        // -> repli sur la qte du bon. Sans ça, l'enlèvement comptait pour ZÉRO et la
        // page de traitement proposait de re-traiter la totalité (sur-livraison).
        $sql = "SELECT SUM(enlevement.qte) AS totalQte,
        SUM(COALESCE(NULLIF(enlevement.qte_servi, 0), enlevement.qte)) AS totalServi,
        detail_commande.qte as qteALivrer
        FROM enlevement INNER JOIN livraison ON livraison.id = enlevement.livraison_id
        INNER JOIN detail_commande on detail_commande.id = livraison.detail_commande_id
        WHERE detail_commande.commande_id = ? AND enlevement.produit_id = ?
        AND detail_commande.statut = 1
        AND enlevement.deleted_at IS NULL AND livraison.deleted_at IS NULL
        -- Les courses REFUSEES par le livreur ne comptent pas : rien n'a ete
        -- transporte. Les compter revenait a dire la ligne entierement traitee,
        -- et l'ecran ne proposait plus rien a reaffecter.
        AND livraison.accepte <> 3
        GROUP BY enlevement.produit_id, detail_commande.qte";
        $total = DB::select($sql, [$commandeId, $produitId]);
        // $total = DB::scalar($sql, [$commandeId, $produitId]);
        if(count($total) > 0){
            if($total[0]->totalQte != $total[0]->totalServi){
                return $total[0]->totalServi;
            }else{
                return $total[0]->totalQte;
            }
        }
        return 0;
    }

    public static function nombreJourEntreDeuxDate($dateDebut, $dateFin)
    {
        $dateDebut = str_replace("/", "-", $dateDebut);
        $dateFin = str_replace("/", "-", $dateFin);
        // On transforme les 2 dates en timestamp
        $date3 = strtotime($dateDebut);
        $date4 = strtotime($dateFin);

        // On récupère la différence de timestamp entre les 2 précédents
        $nbJoursTimestamp = $date4 - $date3;

        // ** Pour convertir le timestamp (exprimé en secondes) en jours **
        // On sait que 1 heure = 60 secondes * 60 minutes et que 1 jour = 24 heures donc :
        return ceil($nbJoursTimestamp / 86400); // 86 400 = 60*60*24
    }

    public static function sommePropriete(array $array, string $propriete)
    {
        $ret =  array_reduce($array, function ($carry, $item) use ($propriete) {
            return $carry + $item->{$propriete};
        });
        return $ret ?? 0;
    }

    public static function afficherTempsEcoule($dateHeure)
    {
        // Date donnée
        $dateDonnee = Carbon::parse($dateHeure);
        // Date et heure actuelles
        $dateActuelle = Carbon::now();
        // Temps écoulé
        return str_replace("avant", "", $dateDonnee->diffForHumans($dateActuelle));
    }

    /**
     * Envoyer un document PDF par email au client
     */
    public static function envoyerDocumentPdf($clientNom, $emailClient, $typeDocument, $numero, $viewName, $viewData, $nomFichier = null)
    {
        try {
            $pdf = \PDF::loadView($viewName, $viewData);
            $pdfContent = $pdf->output();
            $nomFichier = $nomFichier ?? $typeDocument . '_' . $numero . '.pdf';

            \Illuminate\Support\Facades\Mail::send(
                new \App\Mail\DocumentPdfMail($clientNom, $emailClient, $typeDocument, $numero, $pdfContent, $nomFichier)
            );
        } catch (\Exception $e) {
            \Log::error('Erreur envoi document PDF: ' . $e->getMessage());
        }
    }
}

class Retour
{
    public $code;
    public $code_parrain;
    public $token;
    public $type;
    public $photo;
    public $configs;
    public $message;
    public $nom;
    public $data;
}
