<?php

namespace App\Models;

use Help;
use App\Models\Commande;
use App\Models\Paiement;
use App\Models\DetailDevis;
use App\Models\ModePaiement;
use App\Models\LignePaiement;
use App\Models\AdresseLivraison;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Devis extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'devis';
    protected $fillable = [
        'numero',
        'client_id',
        'adresse_livraison_id',
        'libelle',
        'montant',
        'tva',
        'statut',
        'cout_livraison',
        // TVA sur le transport, figée au devis (Help::tvaSurTransport).
        'tva_transport',
        // AIRSI figé sur l'affaire (10/09/2026), comme la TVA du transport.
        'airsi',
        'mode_paiement_id',
        'cout_reduction',
        'montant_ht',
        'service',
        'type_livraison_id',
        'date_livraison',
        // Numéro de bon de commande interne de l'entreprise (09/09/2026),
        // reporté devant chaque désignation du devis.
        'numero_bon_commande',
    ];

    /**
     * Montant réellement à payer sur ce devis.
     *
     * La colonne `montant` ne veut PAS dire la même chose selon l'origine :
     *   - devis créé sur le site   -> montant = HT (montant == montant_ht) ;
     *   - devis créé sur le mobile -> montant = TOTAL déjà net de TVA, de
     *     livraison et de remise (l'API y range `$request->total`, que
     *     l'application calcule comme HT + TVA + livraison − remise).
     *
     * Les écrans faisaient partout « montant + tva + cout_livraison ». Pour un
     * devis venu du mobile, cela ajoutait la TVA une SECONDE fois : un devis de
     * 23 789 F s'affichait 27 418 F sur le site alors que l'application, elle,
     * annonçait le bon chiffre. Le client voyait deux prix, et c'est celui du
     * site qui était faux.
     *
     * On part donc de montant_ht, seule colonne dont le sens ne dépend pas du
     * canal, et on rebâtit le net. Repli sur `montant` pour les devis anciens
     * qui n'ont pas de HT enregistré — même convention que la requête des
     * paiements en attente (COALESCE(NULLIF(montant_ht, 0), …)).
     */
    public function montantHT(): float
    {
        $ht = (float) $this->detailDevis->sum(function ($d) {
            return (float) $d->prix * (float) $d->qte;
        });

        if ($ht > 0) {
            return $ht;
        }

        // Replis, pour les devis sans lignes : d'abord la colonne HT, puis le
        // montant. Un devis sans ligne ne devrait pas exister.
        $ht = (float) ($this->montant_ht ?? 0);

        return $ht > 0 ? $ht : (float) $this->montant;
    }

    public function montantAPayer(): float
    {
        // Le HT vient des LIGNES, jamais de la colonne montant_ht.
        //
        // Constaté sur le devis 687789 : montant_ht valait 10 000 pour une
        // ligne unique de 50 × 100 = 5 000. Le client lisait 14 900 F sur son
        // devis et 9 900 F sur la commande qui en est issue — deux prix pour
        // la même chose, et c'est celui du devis qui était faux.
        //
        // Même règle que Commande::montantHT() : les lignes sont la seule
        // source dont le sens ne dépend ni du canal, ni d'une reprise de
        // saisie ultérieure.
        $ht = $this->montantHT();

        return max(0, $ht
            + (float) ($this->tva ?? 0)
            + (float) ($this->cout_livraison ?? 0)
            + (float) ($this->tva_transport ?? 0)
            // AIRSI figé sur le devis (10/09/2026).
            + (float) ($this->airsi ?? 0)
            - (float) ($this->cout_reduction ?? 0));
    }

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

    public function adresseLivraison(){
        return $this->belongsTo(AdresseLivraison::class, 'adresse_livraison_id');
    }

    public function modePaiement(){
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    public function typeLivraison(){
        return $this->belongsTo(TypeLivraison::class, 'type_livraison_id');
    }

    public static function liste($client_id = null, $adresse_livraison_id = null)
    {
        return Devis::when($client_id, function ($query) use ($client_id) {
            $query->where('client_id', $client_id);
        })
            ->when($adresse_livraison_id, function ($query) use ($adresse_livraison_id) {
                $query->where('adresse_livraison_id', $adresse_livraison_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
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

    /**
     * LE CLIENT PEUT-IL SUPPRIMER CE DEVIS ? (10/09/2026)
     *
     * Un devis se supprime tant qu'il est EN ATTENTE : ni transformé en
     * commande (statut 2), ni rattaché à une commande encore vivante. Une
     * commande abandonnée sur la passerelle de paiement ne compte pas : elle
     * est morte, et le devis reste au client, qui doit pouvoir s'en défaire.
     *
     * Retourne la raison du refus, ou null si la suppression est possible.
     */
    public function motifDeNonSuppression(): ?string
    {
        if ((int) $this->statut !== (int) Help::$STATUT_ACTIF) {
            return 'Ce devis a déjà été transformé en commande : il ne peut plus être supprimé.';
        }
        $commande = Commande::where('devis_id', $this->id)->orderByDesc('id')->get()
            ->first(fn (Commande $c) => $c->affaireVivante());
        if ($commande) {
            return "Ce devis est rattaché à la commande n° {$commande->numero} : il ne peut plus être supprimé.";
        }

        return null;
    }

    public function supprimable(): bool
    {
        return $this->motifDeNonSuppression() === null;
    }

    /**
     * Suppression demandée par le client : le devis et ses lignes passent
     * inactifs, le devis est archivé (soft delete). Rien n'est effacé.
     */
    public function supprimerParLeClient(): void
    {
        foreach ($this->detailDevis as $ligne) {
            $ligne->statut = Help::$STATUT_INACTIF;
            $ligne->save();
        }
        self::supprimer($this->id);
    }

    public function paiements(){
        return $this->hasMany(Paiement::class);
    }

    public function lignes(){
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }
    public function commandes(){
        return $this->hasOne(Commande::class);
    }
    public function detailDevis(){
        return $this->hasMany(DetailDevis::class);
    }

    public function client (){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function produits(){
        return $this->belongsToMany(Produit::class,'detail_devis')->withPivot('id','qte','prix','statut','deleted_at');
    }

    public function reduction(){
        return $this->hasOne(Reduction::class );
    }

    /*public function lignePaiement(): hasManyThrough
    {
        return $this->hasManyThrough(LignePaiement::class, Paiement::class,'devis_id','paiement_id','id','id');
    }*/
}
