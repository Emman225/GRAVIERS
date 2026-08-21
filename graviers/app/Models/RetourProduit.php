<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DetailCommande;
use App\Models\Client;

class RetourProduit extends Model
{
    use HasFactory;
    protected $table = "retour_produit";

    protected $fillable = [
        'motif',
        'client_id',
        'detail_commande_id',
        'observation_reception',
        'user_id',
        'user_paie_id',
        'date_reception',
        // Absente de cette liste, la date de retour était SILENCIEUSEMENT ignorée
        // à la création et la colonne prenait la valeur par défaut figée du
        // schéma. L'application, elle, la renseigne bien de son côté.
        'date_retour',
        'statut'
    ];

    public function detailCommande(){
        return $this->belongsTo(DetailCommande::class);
    }

    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }
}
