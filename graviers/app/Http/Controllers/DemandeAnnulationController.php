<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Commande;
use App\Models\DemandeAnnulationCommande;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Traitement admin des demandes d'annulation (ventes ET locations).
 * Les demandes sont déposées par les clients (web ou mobile) dans
 * demande_annulation_commande (commande_id polymorphe selon type_affaire).
 */
class DemandeAnnulationController extends Controller
{
    public function liste()
    {
        $demandes = DemandeAnnulationCommande::with('client.user')
            ->orderBy('est_traite')          // en attente d'abord
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(function (DemandeAnnulationCommande $d) {
                $service = $d->serviceVise();
                $estLocation = $d->type_affaire === 'LOCATION';

                // Montant déjà encaissé sur ce service (lignes de paiement actives)
                $paye = (float) LignePaiement::where('service', $estLocation ? 'LOCATION' : 'COMMANDE')
                    ->where('service_id', $d->commande_id)
                    ->where('statut', 1)
                    ->sum('montant');

                // Traitement déjà démarré ? (livraisons rattachées au service)
                if ($estLocation) {
                    $etat = $service->etat_location ?? '-';
                    $enTraitement = $service && in_array($service->etat_location, ['EN COURS', 'TERMINE']);
                } else {
                    $etat = $service->etat_commande ?? '-';
                    $detailIds = $service ? $service->detailCommande->pluck('id') : collect();
                    $enTraitement = ($service && $service->etat_commande === 'TERMINEE')
                        || ($detailIds->isNotEmpty() && Livraison::whereIn('detail_commande_id', $detailIds)
                                ->where('provenance', 'COMMANDE')->exists());
                }

                $d->infos = (object) [
                    'numero'        => $service->numero ?? '(introuvable)',
                    'etat'          => $etat,
                    'montant'       => (float) ($service->montant_total ?? 0),
                    'paye'          => $paye,
                    'en_traitement' => $enTraitement,
                    'existe'        => (bool) $service,
                ];
                return $d;
            });

        return view('admin.demandesAnnulation', [
            'demandes' => $demandes,
        ]);
    }

    public function traiter(DemandeAnnulationCommande $demande, Request $request)
    {
        $request->validate([
            'rep'  => 'required|in:1,2',   // 1 = approuver, 2 = refuser
            'note' => 'nullable|string|max:1000',
        ]);

        if ($demande->est_traite) {
            return redirect()->route('show.demandesAnnulation')
                ->with('info', 'Cette demande a déjà été traitée.');
        }

        $service = $demande->serviceVise();
        $estLocation = $demande->type_affaire === 'LOCATION';
        $libelle = $estLocation ? 'location' : 'commande';
        $numero = $service->numero ?? ('#' . $demande->commande_id);

        $paye = (float) LignePaiement::where('service', $estLocation ? 'LOCATION' : 'COMMANDE')
            ->where('service_id', $demande->commande_id)
            ->where('statut', 1)
            ->sum('montant');

        DB::beginTransaction();
        try {
            if ((int) $request->rep === 1) {
                if (!$service) {
                    return redirect()->route('show.demandesAnnulation')
                        ->with('error', "Impossible d'annuler : la $libelle visée est introuvable.");
                }

                // Garde-fous : on n'annule pas un service dont le traitement
                // physique a démarré (livraison/matériel sorti) — cas à gérer
                // manuellement par l'équipe.
                if ($estLocation) {
                    if (in_array($service->etat_location, ['EN COURS', 'TERMINE'])) {
                        return redirect()->route('show.demandesAnnulation')
                            ->with('error', "Annulation refusée automatiquement : la location $numero est déjà {$service->etat_location}. Traitez ce cas manuellement (retour matériel / remboursement).");
                    }
                    $service->etat_location = 'ANNULEE';
                    $service->save();
                    // Libère les lignes de matériel réservées
                    DB::table('detail_location')->where('location_id', $service->id)
                        ->update(['etat_location' => 'EN ATTENTE', 'statut' => 2]);
                } else {
                    $detailIds = $service->detailCommande->pluck('id');
                    $traitementDemarre = $service->etat_commande === 'TERMINEE'
                        || ($detailIds->isNotEmpty() && Livraison::whereIn('detail_commande_id', $detailIds)
                                ->where('provenance', 'COMMANDE')->exists());
                    if ($traitementDemarre) {
                        return redirect()->route('show.demandesAnnulation')
                            ->with('error', "Annulation refusée automatiquement : la commande $numero est déjà en cours de traitement/livraison. Traitez ce cas manuellement.");
                    }
                    $service->etat_commande = 'ANNULEE';
                    $service->save();
                }
            }

            $demande->update([
                'est_traite' => true,
                'decision'   => (int) $request->rep,
                'note'       => $request->note,
                'traite_par' => Auth::id(),
                'decided_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('show.demandesAnnulation')
                ->with('error', 'Erreur lors du traitement : ' . $e->getMessage());
        }

        // Email NON bloquant au client
        try {
            $email = $demande->client?->user?->email ?: $demande->client?->email;
            if ($email) {
                $nom = trim(($demande->client->nom ?? '') . ' ' . ($demande->client->prenom ?? ''));
                if ((int) $request->rep === 1) {
                    $texte = "Bonjour $nom,\n\nVotre demande d'annulation de la $libelle N° $numero a été APPROUVÉE. La $libelle est annulée."
                        . ($paye > 0 ? "\n\nUn paiement de " . number_format($paye, 0, ',', ' ') . " FCFA ayant été enregistré sur cette $libelle, notre équipe vous contactera pour le remboursement." : '')
                        . ($request->note ? "\n\nCommentaire : {$request->note}" : '')
                        . "\n\nMerci,\n" . config('mail.from.name');
                } else {
                    $texte = "Bonjour $nom,\n\nVotre demande d'annulation de la $libelle N° $numero a été REFUSÉE."
                        . ($request->note ? "\n\nMotif : {$request->note}" : '')
                        . "\n\nMerci,\n" . config('mail.from.name');
                }
                Mail::raw($texte, function ($m) use ($email, $libelle) {
                    $m->to($email)->subject("Votre demande d'annulation de $libelle - " . config('mail.from.name'));
                });
            }
        } catch (\Throwable $e) {
            \Log::warning("Email annulation non envoyé: " . $e->getMessage());
        }

        $msg = (int) $request->rep === 1
            ? "Demande approuvée : la $libelle $numero est annulée."
            . ($paye > 0 ? " ⚠ " . number_format($paye, 0, ',', ' ') . " FCFA ont été encaissés : pensez au remboursement." : '')
            : "Demande refusée. Le client a été informé par email.";

        return redirect()->route('show.demandesAnnulation')->with('success', $msg);
    }
}
