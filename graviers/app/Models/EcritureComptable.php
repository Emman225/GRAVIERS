<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une écriture comptable produite par le module : une par facture normalisée,
 * une par avoir, une par annulation. Exportée, elle ne change plus jamais —
 * toute correction passe par une écriture d'annulation puis une nouvelle.
 */
class EcritureComptable extends Model
{
    public const ORIGINE_FACTURE    = 'FACTURE';
    public const ORIGINE_AVOIR      = 'AVOIR';
    public const ORIGINE_ANNULATION = 'ANNULATION';

    public const ORIGINES = [
        self::ORIGINE_FACTURE    => 'Facture',
        self::ORIGINE_AVOIR      => 'Avoir',
        self::ORIGINE_ANNULATION => 'Annulation',
        // Trésorerie (lot 118) — les constantes vivent dans MoteurTresorerie.
        'ENCAISSEMENT'      => 'Encaissement',
        'AVANCE_DEPOT'      => 'Avance reçue',
        'AVANCE_IMPUTATION' => 'Imputation d’avance',
        'DECAISSEMENT'      => 'Décaissement',
        'CAUTION_RECUE'     => 'Caution reçue',
        'CAUTION_RENDUE'    => 'Caution rendue',
    ];

    public const ETAT_A_EXPORTER = 'A_EXPORTER';
    public const ETAT_EXPORTEE   = 'EXPORTEE';
    public const ETAT_ANOMALIE   = 'ANOMALIE';

    public const ETATS = [
        self::ETAT_A_EXPORTER => 'À exporter',
        self::ETAT_EXPORTEE   => 'Exportée',
        self::ETAT_ANOMALIE   => 'En anomalie',
    ];

    protected $table = 'ecriture_comptable';

    protected $fillable = [
        'identifiant', 'origine', 'source_type', 'source_id', 'version', 'annulation_de_id', 'annulee_par_id',
        'journal_comptable_id', 'journal_code', 'date_ecriture', 'piece', 'reference_fne', 'libelle',
        'service', 'service_id', 'numero_affaire', 'client_id', 'total_debit', 'total_credit',
        'etat', 'exportee_le', 'deversement_id', 'tiers_type', 'tiers_id',
    ];

    protected $casts = [
        'date_ecriture' => 'date',
        'exportee_le'   => 'datetime',
        'total_debit'   => 'float',
        'total_credit'  => 'float',
        'version'       => 'integer',
    ];

    public function lignes()
    {
        return $this->hasMany(LigneEcritureComptable::class, 'ecriture_comptable_id')->orderBy('rang');
    }

    public function anomalies()
    {
        return $this->hasMany(AnomalieComptable::class, 'ecriture_comptable_id');
    }

    public function anomaliesOuvertes()
    {
        return $this->anomalies()->whereNull('resolue_le');
    }

    public function journal()
    {
        return $this->belongsTo(JournalComptable::class, 'journal_comptable_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class, 'source_id')->withTrashed();
    }

    public function annulationDe()
    {
        return $this->belongsTo(self::class, 'annulation_de_id');
    }

    public function annuleePar()
    {
        return $this->belongsTo(self::class, 'annulee_par_id');
    }

    public function estExportee(): bool
    {
        return $this->etat === self::ETAT_EXPORTEE;
    }

    public function estEquilibree(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.005 && $this->total_debit > 0;
    }

    /** Les écritures vivantes d'une facture : ni annulées, ni annulations. */
    public function scopeVivantesDeLaFacture($requete, int $factureId)
    {
        return $requete->where('source_type', 'facture')->where('source_id', $factureId)
            ->whereNull('annulee_par_id')->where('origine', '!=', self::ORIGINE_ANNULATION);
    }

    public function getLibelleEtatAttribute(): string
    {
        return self::ETATS[$this->etat] ?? $this->etat;
    }
}
