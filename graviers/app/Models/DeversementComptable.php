<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une transmission d'écritures vers le logiciel comptable : ce qui a été
 * envoyé, quand, par qui, sous quelle forme, et pour quelle période.
 */
class DeversementComptable extends Model
{
    public const TRANSMIS    = 'TRANSMIS';
    public const ACCUSE_RECU = 'ACCUSE_RECU';
    public const REJETE      = 'REJETE';

    public const ETATS = [
        self::TRANSMIS    => 'Transmis',
        self::ACCUSE_RECU => 'Accusé de réception',
        self::REJETE      => 'Rejeté',
    ];

    public const FORMATS = [
        'SAGE' => 'Excel (import Sage)',
        'CSV'  => 'CSV',
        'JSON' => 'JSON',
    ];

    protected $table = 'deversement_comptable';

    protected $fillable = [
        'numero', 'user_id', 'mode_periode', 'du', 'au', 'format', 'nombre_ecritures', 'nombre_lignes',
        'total_debit', 'total_credit', 'fichier', 'etat', 'accuse_le', 'motif_rejet',
    ];

    protected $casts = [
        'du' => 'date', 'au' => 'date', 'accuse_le' => 'datetime',
        'total_debit' => 'float', 'total_credit' => 'float',
        'nombre_ecritures' => 'integer', 'nombre_lignes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ecritures()
    {
        return $this->hasMany(EcritureComptable::class, 'deversement_id');
    }

    public function getLibelleEtatAttribute(): string
    {
        return self::ETATS[$this->etat] ?? $this->etat;
    }

    public function getLibelleFormatAttribute(): string
    {
        return self::FORMATS[$this->format] ?? $this->format;
    }

    /** « du 1er au 30 septembre 2026 », ou « septembre 2026 » quand la période est un mois entier. */
    public function getLibellePeriodeAttribute(): string
    {
        if ($this->mode_periode === 'MOIS') {
            return \Help::phrase($this->du->locale('fr')->isoFormat('MMMM YYYY'));
        }

        return 'du ' . $this->du->format('d/m/Y') . ' au ' . $this->au->format('d/m/Y');
    }

    public function estRejete(): bool
    {
        return $this->etat === self::REJETE;
    }
}
