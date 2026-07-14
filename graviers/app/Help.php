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
            'cout_livraison' => round($prix, 2)
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
    public static function soldeClient($client, $admin = true){
        // dd($client);
        $paiement = DB::select("SELECT SUM(li.montant) AS montant
                                        FROM ligne_paiement li
                                           JOIN paiement p ON p.id = li.paiement_id
                                           JOIN client cli ON cli.id = p.client_id
                                           WHERE p.statut <> 3 AND li.statut <> 3 AND cli.id = ? ", [$client->id]);


        $facture = DB::select("SELECT SUM(fac.montant) AS montant FROM facture fac
                                WHERE fac.client_id = ?", [$client->id]);

        // dd($facture, $paiement, $client->id);

        if($admin){
            // chez le gestionnaire
            $solde = $facture[0]->montant - $paiement[0]->montant;
        }else{
            // chez le client
            $solde = $paiement[0]->montant - $facture[0]->montant;
        }

        return self::formatNombre($solde,true);

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
            $solde = $client->commande->where('statut',1)->sum('montant_total');

            foreach($client->commande->where('statut',1) as $commande){

                foreach($commande->detailCommande as $detail){

                    if(!$detail->livraisons->isEmpty()){

                        foreach($detail->livraisons as $livraison){
                            if($livraison->etat_livraison == 'LIVREE'){
                                // Utiliser le prix unitaire effectivement facturé sur la ligne (detail_commande.prix)
                                // — qui contient déjà le prix personnalisé si applicable — et non le prix_moyen brut.
                                $solde -= ($detail->prix * $livraison->enlevement->qte_servi);
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

    public static function qteDetaillivraisonRestante(DetailLivraison $detail){

        // dd($detail->livraisons);

        if($detail->livraisons->count() > 0){
            return $detail->qte - $detail->livraisons->sum('qte');
        }else{
            return $detail->qte;
        }

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
        $totalCommande = $client->commande->sum('montant_total');

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

    public static function totalEnleveSurCommande($commande){

        $montantEnleveProduit = $commande->TvaCommande->montant + $commande->cout_livraison_client - $commande->remise;

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

        $sql = "SELECT SUM(enlevement.qte) AS totalQte,
        SUM(COALESCE(enlevement.qte_servi, enlevement.qte)) AS totalServi,
        detail_commande.qte as qteALivrer
        FROM enlevement INNER JOIN livraison ON livraison.id = enlevement.livraison_id
        INNER JOIN detail_commande on detail_commande.id = livraison.detail_commande_id
        WHERE detail_commande.commande_id = ? AND enlevement.produit_id = ?
        AND detail_commande.statut = 1
        AND enlevement.deleted_at IS NULL AND livraison.deleted_at IS NULL
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
