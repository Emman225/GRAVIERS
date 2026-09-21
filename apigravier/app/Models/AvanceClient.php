<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UNE AVANCE DÉPOSÉE PAR UN CLIENT (point 19, 07/09/2026).
 *
 * Table créée par la migration du SITE (2026_09_07_140000). Le dépôt se fait
 * au guichet ; l'API ne fait que CONSOMMER les avances disponibles quand une
 * commande mobile est réglée « en agence » (App\Services\Avances).
 */
class AvanceClient extends Model
{
    use SoftDeletes;

    public const EN_ATTENTE = 2;
    public const DISPONIBLE = 1;

    protected $table = 'avance_client';

    protected $fillable = [
        'client_id', 'montant', 'montant_consomme', 'statut', 'numero_recu',
        'mode_paiement_id', 'moyen_paiement', 'reference', 'agence_id',
        'caissier_id', 'libelle', 'origine', 'origine_recu', 'date_depot',
        'user_valide_id', 'user_valide2_id', 'date_validation_1',
        'date_validation_2', 'recu_envoye_le',
    ];

    public function solde(): float
    {
        return max(0.0, (float) $this->montant - (float) $this->montant_consomme);
    }

    /** Les dépôts disponibles d'un client, du plus ancien au plus récent. */
    public static function disponibles(int $clientId)
    {
        return static::where('client_id', $clientId)
            ->where('statut', self::DISPONIBLE)
            ->orderBy('date_depot')
            ->orderBy('id')
            ->get()
            ->filter(fn (AvanceClient $a) => $a->solde() >= 1)
            ->values();
    }

    public static function soldeDisponible(int $clientId): float
    {
        return (float) static::disponibles($clientId)->sum(fn (AvanceClient $a) => $a->solde());
    }
}
