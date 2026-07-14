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
        'client_a_terme',
        'parrain_id',
    ];

    public static function lire($id)
    {
        $obj = Client::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Client();
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
}
