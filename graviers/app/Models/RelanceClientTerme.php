<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RelanceClientTerme extends Model
{
    use SoftDeletes;

    protected $table = 'relance_client_terme';

    protected $fillable = [
        'date_relance',
        'facture_id',
        'client_id',
        'type_relance',
        'niveau',
        'reponse_client',
        'action_suivante',
        'user_id',
    ];

    protected $casts = [
        'date_relance' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class, 'facture_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
