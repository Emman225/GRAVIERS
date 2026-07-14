<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TypeVehiculeLivreur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'type_vehicule_livreur';

    protected $fillable = [
        'libelle',
        'capacite_tonnes',
        'description',
        'statut',
    ];

    protected $casts = [
        'capacite_tonnes' => 'decimal:2',
        'statut'          => 'boolean',
    ];

    public function livreurs()
    {
        return $this->hasMany(Livreur::class, 'type_vehicule_id');
    }

    public function scopeActif($query)
    {
        return $query->where('statut', 1);
    }
}
