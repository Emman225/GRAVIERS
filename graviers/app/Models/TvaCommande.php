<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TvaCommande extends Model
{
    use HasFactory;

    protected $table = 'tva_commande';
    protected $fillable = [
        'client_id',
        'commande_id',
        'montant',
        'type_affaire',
    ];

    public function commande(){
        return $this->belongsTo(Commande::class, 'commande_id', 'id');
    }

    /**
     * Le fcfa (XOF) n'a pas de décimales : on arrondit systématiquement le montant
     * de TVA à l'enregistrement. Évite les résidus type 16,2 (= 18% de 90) qui, une
     * fois comparés au paiement encaissé (tronqué à l'entier), laissaient un « reste »
     * de 0,2 fcfa bloquant la commande sur « Paiement en cours ».
     */
    public function setMontantAttribute($value)
    {
        $this->attributes['montant'] = round((float) $value);
    }
}
