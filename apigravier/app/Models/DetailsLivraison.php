<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DetailsLivraison extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'detail_livraison';
    protected $fillable = [
        "nom_produit",
        "qte",
        "unite",
        "description",
        "poids_vehicule_souhaite",
        "nombre_voyage",
        "demande_livraison_id",
        "etat_livraison",
        "statut",
    ];

    public static function lire($id)
    {
        $obj = DetailsLivraison::selectRaw("detail_livraison.*,
                demande_livraison.numero,
                demande_livraison.libelle,
                demande_livraison.description,
                demande_livraison.client_id,
                pec.affichage as affichage_pec,
                pec.complement_adresse as complement_adresse_pec,
                pec.longitude as longitude_pec,
                pec.latitude as latitude_pec,
                dest.affichage as affichage_dest,
                dest.complement_adresse as complement_adresse_dest,
                dest.longitude as longitude_dest,
                dest.latitude as latitude_dest,
                demande_livraison.montantTotal,
                demande_livraison.etat_commande,
                demande_livraison.date_livraison,
                demande_livraison.date_fin_livraison,
                demande_livraison.remise,
                mode_paiement.libelle as mode_paiement,
                type_livraison.libelle as type_livraison
            ")
            ->join('demande_livraison', 'demande_livraison.id', '=', 'detail_livraison.demande_livraison_id')
            ->join('type_livraison', 'type_livraison.id', '=', 'demande_livraison.type_livraison_id')
            ->join('mode_paiement', 'mode_paiement.id', '=', 'demande_livraison.mode_paiement_id')
            ->join('adresse_livraison as pec', 'pec.id', '=', 'demande_livraison.adresse_livraison_pec_id')
            ->join('adresse_livraison as dest', 'dest.id', '=', 'demande_livraison.adresse_livraison_dest_id')
            ->where('detail_livraison.id', $id)
            ->where('demande_livraison.statut', Help::$STATUT_ACTIF)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new DetailsLivraison();
    }

    public static function liste($client_id = null, $demande_livraison_id = null, $etat_livraison = null)
    {
        return DetailsLivraison::distinct()
            ->selectRaw("detail_livraison.*,
demande_livraison.numero,
demande_livraison.libelle,
demande_livraison.description as description_demande,
demande_livraison.adresse_livraison_pec_id,
demande_livraison.adresse_livraison_dest_id,
demande_livraison.montantTotal,
demande_livraison.etat_commande,
demande_livraison.date_livraison,
demande_livraison.date_fin_livraison,
demande_livraison.remise,
pec.affichage as affichage_pec,
pec.complement_adresse as complement_adresse_pec,
pec.longitude as longitude_pec,
pec.latitude as latitude_pec,
dest.affichage as affichage_dest,
dest.complement_adresse as complement_adresse_dest,
dest.longitude as longitude_dest,
dest.latitude as latitude_dest
")
            ->join('demande_livraison', 'demande_livraison.id', '=', 'detail_livraison.demande_livraison_id')
            ->join('adresse_livraison as pec', 'pec.id', '=', 'demande_livraison.adresse_livraison_pec_id')
            ->join('adresse_livraison as dest', 'dest.id', '=', 'demande_livraison.adresse_livraison_dest_id')
            ->when($etat_livraison, function ($query) use ($etat_livraison) {
                $query->where('detail_livraison.etat_livraison', $etat_livraison);
            })
            ->when($demande_livraison_id, function ($query) use ($demande_livraison_id) {
                $query->where('demande_livraison.id', $demande_livraison_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('demande_livraison.client_id', $client_id);
            })
            ->where('detail_livraison.statut', Help::$STATUT_ACTIF)
            ->where('demande_livraison.statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new DetailsLivraison($arr);
        if ($obj->save()) return $obj;
        else return new DetailsLivraison();
    }

    public static function supprimer($id)
    {
        $obj = DetailsLivraison::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
