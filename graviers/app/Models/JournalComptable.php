<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un journal comptable. Un journal de trésorerie (banque, caisse, Mobile
 * Money) porte son compte de trésorerie ; le journal des ventes n'en a pas.
 */
class JournalComptable extends Model
{
    use SoftDeletes;

    public const TYPE_VENTES       = 'VENTES';
    public const TYPE_BANQUE       = 'BANQUE';
    public const TYPE_CAISSE       = 'CAISSE';
    public const TYPE_MOBILE_MONEY = 'MOBILE_MONEY';
    public const TYPE_DIVERS       = 'DIVERS';

    public const TYPES = [
        self::TYPE_VENTES       => 'Ventes',
        self::TYPE_BANQUE       => 'Banque',
        self::TYPE_CAISSE       => 'Caisse',
        self::TYPE_MOBILE_MONEY => 'Mobile Money',
        self::TYPE_DIVERS       => 'Opérations diverses',
    ];

    /** Les journaux qui reçoivent des règlements, donc qui exigent un compte de trésorerie. */
    public const TYPES_DE_TRESORERIE = [self::TYPE_BANQUE, self::TYPE_CAISSE, self::TYPE_MOBILE_MONEY];

    protected $table = 'journal_comptable';

    protected $fillable = ['code', 'libelle', 'type', 'compte_comptable_id', 'statut'];

    protected $casts = ['statut' => 'integer'];

    public function compte()
    {
        return $this->belongsTo(CompteComptable::class, 'compte_comptable_id');
    }

    public function scopeActifs($requete)
    {
        return $requete->where('statut', 1);
    }

    public function estDeTresorerie(): bool
    {
        return in_array($this->type, self::TYPES_DE_TRESORERIE, true);
    }

    public function getDesignationAttribute(): string
    {
        return $this->code . ' — ' . $this->libelle;
    }

    /** Même règle que pour un compte : un journal employé se désactive, il ne se supprime pas. */
    public function emplois(): array
    {
        $emplois = [
            'mode(s) de règlement' => DB::table('mode_paiement')->whereNull('deleted_at')->where('journal_comptable_id', $this->id)->count(),
        ];

        if (Schema::hasTable('ecriture_comptable')) {
            $emplois['écriture(s)'] = DB::table('ecriture_comptable')->where('journal_comptable_id', $this->id)->count();
        }

        return array_filter($emplois);
    }
}
