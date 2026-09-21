<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * L'HISTORIQUE D'UNE AVANCE : dépôts et déductions (point 19, 07/09/2026).
 *
 *   DEPOT      à la seconde validation du dépôt ;
 *   DEDUCTION  à chaque commande « en agence » qui s'y impute, avec la
 *              commande et le règlement créé.
 */
class MouvementAvance extends Model
{
    public const DEPOT     = 'DEPOT';
    public const DEDUCTION = 'DEDUCTION';

    protected $table = 'mouvement_avance';

    protected $fillable = [
        'avance_client_id', 'client_id', 'type', 'montant', 'commande_id',
        'paiement_id', 'user_id', 'libelle',
    ];

    public function avance()
    {
        return $this->belongsTo(AvanceClient::class, 'avance_client_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function commande()
    {
        return $this->belongsTo(Commande::class, 'commande_id');
    }

    public function paiement()
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    public function auteur()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * L'affaire sur laquelle porte une déduction : « Commande 123456 »,
     * « Location 123456 » ou « Livraison 123456 ». `commande_id` ne désigne
     * qu'une commande ; une location ou une demande de livraison se lit sur
     * le règlement (service / service_id).
     */
    public function libelleAffaire(): string
    {
        if ($this->commande) {
            return 'Commande ' . $this->commande->numero;
        }

        $paiement = $this->paiement;
        if (!$paiement || !$paiement->service_id) {
            return '-';
        }

        if ($paiement->service === \Help::$LOCATION) {
            $numero = Location::where('id', $paiement->service_id)->value('numero');

            return $numero ? 'Location ' . $numero : '-';
        }

        if ($paiement->service === \Help::$LIVRAISON) {
            $numero = DemandeLivraison::where('id', $paiement->service_id)->value('numero');

            return $numero ? 'Livraison ' . $numero : '-';
        }

        return '-';
    }
}
