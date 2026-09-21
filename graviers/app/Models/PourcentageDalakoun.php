<?php

namespace App\Models;

use App\Models\Concerns\TraceLesValidations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * LE POURCENTAGE QUE DALAKOUN AJOUTE AU PRIX D'ACHAT.
 *
 * C'est lui qui fait le prix du catalogue : prix d'achat × (1 + taux / 100).
 * Sans lui, l'entreprise vendait au prix auquel elle achetait.
 *
 * Un taux ne s'applique qu'une fois DOUBLEMENT VALIDÉ : celui qui le saisit ne
 * peut pas le valider, et le second validateur doit être administrateur. Le
 * tarif de tout le catalogue en dépend — cela ne se décide pas seul.
 *
 * Les taux ne s'écrasent pas : chacun reste en base avec sa date d'entrée en
 * vigueur, pour qu'on puisse toujours dire lequel s'appliquait un jour donné.
 */
class PourcentageDalakoun extends Model
{
    use HasFactory, SoftDeletes, TraceLesValidations;

    protected $table = 'pourcentage_dalakoun';

    protected $fillable = [
        'produit_id',
        'taux',
        'motif',
        'user_valide_id',
        'user_valide2_id',
        'date_validation_1',
        'date_validation_2',
        'statut',
    ];

    protected $casts = [
        'taux'              => 'float',
        'date_validation_1' => 'datetime',
        'date_validation_2' => 'datetime',
    ];

    /** Saisi, mais pas encore contrôlé par un second administrateur. */
    public const EN_ATTENTE = 2;
    /** Validé : c'est ce taux qui fait les prix. */
    public const APPLIQUE = 1;
    public const REFUSE = 0;

    /**
     * LE TAUX EN VIGUEUR, ou null si aucun n'a encore été validé.
     *
     * Le dernier validé l'emporte. Tant qu'aucun ne l'est, la méthode renvoie
     * null et les prix restent ce qu'ils sont : l'installation du mécanisme ne
     * change rien tant que personne n'a rien décidé.
     */
    public static function enVigueur(): ?self
    {
        // LA TABLE PEUT NE PAS EXISTER ENCORE. Les fichiers sont déposés sur le
        // serveur avant que la migration ne soit lancée — c'est le mode de
        // déploiement ici, et l'écart a déjà provoqué une page blanche.
        //
        // L'enjeu est sérieux : ce taux est lu par TOUT le catalogue. Une
        // migration oubliée ne casserait pas un écran d'administration mais la
        // boutique entière.
        //
        // On ESSAIE plutôt qu'on ne VÉRIFIE : demander d'abord si la table
        // existe coûterait une requête supplémentaire sur chaque page du site,
        // pour un incident qui ne se produit qu'une fois par déploiement.
        try {
            return static::where('statut', self::APPLIQUE)
                // Le taux GÉNÉRAL : une dérogation ne vaut que pour son produit.
                ->whereNull('produit_id')
                ->whereNotNull('user_valide2_id')
                ->orderByDesc('date_validation_2')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * La table est-elle en place ?
     *
     * Volontairement SANS mémorisation : une réponse gardée en mémoire
     * survivrait à la migration elle-même, et l'écran continuerait d'annoncer
     * une table absente une fois qu'elle existe.
     */
    public static function tableExiste(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('pourcentage_dalakoun');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Le taux en vigueur, en pourcentage. Zéro si aucun n'est validé. */
    public static function tauxEnVigueur(): float
    {
        return (float) (static::enVigueur()?->taux ?? 0);
    }

    /**
     * Applique le taux à un prix d'achat.
     *
     * Aucun taux validé : le prix ressort inchangé. C'est volontaire — mieux
     * vaut un prix sans marge, visible et corrigible, qu'un prix inventé.
     */
    public static function appliquerA(float $prixAchat, ?float $taux = null): float
    {
        $taux = $taux ?? static::tauxEnVigueur();

        return $prixAchat * (1 + $taux / 100);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    /** Une ligne rattachée à un produit déroge au taux général. */
    public function estUneDerogation(): bool
    {
        return !empty($this->produit_id);
    }

    /**
     * Une dérogation sans taux demande le RETOUR au taux général.
     *
     * Retirer une dérogation change un prix autant que la poser : la décision
     * passe donc par la même double validation.
     */
    public function estUnRetraitDeDerogation(): bool
    {
        return $this->estUneDerogation() && $this->taux === null;
    }

    /**
     * APPLIQUE LA DÉCISION AU PRODUIT.
     *
     * Le taux retenu est recopié sur le produit, et son prix de vente refait
     * dans la foulée : la validation n'a de sens que si le prix change.
     */
    public function appliquerAuProduit(): void
    {
        $produit = $this->produit;

        if (!$produit) {
            return;
        }

        $produit->update(['pourcentage_dalakoun' => $this->taux]);

        $prixAchat = Produit::prixAchatDe($produit->id);

        if ($prixAchat === null) {
            // Aucun fournisseur ne le tarife : pas de coût, pas de marge à
            // appliquer. On ne fabrique pas un prix.
            return;
        }

        $produit->update([
            'prix_moyen' => round(static::appliquerA($prixAchat, $produit->fresh()->tauxDalakoun())),
        ]);
    }

    public function estApplique(): bool
    {
        return (int) $this->statut === self::APPLIQUE && !empty($this->user_valide2_id);
    }

    public function attendUneSecondeValidation(): bool
    {
        return (int) $this->statut === self::EN_ATTENTE && empty($this->user_valide2_id);
    }

    /**
     * CET UTILISATEUR PEUT-IL DONNER LA SECONDE VALIDATION ?
     *
     * Trois conditions, les mêmes que sur les six écrans de règlement du
     * back-office : le taux attend encore, l'utilisateur est administrateur,
     * et ce n'est pas lui qui l'a saisi.
     *
     * La règle était appliquée par le contrôleur mais pas par l'écran : le
     * bouton « Valider » s'affichait à l'auteur, qui se voyait proposer une
     * action que le serveur allait lui refuser. Une interdiction qu'on ne
     * découvre qu'en cliquant n'est pas une interdiction lisible.
     */
    public function peutEtreValidePar(?\App\Models\User $user): bool
    {
        if (!$user || !$this->attendUneSecondeValidation()) {
            return false;
        }

        if (!in_array((int) $user->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true)) {
            return false;
        }

        return (int) $this->user_valide_id !== (int) $user->id;
    }

    /**
     * REFUSER OBÉIT À LA MÊME RÈGLE QUE VALIDER : un autre administrateur.
     *
     * L'auteur pouvait d'abord retirer sa propre proposition — un renoncement,
     * pas une validation. Mais la colonne « Action » annonçait alors « En
     * attente d'un autre administrateur » tout en proposant un bouton : deux
     * messages contraires dans la même cellule.
     *
     * Sur son propre taux, l'auteur ne fait donc plus rien. S'il s'est trompé
     * de chiffre, un second administrateur le refuse — c'est exactement
     * l'objet de la double validation.
     */
    public function peutEtreRefusePar(?\App\Models\User $user): bool
    {
        return $this->peutEtreValidePar($user);
    }

    public function libelleStatut(): string
    {
        if ($this->estApplique()) {
            return 'En vigueur';
        }

        if ((int) $this->statut === self::REFUSE) {
            return 'Refusé';
        }

        return 'En attente de validation';
    }
}
