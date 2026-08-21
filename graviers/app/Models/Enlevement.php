<?php

namespace App\Models;

use Help;
use Carbon\Carbon;
use App\Models\Livreur;
use App\Models\Produit;
use App\Models\Vehicule;
use App\Models\Livraison;
use App\Models\Fournisseur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Enlevement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'enlevement';
    protected $fillable = [
        'fournisseur_id',
        'livraison_id',
        'qte',
        'matricule_vehicule',
        'produit_id',
        'gestionnaire_id',
        'livreur_id',
        'statut',
        'code_enleve',
        'fournisseur_validation',
        'livreur_validation',
        'user_id',
        'qte_servi',
        'prix_fournisseur',
        'facture_id',
        'vehicule_id',

        'etat_livraison',
        'date_livraison',
        'complement_adresse',
        'date_echeance',
        'statut_dette',
        'observations',
    ];

    protected $casts = [
        'date_echeance' => 'date',
    ];

    public function paiementsFournisseur()
    {
        return $this->hasMany(PaiementFournisseur::class, 'enlevement_id');
    }

    /**
     * Montant TTC de l'enlèvement (HT + TVA conf).
     */
    /**
     * Montant TTC dû au fournisseur, arrondi au franc.
     *
     * Le franc CFA n'a pas de décimales : un bon de 175 F HT donnait 206,5 F TTC.
     * L'écran affichait « Reste : 207 fcfa » (valeur arrondie) mais préremplissait
     * le champ Montant avec 206,5 — et un agent qui saisissait 207 se voyait
     * refuser son paiement, le contrôle serveur le jugeant supérieur au reste dû.
     * On arrondit donc dès la source : affichage, champ et contrôle portent tous
     * sur la même valeur.
     */
    /**
     * Ce que l'entreprise doit RÉELLEMENT au fournisseur.
     *
     * Le client règle trois choses qui n'ont pas le même destinataire : le
     * produit revient au fournisseur, la TVA à l'État, le transport à
     * l'entreprise. Le fournisseur ne reçoit donc que le coût du produit.
     *
     * Le calcul ajoutait 18 % à tout le monde : l'entreprise reversait au
     * fournisseur la TVA qu'elle doit garder pour la déclarer, et le transport
     * qu'elle facture pour son propre compte n'entrait heureusement jamais
     * dans ce montant.
     *
     * Seul un fournisseur DÉCLARÉ facture la TVA : pour lui, et pour lui seul,
     * elle s'ajoute — sa facture le mentionne, et cette TVA est déductible.
     */
    public function montantDu(): float
    {
        $ht = $this->montantHt();

        if (!$this->fournisseur?->assujetti_tva) {
            return round($ht);
        }

        $tauxTva = (float) (\App\Models\Configuration::first()?->tva ?? 18);

        return round($ht + ($ht * $tauxTva / 100));
    }

    /** Part de TVA incluse dans le règlement — nulle hors fournisseur déclaré. */
    public function tvaFournisseur(): float
    {
        return round($this->montantDu() - $this->montantHt());
    }

    /**
     * Ce que l'on doit au fournisseur : la quantité qu'il a RÉELLEMENT SERVIE,
     * et non celle qu'on lui avait demandée.
     *
     * Le calcul portait sur `qte`, la quantité commandée. Un bon de 10 tonnes
     * servi à 8 était donc payé pour 10 : deux tonnes jamais livrées, réglées
     * malgré tout. La quantité servie est saisie par le fournisseur lui-même à
     * la validation du bon.
     *
     * Tant que le bon n'est pas servi, qte_servi est nulle : on retient alors
     * la quantité commandée, qui est le montant prévisionnel de la dette.
     */
    public function quantiteAPayer(): float
    {
        return $this->qte_servi !== null
            ? (float) $this->qte_servi
            : (float) $this->qte;
    }

    /**
     * Le fournisseur a-t-il servi autre chose que la quantité commandee ?
     *
     * Sert a signaler l'ecart a l'ecran : afficher 1 la ou le bon annoncait 2
     * sans le dire laisserait croire a une erreur de saisie. Couvre aussi le
     * cas inverse, plus rare, d'une livraison superieure a la commande.
     */
    public function quantiteDiffereDeLaCommande(): bool
    {
        return $this->qte_servi !== null
            && (float) $this->qte_servi !== (float) $this->qte;
    }

    public function montantHt(): float
    {
        return $this->quantiteAPayer() * (float) $this->prix_fournisseur;
    }

    /**
     * Le fournisseur a-t-il servi MOINS que ce qui lui était demandé ?
     *
     * Un bon partiel se facture pour ce qu'il a réellement livré ; le reliquat
     * fera l'objet d'un autre bon, donc d'une autre facture.
     */
    public function estServiPartiellement(): bool
    {
        if ($this->qte_servi === null) {
            return false; // pas encore servi : ni partiel ni complet
        }

        return (float) $this->qte_servi > 0
            && (float) $this->qte_servi < (float) $this->qte;
    }

    /**
     * Ce bon peut-il donner lieu à une facture ?
     *
     * Deux conditions, et la seconde dépend du mode de retrait :
     *
     *  · RETRAIT SUR PLACE — la validation du fournisseur vaut remise de la
     *    marchandise au client. Elle suffit : il n'y a pas de transport à
     *    attendre, et faire patienter le client jusqu'à un événement qui
     *    n'arrivera jamais bloquerait la facture indéfiniment.
     *
     *  · COMMANDE LIVRÉE — on attend la clôture de la livraison. Tant que le
     *    camion roule, la marchandise n'est pas chez le client.
     *
     * Un bon déjà rattaché à une facture n'est jamais refacturable.
     */
    public function estFacturable(): bool
    {
        if ($this->facture_id !== null) {
            return false;
        }

        $livraison = $this->livraison;

        // Livraison close : la marchandise est arrivée, quel que soit le mode.
        if ($livraison && $livraison->etat_livraison === 'LIVREE') {
            return true;
        }

        // Retrait sur place : la validation du fournisseur fait foi.
        $commande = $livraison?->detailCommande?->commande;
        $retraitSurPlace = $commande && (int) $commande->est_livrable === 0;

        return $retraitSurPlace && $this->fournisseur_validation !== null;
    }

    public function montantPaye(): float
    {
        return (float) PaiementFournisseur::where('enlevement_id', $this->id)
            ->where('statut', 1)->sum('montant');
    }

    public function resteAPayer(): float
    {
        $reste = round($this->montantDu() - $this->montantPaye());

        // Un résidu de moins d'un franc n'est pas payable : le bon est soldé.
        // (Même convention que Commande::montantRestantDu.)
        return $reste < 1 ? 0.0 : $reste;
    }

    public function joursRetard(): int
    {
        if (!$this->date_echeance) return 0;
        $today = Carbon::today();
        $echeance = Carbon::parse($this->date_echeance)->startOfDay();
        return $today->greaterThan($echeance) ? $echeance->diffInDays($today) : 0;
    }

    public function statutDetteCalcule(): string
    {
        if (!empty($this->statut_dette)) {
            return $this->statut_dette;
        }
        $paye = $this->montantPaye();
        $ttc  = $this->montantDu();
        if ($ttc > 0 && $paye >= $ttc) {
            return 'Payée';
        }
        if (!$this->date_echeance) {
            return $paye > 0 ? 'Partiellement payée' : 'À payer';
        }
        $today = Carbon::today();
        $echeance = Carbon::parse($this->date_echeance)->startOfDay();
        if ($today->greaterThan($echeance) && $paye < $ttc) {
            return $paye > 0 ? 'Partiellement payée' : 'Échue impayée';
        }
        return $paye > 0 ? 'Partiellement payée' : 'À payer';
    }

    public static function lire($id)
    {
        $obj = Enlevement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Enlevement();
    }

    public static function lireSurLivraison($idLivraison)
    {
        $obj = Enlevement::where('livraison_id', $idLivraison)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Enlevement();
    }

    public static function liste($fournisseur_id = null, $livraison_id = null, $produit_id = null, $du = null, $au = null)
    {
        $enlevements = Enlevement::selectRaw("enlevement.*, livraison.etat_livraison, livraison.date_livraison, CONCAT(" . \App\Models\Client::sqlNomAffiche() . ", ' - ', client.contact1) as le_client,
        produit.nom as le_produit, adresse_livraison.complement_adresse, concat(fournisseur.nom_prenoms,' - ', fournisseur.contact1) as le_fournisseur,
        concat(users.nom_prenoms,' - ', users.contact) as le_livreur, concat(vehicule.nom, ' - ', vehicule.immatriculation) as le_vehicule")
            ->orderBy('enlevement.id', 'desc')
            ->when($fournisseur_id, function ($query) use ($fournisseur_id) {
                $query->where('enlevement.fournisseur_id', $fournisseur_id);
            })
            ->when($livraison_id, function ($query) use ($livraison_id) {
                $query->where('enlevement.livraison_id', $livraison_id);
            })
            ->when($produit_id, function ($query) use ($produit_id) {
                $query->where('enlevement.produit_id', $produit_id);
            })
            ->join('livraison', 'livraison.id', '=', 'enlevement.livraison_id')
            ->join('client', 'client.id', '=', 'livraison.client_id')
            ->join('produit', 'produit.id', '=', 'enlevement.produit_id')
            ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
            ->join('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
            ->join('livreur', 'livreur.id', '=', 'enlevement.livreur_id')
            ->join('users', 'users.id', '=', 'livreur.user_id')
            // Le véhicule est renseigné sur la LIVRAISON (OrdersController::traitementItem),
            // pas sur l'enlèvement (enlevement.vehicule_id reste NULL) -> on joint via livraison.
            ->leftJoin('vehicule', 'vehicule.id', '=', 'livraison.vehicule_id')
            ->where('livraison.statut', Help::$STATUT_ACTIF)
            ->where('enlevement.statut', Help::$STATUT_ACTIF)
            ->whereBetween('livraison.date_livraison', [$du, $au])
            ->limit(3000)
            ->get();
        return $enlevements;
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Enlevement($arr);
        if ($obj->save()) return $obj;
        else return new Enlevement();
    }

    public static function supprimer($id)
    {
        $obj = Enlevement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function livraison()
    {
        return $this->belongsTo(Livraison::class, 'livraison_id')->withDefault(['numero'=>'','qte'=>0,'etat_livraison'=>'']);
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class, 'vehicule_id')->withDefault(['immatriculation'=>'','nom'=>'']);
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id')->withDefault(['nom_prenoms'=>'','contact1'=>'','adresse_geo'=>'']);
    }
    public function produit()
    {
        return $this->belongsTo(Produit::class)->withDefault(['nom'=>'','unite'=>'']);
    }
    public function livreur()
    {
        return $this->belongsTo(Livreur::class)->withDefault([]);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'']);
    }

    // Agent (gestionnaire) ayant créé l'enlèvement (colonne gestionnaire_id).
    public function gestionnaire()
    {
        return $this->belongsTo(User::class, 'gestionnaire_id')->withDefault(['nom_prenoms'=>'']);
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class)->withDefault(['numero'=>'']);
    }
}
