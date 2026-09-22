<?php

namespace App\Services\Comptabilite;

use App\Models\AnomalieComptable;
use App\Models\CompteComptable;
use App\Models\Configuration;
use App\Models\JournalComptable;
use App\Models\ModePaiement;
use App\Models\RubriqueComptable;

/**
 * Ce qu'un moteur va chercher dans le paramétrage pendant qu'il prépare une
 * écriture — et les anomalies que cela révèle, chacune avec l'onglet où corriger.
 */
class Preparation
{
    public array $anomalies = [];

    private function signaler(string $code, string $objet, string $colonne, string $cause, ?string $onglet): void
    {
        $this->anomalies[] = ['code' => $code, 'objet' => $objet, 'colonne' => $colonne, 'cause' => $cause, 'onglet' => $onglet, 'rang' => null];
    }

    /** @return array{0:?CompteComptable,1:?CompteComptable} le compte général actif de la rubrique, et son analytique */
    public function rubrique(string $code): array
    {
        $rubrique = RubriqueComptable::pour($code);
        $compte = $rubrique?->compte_comptable_id ? CompteComptable::actifs()->generaux()->find($rubrique->compte_comptable_id) : null;
        if (!$compte) {
            $this->signaler(AnomalieComptable::RUBRIQUE_SANS_COMPTE, $rubrique?->libelle ?? $code, 'Compte général',
                'Aucun compte général actif pour cette rubrique.', 'rubriques');
        }
        $analytique = ($rubrique && $rubrique->accepteUnAnalytique() && $rubrique->compte_analytique_id)
            ? CompteComptable::actifs()->analytiques()->find($rubrique->compte_analytique_id) : null;

        return [$compte, $analytique];
    }

    public function tiers(?string $compte, string $nom, string $cause): ?string
    {
        $compte = trim((string) $compte);
        if ($compte === '') {
            $this->signaler(AnomalieComptable::TIERS_SANS_COMPTE, $nom, 'Compte tiers', $cause, 'tiers');

            return null;
        }

        return $compte;
    }

    /** @return array{0:?JournalComptable,1:?CompteComptable} le journal de l'instrument réel et son compte de trésorerie */
    public function journalDuMode(?ModePaiement $mode, ?string $moyen): array
    {
        $nom = $mode?->libelle ?: ($moyen ?: 'Mode de règlement inconnu');
        $journal = $mode?->journal_comptable_id ? JournalComptable::actifs()->find($mode->journal_comptable_id) : null;
        if (!$journal || !$journal->estDeTresorerie()) {
            $this->signaler(AnomalieComptable::MODE_SANS_JOURNAL, $nom, 'Journal', 'Aucun journal de trésorerie actif pour ce mode de règlement.', 'journaux');

            return [null, null];
        }

        return [$journal, $this->compteDuJournal($journal)];
    }

    public function journalDesCautions(): array
    {
        $id = Configuration::first()?->journal_cautions_id;
        $journal = $id ? JournalComptable::actifs()->find($id) : null;
        if (!$journal || !$journal->estDeTresorerie()) {
            $this->signaler(AnomalieComptable::MODE_SANS_JOURNAL, 'Cautions des locations', 'Journal des cautions',
                'Aucun journal de trésorerie choisi pour les cautions (onglet « Réglages »).', 'reglages');

            return [null, null];
        }

        return [$journal, $this->compteDuJournal($journal)];
    }

    public function journalDesOperationsDiverses(): ?JournalComptable
    {
        $journal = JournalComptable::actifs()->where('type', JournalComptable::TYPE_DIVERS)->orderBy('id')->first();
        if (!$journal) {
            $this->signaler(AnomalieComptable::JOURNAL_ABSENT, 'Journal des opérations diverses', 'Type',
                'Aucun journal actif de type « Opérations diverses » : il reçoit les imputations d\'avances.', 'journaux');
        }

        return $journal;
    }

    private function compteDuJournal(JournalComptable $journal): ?CompteComptable
    {
        $compte = $journal->compte_comptable_id ? CompteComptable::actifs()->generaux()->find($journal->compte_comptable_id) : null;
        if (!$compte) {
            $this->signaler(AnomalieComptable::JOURNAL_SANS_COMPTE, $journal->designation, 'Compte de trésorerie',
                'Ce journal de trésorerie n\'a pas de compte de trésorerie actif.', 'journaux');
        }

        return $compte;
    }
}
