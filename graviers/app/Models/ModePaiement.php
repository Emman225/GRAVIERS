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

    /**
     * Instruments de paiement que le CLIENT ne choisit pas depuis le site.
     *
     * Un virement, un chèque, des espèces ou une carte, c'est l'agent qui les
     * constate au moment où l'argent arrive. Le client, lui, annonce seulement
     * s'il paie en ligne ou s'il viendra régler en agence.
     *
     * Motifs volontairement courts et accentués/non accentués : le libellé est
     * saisi au back-office et varie d'une base à l'autre (« Chèque », « Cheque »,
     * « Paiement par chèque »…). La comparaison MySQL ignore déjà les accents,
     * les deux formes sont là par sécurité.
     */
    private const INSTRUMENTS_RESERVES_AGENT = [
        'virement',
        'chèque',
        'cheque',
        'espèce',
        'espece',
        'carte',
    ];

    public static function lire($id)
    {
        $obj = ModePaiement::find($id);
        if (isset($obj->id) && $obj->id > 0) return $obj;
        else return new ModePaiement();
    }

    /**
     * Liste GÉNÉRALE des modes de paiement proposables.
     *
     * Utilisée là où quelqu'un déclare comment il souhaite RECEVOIR son argent :
     * demande de paie d'un fournisseur ou d'un livreur. Tout y est légitime, y
     * compris « en agence » (venir retirer sur place) comme le virement ou le
     * chèque.
     *
     * Seul le mode historique « En Agence (virement, Chèque, Espèce) » (id = 1)
     * reste exclu : décision antérieure, il ne doit plus être proposé nulle part.
     * La ligne reste en base pour l'historique des paiements déjà enregistrés.
     *
     * ⚠ Deux écrans ont des besoins plus étroits :
     *   - le CLIENT qui règle sa commande        → listePourClient() ;
     *   - tout écran tenu par un AGENT
     *     (encaissement, paiement fournisseur,
     *      apporteur ou livreur)                 → listePourAgent().
     */
    public static function liste()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('id', '!=', 1)
            ->get();
    }

    /**
     * Modes proposés au CLIENT au moment de payer sa commande.
     *
     * Le client n'a que deux façons de régler sur le site :
     *   - un paiement en ligne (mobile money) : l'argent part tout de suite ;
     *   - « Paiement en agence » : il annonce qu'il viendra régler sur place.
     *
     * « Virement bancaire », « Chèque », « Espèces » et « Carte bancaire » sont des
     * INSTRUMENTS que l'agent constate à l'encaissement, pas des choix que le client
     * pose depuis le site : les proposer laissait croire à un règlement immédiat
     * alors que rien n'était encaissé — et faisait passer la commande pour du
     * comptant. Ils restent proposés partout ailleurs (encaissement par un agent,
     * demande de paie d'un fournisseur ou d'un livreur) : voir liste() et
     * listePourAgent().
     *
     * Le filtre porte sur en_ligne et sur le libellé, jamais sur des id : ceux-ci
     * diffèrent entre le local et la production. Tout nouveau moyen de paiement en
     * ligne créé au back-office apparaît donc ici sans retoucher au code.
     *
     * Les instruments sont écartés par leur LIBELLÉ et non par en_ligne = 0, parce
     * que ce drapeau n'est pas fiable : en production, « Carte bancaire » et
     * « Virement bancaire » sont marqués en ligne et repassaient donc dans la liste.
     * Le libellé, lui, dit ce que la chose EST.
     *
     * LE PLAFOND DU PAIEMENT EN LIGNE.
     *
     * Passer le montant de l'affaire retire les modes en ligne au-delà du
     * plafond : il ne sert à rien de proposer un règlement que la suite du
     * parcours refusera. Le client lisait déjà la phrase d'avertissement, mais
     * le menu continuait de lui offrir Orange Money ou Wave.
     *
     * Le tri se fait sur `en_ligne`, le même drapeau que celui qui décide
     * d'appeler la passerelle : le menu propose donc exactement ce que le
     * parcours acceptera, quelle que soit la valeur de ce drapeau en ligne.
     */
    public static function listePourClient($montant = null)
    {
        $modes = ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('id', '!=', 1)
            ->where(function ($query) {
                // Le règlement en agence passe d'abord : c'est la seule façon de
                // commander à crédit, et son libellé énumère parfois les
                // instruments acceptés (« En Agence (virement, Chèque…) »), ce qui
                // le ferait éliminer par l'exclusion ci-dessous.
                $query->where('libelle', 'like', '%agence%')
                    ->orWhere(function ($enLigne) {
                        $enLigne->where('en_ligne', 1);
                        foreach (self::INSTRUMENTS_RESERVES_AGENT as $motif) {
                            $enLigne->where('libelle', 'not like', '%' . $motif . '%');
                        }
                    });
            })
            ->get();

        if ($montant !== null && \App\Support\PlafondPaiementEnLigne::depasse($montant)) {
            $modes = $modes->filter(
                fn ($mode) => \App\Support\PlafondPaiementEnLigne::modeAutorise($mode, $montant)
            )->values();
        }

        return $modes;
    }

    /**
     * Modes proposés sur les écrans tenus par un AGENT, dans les deux sens :
     *   - encaissement d'un client (commande, location, créance) ;
     *   - paiement émis par l'entreprise : fournisseur, apporteur, livreur.
     *
     * Dans tous ces cas « en agence » n'a aucun sens : c'est un LIEU, pas un
     * instrument. L'agent EST en agence ; ce qu'il doit saisir, c'est COMMENT
     * l'argent circule (Espèces, Chèque, Virement, Carte, mobile money…).
     * Exclusion par libellé car l'id de la ligne « Paiement en agence » diffère
     * entre le local et la production.
     */
    public static function listePourAgent()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('id', '!=', 1)
            ->where('libelle', 'not like', '%agence%')
            ->get();
    }

    // Liste COMPLÈTE des modes actifs (y compris "En Agence") — pour l'admin/technique,
    // pas pour les selects de paiement client.
    /**
     * MODES PROPOSÉS À L'APPORTEUR D'AFFAIRE.
     *
     * C'est une préférence de VERSEMENT : comment l'apporteur souhaite toucher
     * sa commission. « En agence » n'est pas un instrument mais un lieu, et il
     * n'a aucun sens ici — on ne verse pas une commission « en agence » au sens
     * d'un moyen de paiement.
     *
     * Règle identique à celle de l'API (ModePaiement::listePourApporteur de
     * apigravier), qui sert la liste à l'application mobile de l'apporteur : les
     * deux formulaires doivent proposer exactement les mêmes choix, sans quoi un
     * apporteur inscrit depuis le site aurait une préférence introuvable dans
     * l'application.
     */
    public static function listePourApporteur()
    {
        return ModePaiement::orderBy('libelle', 'asc')
            ->where('statut', Help::$STATUT_ACTIF)
            ->where('libelle', 'not like', '%agence%')
            ->get();
    }

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
