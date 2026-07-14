<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DetailCommande;
use App\Models\Client;

class TicketSAV extends Model
{
    use HasFactory;

    protected $table = "ticket_sav";

    protected $fillable = [
        'numero',
        'client_id',
        'user_id',
        'detail_commande_id',
        'objet',
        'message',
        'est_traite',
        'solution_trouvee',
        'statut'
    ];

    public function detailCommande(){
        // Classe canonique DetailCommande (PascalCase) + FK explicite : sur un serveur
        // Linux (sensible à la casse), la classe minuscule "detailCommande" ne se résout
        // pas et faisait échouer belongsTo() (500 à l'affichage de la liste des tickets).
        return $this->belongsTo(DetailCommande::class, 'detail_commande_id');
    }

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }
}
