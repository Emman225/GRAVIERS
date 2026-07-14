<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'location';
    protected $fillable = [
        "numero",
        "client_id",
        "mode_paiement_id",
        "adresse_livraison_id",
        "date_location",
        "montant_total",
        "etat_location",
        "note",
        "remise",
        "statut",
        "montant_tva",
        "cout_livraison_client",
    ];

    public static function lire($id)
    {
        $obj = Location::selectRaw('location.*, mode_paiement.libelle as mode_paiement, tva_commande.montant as montant_tva, adresse_livraison.complement_adresse as adresse')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'location.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'location.adresse_livraison_id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'location.id');
                $query->where('tva_commande.type_affaire', Help::$LOCATION);
            })
            ->where('location.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function lireSurNumero($numero)
    {
        $obj = Location::where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function lireSurDevis($idDevis)
    {
        $obj = Location::where('devis_id', $idDevis)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Location();
    }

    public static function liste($client_id = null, $etat_location = null)
    {
        return Location::selectRaw('location.*, mode_paiement.libelle as mode_paiement, tva_commande.montant as montant_tva, adresse_livraison.complement_adresse as adresse')
            ->orderBy('location.id', 'desc')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'location.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'location.adresse_livraison_id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'location.id');
                $query->where('tva_commande.type_affaire', Help::$LOCATION);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('location.client_id', $client_id);
            })
            ->when($etat_location, function ($query) use ($etat_location) {
                $query->where('location.etat_location', $etat_location);
            })
            ->where('location.statut', Help::$STATUT_ACTIF)
            ->limit(500)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new location($arr);
        if ($obj->save()) return $obj;
        else return new Location();
    }

    public static function modifierEtatlocation($idlocation, $newEtat)
    {
        $obj = Location::lire($idlocation);
        $obj->etat_location = $newEtat;
        $obj->save();
        return $obj;
    }

    public static function supprimer($id)
    {
        $obj = Location::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
