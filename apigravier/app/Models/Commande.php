<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'commande';
    protected $fillable = [
        'numero',
        'devis_id',
        'client_id',
        'adresse_livraison_id',
        'mode_paiement_id',
        'date_commande',
        'date_livraison',
        'montant_total',
        'etat_commande',
        'date_fin_livraison',
        'statut',
        'note',
        'remise',
        'type_livraison_id',
        'type_livraison',
        'cout_livraison_client',
        'cout_reduction',
        'fichier_bl',
        'numero_bl',
    ];

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = Commande::selectRaw("commande.*, mode_paiement.libelle as mode_paiement,tva_commande.montant as montant_tva,
        adresse_livraison.complement_adresse as adresse, type_livraison.libelle as type_livraison,
        bl_client.numero as numero_bl, concat('$url',bl_client.fichier) as fichier_bl")
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftjoin('type_livraison', 'type_livraison.id', '=', 'commande.type_livraison_id')
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'commande.id');
                $query->where('tva_commande.type_affaire', Help::$VENTE);
            })
            ->where('commande.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Commande();
    }

    public static function lireSurNumero($numero)
    {
        $obj = Commande::where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Commande();
    }

    public static function lireSurDevis($idDevis)
    {
        $obj = Commande::where('devis_id', $idDevis)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Commande();
    }

    public static function liste($client_id = null, $etat_commande = null)
    {
        $url = Help::$URL_BASE_FICHIER;
        return Commande::selectRaw("commande.*,
        mode_paiement.libelle as mode_paiement,
        adresse_livraison.complement_adresse as adresse,
        tva_commande.montant as montant_tva,
        bl_client.numero as numero_bl,
        concat('$url',bl_client.fichier) as fichier_bl")
            ->orderBy('commande.id', 'desc')
            ->leftJoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftJoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->leftJoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'commande.id');
                $query->where('tva_commande.type_affaire', Help::$VENTE);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('commande.client_id', $client_id);
            })
            ->when($etat_commande, function ($query) use ($etat_commande) {
                $query->where('commande.etat_commande', $etat_commande);
            })
            ->where('commande.statut', Help::$STATUT_ACTIF)
            ->limit(500)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Commande($arr);
        if ($obj->save()) return $obj;
        else return new Commande();
    }

    public static function modifierEtatCommande($idCommande, $newEtat)
    {
        $obj = new Commande($idCommande);
        $obj->etat_commande = $newEtat;
        $obj->save();
        return $obj;
    }

    public static function supprimer($id)
    {
        $obj = Commande::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
}
