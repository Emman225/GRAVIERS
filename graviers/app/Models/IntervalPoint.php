<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntervalPoint extends Model
{
    use HasFactory;
    protected $table = 'interval_point';
    protected $fillable = [
        'montant_de',
        'montant_a',
        'nombre_point',
    ];
}
