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
