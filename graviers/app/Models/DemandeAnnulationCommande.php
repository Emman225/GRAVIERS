<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandeAnnulationCommande extends Model
{
    use HasFactory;

    // Sans $table, Eloquent cherchait "demande_annulation_commandes" (pluriel)
    // qui n'existe pas -> toute écriture web plantait silencieusement.
    protected $table = 'demande_annulation_commande';

    protected $fillable = [
        'client_id',
        'user_id',
        'commande_id',
        'motif',
        'est_traite',
        'note',
        'statut',
        'type_affaire',
        'decision',
        'traite_par',
        'decided_at',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * commande_id est POLYMORPHE : id de COMMANDE (type_affaire = VENTE)
     * ou de LOCATION (type_affaire = LOCATION).
     */
    public function serviceVise()
    {
        return $this->type_affaire === 'LOCATION'
            ? Location::find($this->commande_id)
            : Commande::find($this->commande_id);
    }
}
