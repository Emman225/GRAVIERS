<?php

namespace App\Models;

use App\Models\Concerns\TraceLesValidations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UNE AVANCE DÉPOSÉE PAR UN CLIENT (point 19, 07/09/2026).
 *
 * Le dépôt attend une seconde validation (statut 2), puis devient disponible
 * (statut 1). Ce qui reste = montant − montant_consomme. Les commandes réglées
 * « en agence » s'y imputent d'elles-mêmes (App\Services\Avances).
 */
class AvanceClient extends Model
{
    use SoftDeletes, TraceLesValidations;

    public const EN_ATTENTE = 2;
    public const DISPONIBLE = 1;
    public const ANNULEE    = 0;

    protected $table = 'avance_client';

    protected $fillable = [
        'client_id', 'montant', 'montant_consomme', 'statut', 'numero_recu',
        'mode_paiement_id', 'moyen_paiement', 'reference', 'agence_id',
        'caissier_id', 'libelle', 'origine', 'origine_recu', 'date_depot',
        'user_valide_id', 'user_valide2_id', 'date_validation_1',
        'date_validation_2', 'recu_envoye_le',
    ];

    protected $casts = [
        'date_depot'        => 'datetime',
        'date_validation_1' => 'datetime',
        'date_validation_2' => 'datetime',
        'recu_envoye_le'    => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class, 'agence_id');
    }

    public function caissier()
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    public function mouvements()
    {
        return $this->hasMany(MouvementAvance::class, 'avance_client_id')->orderBy('id');
    }

    /** Ce qu'il reste à consommer sur ce dépôt (jamais négatif). */
    public function solde(): float
    {
        return max(0.0, (float) $this->montant - (float) $this->montant_consomme);
    }

    public function estDisponible(): bool
    {
        return (int) $this->statut === self::DISPONIBLE && $this->solde() >= 1;
    }

    public function libelleStatut(): string
    {
        if ((int) $this->statut === self::ANNULEE) {
            return 'Annulée';
        }
        if ((int) $this->statut === self::EN_ATTENTE) {
            return 'En attente de validation';
        }
        return $this->solde() < 1 ? 'Épuisée' : 'Disponible';
    }

    /**
     * Les dépôts disponibles d'un client, du plus ancien au plus récent :
     * c'est l'ordre d'imputation.
     */
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

    /** Solde d'avance d'un client = dépôts validés − déductions. */
    public static function soldeDisponible(int $clientId): float
    {
        return (float) static::disponibles($clientId)->sum(fn (AvanceClient $a) => $a->solde());
    }

    /** Soldes de tous les clients, en une requête : [client_id => solde]. */
    public static function soldesParClient(): array
    {
        return static::where('statut', self::DISPONIBLE)
            ->selectRaw('client_id, SUM(montant - montant_consomme) AS solde')
            ->groupBy('client_id')
            ->pluck('solde', 'client_id')
            ->map(fn ($s) => max(0.0, (float) $s))
            ->all();
    }
}
