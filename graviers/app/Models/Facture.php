<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Facture extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'facture';
    protected $fillable = [
        'numero',
        'numero_fne',
        'user_id',
        'montant',
        'statut',
        'service',
        'service_id',
        'client_id',
        'date_echeance',
        'observations',
        'statut_creance',

        // Champs renseignés après certification FNE par la DGI
        'fne_invoice_id',
        'fne_reference',
        'fne_token',
        'fne_balance_sticker',
        'fne_warning',
        'fne_template',
        'fne_payment_method',
        'fne_status',
        'fne_certified_at',
        'fne_error_message',
        'fne_request_payload',
        'fne_response_payload',
    ];

    protected $casts = [
        'fne_warning' => 'boolean',
        'fne_certified_at' => 'datetime',
        'fne_request_payload' => 'array',
        'fne_response_payload' => 'array',
        'date_echeance' => 'date',
    ];

    function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    function commande(){
        return $this->belongsTo(Commande::class, 'service_id');
    }

    // Facture d'une location (service = LOCATION, service_id = location.id).
    function location(){
        return $this->belongsTo(Location::class, 'service_id');
    }

    // Relation manquante : utilisée par facturesNonValidees/facturesValidees
    // (with(['client',...])) et par la vue ($facture->client->nom). Sans elle,
    // l'eager-load lève RelationNotFoundException -> 500 dès qu'il y a des factures.
    function client(){
        return $this->belongsTo(Client::class, 'client_id');
    }

    function paiements(){
        return $this->hasMany(Paiement::class, 'facture_id');
    }

    /**
     * Indique si la facture a été certifiée avec succès par la plateforme FNE.
     */
    public function isCertifiedFne(): bool
    {
        return $this->fne_status === 'certified' && !empty($this->fne_reference);
    }

    public function relances()
    {
        return $this->hasMany(RelanceClientTerme::class, 'facture_id');
    }

    public function montantPaye(): float
    {
        return (float) Paiement::where('facture_id', $this->id)->where('statut', 1)->sum('montant_total');
    }

    public function resteAPayer(): float
    {
        return max(0, ((float) $this->montant) - $this->montantPaye());
    }

    public function joursRetard(): int
    {
        if (!$this->date_echeance) {
            return 0;
        }
        $today = \Carbon\Carbon::today();
        $echeance = \Carbon\Carbon::parse($this->date_echeance)->startOfDay();
        return $today->greaterThan($echeance) ? $echeance->diffInDays($today) : 0;
    }

    public function statutCreance(): string
    {
        if (!empty($this->statut_creance)) {
            return $this->statut_creance;
        }
        $paye  = $this->montantPaye();
        $total = (float) $this->montant;
        if ($total > 0 && $paye >= $total) {
            return 'Soldée';
        }
        if (!$this->date_echeance) {
            return $paye > 0 ? 'Échue partielle' : 'À échoir';
        }
        $today = \Carbon\Carbon::today();
        $echeance = \Carbon\Carbon::parse($this->date_echeance)->startOfDay();
        if ($today->lessThanOrEqualTo($echeance)) {
            return 'À échoir';
        }
        return $paye > 0 ? 'Échue partielle' : 'Échue impayée';
    }
}
