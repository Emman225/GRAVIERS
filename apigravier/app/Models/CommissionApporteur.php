<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CommissionApporteur extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'commission_apporteur';
    protected $fillable = [
        'apporteur_id',
        'commande_id',
        'montant',
        'statut',
    ];

    public static function liste($apporteur_id = null, $commande_id = null)
    {
        // commande_id est POLYMORPHE : id de COMMANDE (type_affaire=VENTE) ou de
        // LOCATION (type_affaire=LOCATION). L'ancien join unique sur commande
        // renvoyait montant_total/client NULL pour les commissions de location,
        // ce qui faisait planter le parsing du mobile (liste entièrement vide).
        return CommissionApporteur::selectRaw("commission_apporteur.*,
                COALESCE(commande.client_id, location.client_id) as client_id,
                COALESCE(commande.montant_total, location.montant_total) as montant_total,
                client.nom, client.prenom")
            ->orderBy('commission_apporteur.id', 'desc')
            ->leftJoin('commande', function ($j) {
                $j->on('commande.id', '=', 'commission_apporteur.commande_id')
                  ->where('commission_apporteur.type_affaire', '=', 'VENTE');
            })
            ->leftJoin('location', function ($j) {
                $j->on('location.id', '=', 'commission_apporteur.commande_id')
                  ->where('commission_apporteur.type_affaire', '=', 'LOCATION');
            })
            ->leftJoin('client', \DB::raw('client.id'), '=', \DB::raw('COALESCE(commande.client_id, location.client_id)'))
            ->when($apporteur_id, function ($query) use ($apporteur_id) {
                $query->where('commission_apporteur.apporteur_id', $apporteur_id);
            })
            ->when($commande_id, function ($query) use ($commande_id) {
                $query->where('commission_apporteur.commande_id', $commande_id);
            })
            ->where('commission_apporteur.statut', Help::$STATUT_ACTIF)
            ->limit(1000)
            ->get();
    }
}
