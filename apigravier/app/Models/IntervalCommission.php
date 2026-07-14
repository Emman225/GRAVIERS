<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntervalCommission extends Model
{
    use HasFactory;
    protected $table = 'interval_commission';
    protected $fillable = [
        'montant_de',
        'montant_a',
        'pourcentage',
    ];

    public static function lireCommissionSurMontant($montant)
    {
        return IntervalCommission::where(function ($query) use ($montant) {
            $query->where('montant_de', '<=', $montant);
            $query->where('montant_a', '>=', $montant);
        })->first();
    }
}
