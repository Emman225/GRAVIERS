<?php

namespace App\Models;

use Help;
use App\Models\Devis;
use App\Models\Produit;
use App\Models\BlClient;
use App\Models\Paiement;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\TvaCommande;
use App\Models\ModePaiement;
use App\Models\DetailCommande;
use App\Models\PreuveOperation;
use App\Models\AdresseLivraison;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Commande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'commande';
    protected $fillable = [
        'numero',
        'devis_id',
        'client_id',
        'mode_paiement_id',
        'adresse_livraison_id',
        'agence_id',
        'date_commande',
        'date_limite_paiement',
        'montant_total',
        'etat_commande',
        'statut_comptant',
        'date_livraison',
        'date_fin_livraison',
        'statut',
        'note',
        'remise',
        'type_livraison_id',
        'cout_livraison_client',
        'mode_paiement',
        'contact1',
        'email',
        'adresse',
        'type_livraison',
        'numero_bl',
        'fichier_bl',
        'est_livrable'
    ];

    public function preuve(){
        return $this->hasOne(PreuveOperation::class, 'commande_id');
    }
    public function TvaCommande(){
        // dd('ok');
        return $this->hasOne(TvaCommande::class, 'commande_id', 'id');
    }



    public function blClient(){
        return $this->hasOne(BlClient::class, 'commande_id', 'id');
    }

    public static function lire($id)
    {
        $obj = Commande::selectRaw("commande.*, mode_paiement.libelle as mode_paiement,
        adresse_livraison.complement_adresse as adresse, type_livraison.libelle as type_livraison,
        bl_client.numero as numero_bl, bl_client.fichier as fichier_bl, pays.nom as lePays, ville.nom as laVille")
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftjoin('pays', 'pays.id', '=', 'adresse_livraison.pays_id')
            ->leftjoin('ville', 'ville.id', '=', 'adresse_livraison.ville_id')
            ->leftjoin('type_livraison', 'type_livraison.id', '=', 'commande.type_livraison_id')
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->where('commande.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Commande();
    }

    public static function lireSurNumero($numero)
    {
        $obj = Commande::selectRaw("commande.*, mode_paiement.libelle as mode_paiement,
        concat(client.nom, ' ', client.prenom) as le_client, client.contact1, client.email,
        adresse_livraison.complement_adresse as adresse, type_livraison.libelle as type_livraison,
        bl_client.numero as numero_bl, bl_client.fichier as fichier_bl")
            ->join('client', 'client.id', '=', 'commande.client_id')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftjoin('type_livraison', 'type_livraison.id', '=', 'commande.type_livraison_id')
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->where('commande.numero', $numero)->first();
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
        return  Commande::selectRaw("commande.*,
        concat(client.nom, ' ', client.prenom, ' - ', client.contact1) as infos_client,
        client.type_client,
        client.client_a_terme,
        mode_paiement.libelle as mode_paiement,
        paiement.montant_restant,
        adresse_livraison.complement_adresse as adresse,
        bl_client.numero as numero_bl,
        bl_client.fichier as fichier_bl")
            ->orderBy('commande.id', 'desc')
            ->join('client', 'client.id', '=', 'commande.client_id')
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->leftjoin('paiement', 'paiement.service_id', 'commande.id')
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('commande.client_id', $client_id);
            })
            ->when($etat_commande, function ($query) use ($etat_commande) {
                $query->whereIn('commande.etat_commande', $etat_commande);
            })
            ->where('commande.statut', Help::$STATUT_ACTIF)
            ->limit(1000)
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

    /**
     * Get the client that owns the Commande
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function devis(): BelongsTo
    {
        return $this->belongsTo(devis::class, 'devis_id');
    }

    public function CommissionApporteur()
    {
        return $this->hasMany(CommissionApporteur::class);
    }

    public function adresseLivraison()
    {
        return $this->belongsTo(AdresseLivraison::class)->withDefault([]);
    }
    /**
     * The produits that belong to the Commande
     */
    public function produits(): BelongsToMany
    {
        return $this->belongsToMany(Produit::class,'detail_commande')->withPivot('id','qte','prix','statut');
    }

    function enlevements(){
        return $this->hasManyThrough(Enlevement::class, Livraison::class, 'commande_id', 'livraison_id','id','id');
    }

    function paiements(){
        // dd('ok');
        return $this->hasOne(Paiement::class, 'service_id');
    }
    public function detailCommande(){
        return $this->hasMany(DetailCommande::class);
    }

    /**
     * Montant HT de la commande = somme des lignes (prix unitaire × qte).
     *
     * NE PAS utiliser montant_total pour calculer le "total à payer" :
     * le web y stocke le HT, mais le mobile (apigravier) y stocke le NET
     * final (HT + TVA + livraison − remise). Additionner TVA/livraison/remise
     * par-dessus montant_total double-compte donc pour les commandes mobiles
     * (ex. payé 108 en ligne mais total web affiché 116 -> "Paiement en cours").
     * Les lignes detail_commande, elles, sont identiques dans les deux flux.
     */
    public function montantHT(): float
    {
        $ht = (float) $this->detailCommande->sum(function ($d) {
            return (float) $d->prix * (float) $d->qte;
        });

        // Repli défensif : commande sans lignes (ne devrait pas arriver).
        return $ht > 0 ? $ht : (float) $this->montant_total;
    }

    /**
     * Total net à payer par le client : HT + TVA + livraison − remise.
     */
    public function montantAPayer(): float
    {
        return $this->montantHT()
            + (float) ($this->TvaCommande->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0)
            - (float) ($this->remise ?? 0);
    }
    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function modePaiement(){
        return $this->belongsTo(ModePaiement::class,'mode_paiement_id')->withDefault(['libelle'=>'','description'=>'']);
    }

    public function factures(){
        return $this->hasMany(Facture::class,'service_id');
    }
    public function lastLivraisons(){
        return $this->hasManyThrough(Livraison::class,DetailCommande::class,  'commande_id', 'detailCommande_id', 'id', 'id');
    }

 public function paiement(){
    return $this->hasOne(Paiement::class, 'service_id');
 }

    public function agence()
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    /**
     * Total payé sur cette commande (somme des paiements validés rattachés).
     *
     * Source de vérité = ligne_paiement (comme la page /orders-be) : c'est elle
     * que le callback de paiement en ligne passe à statut=1 encaissement par
     * encaissement. L'ancien calcul sur paiement.montant_total (statut=1) rendait
     * 0 tant que le paiement n'était pas SOLDÉ (statut du Paiement passé à 1
     * seulement quand montant_restant <= 0) et divergeait de la page BE
     * -> "En attente paiement" alors que la commande était déjà payée.
     */
    public function montantPayeComptant(): float
    {
        $lignes = (float) LignePaiement::where('service', 'COMMANDE')
            ->where('service_id', $this->id)
            ->where('statut', 1)
            ->sum('montant');

        if ($lignes > 0) {
            return $lignes;
        }

        // Repli historique : paiements rattachés directement ou via facture.
        return (float) Paiement::where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('service', 'COMMANDE')->where('service_id', $this->id);
                })->orWhereIn('facture_id', $this->factures()->pluck('id'));
            })
            ->where('statut', 1)
            ->sum('montant_total');
    }

    /**
     * Statut de la commande comptant calculé selon les règles Excel :
     * - statut_comptant manuel (Annulée / Livrée) prioritaire
     * - sinon basé sur paiements + délai limite
     */
    public function statutComptant(): string
    {
        if (!empty($this->statut_comptant)) {
            return $this->statut_comptant;
        }
        $paye  = $this->montantPayeComptant();
        // Total NET à payer depuis les lignes (montant_total : HT côté web,
        // net final côté mobile -> cf. montantAPayer()). Le fcfa n'a pas de
        // décimales : un restant < 1 (résidu d'arrondi) = commande payée.
        $total = $this->montantAPayer();

        if ($total > 0 && $total - $paye < 1) {
            return 'Payée';
        }
        if ($paye > 0) {
            return 'Partiellement payée';
        }
        if ($this->date_limite_paiement) {
            $today = \Carbon\Carbon::today();
            $limite = \Carbon\Carbon::parse($this->date_limite_paiement)->startOfDay();
            if ($today->greaterThan($limite)) {
                return 'En retard';
            }
        }
        return 'En attente paiement';
    }
}
