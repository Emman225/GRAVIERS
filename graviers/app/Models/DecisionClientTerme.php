<?php

namespace App\Models;

use App\Models\Concerns\TraceLesValidations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * UNE DÉCISION DE CRÉDIT EN ATTENTE DE SON SECOND CONTRÔLE.
 *
 * Accorder le statut de client à terme, le retirer ou relever un plafond, c'est
 * décider combien l'entreprise accepte de ne pas être payée tout de suite. Ces
 * décisions se prenaient seul et s'appliquaient au clic.
 *
 * Elles suivent maintenant la règle du reste du back-office : celui qui saisit
 * ne valide pas, et le second doit être administrateur.
 */
class DecisionClientTerme extends Model
{
    use HasFactory, SoftDeletes, TraceLesValidations;

    protected $table = 'decision_client_terme';

    protected $fillable = [
        'client_id', 'demande_id', 'type',
        'plafond_credit', 'delai_paiement', 'commentaire',
        'ancien_plafond', 'ancien_delai',
        'user_valide_id', 'user_valide2_id',
        'date_validation_1', 'date_validation_2',
        'statut',
    ];

    protected $casts = [
        'plafond_credit'    => 'float',
        'ancien_plafond'    => 'float',
        'date_validation_1' => 'datetime',
        'date_validation_2' => 'datetime',
    ];

    public const EN_ATTENTE = 2;
    public const APPLIQUEE  = 1;
    public const REFUSEE    = 0;

    public const ACTIVATION     = 'activation';
    public const DESACTIVATION  = 'desactivation';
    public const PLAFOND        = 'plafond';
    public const REFUS_DEMANDE  = 'refus_demande';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function demande()
    {
        return $this->belongsTo(DemandeCompteClientATerme::class, 'demande_id');
    }

    /**
     * La table est-elle en place ?
     *
     * Les fichiers sont déposés sur le serveur avant que la migration ne soit
     * lancée — c'est le mode de déploiement ici, et l'écart a déjà provoqué une
     * page blanche. Les écrans concernés préfèrent le dire.
     */
    public static function tableExiste(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('decision_client_terme');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Les décisions qui attendent encore, tous clients confondus. */
    public static function enAttente()
    {
        try {
            return static::with(['client', 'initiateur'])
                ->where('statut', self::EN_ATTENTE)
                ->whereNull('user_valide2_id')
                ->orderByDesc('id')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** La décision qui attend sur CE client, s'il y en a une. */
    public static function enAttentePour(int $clientId): ?self
    {
        try {
            return static::where('client_id', $clientId)
                ->where('statut', self::EN_ATTENTE)
                ->whereNull('user_valide2_id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function attendUneSecondeValidation(): bool
    {
        return (int) $this->statut === self::EN_ATTENTE && empty($this->user_valide2_id);
    }

    /**
     * CET UTILISATEUR PEUT-IL DONNER LA SECONDE VALIDATION ?
     *
     * Les trois mêmes conditions que partout ailleurs : la décision attend
     * encore, l'utilisateur est administrateur, et ce n'est pas lui qui l'a
     * saisie. La méthode sert aussi bien au serveur qu'à l'écran, pour qu'un
     * bouton ne s'affiche jamais là où l'action sera refusée.
     */
    public function peutEtreValidePar(?User $user): bool
    {
        if (!$user || !$this->attendUneSecondeValidation()) {
            return false;
        }

        if (!in_array((int) $user->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true)) {
            return false;
        }

        return (int) $this->user_valide_id !== (int) $user->id;
    }

    /** Refuser obéit à la même règle que valider : un autre administrateur. */
    public function peutEtreRefusePar(?User $user): bool
    {
        return $this->peutEtreValidePar($user);
    }

    public function libelleType(): string
    {
        return [
            self::ACTIVATION    => 'Passage en client à terme',
            self::DESACTIVATION => 'Retrait du statut à terme',
            self::PLAFOND       => 'Révision du plafond',
            self::REFUS_DEMANDE => 'Refus de la demande',
        ][$this->type] ?? $this->type;
    }

    public function libelleStatut(): string
    {
        if ((int) $this->statut === self::APPLIQUEE) {
            return 'Appliquée';
        }

        if ((int) $this->statut === self::REFUSEE) {
            return 'Refusée';
        }

        return 'En attente de validation';
    }

    /**
     * CE QUE LA DÉCISION CHANGE, en une phrase.
     *
     * L'ancien état est figé à la saisie : sans lui, on ne pourrait plus dire
     * six mois après ce que la décision avait modifié.
     */
    public function resume(): string
    {
        $montant = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' FCFA';

        return match ($this->type) {
            self::ACTIVATION => 'Plafond ' . $montant($this->plafond_credit)
                . ', délai ' . (int) $this->delai_paiement . ' jours.',
            self::DESACTIVATION => 'Le client repasse au comptant : plus de crédit, plus de plafond.',
            self::REFUS_DEMANDE => 'La demande est écartée ; le client reste au comptant.',
            self::PLAFOND => 'Plafond ' . $montant($this->ancien_plafond) . ' → ' . $montant($this->plafond_credit)
                . ' ; délai ' . (int) $this->ancien_delai . ' → ' . (int) $this->delai_paiement . ' jours.',
            default => '',
        };
    }

    /**
     * APPLIQUE LA DÉCISION AU CLIENT.
     *
     * Appelée seulement après la seconde validation. Elle ne s'occupe QUE de
     * l'état du client : l'e-mail et la mise à jour de la demande restent au
     * contrôleur, pour qu'un envoi qui échoue ne laisse pas le client dans un
     * état à moitié changé.
     */
    public function appliquer(): void
    {
        $client = $this->client;

        if (!$client) {
            return;
        }

        match ($this->type) {
            self::ACTIVATION => $client->update([
                'client_a_terme' => 1,
                'plafond_credit' => $this->plafond_credit,
                'delai_paiement' => $this->delai_paiement,
            ]),
            self::DESACTIVATION => $client->update([
                'client_a_terme' => 0,
            ]),
            self::PLAFOND => $client->update([
                'plafond_credit' => $this->plafond_credit,
                'delai_paiement' => $this->delai_paiement,
            ]),
            // Un refus de demande ne touche pas au client : il n'avait rien.
            default => null,
        };
    }
}
