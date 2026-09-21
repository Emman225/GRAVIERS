<?php

namespace App\Models\Concerns;

use App\Models\User;

/**
 * Qui a initié un enregistrement, et qui l'a validé.
 *
 * Les quatre tables de règlement — paiement, paiement_fournisseur,
 * paiement_livreur, paiement_apporteur — portent les mêmes deux colonnes :
 *
 *   user_valide_id   1re validation, c'est-à-dire l'agent qui a SAISI
 *   user_valide2_id  2e validation, l'administrateur qui a CONTRÔLÉ
 *
 * Elles étaient renseignées mais jamais montrées : une créance encaissée ou une
 * dette réglée en agence n'indiquait nulle part qui en était l'auteur. Ce trait
 * expose les deux comptes et un libellé prêt à afficher, plutôt que de répéter
 * la même relation dans chaque modèle.
 */
trait TraceLesValidations
{
    public function initiateur()
    {
        return $this->belongsTo(User::class, 'user_valide_id');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'user_valide2_id');
    }

    /** « NOM PRÉNOMS (identifiant) », ou un tiret si personne — règle logée dans Help. */
    public static function libelleCompte(?User $user): string
    {
        return \Help::compteAvecIdentifiant($user);
    }

    public function getInitieParAttribute(): string
    {
        return self::libelleCompte($this->initiateur);
    }

    public function getValideParAttribute(): string
    {
        return self::libelleCompte($this->validateur);
    }
}
