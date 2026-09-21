<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Livraison;
use App\Models\User;
use App\Models\Apporteur;

class Client extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'client';
    protected $fillable = [
        'user_id',
        'nom',
        'prenom',
        'email',
        'contact1',
        'contact2',
        'code_parrain',
        'rccm_clt',
        'ncc_clt',
        'type_client',
        'statut',
        'point',
        'regime_imposition',
        'dfe',
        'registre_commerce',
        'applique_tva',
        // TVA sur le transport, retirable par client (10/09/2026).
        'applique_tva_transport',
        'client_a_terme',
        'parrain_id',
    ];

    public static function lire($id)
    {
        $obj = Client::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Client();
    }

    /**
     * Le nom sous lequel le client doit apparaître partout.
     *
     * Une entreprise a une RAISON SOCIALE, pas un prénom. Le formulaire recopie
     * pourtant la raison sociale dans les deux champs : afficher « nom prénom »
     * donnait « TEST TEST ». On ne rend donc que le nom dès que le prénom
     * n'apporte rien.
     *
     * MÊME RÈGLE que dans le site (App\Models\Client du projet graviers) : les
     * deux applications lisent la même base et doivent nommer le client de la
     * même façon, sur le site comme dans les applications mobiles.
     */
    public function getDisplayNameAttribute(): string
    {
        $nom = trim((string) $this->nom);
        $prenom = trim((string) $this->prenom);
        if ($this->type_client === 'ENTREPRISE' || $prenom === '' || $prenom === $nom) {
            return $nom;
        }
        return trim($nom . ' ' . $prenom);
    }

    /** La même règle, exprimée en SQL, pour les requêtes qui composent le nom. */
    public static function sqlNomAffiche(string $alias = 'client'): string
    {
        return "TRIM(CASE
                WHEN {$alias}.type_client = 'ENTREPRISE'
                     OR {$alias}.prenom IS NULL
                     OR TRIM({$alias}.prenom) = ''
                     OR TRIM({$alias}.prenom) = TRIM({$alias}.nom)
                THEN {$alias}.nom
                ELSE CONCAT(TRIM({$alias}.nom), ' ', TRIM({$alias}.prenom))
            END)";
    }


    public static function lireSurUser($idUser)
    {
        $obj = Client::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Client();
    }

    public static function listeFilleule($parrain_id)
    {
        return Client::orderBy('nom', 'asc')
            ->orderBy('prenom', 'asc')
            ->when($parrain_id, function ($query) use ($parrain_id) {
                $query->where('parrain_id', $parrain_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function liste($code_parrain = null, $type_client = null)
    {
        return Client::orderBy('nom', 'asc')
            ->orderBy('prenom', 'asc')
            ->when($code_parrain, function ($query) use ($code_parrain) {
                $query->where('code_parrain', $code_parrain);
            })
            ->when($type_client, function ($query) use ($type_client) {
                $query->where('type_client', $type_client);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Client($arr);
        if ($obj->save()) return $obj;
        else return new Client();
    }

    public static function supprimer($id)
    {
        $obj = Client::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    /**
     * Get all of the commande for the Client
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Commande()
    {
        return $this->hasMany(Commande::class);
    }

    public function produits(){
        return $this->belongsToMany(Produit::class, 'likes');
    }

    public function Livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function apporteur(){
        return $this->belongsTo(Apporteur::class,'code_parrain');
    }

    // ------------------------------------------------------------------
    //  Crédit d'un client à terme — PORTÉ À L'IDENTIQUE depuis le site
    //  (graviers/app/Models/Client.php).
    //
    //  La limite de crédit ne vaut que si elle vaut sur les DEUX canaux : une
    //  limite qu'on contourne en ouvrant l'application n'est pas une limite.
    //  Toute correction ici doit être reportée là-bas, et réciproquement.
    // ------------------------------------------------------------------

    /**
     * Encours de crédit : tout ce que le client doit encore sur ses commandes.
     *
     * On se fonde sur les COMMANDES et non sur les factures : une facture n'est
     * émise qu'après livraison, alors que le crédit est engagé dès la commande.
     *
     * Une commande « EN ATTENTE DE PAIEMENT » est écartée : c'est un règlement en
     * ligne lancé puis abandonné, jamais confirmé. Aucune marchandise n'est
     * engagée, et comme elle ne sera jamais soldée, la compter amputerait le
     * plafond définitivement.
     */
    public function encoursCredit(): float
    {
        // Une LOCATION engage le crédit comme une commande : le client dispose du
        // matériel et paiera plus tard. L'omettre laissait une location hors du
        // plafond, et permettait ensuite de commander comme si rien n'était dû.
        $locations = (float) Location::where('client_id', $this->id)
            ->where('etat_location', '!=', 'ANNULEE')
            ->get()
            ->sum(fn (Location $l) => $l->montantRestantDu());

        return $locations + (float) Commande::where('client_id', $this->id)
            ->whereNotIn('etat_commande', [
                'ANNULEE',
                Help::$COMMANDE_EN_ATTENTE_PAIEMENT,
            ])
            ->get()
            ->sum(fn (Commande $c) => $c->montantRestantDu());
    }

    /**
     * Crédit encore disponible, jamais négatif.
     *
     * Renvoie null quand aucun plafond n'est défini : on ne fait pas respecter une
     * limite qui n'existe pas, et l'appelant doit en décider plutôt que de recevoir
     * un 0 trompeur.
     */
    public function plafondDisponible(): ?float
    {
        $plafond = (float) ($this->plafond_credit ?? 0);

        if ($plafond <= 0) {
            return null;
        }

        return max(0, $plafond - $this->encoursCredit());
    }
}
