<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Client;
use App\Models\CommissionApporteur;
use App\Models\PaiementApporteur;
use App\Models\DemandePaiement;

class Apporteur extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'apporteur';
    protected $fillable = [
        'user_id',
        'code',
        'solde',
        'statut',
        'pourcentage',
        'nom',
        'prenom',
        'piece_recto',
        'piece_verso',
        'numero_piece',
        'mode_paiement_id',
        'mode_paiement_prefere',
        'coordonnees_paiement',
        'zone_intervention',
    ];

    public function modePaiement()
    {
        return $this->belongsTo(ModePaiement::class, 'mode_paiement_id');
    }

    /**
     * Impute une demande de paiement acceptée sur les commissions de l'apporteur.
     *
     * L'apporteur est payé par deux chemins : la demande qu'il initie lui-même,
     * et le règlement qu'un administrateur saisit sur une de ses commissions.
     * Seul le second écrivait dans `paiement_apporteur` ; ses commissions
     * restaient donc entièrement dues après avoir été payées, et le formulaire
     * « Enregistrer un paiement de commission » proposait encore la totalité.
     *
     * Le montant est réparti sur les commissions non soldées DE LA PLUS
     * ANCIENNE À LA PLUS RÉCENTE : c'est l'ordre d'apurement usuel.
     *
     * Chaque ligne créée porte `demande_paiement_id` : ce lien permet à
     * soldeCalcule() de ne pas retrancher deux fois le même montant.
     *
     * @return float Le montant réellement imputé.
     */
    public function imputerDemandeSurLesCommissions(DemandePaiement $demande, $validateur2 = null): float
    {
        $restant = (float) $demande->montant;

        if ($restant <= 0) {
            return 0.0;
        }

        $impute = 0.0;

        $commissions = CommissionApporteur::where('apporteur_id', $this->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($commissions as $commission) {
            if ($restant < 1) {
                break;
            }

            $reste = $commission->resteAPayerCommission();

            if ($reste < 1) {
                continue;
            }

            $part = min($restant, $reste);

            PaiementApporteur::create([
                'date_paiement'       => optional($demande->date_validation)->toDateString()
                    ?? now()->toDateString(),
                'commission_id'       => $commission->id,
                'demande_paiement_id' => $demande->id,
                'apporteur_id'        => $this->id,
                'montant'             => $part,
                'mode_paiement_id'    => $demande->mode_paiement_id,
                'reference'           => $demande->numero,
                'notes'               => 'Demande de paiement de l\'apporteur n° '
                    . ($demande->numero ?: $demande->id),
                'user_id'             => $demande->user_valide_id,
                'statut'              => 1,
                'user_valide_id'      => $demande->user_valide_id,
                'user_valide2_id'     => $validateur2 ?? $demande->user_valide2_id,
                'date_validation_1'   => $demande->created_at,
                'date_validation_2'   => $demande->date_validation ?? now(),
            ]);

            $restant -= $part;
            $impute  += $part;
        }

        return $impute;
    }

    /**
     * Le solde reconstitué à partir des pièces, et non lu dans la colonne.
     *
     * Trois termes :
     *   + les commissions acquises ;
     *   − ce qui lui a déjà été réglé ;
     *   − ce qu'il a demandé et qui n'a pas été refusé.
     *
     * ATTENTION — CE CALCUL PEUT SOUS-ESTIMER LE PASSÉ.
     *
     * Jusqu'à la migration `tracer_les_commissions_de_location`, une commission
     * gagnée sur une LOCATION créditait le solde sans créer la moindre
     * commission. Ces gains-là n'ont laissé aucune trace : ils sont
     * irrécupérables, et ce calcul ne peut pas les compter.
     *
     * C'est pourquoi la commande de rattrapage n'AUGMENTE le solde que par
     * défaut, et ne le baisse que si on le lui demande explicitement.
     */
    public function soldeCalcule(): float
    {
        $acquis = (float) CommissionApporteur::where('apporteur_id', $this->id)
            ->whereNull('deleted_at')
            ->sum('montant');

        // Les règlements issus d'une demande sont EXCLUS ici : ils sont déjà
        // comptés plus bas, en tant que demande. Sans cette exclusion, une
        // demande acceptée serait retranchée deux fois et le solde de
        // l'apporteur tomberait au double de ce qu'il a réellement touché.
        $regle = (float) PaiementApporteur::whereIn(
                'commission_id',
                CommissionApporteur::where('apporteur_id', $this->id)->pluck('id')
            )
            ->where('statut', 1)
            ->whereNull('demande_paiement_id')
            ->sum('montant');

        $demande = (float) DemandePaiement::where('user_id', $this->user_id)
            ->where(function ($q) {
                // NULL ou 0 = en attente, 1 = acceptée. 2 = refusée, exclue.
                $q->whereNull('paye')->orWhereIn('paye', [0, 1]);
            })
            ->sum('montant');

        return max(0.0, round($acquis - $regle - $demande));
    }

    public function commissions()
    {
        return $this->hasMany(CommissionApporteur::class, 'apporteur_id');
    }

    public function paiementsApporteur()
    {
        return $this->hasMany(PaiementApporteur::class, 'apporteur_id');
    }

    public static function lire($id)
    {
        $obj = Apporteur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Apporteur();
    }

    public static function lireSurUser($idUser)
    {
        $obj = Apporteur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Apporteur();
    }

    public static function liste()
    {
        return Apporteur::selectRaw("apporteur.*, users.nom_prenoms")
            ->orderBy('users.nom_prenoms')
            ->join('users', 'users.id', '=', 'apporteur.user_id')
            ->where('apporteur.statut', Help::$STATUT_ACTIF)->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Apporteur($arr);
        if ($obj->save()) return $obj;
        else return new Apporteur();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'','photo'=>'']);
    }

    public static function supprimer($id)
    {
        $obj = Apporteur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function clients()
    {
        return $this->hasMany(Client::class, 'code_parrain');
    }
}
