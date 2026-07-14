<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HistoriquePrixLivraisonLivreur extends Model
{
    use HasFactory;

    protected $table = 'historique_prix_livraison_livreur';

    protected $fillable = [
        'livreur_id',
        'ancien_prix',
        'nouveau_prix',
        'user_id',
        'motif',
    ];

    public function livreur()
    {
        return $this->belongsTo(Livreur::class, 'livreur_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->withDefault(['nom_prenoms' => 'Système', 'email' => '']);
    }
}
