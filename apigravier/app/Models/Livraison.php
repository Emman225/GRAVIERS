<?php

namespace App\Models;

use Help;
use App\Models\Client;
use App\Models\Livreur;
use App\Models\Enlevement;
use App\Models\Fournisseur;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livraison extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'livraison';
    protected $fillable = [
        'numero',
        'livreur_id',
        'client_id',
        'commande_id',
        'detail_commande_id',
        'detail_livraison_id',
        'adresse_livraison_id',
        'cout_livraison',
        'date_livraison',
        'qte',
        'note_livreur',
        'etat_livraison',
        'statut',
        'provenance',
        'vehicule_id',
        'type_livraison_id',
        'accepte',
        'date_accord',
        'date_affectation_livreur',
    ];

    public static function lire($id)
    {
        $obj = Livraison::distinct()
            ->selectRaw("livraison.*,
        users.nom_prenoms as nom_livreur,
        users.contact as contact_livreur,
        concat(client.nom,' ',client.prenom) as nom_client,
        client.contact1 as contact_client,
        adresse_livraison.affichage as adresse,
        adresse_livraison.complement_adresse,
        adresse_livraison.longitude,
        adresse_livraison.latitude,
        type_livraison.libelle as type_livraison,
        enlevement.code_enleve as code_enlevement,
        fournisseur.nom_prenoms as nom_fournisseur,
        fournisseur.contact1 as tel_fournisseur,
        fournisseur.adresse_geo as adresse_fournisseur,
        fournisseur.longitude as longitude_fournisseur,
        fournisseur.latitude as latitude_fournisseur
        ")
            ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
            ->join('users', 'users.id', '=', 'livreur.user_id')
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->leftJoin('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->leftJoin('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->where('livraison.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livraison();
    }

    public static function statLivreur($livreur_id)
    {
        return DB::select("
        SELECT
          (SELECT count(livraison.id) from livraison where livreur_id = $livreur_id AND statut = 1 AND accepte IN (1,2) AND etat_livraison IN ('EN ATTENTE')) as attente,
          (SELECT count(livraison.id) from livraison where livreur_id = $livreur_id AND statut = 1 AND accepte IN (1,2) AND etat_livraison = 'LIVREE') as livree
        ");
    }

    public static function lireNumero($numero)
    {
        $obj = Livraison::distinct()
            ->selectRaw("livraison.*,
        users.nom_prenoms as nom_livreur,
        users.contact as contact_livreur,
        concat(client.nom,' ',client.prenom) as nom_client,
        client.contact1 as contact_client,
        adresse_livraison.affichage as adresse,
        adresse_livraison.complement_adresse,
        adresse_livraison.longitude,
        adresse_livraison.latitude,
        type_livraison.libelle as type_livraison,
        enlevement.code_enleve as code_enlevement,
        fournisseur.nom_prenoms as nom_fournisseur,
        fournisseur.contact1 as tel_fournisseur,
        fournisseur.adresse_geo as adresse_fournisseur,
        fournisseur.longitude as longitude_fournisseur,
        fournisseur.latitude as latitude_fournisseur
        ")
            ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
            ->join('users', 'users.id', '=', 'livreur.user_id')
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->leftJoin('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->leftJoin('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->where('numero', $numero)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livraison();
    }

    public static function liste($livreur_id = null, $client_id = null, $detail_commande_id = null, $detail_livraison_id = null, $adresse_livraison_id = null)
    {
        return Livraison::distinct()
            ->selectRaw("livraison.*,
            users.nom_prenoms as nom_livreur,
            users.contact as contact_livreur,
            concat(client.nom,' ',client.prenom) as nom_client,
            client.contact1 as contact_client,
            adresse_livraison.affichage as adresse,
            adresse_livraison.complement_adresse,
            adresse_livraison.longitude,
            adresse_livraison.latitude,
            type_livraison.libelle as type_livraison,
            enlevement.code_enleve as code_enlevement,
            fournisseur.nom_prenoms as nom_fournisseur,
            fournisseur.contact1 as tel_fournisseur,
            fournisseur.adresse_geo as adresse_fournisseur,
            fournisseur.longitude as longitude_fournisseur,
            fournisseur.latitude as latitude_fournisseur
            ")
            ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
            ->join('users', 'users.id', '=', 'livreur.user_id')
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->leftJoin('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->leftJoin('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->orderBy('livraison.id', 'desc')
            ->when($livreur_id, function ($query) use ($livreur_id) {
                $query->where('livraison.livreur_id', $livreur_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('livraison.client_id', $client_id);
            })
            ->when($detail_commande_id, function ($query) use ($detail_commande_id) {
                $query->where('livraison.detail_commande_id', $detail_commande_id);
            })
            ->when($detail_livraison_id, function ($query) use ($detail_livraison_id) {
                $query->where('livraison.detail_livraison_id', $detail_livraison_id);
            })
            ->when($adresse_livraison_id, function ($query) use ($adresse_livraison_id) {
                $query->where('livraison.adresse_livraison_id', $adresse_livraison_id);
            })
            ->where('livraison.statut', Help::$STATUT_ACTIF)
            ->whereIn('livraison.accepte', [1, 2])
            ->limit(1000)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Livraison($arr);
        if ($obj->save()) return $obj;
        else return new Livraison();
    }

    public static function supprimer($id)
    {
        $obj = Livraison::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function livreur()
    {
        return $this->belongsTo(Livreur::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function commande()
    {
        return $this->belongsTo(Commande::class);
    }
    public function fournisseurs()
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function AdresseLivraison()
    {
        return $this->belongsTo(AdresseLivraison::class);
    }
    /**
     * Get the enlevement associated with the Livraison
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function enlevement()
    {
        return $this->hasOne(Enlevement::class, 'livraison_id');
    }
}
