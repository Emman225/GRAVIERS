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
        // AIRSI figé sur la demande (10/09/2026).
        'airsi',
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

    /**
     * Les courses acceptées par un livreur (10/09/2026) : celles dont le code
     * de livraison a un sens pour le client — même règle que la vente et la
     * location. Une demande de livraison ne produit pas de bon d'enlèvement.
     */
    public function coursesAcceptees()
    {
        return $this->livraisons->filter(fn (Livraison $l) => (int) $l->accepte === Livraison::ACCEPTEE)->values();
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

        // AIRSI figé sur la demande (10/09/2026).
        $net = (float) $this->montantTotal + $tva + (float) ($this->airsi ?? 0) - (float) ($this->remise ?? 0);

        return $net < 0 ? 0.0 : $net;
    }

    /**
     * Somme des encaissements VALIDÉS imputés à cette demande.
     *
     * Seules les lignes au statut 1 comptent : un encaissement en attente de la
     * seconde validation n'a pas encore d'existence comptable, et ne doit donc
     * pas débloquer le traitement de la demande.
     */
    /**
     * CETTE DEMANDE EST-ELLE EXPLOITABLE PAR LE GESTIONNAIRE ?
     *
     * Une demande est enregistrée AVANT l'ouverture de la passerelle de
     * paiement. Le client qui referme la passerelle — pour changer de mode, ou
     * simplement parce qu'il renonce — laisse donc derrière lui une demande
     * complète, active, et proposée à l'affectation comme les autres.
     *
     * Constaté le 25/08/2026 : un client abandonne le paiement en ligne,
     * recommence en choisissant le règlement en agence, et DEUX demandes
     * apparaissent. Le gestionnaire affecte les deux — donc deux fois le
     * camion. La course fantôme n'étant jamais livrée, elle retient le véhicule
     * pour toujours : terminer la vraie livraison ne le libère pas, puisqu'une
     * autre course active le tient encore.
     *
     * RÈGLE : un règlement EN LIGNE n'engage à rien tant qu'il n'est pas
     * encaissé. Une telle demande n'est donc exploitable qu'une fois payée. Le
     * règlement AU GUICHET, lui, est un engagement pris en agence : la demande
     * est exploitable d'emblée, c'est tout son intérêt.
     *
     * On ne SUPPRIME pas la demande non payée : le client peut revenir régler,
     * et une trace vaut mieux qu'un trou. On la garde simplement hors des
     * écrans d'affectation.
     */
    public function estExploitable(): bool
    {
        // Le nom EXACT de la relation : « ModeDePaiement », pas « modePaiement ».
        // Une relation inexistante rend null en silence — la regle laissait alors
        // passer toutes les demandes, et le garde-fou ne gardait rien.
        $mode = $this->ModeDePaiement;

        // Mode inconnu ou hors ligne (guichet, virement) : engagement pris.
        if (!$mode || (int) ($mode->en_ligne ?? 0) !== 1) {
            return true;
        }

        // En ligne : il faut que l'argent soit arrivé.
        return $this->montantPayeComptant() > 0
            || $this->montantEnAttenteValidation() > 0;
    }

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
     * PUIS-JE ENCAISSER CETTE AFFAIRE AU GUICHET ?
     *
     * Ce n'est PAS la même question que « le mode de règlement est-il un mode
     * d'agence ? ». Le guichet ne s'écartait que devant la passerelle : une
     * affaire qu'elle a réellement encaissée n'a rien à faire à la caisse.
     *
     * Mais une initiation ÉCHOUÉE — le site crée alors l'affaire tout de même —
     * ou un client qui abandonne la page de paiement laissent une affaire due
     * portant un mode en ligne. Elle n'était encaissable NULLE PART : ni par la
     * passerelle, qui n'a rien pris, ni au guichet, qui l'ignorait. Le caissier
     * ouvrait un sélecteur vide devant un client venu payer. Constaté le
     * 01/09/2026.
     *
     * Les deux questions ont donc chacune leur méthode : confondre les deux
     * bloquait le traitement d'affaires que rien n'avait à bloquer.
     *
     * Aucun risque de double encaissement : `montantEncaissable()` retire déjà
     * ce qui a été perçu et ce qui attend sa seconde validation.
     */
    /** Une demande annulée n'existe plus : elle ne s'encaisse pas. */
    public function affaireVivante(): bool
    {
        return $this->etat_commande !== \Help::$AFFAIRE_ANNULEE;
    }

    public function encaissableAuGuichet(): bool
    {
        // Même correctif que pour les ventes et les locations : l'état de
        // l'affaire passe avant son mode de règlement.
        return $this->affaireVivante()
            && ($this->reglementEnAgence() || $this->montantPayeComptant() <= 0);
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
