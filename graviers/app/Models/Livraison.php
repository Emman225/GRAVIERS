<?php

namespace App\Models;

use Help;
use App\Models\User;
use App\Models\Client;
use App\Models\Livreur;
// use App\Models\Enlevement;
use App\Models\Produit;
use App\Models\Vehicule;
use App\Models\Fournisseur;
use App\Models\DetailCommande;
use App\Models\DetailLivraison;
use App\Models\DemandeLivraison;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livraison extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'livraison';

    /**
     * Retourne le nom de la colonne facture_id dans enlevement
     * (gère la typo fature_id vs facture_id selon la base)
     */
    public static function getFactureIdColumn(): string
    {
        static $col = null;
        if ($col === null) {
            $col = Schema::hasColumn('enlevement', 'facture_id') ? 'facture_id' : 'fature_id';
        }
        return $col;
    }

    protected $fillable = [
        'numero',
        'livreur_id',
        'client_id',
        'commande_id',
        'adresse_livraison_id',
        'date_livraison',
        'statut',
        'detail_commande_id',
        'qte',
        'note_livreur',
        'type_livraison_id',
        'etat_livraison',
        'cout_livraison',
        'detail_livraison_id',
        'provenance',
        'user_id',
        'accepte',
        'date_accord',
        'gestionnaire_id',
        'statut',
        'vehicule_id',
        'date_affectation_livreur',
        'livre_par',
        'distance_km',
        'forfait_base',
        'frais_km',
        'statut_paiement_livreur',
        'date_paiement_livreur',
    ];

    protected $casts = [
        'date_paiement_livreur' => 'date',
        'date_livraison'        => 'date',
    ];

    public function paiementsLivreur()
    {
        return $this->hasMany(PaiementLivreur::class, 'livraison_id');
    }

    /**
     * Total dû au livreur pour cette livraison.
     * Si forfait_base/frais_km sont saisis, on les somme. Sinon on retombe sur cout_livraison.
     */
    public function totalDuLivreur(): float
    {
        $forfait = (float) ($this->forfait_base ?? 0);
        $fraisKm = (float) ($this->frais_km ?? 0);
        if ($forfait > 0 || $fraisKm > 0) {
            return $forfait + $fraisKm;
        }
        return (float) ($this->cout_livraison ?? 0);
    }

    public function montantPayeLivreur(): float
    {
        return (float) PaiementLivreur::where('livraison_id', $this->id)
            ->where('statut', 1)->sum('montant');
    }

    public function resteAPayerLivreur(): float
    {
        return max(0, $this->totalDuLivreur() - $this->montantPayeLivreur());
    }

    /**
     * Statut paiement livreur calculé selon les règles Excel :
     * - statut_paiement_livreur manuel (Annulée / En contestation) prioritaire
     * - sinon : Payée (paye>=total), Validée à payer (livré, en attente paiement),
     *   Livraison effectuée (en cours)
     */
    public function statutPaiementLivreurCalcule(): string
    {
        if (!empty($this->statut_paiement_livreur)) {
            return $this->statut_paiement_livreur;
        }
        $paye  = $this->montantPayeLivreur();
        $total = $this->totalDuLivreur();
        if ($total > 0 && $paye >= $total) {
            return 'Payée';
        }
        if ($this->etat_livraison === 'LIVREE') {
            return 'Validée à payer';
        }
        return 'Livraison effectuée';
    }

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
            ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->join('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->where('livraison.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livraison();
    }

    public function vehicule(){
        return $this->belongsTo(Vehicule::class, 'vehicule_id');
    }

    public function gestionnaire (){
        return $this->belongsTo(User::class, 'gestionnaire_id');
    }

    public static function statLivreur($livreur_id)
    {
        return DB::select("
        SELECT
          (SELECT count(livraison.id) from livraison where livreur_id = $livreur_id AND etat_livraison = 'EN TRAITEMENT') as attente,
          (SELECT count(livraison.id) from livraison where livreur_id = $livreur_id AND etat_livraison = 'LIVREE') as livree
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
            ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->join('client', 'client.id', '=', 'livraison.client_id')
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
            concat(gestionnaire.nom_prenoms,' - ',gestionnaire.contact) as gestionnaire,
            concat(client.nom,' ',client.prenom) as nom_client,
            client.contact1 as contact_client,
            adresse_livraison.affichage as adresse,
            adresse_livraison.complement_adresse,
            adresse_livraison.longitude,
            adresse_livraison.latitude,
            type_livraison.libelle as type_livraison,
            enlevement.id as id_enlevement,
            enlevement.code_enleve as code_enlevement,
            enlevement." . self::getFactureIdColumn() . " as facture_id,
            facture.numero as numero_facture,
            enlevement.livraison_id as livraison_id,
            enlevement.qte as qte_enleve,
            fournisseur.nom_prenoms as nom_fournisseur,
            fournisseur.contact1 as tel_fournisseur,
            fournisseur.adresse_geo as adresse_fournisseur,
            fournisseur.longitude as longitude_fournisseur,
            fournisseur.latitude as latitude_fournisseur
            ")
            ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
            ->join('users', 'users.id', '=', 'livreur.user_id')
            ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->join('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftjoin('users as gestionnaire', 'gestionnaire.id', '=', 'enlevement.gestionnaire_id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->leftJoin('facture', 'facture.id', '=', 'enlevement.' . self::getFactureIdColumn())
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

    public static function listeSansLivraison($livreur_id = null, $client_id = null, $detail_commande_id = null, $detail_livraison_id = null, $adresse_livraison_id = null){
        return Livraison::distinct()
            ->selectRaw("livraison.*,
            concat(gestionnaire.nom_prenoms,' - ',gestionnaire.contact) as gestionnaire,
            concat(client.nom,' ',client.prenom) as nom_client,
            client.contact1 as contact_client,
            type_livraison.libelle as type_livraison,
            enlevement.id as id_enlevement,
            enlevement.code_enleve as code_enlevement,
            enlevement." . self::getFactureIdColumn() . " as facture_id,
            facture.numero as numero_facture,
            enlevement.livraison_id as livraison_id,
            enlevement.qte as qte_enleve,
            fournisseur.nom_prenoms as nom_fournisseur,
            fournisseur.contact1 as tel_fournisseur,
            fournisseur.adresse_geo as adresse_fournisseur,
            fournisseur.longitude as longitude_fournisseur,
            fournisseur.latitude as latitude_fournisseur
            ")
            ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
            ->join('client', 'client.id', '=', 'livraison.client_id')
            ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
            ->leftjoin('users as gestionnaire', 'gestionnaire.id', '=', 'enlevement.gestionnaire_id')
            ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->leftJoin('facture', 'facture.id', '=', 'enlevement.' . self::getFactureIdColumn())
            ->orderBy('livraison.id', 'desc')

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

    public function livreur(){
        return $this->belongsTo(Livreur::class)->withDefault([]);
    }
    public function clientLivreur(){
        return $this->belongsTo(Client::class, 'livreur_id')->withDefault(['nom'=>'','prenom'=>'']);
    }

    public function detailCommande(){
        return $this->belongsTo(DetailCommande::class)->withDefault([]);
    }

    public function detailLivraison(){
        return $this->belongsTo(DetailLivraison::class)->withDefault([]);
    }

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }
    public function commande(){
        return $this->belongsTo(Commande::class)->withDefault(['numero'=>'','montant_total'=>0]);
    }
    public function fournisseurs(){
        return $this->belongsTo(Fournisseur::class)->withDefault(['nom_prenoms'=>'']);
    }

    public function AdresseLivraison(){
        return $this->belongsTo(AdresseLivraison::class)->withDefault([]);
    }

    public function user(){
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'']);
    }

    public function enlevement()
    {
        return $this->hasOne(Enlevement::class, 'livraison_id');
    }

    public function produit(){
        return $this->belongsTo(Produit::class)->withDefault(['nom'=>'','unite'=>'']);
    }


}
