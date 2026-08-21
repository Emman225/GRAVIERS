<?php

namespace App\Models;

use Help;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Livraison;
use App\Models\DemandePaiement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Livreur extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'livreur';
    protected $fillable = [
        'user_id',
        'num_piece_identite',
        'piece_recto',
        'piece_verso',
        'statut',
        'solde',
        'cout_livraison',
        'nom_prenoms',
        'longitude',
        'latitude',
        'derniere_position_at',
        'disponible',
        'code',
        'zone_intervention',
        'tarif_km',
        'tarif_forfait_base',
        'mode_tarification',
    ];

    /**
     * Impute une demande de paiement acceptée sur les courses du livreur.
     *
     * Le livreur est payé par deux chemins : la demande qu'il initie depuis
     * l'application mobile, et le règlement qu'un administrateur saisit sur une
     * de ses courses. Seul le second écrivait dans `paiement_livreur` ; les
     * courses restaient donc dues après avoir été payées, et l'enregistrement
     * manuel avait dû être désactivé pour éviter un double paiement.
     *
     * Le montant est réparti sur les courses non soldées DE LA PLUS ANCIENNE À
     * LA PLUS RÉCENTE : c'est l'ordre d'apurement usuel.
     *
     * Chaque ligne créée porte `demande_paiement_id` : ce lien permet à
     * soldeCalcule() de ne pas retrancher deux fois le même montant.
     *
     * @return float Le montant réellement imputé.
     */
    public function imputerDemandeSurLesCourses(DemandePaiement $demande, $validateur2 = null): float
    {
        $restant = (float) $demande->montant;

        if ($restant <= 0) {
            return 0.0;
        }

        $impute = 0.0;

        $courses = Livraison::where('livreur_id', $this->id)
            ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
            ->whereNull('deleted_at')
            ->orderBy('date_livraison')
            ->orderBy('id')
            ->get();

        foreach ($courses as $course) {
            if ($restant < 1) {
                break;
            }

            $reste = $course->resteAPayerLivreur();

            if ($reste < 1) {
                continue;
            }

            $part = min($restant, $reste);

            PaiementLivreur::create([
                'date_paiement'       => optional($demande->date_validation)->toDateString()
                    ?? now()->toDateString(),
                'livraison_id'        => $course->id,
                'demande_paiement_id' => $demande->id,
                'livreur_id'          => $this->id,
                'montant'             => $part,
                'mode_paiement_id'    => $demande->mode_paiement_id,
                'reference'           => $demande->numero,
                'notes'               => 'Demande de paiement du livreur n° '
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
     * `solde` est un cumul tenu à la main : crédité à la clôture de chaque
     * course (site ET application mobile), débité aux demandes de paiement.
     * Il dérive donc dès qu'un événement lui échappe — une course supprimée,
     * un vidage de la base, ou une demande dont l'enregistrement a échoué
     * après que le solde eut été débité.
     *
     * Trois termes :
     *   + ce que rapportent les courses réellement LIVRÉES
     *     (Livraison::totalDuLivreur : forfait + frais kilométriques quand ils
     *     sont renseignés, sinon le coût de livraison) ;
     *   − ce qui a déjà été réglé par un paiement enregistré ;
     *   − ce qu'il a demandé et qui n'a pas été refusé.
     *
     * Une demande REFUSÉE n'entre pas : son montant a été restitué.
     */
    public function soldeCalcule(): float
    {
        $courses = Livraison::where('livreur_id', $this->id)
            ->where('etat_livraison', Help::$LIVRAISON_LIVREE)
            ->whereNull('deleted_at')
            ->get();

        $gagne = (float) $courses->sum(fn (Livraison $l) => $l->totalDuLivreur());

        // Les règlements issus d'une demande sont EXCLUS ici : ils sont déjà
        // comptés plus bas, en tant que demande. Sans cette exclusion, une
        // demande acceptée serait retranchée deux fois et le solde du livreur
        // tomberait au double de ce qu'il a réellement touché.
        $regle = (float) PaiementLivreur::whereIn('livraison_id', $courses->pluck('id'))
            ->where('statut', 1)
            ->whereNull('demande_paiement_id')
            ->sum('montant');

        $demande = (float) DemandePaiement::where('user_id', $this->user_id)
            ->where(function ($q) {
                // NULL ou 0 = en attente, 1 = acceptée. 2 = refusée, exclue.
                $q->whereNull('paye')->orWhereIn('paye', [0, 1]);
            })
            ->sum('montant');

        return max(0.0, round($gagne - $regle - $demande));
    }

    public function paiementsLivreur()
    {
        return $this->hasMany(PaiementLivreur::class, 'livreur_id');
    }

    public static function lire($id)
    {
        $obj = Livreur::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livreur();
    }

    /**
     * Gain du livreur pour UNE livraison, selon sa tarification (appliquée telle
     * quelle, sans multiplier par le nombre de voyages) :
     *   - mode 'km'   : tarif_km × distance
     *   - mode 'base' : cout_livraison (tarif de base forfaitaire, saisi via le profil)
     * Si la tarification n'est pas configurée (mode/tarif vides ou à 0), on retombe
     * sur $coutGlobal (le calcul global : distance × coût fixe × nombre de voyages).
     */
    public function gainPourLivraison(float $distance, float $coutGlobal = 0): float
    {
        return (float) $this->tarificationLivraison($distance, $coutGlobal)['total'];
    }

    /**
     * Décomposition de la rémunération du livreur pour UNE livraison :
     *   - mode 'km'   : frais_km = tarif_km × distance (forfait_base = 0)
     *   - mode 'base' : forfait_base = cout_livraison  (frais_km = 0)
     *   - non configuré : repli sur le coût global (rangé en forfait_base)
     * Retourne ['forfait_base', 'frais_km', 'total'] pour alimenter les colonnes
     * forfait_base / frais_km / cout_livraison de la livraison.
     */
    /**
     * Nombre de ROTATIONS qu'impose une quantité pour un véhicule donné.
     *
     * Toujours arrondi au supérieur : un quart de chargement demande un
     * déplacement complet. Et jamais moins d'un voyage.
     *
     * La capacité du véhicule RÉELLEMENT affecté est la seule mesure honnête.
     * Un écran s'appuyait sur la moyenne « tonne_moyenne » de la configuration
     * (40) : avec un camion de 20 tonnes, 60 tonnes n'y comptaient que pour un
     * voyage et demi au lieu de trois. Cette moyenne ne sert plus que de repli
     * quand aucun véhicule n'est connu.
     */
    public static function nombreDeVoyages(float $quantite, ?float $capacite, ?float $capaciteParDefaut = null): int
    {
        $ref = ($capacite && $capacite > 0)
            ? $capacite
            : (($capaciteParDefaut && $capaciteParDefaut > 0) ? $capaciteParDefaut : 0.0);

        if ($ref <= 0 || $quantite <= 0) {
            return 1;
        }

        return max(1, (int) ceil($quantite / $ref));
    }

    /**
     * @param float $voyages Nombre de rotations. Le tarif porte sur UNE course :
     *                       trois rotations, c'est trois fois le déplacement, donc
     *                       la part fixe ET le kilométrage sont multipliés. Ne
     *                       multiplier que la part fixe laisserait le livreur
     *                       payer le carburant des voyages supplémentaires.
     */
    public function tarificationLivraison(float $distance, float $coutGlobal = 0, float $voyages = 1): array
    {
        $voyages = max(1.0, (float) $voyages);

        // MIXTE : un montant de départ, PLUS le kilométrage.
        //
        // Les deux modes historiques rémunèrent mal les extrêmes : au forfait,
        // une course de 150 km rapporte autant qu'une course de 5 km ; au
        // kilomètre, une course de 2 km ne couvre même pas le déplacement du
        // livreur jusqu'au point de chargement.
        //
        // Le mixte épouse la structure réelle du coût : une part fixe — venir,
        // charger, attendre — et une part proportionnelle à la distance —
        // carburant et usure. C'est aussi ce que décrivait la direction :
        // « un minimum fixe et le reste en fonction du kilométrage ».
        //
        // La décomposition renvoyée alimente l'état « dette livreur », qui
        // distingue déjà forfait_base et frais_km : jusqu'ici l'une des deux
        // valeurs était toujours nulle.
        if ($this->mode_tarification === 'mixte') {
            $base = (float) $this->tarif_forfait_base * $voyages;
            $km   = (float) $this->tarif_km * $distance * $voyages;

            if ($base > 0 || $km > 0) {
                return [
                    'forfait_base' => $base,
                    'frais_km'     => $km,
                    'total'        => $base + $km,
                ];
            }
        }

        if ($this->mode_tarification === 'km' && (float) $this->tarif_km > 0) {
            $km = (float) $this->tarif_km * $distance * $voyages;
            return ['forfait_base' => 0.0, 'frais_km' => $km, 'total' => $km];
        }
        if ($this->mode_tarification === 'base' && (float) $this->cout_livraison > 0) {
            $base = (float) $this->cout_livraison * $voyages;
            return ['forfait_base' => $base, 'frais_km' => 0.0, 'total' => $base];
        }
        // Tarification non configurée -> repli sur le coût global.
        return ['forfait_base' => (float) $coutGlobal, 'frais_km' => 0.0, 'total' => (float) $coutGlobal];
    }


    public static function lireSurUser($idUser)
    {
        $obj = Livreur::where('user_id', $idUser)->first();
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new Livreur();
    }

    public static function liste($num_piece_identite = null)
    {
        return Livreur::when($num_piece_identite, function ($query) use ($num_piece_identite) {
                $query->where('num_piece_identite', $num_piece_identite);
            })
            ->where('statut', Help::$STATUT_ACTIF)
            ->get();
    }

    public static function enregistrer(array $arr)
    {
        $obj = new Livreur($arr);
        if ($obj->save()) return $obj;
        else return new Livreur();
    }

    public static function supprimer($id)
    {
        $obj = Livreur::lire($id);
        $obj->statut = Help::$STATUT_INACTIF;
        $obj->save();
        $obj->delete();
        return $obj;
    }

    public function user(){
        return $this->belongsTo(User::class)->withDefault(['nom_prenoms'=>'','email'=>'','contact'=>'','photo'=>'']);
    }
    public function livraisons(){
        return $this->hasMany(Livraison::class);
    }

    public function vehicules(){
        return $this->hasMany(Vehicule::class);
    }

    public function historiquesPrix(){
        return $this->hasMany(HistoriquePrixLivraisonLivreur::class, 'livreur_id')
            ->orderByDesc('created_at');
    }
}
