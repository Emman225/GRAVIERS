<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reduction extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'reduction';
    protected $fillable = [
        'code',
        'libelle',
        'debut',
        'fin',
        'est_utilise',
        'taux_reduction',
        'devis_id',
        'commande_id',
        'client_id',
        'statut',
        'deleted_at',
        'user_id'
    ];

    public static function lire($id)
    {
        $obj = Reduction::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Reduction();
    }

    public static function lireCode($code)
    {
        $obj = Reduction::where('code', $code)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Reduction();
    }

    public static function liste($client_id = null)
    {
        return Reduction::orderBy('libelle', 'asc')
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    /**
     * Réductions en attente de validation pour un lot de commandes, indexées
     * par identifiant de commande.
     *
     * Une seule requête pour toute la liste : l'écran des commandes en attente
     * affiche l'indicateur sur chaque ligne, et interroger la base ligne par
     * ligne y coûterait autant de requêtes que de commandes.
     *
     * Le rattrapage par devis_id ne sert qu'aux réductions écrites avant
     * l'ajout de commande_id.
     */
    public static function reductionsEnAttentePour($commandes)
    {
        $idsCommande = collect($commandes)->pluck('id')->filter()->all();
        $idsDevis    = collect($commandes)->pluck('devis_id')->filter()->all();

        if (empty($idsCommande) && empty($idsDevis)) {
            return collect();
        }

        $lignes = Reduction::where('est_utilise', false)
            ->where('statut', Help::$STATUT_ACTIF)
            ->where(function ($q) use ($idsCommande, $idsDevis) {
                if (!empty($idsCommande)) { $q->whereIn('commande_id', $idsCommande); }
                if (!empty($idsDevis))    { $q->orWhereIn('devis_id', $idsDevis); }
            })
            ->orderBy('id')
            ->get();

        // orderBy('id') puis keyBy : la DERNIÈRE demande écrase les précédentes,
        // c'est bien la plus récente qui doit s'afficher.
        $parCommande = collect();
        $devisVersCommande = collect($commandes)->filter(fn ($c) => $c->devis_id)
            ->pluck('id', 'devis_id');

        foreach ($lignes as $ligne) {
            $idCommande = $ligne->commande_id ?: ($devisVersCommande[$ligne->devis_id] ?? null);
            if ($idCommande) {
                $parCommande[$idCommande] = $ligne;
            }
        }

        return $parCommande;
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Reduction($arr);
        if ($obj->save()) return $obj;
        else return new Reduction();
    }

    public static function supprimer($id)
    {
        $obj = Reduction::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commande(){
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    /**
     * Vrai si le code promo a une date de fin et que celle-ci est dépassée
     * (la validité court jusqu'à la fin de la journée de la date `fin`).
     * Un code sans date de fin n'expire jamais.
     */
    public function getEstExpireAttribute(): bool
    {
        return !empty($this->fin)
            && \Illuminate\Support\Carbon::parse($this->fin)->endOfDay()->isPast();
    }
}
