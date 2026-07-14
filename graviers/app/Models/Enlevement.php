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
    public function montantTtc(): float
    {
        $tauxTva = (float) (\App\Models\Configuration::first()?->tva ?? 18);
        $ht = (float) $this->qte * (float) $this->prix_fournisseur;
        return $ht + ($ht * $tauxTva / 100);
    }

    public function montantHt(): float
    {
        return (float) $this->qte * (float) $this->prix_fournisseur;
    }

    public function montantPaye(): float
    {
        return (float) PaiementFournisseur::where('enlevement_id', $this->id)
            ->where('statut', 1)->sum('montant');
    }

    public function resteAPayer(): float
    {
        return max(0, $this->montantTtc() - $this->montantPaye());
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
        $ttc  = $this->montantTtc();
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
        $enlevements = Enlevement::selectRaw("enlevement.*, livraison.etat_livraison, livraison.date_livraison, concat(client.nom, ' ', client.prenom, ' - ', client.contact1) as le_client,
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
