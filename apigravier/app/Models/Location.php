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
        // 0 = « Retrait sur place » : aucun livreur n'intervient, la page de validation
        // gestionnaire (web) n'exige alors ni livreur ni véhicule.
        "est_livrable",
    ];

    // ------------------------------------------------------------------
    //  Montants d'une location — PORTÉS À L'IDENTIQUE depuis le site
    //  (graviers/app/Models/Location.php). Voir la note du modèle Commande :
    //  le plafond doit donner le même chiffre sur les deux canaux.
    // ------------------------------------------------------------------

    public function tvaLocation()
    {
        // Filtre type_affaire : commande_id d'une location et d'une commande
        // peuvent porter la même valeur (tables séparées).
        return $this->hasOne(TvaCommande::class, 'commande_id')
            ->where('type_affaire', Help::$LOCATION)
            ->withDefault(['montant' => 0]);
    }

    /**
     * Total net à payer : HT − remise, puis TVA et livraison.
     * Même formule qu'à la facturation (OrdersController::genererFactureLocation).
     */
    public function detailLocation()
    {
        return $this->hasMany(DetailLocation::class, 'location_id');
    }

    /**
     * HT marchandise, recalculé DEPUIS LES LIGNES.
     *
     * `montant_total` n'a PAS le même sens selon le canal : le site y écrit le
     * HT, l'application le NET final, TVA et livraison comprises. Le lire ici
     * puis y rajouter TVA et livraison gonflait le dû de ces deux montants sur
     * toute location passée depuis le téléphone — 144 000 réclamés là où le
     * back-office et la facture annonçaient 122 000.
     *
     * ATTENTION : `detail_location.prix` porte le TOTAL de la ligne, quantité
     * ET nombre de jours compris. On ne le multiplie donc pas par la quantité,
     * contrairement à `detail_commande.prix`. C'est déjà la source qu'emploient
     * la facture et le site.
     */
    public function montantHT(): float
    {
        $ht = (float) $this->detailLocation->sum('prix');

        // Repli défensif : une location sans ligne ne devrait pas exister, mais
        // rendre zéro la ferait passer pour soldée.
        return $ht > 0 ? $ht : (float) $this->montant_total;
    }

    public function montantAPayer(): float
    {
        return max(0, $this->montantHT() - (float) ($this->remise ?? 0))
            + (float) ($this->tvaLocation->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0)
            // TVA sur le transport (point 5) : même dû que le site.
            + (float) ($this->tva_transport ?? 0)
            // AIRSI figé sur la location (10/09/2026).
            + (float) ($this->airsi ?? 0);
    }

    /** Ce qui a réellement été encaissé sur la location. */
    public function montantPayeComptant(): float
    {
        return (float) LignePaiement::where('service', Help::$LOCATION)
            ->where('service_id', $this->id)
            ->where('statut', Help::$STATUT_ACTIF)
            ->sum('montant');
    }

    /** Reste dû ; un résidu < 1 fcfa est considéré comme nul. */
    public function montantRestantDu(): float
    {
        $reste = $this->montantAPayer() - $this->montantPayeComptant();
        return $reste < 1 ? 0.0 : $reste;
    }

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
            // location.statut est surchargé : 1 = active, 3 = soldée (payée), 2 = INACTIF.
            // Le callback de paiement (web ET mobile) met statut = 3 quand la location est
            // entièrement payée. Filtrer uniquement statut = 1 faisait DISPARAÎTRE de la
            // liste toute location soldée. On affiche donc active + soldée, on exclut INACTIF.
            ->whereIn('location.statut', [Help::$STATUT_ACTIF, 3])
            ->limit(500)
            ->get();
    }

    /**
     * OÙ EN EST LA LIVRAISON DU MATÉRIEL (10/09/2026) — même règle que le site
     * (Location::etatLivraison) : la location reste EN COURS jusqu'au retour,
     * le client doit pourtant voir si son matériel est arrivé. Une course de
     * location porte l'id de sa LIGNE dans livraison.detail_commande_id.
     *
     * Retourne null sans course, sinon ['code' => …, 'libelle' => …].
     */
    public static function etatLivraison($location): ?array
    {
        $lignes = \Illuminate\Support\Facades\DB::table('detail_location')->where('location_id', $location->id)->pluck('id');
        if ($lignes->isEmpty()) {
            return null;
        }
        $courses = \Illuminate\Support\Facades\DB::table('livraison')
            ->where('provenance', Help::$LOCATION)
            ->whereIn('detail_commande_id', $lignes)
            ->whereNull('deleted_at')
            ->where('accepte', '<>', 3)
            ->get(['accepte', 'etat_livraison', 'updated_at']);
        if ($courses->isEmpty()) {
            return null;
        }
        $retrait   = (int) $location->est_livrable !== 1 && !$location->adresse_livraison_id;
        $acceptees = $courses->where('accepte', 1);
        $livrees   = $acceptees->where('etat_livraison', Help::$LIVRAISON_LIVREE);

        if ($acceptees->isNotEmpty() && $livrees->count() === $acceptees->count()) {
            $date  = $livrees->max('updated_at');
            $quand = $date ? ' le ' . \Carbon\Carbon::parse($date)->format('d/m/Y H:i:s') : '';
            return $retrait
                ? ['code' => 'RETIREE', 'libelle' => 'Retirée' . $quand]
                : ['code' => 'LIVREE', 'libelle' => 'Livrée' . $quand];
        }
        if ($acceptees->isNotEmpty()) {
            return $retrait
                ? ['code' => 'A_RETIRER', 'libelle' => 'À retirer chez le fournisseur']
                : ['code' => 'EN_LIVRAISON', 'libelle' => 'En livraison'];
        }

        return ['code' => 'A_CONFIRMER', 'libelle' => 'Livreur à confirmer'];
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
