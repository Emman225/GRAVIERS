<?php

use App\Models\Logs;
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

    // public static $URL_BASE_FICHIER = "https://gravierci.com/public/storage/";
    // public static $URL_BASE_FICHIER = "https://test.gravierci.com/public/storage/";
    // public static $URL_BASE_FICHIER = "https://apigravier.fneconnect.net/storage/";
    // Source unique des images = stockage du site web (où l'admin téléverse les
    // produits). Le mobile lit donc les mêmes images que le catalogue web.
    public static $URL_BASE_FICHIER = "https://graviers.fneconnect.net/storage/";

    public function __construct() {}

    public static function urlFichier($path)
    {
        if (empty($path)) return null;
        if (str_starts_with($path, 'http')) return $path;
        // Si le chemin contient une URL absolue enfouie (ex: categorieImage/https://...)
        if (preg_match('/(https?:\/\/.+)$/', $path, $matches)) {
            return $matches[1];
        }
        return self::$URL_BASE_FICHIER . $path;
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