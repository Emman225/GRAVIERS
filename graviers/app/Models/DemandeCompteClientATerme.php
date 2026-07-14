<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client;
use App\Models\User;

class DemandeCompteClientATerme extends Model
{
    use HasFactory;

    protected $table = "demande_compte_client_a_terme";
    protected $fillable = [
        'objet',
        'description',
        'documents_path',
        'client_id',
        'approuve',
        'plafond_credit',
        'delai_paiement',
        'commentaire_admin',
        'motif_refus',
        'decided_at',
        'user_id',
    ];

    protected $casts = [
        'documents_path' => 'array',
        'decided_at' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'']);
    }
    public function client(){
        return $this->belongsTo(Client::class)->withDefault(['nom'=>'','prenom'=>'','email'=>'','contact1'=>'','contact2'=>'','type_client'=>'','client_a_terme'=>0]);
    }
}
