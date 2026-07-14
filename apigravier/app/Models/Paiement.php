<?php

namespace App\Models;

use Help;
use App\Models\Devis;
use App\Models\LignePaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Paiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'paiement';
    protected $fillable = [
        'client_id',
        'devis_id',
        'code',
        'libelle',
        'montant_total',
        'montant_restant',
        'statut',
        'facture_id',
    ];

    public static function lire($id)
    {
        $obj = Paiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function lireSurIds($ids)
    {
        return Paiement::whereIn('id', $ids)->get();
    }

    public static function lireSurFacture($facture_id)
    {
        $obj = Paiement::where('facture_id', $facture_id)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function lireCode($code)
    {
        $obj = Paiement::where('code', $code)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Paiement();
    }

    public static function liste($devis_id = null, $client_id = null, $statut = null)
    {
        return Paiement::orderBy('created_at', 'desc')
            ->when($devis_id, function ($query) use ($devis_id) {
                $query->where('devis_id', $devis_id);
            })
            ->when($client_id, function ($query) use ($client_id) {
                $query->where('client_id', $client_id);
            })
            ->when($statut, function ($query) use ($statut) {
                $query->whereIn('statut', $statut);
            })
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Paiement($arr);
        if ($obj->save()) return $obj;
        else return new Paiement();
    }

    public static function supprimer($id)
    {
        $obj = Paiement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function devis()
    {
        return $this->belongsTo(Devis::class);
    }

    /**
     * The roles that belong to the Paiement
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function modePaiement(): BelongsToMany
    {
        return $this->belongsToMany(modePaiement::class, 'ligne_paiement', 'paiement_id', 'mode_paiement_id')
            ->withPivot(['reference', 'date_paiement']);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function lignePaiements()
    {
        return $this->hasOne(LignePaiement::class, 'paiement_id');
    }
}
