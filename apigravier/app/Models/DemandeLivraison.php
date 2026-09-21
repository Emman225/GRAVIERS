<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandeLivraison extends Model
{
    use HasFactory;
    protected $table = 'demande_livraison';
    protected $fillable = [
        'numero',
        'libelle',
        'description',
        'client_id',
        'adresse_livraison_pec_id',
        'adresse_livraison_dest_id',
        'montantTotal',
        'etat_commande',
        'date_demande_livraison',
        'date_fin_demande_livraison',
        'remise',
        'statut',
        'mode_paiement_id',
        'type_demande_livraison_id',
    ];

    /**
     * Total net dû sur la demande : transport (montantTotal, hors taxe) + TVA
     * sur le transport si elle a été figée avec la demande − remise. Même
     * formule que le site (App\Models\DemandeLivraison::montantAPayer).
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

    /** Ce qui a réellement été encaissé sur la demande (lignes validées). */
    public function montantPayeComptant(): float
    {
        return (float) LignePaiement::where('service', Help::$LIVRAISON)
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

    public static function liste($client_id, $etat_commande = null, $mode_paiement_id = null, $type_livraison_id = null)
    {
        return DemandeLivraison::distinct()
            ->selectRaw("demande_livraison.*,
            mode_paiement.libelle as mode_paiement,
            pec.affichage as affichage_pec,
pec.complement_adresse as complement_adresse_pec,
pec.longitude as longitude_pec,
pec.latitude as latitude_pec,
dest.affichage as affichage_dest,
dest.complement_adresse as complement_adresse_dest,
dest.longitude as longitude_dest,
dest.latitude as latitude_dest
            ")
            ->orderBy('demande_livraison.id', 'desc')
            ->join('mode_paiement', 'mode_paiement.id', '=', 'demande_livraison.mode_paiement_id')
            ->join('type_livraison', 'type_livraison.id', '=', 'demande_livraison.type_livraison_id')
            ->join('adresse_livraison as pec', 'pec.id', '=', 'demande_livraison.adresse_livraison_pec_id')
            ->join('adresse_livraison as dest', 'dest.id', '=', 'demande_livraison.adresse_livraison_dest_id')
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('demande_livraison.client_id', $client_id);
            })
            ->when($etat_commande, function ($query) use ($etat_commande) {
                $query->where('demande_livraison.etat_commande', $etat_commande);
            })
            ->when($mode_paiement_id, function ($query) use ($mode_paiement_id) {
                $query->where('demande_livraison.mode_paiement_id', $mode_paiement_id);
            })
            ->when($type_livraison_id, function ($query) use ($type_livraison_id) {
                $query->where('demande_livraison.type_livraison_id', $type_livraison_id);
            })
            ->where('demande_livraison.statut', Help::$STATUT_ACTIF)
            ->get();
    }
}
