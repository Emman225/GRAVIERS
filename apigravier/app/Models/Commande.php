<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Commande extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'commande';
    protected $fillable = [
        'numero',
        'devis_id',
        'client_id',
        'adresse_livraison_id',
        'mode_paiement_id',
        'date_commande',
        'date_livraison',
        'montant_total',
        'etat_commande',
        'date_fin_livraison',
        'statut',
        'note',
        'remise',
        'type_livraison_id',
        'type_livraison',
        'cout_livraison_client',
        'cout_reduction',
        'fichier_bl',
        'numero_bl',
    ];

    // ------------------------------------------------------------------
    //  Montants d'une commande — PORTÉS À L'IDENTIQUE depuis le site
    //  (graviers/app/Models/Commande.php).
    //
    //  Les deux projets partagent la même base. Le plafond de crédit d'un client
    //  doit donc donner le même chiffre qu'on commande depuis le site ou depuis
    //  l'application : deux calculs voisins mais différents produiraient deux
    //  limites différentes selon le canal, ce qui n'est pas une limite.
    //
    //  Toute correction ici doit être reportée là-bas, et réciproquement.
    // ------------------------------------------------------------------

    public function detailCommande()
    {
        return $this->hasMany(DetailCommande::class, 'commande_id');
    }

    public function TvaCommande()
    {
        return $this->hasOne(TvaCommande::class, 'commande_id')->withDefault(['montant' => 0]);
    }

    public function factures()
    {
        return $this->hasMany(Facture::class, 'service_id');
    }

    /**
     * HT marchandise, recalculé depuis les lignes.
     *
     * On ne lit PAS montant_total : cette colonne contient le HT pour une commande
     * créée sur le site, mais le NET pour une commande créée depuis l'application.
     */
    public function montantHT(): float
    {
        $ht = (float) $this->detailCommande->sum(function ($d) {
            return (float) $d->prix * (float) $d->qte;
        });

        // Repli défensif : commande sans lignes (ne devrait pas arriver).
        return $ht > 0 ? $ht : (float) $this->montant_total;
    }

    /** Total net à payer par le client : HT + TVA + livraison − remise. */
    public function montantAPayer(): float
    {
        return $this->montantHT()
            + (float) ($this->TvaCommande->montant ?? 0)
            + (float) ($this->cout_livraison_client ?? 0)
            // TVA sur le transport (point 5, 07/09/2026), figée à la commande :
            // le site la compte dans le dû, l'API doit dire le même reste.
            + (float) ($this->tva_transport ?? 0)
            // AIRSI figé sur la commande (10/09/2026) : fait partie du net à payer.
            + (float) ($this->airsi ?? 0)
            - (float) ($this->remise ?? 0);
    }

    /** Ce qui a réellement été encaissé sur la commande. */
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
     * Reste réellement dû sur la commande (net à payer − encaissé).
     * Le fcfa n'a pas de décimales : un résidu < 1 est considéré comme nul.
     */
    public function montantRestantDu(): float
    {
        $reste = $this->montantAPayer() - $this->montantPayeComptant();
        return $reste < 1 ? 0.0 : $reste;
    }

    public static function lire($id)
    {
        $url = Help::$URL_BASE_FICHIER;
        $obj = Commande::selectRaw("commande.*, mode_paiement.libelle as mode_paiement,tva_commande.montant as montant_tva,
        adresse_livraison.complement_adresse as adresse, type_livraison.libelle as type_livraison,
        bl_client.numero as numero_bl, concat('$url',bl_client.fichier) as fichier_bl")
            ->leftjoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftjoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftjoin('type_livraison', 'type_livraison.id', '=', 'commande.type_livraison_id')
            ->leftjoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->leftjoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'commande.id');
                $query->where('tva_commande.type_affaire', Help::$VENTE);
            })
            ->where('commande.id', $id)
            ->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Commande();
    }

    public static function lireSurNumero($numero)
    {
        $obj = Commande::where('numero', $numero)->first();
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
        $url = Help::$URL_BASE_FICHIER;
        // montant_total est ambigu en base : le SITE y stocke le HT, l'APPLICATION le
        // net final. Une commande passée sur le site s'affichait donc dans l'application
        // avec un montant inférieur à celui réellement dû (TVA et livraison manquantes).
        // On renvoie ici le NET recalculé depuis les lignes, quelle que soit l'origine.
        // (L'alias placé APRÈS commande.* écrase la valeur brute de la colonne.)
        return Commande::selectRaw("commande.*,
        (COALESCE(NULLIF((SELECT SUM(d.prix * d.qte) FROM detail_commande d
                           WHERE d.commande_id = commande.id AND d.deleted_at IS NULL), 0),
                  commande.montant_total, 0)
         + IFNULL(commande.cout_livraison_client, 0)
         + IFNULL(tva_commande.montant, 0)
         - IFNULL(commande.remise, 0)) as montant_total,
        mode_paiement.libelle as mode_paiement,
        adresse_livraison.complement_adresse as adresse,
        tva_commande.montant as montant_tva,
        bl_client.numero as numero_bl,
        concat('$url',bl_client.fichier) as fichier_bl,
        (SELECT COUNT(*) FROM paiement p
           WHERE p.service = 'COMMANDE' AND p.service_id = commande.id AND p.statut = 1
             AND p.agence_id IS NULL AND p.caissier_id IS NULL AND p.deleted_at IS NULL) as paye_en_ligne")
            ->orderBy('commande.id', 'desc')
            ->leftJoin('mode_paiement', 'mode_paiement.id', '=', 'commande.mode_paiement_id')
            ->leftJoin('adresse_livraison', 'adresse_livraison.id', '=', 'commande.adresse_livraison_id')
            ->leftJoin('bl_client', 'bl_client.commande_id', '=', 'commande.id')
            ->leftJoin('tva_commande', function($query) {
                $query->on('tva_commande.commande_id', '=', 'commande.id');
                $query->where('tva_commande.type_affaire', Help::$VENTE);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('commande.client_id', $client_id);
            })
            ->when($etat_commande, function ($query) use ($etat_commande) {
                $query->where('commande.etat_commande', $etat_commande);
            })
            ->where('commande.statut', Help::$STATUT_ACTIF)
            ->limit(500)
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
}
