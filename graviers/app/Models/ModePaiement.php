<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\LignePaiement;

class ModePaiement extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'mode_paiement';
    protected $fillable = [
        'libelle',
        'description',
        'statut',
    ];

    public static function lire($id)
    {
        $obj = ModePaiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new ModePaiement();
    }

    public static function liste()
    {
        // Liste des modes SÉLECTIONNABLES pour un paiement.
        // Exclut le mode "En Agence (virement, Chèque, Espèce)" (id=1) : il ne doit
        // plus être proposé nulle part (web + mobile). Les modes Chèque, Espèces,
        // Virement, mobile money, etc. restent disponibles. L'id 1 est fixe (cf.
        // ProductionSeeder) ; la ligne reste en base pour l'historique des paiements.
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('id', '!=', 1)
            ->get();
    }

    // Liste COMPLÈTE des modes actifs (y compris "En Agence") — pour l'admin/technique,
    // pas pour les selects de paiement client.
    public static function listeTous()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new ModePaiement($arr);
        if ($obj->save()) return $obj;
        else return new ModePaiement();
    }

    public static function supprimer($id)
    {
        $obj = ModePaiement::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }
    public function lignePaiement(){
        return $this->hasMany(LignePaiement::class);
    }
}
