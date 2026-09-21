<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Paiement;
use App\Models\Commande;
use App\Models\DetailDevis;
use App\models\LignePaiement;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'devis';
    protected $fillable = [
        "numero",
        "client_id",
        "montant",
        "statut",
        "libelle",
        "numero_bon_commande",
    ];

    public static function lire($id)
    {
        $obj = Devis::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Devis();
    }

    public static function lireNumero($numero)
    {
        $obj = Devis::where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Devis();
    }

    /**
     * Devis d'un client, par statut.
     *
     * Le statut par défaut reste ACTIF — les devis encore ouverts — pour que
     * les applications DÉJÀ INSTALLÉES, qui n'envoient pas ce paramètre,
     * continuent de recevoir exactement la même liste qu'auparavant. Les
     * versions récentes demandent le statut 2 pour l'historique des devis
     * transformés en commande.
     */
    public static function liste($client_id = null, $statut = null)
    {
        $statut = $statut !== null && $statut !== '' ? (int) $statut : Help::$STATUT_ACTIF;

        return Devis::selectRaw('devis.*, adresse_livraison.complement_adresse as adresse_livraison')
        ->when($client_id, function ($query) use ($client_id) {
            $query->where('devis.client_id', $client_id);
        })
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'devis.adresse_livraison_id')
            ->where('devis.statut', $statut)
            // Les plus récents en tête : un historique se lit à l'envers.
            ->orderByDesc('devis.created_at')
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Devis($arr);
        if ($obj->save()) return $obj;
        else return new Devis();
    }

    public static function supprimer($id)
    {
        $obj = Devis::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }
    public function commandes()
    {
        return $this->hasOne(Commande::class);
    }
    public function detailDevis()
    {
        return $this->hasMany(DetailDevis::class);
    }

    /*public function lignePaiement(): hasManyThrough
    {
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }*/
}
