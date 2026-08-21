<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\User;
use App\Models\Enlevement;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Produit;
use Cviebrock\EloquentSluggable\Sluggable;

class Fournisseur extends Model
{
    use HasFactory, SoftDeletes ;
    protected $table = 'fournisseur';
    protected $fillable = [
        'user_id',
        'nom_prenoms',
        'nom',
        'prenom',
        'email',
        'contact1',
        'contact2',
        'contact',
        'adresse_geo',
        'adresse_postale',
        'adresse',
        'longitude',
        'latitude',
        'statut',
        'solde',
        'code',
        'type_fournisseur',
        'produit_principal',
        'delai_paiement',
        'notes',
        'dfe',
        'registre_commerce',
        // Faux par défaut : le règlement porte alors sur le seul coût du produit.
        // Vrai pour un fournisseur déclaré, qui facture la TVA (cf. Enlevement::montantDu).
        'assujetti_tva',
    ];

    protected $casts = [
        'assujetti_tva' => 'boolean',
    ];

    public function paiementsFournisseur()
    {
        return $this->hasMany(PaiementFournisseur::class, 'fournisseur_id');
    }

    /**
     * Impute une demande de paiement acceptée sur les bons du fournisseur.
     *
     * L'entreprise paie ses fournisseurs par deux chemins : le règlement d'un
     * bon précis (« Dettes fournisseurs »), et la demande de paiement portant
     * sur un montant. Seul le premier écrivait dans `paiement_fournisseur` ;
     * les bons restaient donc entièrement dus après avoir été payés, et le
     * popup « Enregistrer un paiement fournisseur » proposait encore la
     * totalité — au risque d'un second règlement du même montant.
     *
     * Le montant est réparti sur les bons non soldés DU PLUS ANCIEN AU PLUS
     * RÉCENT : c'est l'ordre d'apurement usuel, et il évite qu'une vieille
     * dette reste ouverte pendant qu'une récente est soldée.
     *
     * Chaque ligne créée porte `demande_paiement_id` : ce lien permet à
     * soldeCalcule() de ne pas retrancher deux fois le même montant — une fois
     * comme demande, une fois comme règlement.
     *
     * Si le montant dépasse ce que les bons doivent encore — cas anormal, mais
     * possible sur un solde hérité —, le reliquat n'est imputé nulle part
     * plutôt que d'inventer une dette.
     *
     * Vit ici, et non dans le contrôleur, parce que deux appelants s'en
     * servent : la 2e validation d'une demande, et le rattrapage des demandes
     * acceptées avant que ce mécanisme n'existe.
     *
     * @return float Le montant réellement imputé.
     */
    public function imputerDemandeSurLesBons(DemandePaiement $demande, $validateur2 = null): float
    {
        $restant = (float) $demande->montant;

        if ($restant <= 0) {
            return 0.0;
        }

        $impute = 0.0;

        $bons = Enlevement::where('fournisseur_id', $this->id)
            ->whereNotNull('fournisseur_validation')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($bons as $bon) {
            if ($restant < 1) {
                break;
            }

            $reste = $bon->resteAPayer();

            if ($reste < 1) {
                continue;
            }

            $part = min($restant, $reste);

            PaiementFournisseur::create([
                'date_paiement'       => optional($demande->date_validation)->toDateString()
                    ?? now()->toDateString(),
                'enlevement_id'       => $bon->id,
                'demande_paiement_id' => $demande->id,
                'fournisseur_id'      => $this->id,
                'montant'             => $part,
                'mode_paiement_id'    => $demande->mode_paiement_id,
                'reference'           => $demande->numero,
                'notes'               => 'Demande de paiement du fournisseur n° '
                    . ($demande->numero ?: $demande->id),
                'user_id'             => $demande->user_valide_id,
                'statut'              => 1,
                // Les deux validateurs de la demande : le journal des paiements
                // doit dire qui a décidé, comme pour une saisie manuelle.
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
     * `solde` est une colonne tenue à la main, incrémentée à la validation d'un
     * bon et décrémentée depuis cinq endroits. Elle ne peut donc que dériver :
     * un bon supprimé, un crédit calculé par une version antérieure du code ou
     * un vidage de la base laissent une valeur que plus aucune pièce ne
     * justifie. Cette méthode rebâtit le montant depuis les enregistrements.
     *
     * Trois termes, et trois seulement :
     *   + ce que l'entreprise doit sur les bons VALIDÉS par le fournisseur ;
     *   − ce qui lui a déjà été réglé (écran « Dettes fournisseurs ») ;
     *   − ce qu'il a lui-même demandé et qui n'a pas été refusé — accepté donc
     *     versé, ou encore en attente donc réservé.
     *
     * Une demande REFUSÉE n'entre pas : son montant a été restitué.
     */
    public function soldeCalcule(): float
    {
        $bons = Enlevement::where('fournisseur_id', $this->id)
            ->whereNotNull('fournisseur_validation')
            ->get();

        $du = (float) $bons->sum(fn (Enlevement $e) => $e->montantDu());

        // Les règlements issus d'une demande de paiement sont EXCLUS ici : ils
        // sont déjà comptés plus bas, en tant que demande. Sans cette
        // exclusion, une demande acceptée serait retranchée deux fois — une
        // fois comme demande, une fois comme règlement — et le solde du
        // fournisseur tomberait au double de ce qu'il a réellement touché.
        $regle = (float) PaiementFournisseur::whereIn('enlevement_id', $bons->pluck('id'))
            ->where('statut', 1)
            ->whereNull('demande_paiement_id')
            ->sum('montant');

        $demande = (float) DemandePaiement::where('user_id', $this->user_id)
            ->where(function ($q) {
                // NULL ou 0 = en attente, 1 = acceptée. 2 = refusée, exclue.
                $q->whereNull('paye')->orWhereIn('paye', [0, 1]);
            })
            ->sum('montant');

        // Un solde négatif ne veut rien dire pour le fournisseur : il signale
        // un trop-versé, qui se traite dans l'écran des dettes, pas ici.
        return max(0.0, round($du - $regle - $demande));
    }



    public static function lire($id)
    {
        $obj = Fournisseur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Fournisseur();
    }
    public function produits(){
        return $this->belongsToMany(Produit::class,'stock_produit')->withPivot('qte','prix','seuil_alert');
    }
    public function enlevements(){
        return $this->hasMany(Enlevement::class,'fournisseur_id');
    }
    public function user(){
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'','photo'=>'']);
    }
    public static function lireSurUser($idUser)
    {
        $obj = Fournisseur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Fournisseur();
    }

    public static function liste()
    {
        return Fournisseur::orderBy('nom_prenoms', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Fournisseur($arr);
        if ($obj->save()) return $obj;
        else return new Fournisseur();
    }

    public static function supprimer($id)
    {
        $obj = Fournisseur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

}
