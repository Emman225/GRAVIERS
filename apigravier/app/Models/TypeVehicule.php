<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeVehicule extends Model
{
    use HasFactory;

    protected $table = 'type_vehicule';
    protected $fillable = [
        "libelle",
    ];

    public static function liste(){
        return TypeVehicule::all();
    }
}
