<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Abonné à la lettre d'information, recueilli depuis le pied de page du site.
 */
class Newsletter extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'newsletter';

    protected $fillable = ['email', 'statut', 'origine'];

    /** Abonnés actifs, ceux à qui l'on écrit. */
    public function scopeAbonnes($query)
    {
        return $query->where('statut', 1);
    }
}
