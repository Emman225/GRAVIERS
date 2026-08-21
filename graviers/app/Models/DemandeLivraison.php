<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AdresseLivraison;
use App\Models\Client;
use App\Models\Livraison;
use App\Models\DetailLivraison;
use App\Models\LignePaiement;
use App\Models\TvaCommande;

class DemandeLivraison extends Model
{
    use HasFactory;

    protected $table = "demande_livraison";
    protected $fillable = [
        'numero',
        'libelle',
        'description',
        'montantTotal',
        'client_id',
        'adresse_livraison_pec_id',
        'adresse_livraison_dest_id',
        'date_livraison',
        'date_fin_livraison',
        'remise',
        'mode_paiement_id',
        'type_livraison_id',
        'etat_commande',
        'statut',

    ];

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }

    public function priseEnCharge(){
        return $this->belongsTo(AdresseLivraison::class,'adresse_livraison_pec_id');
    }
    public function destination(){
        return $this->belongsTo(AdresseLivraison::class,'adresse_livraison_dest_id');
    }

    public function detailLivraison(){
        return $this->hasMany(DetailLivraison::class);
    }

    public function livraisons(){
        return $this->hasManyThrough(Livraison::class,DetailLivraison::class);
    }

    public function TypeLivraison(){
        return $this->belongsTo(TypeLivraison::class);
    }
    
    public function ModeDePaiement(){
        return $this->belongsTo(ModePaiement::class,'mode_paiement_id');
    }

    /**
     * Bon de commande joint par le client au moment de la demande.
     * Obligatoire pour un compte à terme, facultatif sinon.
     */
    public function blClient(){
        return $this->hasOne(BlClient::class,'demande_livraison_id');
    }

    /**
     * Montant réellement dû par le client : coût du transport + TVA - remise.
     *
     * montantTotal ne porte que le coût de transport. La TVA est enregistrée à
     * part, dans tva_commande, avec type_affaire = LIVRAISON — même convention
     * que pour les commandes, où montant_total stocke le HT.
     */
    public function montantAPayer(): float
    {
        $tva = (float) TvaCommande::where('commande_id', $this->id)
            ->where('type_affaire', Help::$LIVRAISON)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');

        $net = (float) $this->montantTotal + $tva - (float) ($this->remise ?? 0);

        return $net < 0 ? 0.0 : $net;
    }

    /**
     * Somme des encaissements VALIDÉS imputés à cette demande.
     *
     * Seules les lignes au statut 1 comptent : un encaissement en attente de la
     * seconde validation n'a pas encore d'existence comptable, et ne doit donc
     * pas débloquer le traitement de la demande.
     */
    public function montantPayeComptant(): float
    {
        return (float) LignePaiement::where('service', Help::$LIVRAISON)
            ->where('service_id', $this->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');
    }

    /**
     * Encaissements saisis mais pas encore validés par un second administrateur.
     */
    public function montantEnAttenteValidation(): float
    {
        return (float) LignePaiement::where('service', Help::$LIVRAISON)
            ->where('service_id', $this->id)
            ->where('statut', 2)
            ->sum('montant');
    }

    /**
     * Reste à payer. Le seuil d'un franc absorbe les arrondis : une demande à
     * laquelle il manquerait 0,4 FCFA est considérée soldée. Même règle que
     * Commande::montantRestantDu().
     */
    public function montantRestantDu(): float
    {
        $reste = $this->montantAPayer() - $this->montantPayeComptant();

        return $reste < 1 ? 0.0 : $reste;
    }

    /**
     * Ce qu'il reste à ENCAISSER, par opposition à ce qu'il reste à devoir.
     *
     * Les deux notions diffèrent tant qu'un encaissement attend sa seconde
     * validation : il ne solde pas encore la demande — le camion ne doit pas
     * partir — mais il ne doit pas non plus pouvoir être saisi une deuxième
     * fois. Sans cette distinction, deux encaissements du montant total
     * pouvaient coexister au guichet et se valider ensuite tous les deux.
     */
    public function montantEncaissable(): float
    {
        $reste = $this->montantAPayer()
            - $this->montantPayeComptant()
            - $this->montantEnAttenteValidation();

        return $reste < 1 ? 0.0 : $reste;
    }

    /**
     * Le règlement se fait-il en agence (donc hors ligne) ?
     * Une demande réglée en ligne est encaissée par la passerelle, pas au guichet.
     */
    public function reglementEnAgence(): bool
    {
        return (int) ($this->modeDePaiement?->en_ligne ?? 0) === 0;
    }




    /**
     * Une course de cette demande a-t-elle ete refusee sans etre reconfiee ?
     *
     * C'est le signal qui manquait a la liste : la demande restait affichee
     * « EN TRAITEMENT » sans que rien n'indique qu'un livreur avait refuse et
     * qu'une reaffectation etait attendue.
     */
    public function attendUneReaffectation(): bool
    {
        foreach ($this->detailLivraison as $detail) {
            if ($detail->attendUneReaffectation()) {
                return true;
            }
        }

        return false;
    }
}
