<?php

namespace App\Http\Controllers;

use App\Models\Contact;

/**
 * Messages reçus depuis la page publique « Nous contacter ».
 *
 * Ils étaient enregistrés en base depuis toujours, mais aucun écran, aucune
 * route et aucune entrée de menu ne les affichait : un client qui écrivait
 * n'obtenait jamais de réponse, alors que le formulaire lui en promettait une.
 */
class MessageContactController extends Controller
{
    public function liste()
    {
        return view('gestionnaire.listeMessagesContact', [
            // Les non-lus d'abord, puis du plus récent au plus ancien.
            'messages' => Contact::withTrashed()
                ->orderBy('lu')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    /** Marque lu / non lu sans rien détruire. */
    public function basculerLu($id)
    {
        $message = Contact::withTrashed()->findOrFail($id);
        $message->lu = !$message->lu;
        $message->save();

        return back()->with('success', $message->lu
            ? 'Message de ' . $message->nom_prenoms . ' marqué comme lu.'
            : 'Message de ' . $message->nom_prenoms . ' marqué comme non lu.');
    }

    /** Mise à la corbeille, ou restauration si déjà à la corbeille. */
    public function supprimer($id)
    {
        $message = Contact::withTrashed()->findOrFail($id);

        if ($message->trashed()) {
            $message->restore();
            return back()->with('success', 'Le message de ' . $message->nom_prenoms . ' a été restauré.');
        }

        $message->delete();
        return back()->with('success', 'Le message de ' . $message->nom_prenoms . ' a été mis à la corbeille.');
    }
}
