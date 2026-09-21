<?php

use App\Models\Logs;
use App\Models\Configuration;
use App\Models\User;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Pagination\LengthAwarePaginator;

class Help
{
    public static $STATUT_ACTIF = 1;
    public static $STATUT_INACTIF = 2;
    public static $STATUT_DEMANDE_SUP = 3;

    public static function urlPaiement(string $url): string
    {
        // Repli sur config('app.url') (= APP_URL) : env() renvoie null après
        // `php artisan config:cache`, ce qui ferait perdre l'override de base.
        $baseUrl = env('PAIEMENT_BASE_URL') ?: config('app.url');
        if ($baseUrl) {
            // Extraire la base de l'URL générée (ex: http://10.10.10.89:8002)
            $parsed = parse_url($url);
            $currentBase = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            $url = str_replace($currentBase, rtrim($baseUrl, '/'), $url);
        }
        return str_replace('http://', 'https://', $url);
    }

    public static $STATUT_SUPPRIMER = 4;

    public static $PARTICULIER = "PARTICULIER";
    public static $ENTREPRISE = "ENTREPRISE";

    public static $COMMANDE_EN_ATTENTE = "EN ATTENTE";
    public static $COMMANDE_EN_TRAITEMENT = "EN TRAITEMENT";
    public static $COMMANDE_TERMINE = "TERMINEE";
    // Commande créée mais dont le paiement en ligne n'est pas encore confirmé :
    // volontairement hors de la file de traitement du gestionnaire tant qu'elle
    // n'est pas payée. Passe à EN ATTENTE une fois le paiement confirmé.
    public static $COMMANDE_EN_ATTENTE_PAIEMENT = "EN ATTENTE DE PAIEMENT";

    public static $LIVRAISON_EN_ATTENTE = "EN ATTENTE";
    public static $LIVRAISON_EN_TRAITEMENT = "EN TRAITEMENT";
    public static $LIVRAISON_LIVREE = "LIVREE";
    // Quatrième valeur de l'ENUM detail_commande.etat_livraison, présente côté
    // site (Help::$LIVRAISON_EN_COURS) mais qui manquait ici : la marchandise a
    // quitté le fournisseur et voyage avec le livreur.
    public static $LIVRAISON_EN_COURS = "EN COURS LIVRAISON";

    public static $BANNIERE_TOP = "TOP";
    public static $BANNIERE_FLASH = "FLASH";
    public static $BANNIERE_BOTTOM = "BOTTOM";

    public static $LOCATION = "LOCATION";
    public static $VENTE = "VENTE";

    public static $LOCATION_EN_ATTENTE = "EN ATTENTE";
    public static $LOCATION_EN_COURS = "EN COURS";
    public static $LOCATION_TERMINE = "TERMINE";

    public static $COMMANDE = "COMMANDE";
    public static $LIVRAISON = "LIVRAISON";

    public static $USER_SA = 1;
    public static $USER_ADMIN = 2;
    public static $USER_GESTIONNAIRE = 3;
    public static $USER_CLIENT = 4;
    public static $USER_FOURNISSEUR = 5;
    public static $USER_APPORTEUR = 6;
    public static $USER_AGENT_SAV = 7;
    public static $USER_LIVREUR = 8;

    public static $CODE_INSCRIPTION = 1;
    public static $CODE_PASS_OUBLIE = 2;
    public static $CODE_CONNEXION = 3;

    // Source unique des images = stockage du site web (où l'admin téléverse les
    // produits). Le mobile lit donc les mêmes images que le catalogue web.
    // 12/09/2026 : valeur par défaut ; AppServiceProvider la remplace par URL_SITE/storage/ au démarrage.
    public static $URL_BASE_FICHIER = "https://mongravier.com/storage/";

    public function __construct() {}

    public static function urlFichier($path)
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http')) return $path;
        // Si le chemin contient une URL absolue enfouie (ex: categorieImage/https://...)
        if (preg_match('/(https?:\/\/.+)$/', $path, $matches)) {
            return $matches[1];
        }
        // L'adresse suit URL_SITE (.env), plus aucun hôte en dur (17/09/2026).
        return self::baseFichiers() . $path;
    }

    /** La base des fichiers servis : le stockage du site (URL_SITE/storage/). */
    public static function baseFichiers(): string
    {
        // Hors application Laravel (essai unitaire), la base historique.
        try {
            $site = trim((string) config('constantes.url_site'));
        } catch (\Throwable $e) {
            $site = '';
        }

        return $site !== '' ? rtrim($site, '/') . '/storage/' : self::$URL_BASE_FICHIER;
    }

    public static function sansAccent($string) {
        $a = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        $b = 'aaaaaaaceeeiiiidnoooooouuuuybsaaaaaaaceeeiiiidnoooooouuuyybyRr';
        $string = mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
        $string = strtr($string, mb_convert_encoding($a, 'ISO-8859-1', 'UTF-8'), $b);
        return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
    }

    public static function formatterDate($dateString, $formatCreation = 'd/m/Y H:i', $formatRetour = 'Y-m-d H:i') {
        $newDate = new DateTime();
        try {
            $newDate = DateTime::createFromFormat($formatCreation, $dateString);
        } catch (\Throwable $th) {
            throw $th;
            $newDate = DateTime::createFromFormat("Y-m-d H:i:s", date("Y-m-d H:i:s"));
        }
        return $newDate == false ? date($formatRetour) : $newDate->format($formatRetour);
    }

    public static function getCommandeNo() {
        return time() . Help::ChaineAleatoireNombre(10);
    }

    /**
     * Largeur fixe utilisée pour tous les numéros de devis / commande / location
     * affichés aux clients (ex. 000123 / 045678 / 999999).
     */
    public static $NUMERO_FACTURE_WIDTH = 6;

    /**
     * Génère un numéro unique sur 6 chiffres pour une table donnée
     * (devis, commande, location, demande_livraison, ...).
     * Re-tire en cas de collision sur la colonne `numero`.
     */
    public static function genererNumeroUnique(string $table, string $colonne = 'numero', int $largeur = null): string
    {
        $largeur = $largeur ?? self::$NUMERO_FACTURE_WIDTH;
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
     * Numéro d'une commande — PORTÉ À L'IDENTIQUE depuis le site
     * (graviers/app/Help.php).
     *
     * Le tirage est vérifié dans devis ET dans commande. Une commande issue d'un
     * devis reprend le numéro de ce devis, et commande.numero porte un index
     * UNIQUE : ne contrôler que la table commande — ce que faisait
     * genererNumeroUnique('commande') — laissait une commande mobile prendre un
     * numéro déjà réservé par un devis. La transformation ultérieure de ce devis
     * échouait alors sur l'index, en page blanche, très loin de la cause.
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

        // Espace saturé : on élargit d'un chiffre plutôt que de rendre un numéro
        // déjà pris, qui ferait échouer l'insertion.
        return self::genererNumeroCommande($largeur + 1);
    }

    public static function getCodeParain() {
        $code = Help::ChaineAleatoire(6);
        $par = Client::lireSurCodeParrain($code);
        if ($par->id <= 0) {
            return $code;
        } else {
            return Help::ChaineAleatoire(6);
        }
    }

    public static function formatNombre($valeur, $monetaire = false, $devise = "F") {
        if ($monetaire == true) return number_format($valeur, 0, ",", ".") . " $devise";
        else return number_format($valeur, 2, ",", ".");
    }

    public static function HashPassword(String $password): String {
        return Hash::make("@#MonGr@vier#@" . $password . "@C0m@");
    }

    public static function unique_multidim_array($array, $key) {
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

    public static function array_sort($array, $on, $order = SORT_ASC) {
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

    public static function HashVerifier(String $password, String $hashPassword): bool {
        return Hash::check("@#MonGr@vier#@" . $password . "@C0m@", $hashPassword);
    }

    public static function listeStatutCommande() {
        return [
            Help::$COMMANDE_EN_ATTENTE,
            Help::$COMMANDE_EN_TRAITEMENT,
            Help::$COMMANDE_TERMINE
        ];
    }

    public static function listeStatutLivraison() {
        return [
            Help::$LIVRAISON_EN_ATTENTE,
            Help::$LIVRAISON_EN_TRAITEMENT,
            Help::$LIVRAISON_LIVREE,
        ];
    }

    public static function getRequestUser(Request $request) {
        $key = "access";
        $type = "type";
        if ($request->hasHeader($key)) {
            $headers = $request->header();
            $idUsr = Crypt::decrypt($headers[$key]);
            $user = User::lire($idUsr);
            if ($user->id > 0 && $user->type_user_id = $headers[$type]) {
                return $user;
            }
        }
        return new User();
    }

    public static function ChaineAleatoireNombre(int $nombreChaine) {
        $str = '0123456789';
        $randomStr = '';
        for ($i = 0; $i < $nombreChaine; $i++) {
            $index = rand(0, strlen($str) - 1);
            $randomStr .= $str[$index];
        }
        return $randomStr;
    }

    public static function ChaineAleatoire(int $nombreChaine) {
        $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomStr = '';
        for ($i = 0; $i < $nombreChaine; $i++) {
            $index = rand(0, strlen($str) - 1);
            $randomStr .= $str[$index];
        }
        return $randomStr;
    }

    public static function NombreCommancantParzero(int $LeNombre, int $Taille = 0) {
        $length = 0;
        ($Taille > 0) ? $length = $Taille : $length = 6;
        $char = 0;
        $type = 'd';
        $format = "%{$char}{$length}{$type}";
        $newFormat = sprintf($format, $LeNombre);
        return $newFormat;
    }

    public function paginate($items, $total, $perPage = 5, $page = null, $options = []) {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items, $total, $perPage, $page, $options);
    }

    public static function rechercheParCle($tableaux, $cle, $valeur) {
        foreach ($tableaux as $object) {
            if ($object->{$cle} == $valeur) {
                return $object;
            }
        }
        return null;
    }

    public static function dateViewToDB($dateString) {
        $myDateTime = DateTime::createFromFormat('d/m/Y H:i', $dateString);
        return $myDateTime->format('Y-m-d H:i');
    }

    public static function setElementToSession($key, $value) {
        if (session()->has($key)) {
            session()->forget($key);
        }
        session()->put($key, $value);
    }

    public static function getElementToSession($key) {
        if (session()->has($key)) {
            return session()->get($key);
        }
        return null;
    }

    public static function nombreJourEntreDeuxDate($dateDebut, $dateFin) {
        $dateDebut = str_replace("/", "-", $dateDebut);
        $dateFin = str_replace("/", "-", $dateFin);
        $date3 = strtotime($dateDebut);
        $date4 = strtotime($dateFin);
        $nbJoursTimestamp = $date4 - $date3;
        return ceil($nbJoursTimestamp / 86400);
    }

    public static function sommePropriete(array $array, string $propriete) {
        $ret = array_reduce($array, function ($carry, $item) use ($propriete) {
            return $carry + $item->{$propriete};
        });
        return $ret ?? 0;
    }

    public static function afficherTempsEcoule($dateHeure) {
        $dateDonnee = Carbon::parse($dateHeure);
        $dateActuelle = Carbon::now();
        return str_replace("avant", "", $dateDonnee->diffForHumans($dateActuelle));
    }

    /**
     * LA TVA SUR LE TRANSPORT, POUR TOUTES LES AFFAIRES (point 5, 07/09/2026).
     *
     * Même règle que le site (Help::tvaSurTransport) : la case « TVA sur le
     * transport » du paramétrage vaut pour les ventes et les locations comme
     * pour les demandes de livraison. Le taux est celui du client (0 s'il
     * n'est pas assujetti), et le montant est figé sur l'affaire.
     */
    /**
     * TVA marchandise du client, en pourcentage (10/09/2026) : appliquée par
     * défaut, retirable par client (applique_tva à 0). Même règle que le site.
     */
    public static function tauxTvaClient($client): float
    {
        if ($client && $client->applique_tva !== null && (int) $client->applique_tva === 0) {
            return 0.0;
        }
        $taux = \App\Models\Configuration::first()?->tva;

        return ($taux === null || $taux === '') ? 18.0 : (float) $taux;
    }

    /**
     * TVA sur le transport POUR UN CLIENT, en pourcentage : nulle si la
     * configuration ne taxe pas le transport ou si ce client en est dispensé
     * (applique_tva_transport à 0), indépendamment de la TVA marchandise.
     */
    public static function tauxTvaTransportClient($client): float
    {
        $conf = \App\Models\Configuration::first();
        if ((int) ($conf?->tva_transport ?? 0) !== 1) {
            return 0.0;
        }
        if ($client && $client->applique_tva_transport !== null && (int) $client->applique_tva_transport === 0) {
            return 0.0;
        }
        $taux = $conf?->tva;

        return ($taux === null || $taux === '') ? 18.0 : (float) $taux;
    }

    public static function tvaTransportPour($client, float $coutTransport): float
    {
        if ($coutTransport <= 0) {
            return 0.0;
        }

        return (float) round($coutTransport * self::tauxTvaTransportClient($client) / 100);
    }

    /** Le client est soumis à l'AIRSI s'il n'a pas déclaré un régime réel (RNI / RSI). */
    public static function soumisAirsi($client): bool
    {
        if (!$client) {
            return false;
        }
        $v = strtoupper(trim((string) ($client->regime_imposition ?? '')));
        foreach (['RNI', 'RSI'] as $code) {
            if ($v === $code || preg_match('/^' . $code . '\s*[—\-–]/u', $v)) {
                return false;
            }
        }

        return true;
    }

    /** Taux de l'AIRSI en pourcentage (5 par défaut). */
    public static function tauxAirsi(): float
    {
        $taux = \App\Models\Configuration::first()?->taux_airsi;

        return ($taux === null || $taux === '') ? 5.0 : (float) $taux;
    }

    /** L'AIRSI dû sur une base TTC (HT net + TVA) : 0 pour un client au réel. */
    public static function airsiPour($client, float $baseTtc, ?float $htNet = null, ?float $tauxTva = null,
        float $transport = 0, float $tauxTvaTransport = 0): float
    {
        if ($baseTtc <= 0 || !self::soumisAirsi($client)) {
            return 0.0;
        }
        $taux = self::tauxAirsi();

        // Le transport entre dans l'assiette, comme la DGI (lot 97, 16/09/2026) ;
        // même règle que le site : l'AIRSI figé porte l'écart d'arrondi.
        if ($htNet !== null && $tauxTva !== null && $htNet > 0 && $transport > 0) {
            $netDgi = round(($htNet * (1 + $tauxTva) + $transport * (1 + $tauxTvaTransport)) * (1 + $taux / 100));
            $base = round($baseTtc) + round($transport) + round($transport * $tauxTvaTransport);

            return (float) max(0, $netDgi - $base);
        }

        // L'ARRONDI DE LA DGI (lot 94, 16/09/2026), même règle que le site :
        // net = arrondi(HT × (1 + TVA) × (1 + AIRSI)) ; l'AIRSI figé porte l'écart
        // d'arrondi (taux de TVA en fraction, 0,18).
        if ($htNet !== null && $tauxTva !== null && $htNet > 0) {
            $netDgi = round($htNet * (1 + $tauxTva) * (1 + $taux / 100));

            return (float) max(0, $netDgi - round($baseTtc));
        }

        return (float) round($baseTtc * $taux / 100);
    }

    public static function tvaSurTransport(float $coutTransport, float $tauxPourcent): float
    {
        if ($coutTransport <= 0 || $tauxPourcent <= 0) {
            return 0.0;
        }

        $conf = \App\Models\Configuration::first();
        if ((int) ($conf->tva_transport ?? 0) !== 1) {
            return 0.0;
        }

        return (float) round($coutTransport * $tauxPourcent / 100);
    }

    public static function ecrireLog($fn, $titre, $details, $user_id) {
        $log = new Logs();
        $log->fn = $fn;
        $log->titre = $titre;
        $log->details = $details;
        $log->user_id = $user_id;
        $log->save();
    }

    public static function distance($long1, $lat1, $long2, $lat2) {
        $earthRadius = 6371;
        $lon1 = deg2rad($long1);
        $lat1 = deg2rad($lat1);
        $lon2 = deg2rad($long2);
        $lat2 = deg2rad($lat2);
        $latDelta = $lat2 - $lat1;
        $lonDelta = $lon2 - $lon1;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));
        return intVal($angle * $earthRadius);
    }
    /**
     * LES POINTS DE FIDÉLITÉ GAGNÉS POUR UN MONTANT ENCAISSÉ.
     *
     * UNE SEULE RÈGLE, POUR TOUS LES CANAUX : X francs réellement encaissés
     * donnent 1 point. Trois règles s'appliquaient auparavant selon la façon de
     * payer — 200 points forfaitaires au guichet, 1 à 15 selon une grille par
     * le mobile, rien depuis le site — si bien que le CANAL décidait de la
     * récompense, ce qu'aucun client n'aurait compris.
     *
     * Sur ce qui est ENCAISSÉ, jamais sur le montant commandé : un acompte
     * rapporte au prorata, et une commande impayée ne rapporte rien.
     *
     * Le résultat est tronqué, non arrondi : 1 999 F ne peuvent pas valoir
     * 2 points quand la règle en annonce 1 pour 1 000. Mieux vaut promettre
     * moins et tenir.
     */
    public static function pointsPour($montant): int
    {
        $parPoint = (float) (Configuration::first()->montant_pour_un_point ?? 0);

        // Paramètre absent ou nul : on n'attribue rien plutôt que de diviser
        // par zéro. Un écran de paramétrage mal rempli ne doit pas casser un
        // encaissement.
        if ($parPoint <= 0) {
            return 0;
        }

        return (int) floor(max(0, (float) $montant) / $parPoint);
    }

    /**
     * PLANCHER DE PAIEMENT : combien de points le client peut réellement poser
     * sur cette commande.
     *
     * Les points pouvaient couvrir la totalité d'une commande, et le total à
     * payer tombait à ZÉRO. C'était une impasse quel que soit le mode choisi :
     * la passerelle était appelée avec 0, l'encaissement au guichet refusait le
     * montant nul (`min:1`), et le virement supposait un justificatif de 0 —
     * alors que les points, eux, étaient déjà débités.
     *
     * Les points ne réduisent donc le total que jusqu'à `montant_minimum_a_payer`.
     * Le reliquat RESTE au compte du client : rien n'est perdu, la remise est
     * seulement étalée sur la commande suivante.
     *
     * Le calcul, en partant du total réellement dû :
     *
     *     total = (ht − remise) × (1 + tva) + livraison   ⩾   plancher
     *  ⟺  ht − remise                                     ⩾   (plancher − livraison) / (1 + tva)
     *  ⟺  remise                                          ⩽   ht − htMinimum
     *
     * La livraison n'est jamais effacée par une remise : dès qu'elle atteint le
     * plancher à elle seule, les points peuvent couvrir toute la marchandise —
     * le client paiera son transport, et le total ne sera pas nul.
     *
     * @param float $ht          Marchandise hors taxe, avant toute remise.
     * @param float $remisePromo Remise du code promo, déjà calculée.
     * @param float $tauxTva     Taux de TVA en POURCENTAGE (18 pour 18 %).
     * @param float $livraison   Coût de livraison, hors remise.
     * @param float $valeurPoint Ce que vaut un point, en francs.
     * @param float $demandes    Points que le client souhaite utiliser.
     * @param float $solde       Points réellement détenus par le client.
     *
     * @return float Points utilisables, jamais supérieurs au solde ni à la demande.
     */
    public static function pointsUtilisables(
        float $ht,
        float $remisePromo,
        float $tauxTva,
        float $livraison,
        float $valeurPoint,
        float $demandes,
        float $solde
    ): float {
        $possible = min(max(0.0, $demandes), max(0.0, $solde));

        // Un point sans valeur ne réduit rien : inutile d'en consommer, et
        // surtout pas de diviser par zéro.
        if ($possible <= 0 || $valeurPoint <= 0) {
            return 0.0;
        }

        $plancher = (float) (Configuration::first()->montant_minimum_a_payer ?? 0);

        // Plancher non paramétré : on ne bride rien, l'ancien comportement
        // s'applique. Mieux vaut cela qu'un plancher inventé.
        if ($plancher <= 0) {
            return $possible;
        }

        $coefficient = 1 + (max(0.0, $tauxTva) / 100);
        $htMinimum = max(0.0, ($plancher - max(0.0, $livraison)) / $coefficient);

        // Ce que la remise TOTALE ne doit pas dépasser, puis ce qu'il reste
        // pour les points une fois le code promo honoré : la remise promo est
        // un engagement commercial déjà pris, ce sont les points qui cèdent.
        $remiseMax = max(0.0, $ht - $htMinimum);
        $pourLesPoints = max(0.0, $remiseMax - max(0.0, $remisePromo));

        // Arrondi À L'INFÉRIEUR : un point entamé serait un point facturé au
        // client sans qu'il en voie l'effet entier.
        $utilisables = floor($pourLesPoints / $valeurPoint);

        return min($possible, max(0.0, $utilisables));
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
    public $email;
    public $livreur;
    public $apporteur;
    public $data;
    public $tva;
    public $nomBrownPoint;
    public $montantPoint;
    public $device;
    public $cat;

}