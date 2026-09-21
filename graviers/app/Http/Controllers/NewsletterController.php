<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterInscription;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\NewsletterExport;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Lettre d'information : inscription depuis le pied de page du site public,
 * et gestion des abonnés côté back-office.
 */
class NewsletterController extends Controller
{
    // -----------------------------------------------------------------
    // Côté public
    // -----------------------------------------------------------------

    /**
     * Inscription depuis le pied de page.
     *
     * Le formulaire n'était relié à rien : ni action, ni nom de champ. Le
     * visiteur croyait s'inscrire, et son adresse n'était nulle part.
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:150',
        ], [
            'email.required' => 'Veuillez saisir votre adresse e-mail.',
            'email.email'    => 'Cette adresse e-mail n\'est pas valide.',
            'email.max'      => 'Cette adresse e-mail est trop longue.',
        ]);

        $adresse = mb_strtolower(trim($request->email));

        // D'OÙ VIENT CETTE INSCRIPTION.
        //
        // Le pied de page n'était pas le seul point de recueil : le popup de
        // l'accueil en est un second. Enregistrer « Pied de page » pour les deux
        // rendrait impossible de savoir lequel travaille, et donc de juger si le
        // popup mérite d'être conservé.
        //
        // La valeur vient du formulaire mais n'est PAS reprise telle quelle :
        // elle est confrontée à une liste connue. Un champ caché se modifie
        // depuis le navigateur, et rien ne doit permettre d'écrire n'importe
        // quoi dans la fiche d'un abonné.
        $originesConnues = [
            'Popup de la page d\'accueil',
            'Pied de page du site',
        ];

        $origine = in_array($request->origine, $originesConnues, true)
            ? $request->origine
            : 'Pied de page du site';

        // Une adresse déjà connue n'est pas dupliquée : on la réactive.
        // withTrashed() : une adresse retirée puis ressaisie doit revenir,
        // sinon la contrainte d'unicité ferait échouer l'enregistrement.
        $abonne = Newsletter::withTrashed()->where('email', $adresse)->first();

        if ($abonne) {
            $dejaAbonne = !$abonne->trashed() && $abonne->statut == 1;
            $abonne->restore();
            $abonne->statut = 1;
            $abonne->save();

            if ($dejaAbonne) {
                // Deux clés : « success » pour la bulle habituelle du site, et
                // une clé propre que le popup affiche dans son cadre. Flasher
                // capte success/error et les rejoue en bulle éphémère, qui
                // s'efface avant d'avoir été lue dans une fenêtre modale.
                return back()
                    ->with('success', 'Cette adresse est déjà inscrite à notre lettre d\'information.')
                    ->with('newsletter_message', 'Cette adresse est déjà inscrite à notre lettre d\'information.');
            }
        } else {
            $abonne = Newsletter::create([
                'email'   => $adresse,
                'statut'  => 1,
                'origine' => $origine,
            ]);
        }

        // L'accusé de réception ne doit jamais faire échouer l'inscription :
        // l'adresse est déjà enregistrée à ce stade. Un serveur de messagerie
        // indisponible afficherait sinon une erreur au visiteur alors que son
        // inscription a bien été prise en compte.
        try {
            Mail::to($adresse)->send(new NewsletterInscription($adresse));
        } catch (\Throwable $e) {
            Log::error('Newsletter : accusé de réception non envoyé à ' . $adresse . ' — ' . $e->getMessage());
        }

        return back()
            ->with('success', 'Merci ! Votre inscription à notre lettre d\'information est enregistrée.')
            ->with('newsletter_message', 'Merci ! Votre inscription à notre lettre d\'information est enregistrée.');
    }

    // -----------------------------------------------------------------
    // Côté back-office
    // -----------------------------------------------------------------

    public function liste()
    {
        return view('gestionnaire.listeNewsletter', [
            'abonnes' => Newsletter::withTrashed()->orderByDesc('created_at')->get(),
        ]);
    }

    /** Abonne / désabonne une adresse sans la supprimer. */
    public function basculerStatut($id)
    {
        $abonne = Newsletter::withTrashed()->findOrFail($id);
        $abonne->statut = $abonne->statut == 1 ? 0 : 1;
        $abonne->save();

        return back()->with('success', $abonne->statut == 1
            ? 'L\'adresse ' . $abonne->email . ' est de nouveau abonnée.'
            : 'L\'adresse ' . $abonne->email . ' ne recevra plus la lettre d\'information.');
    }

    /** Mise à la corbeille, ou restauration si déjà à la corbeille. */
    public function supprimer($id)
    {
        $abonne = Newsletter::withTrashed()->findOrFail($id);

        if ($abonne->trashed()) {
            $abonne->restore();
            return back()->with('success', 'L\'adresse ' . $abonne->email . ' a été restaurée.');
        }

        $abonne->delete();
        return back()->with('success', 'L\'adresse ' . $abonne->email . ' a été mise à la corbeille.');
    }

    // -----------------------------------------------------------------
    // Exports
    // -----------------------------------------------------------------

    private function abonnesAExporter()
    {
        // On n'exporte pas la corbeille : une adresse retirée n'a pas à
        // ressortir dans un fichier transmis à un tiers.
        return Newsletter::orderByDesc('created_at')->get();
    }

    public function exportExcel()
    {
        return Excel::download(new NewsletterExport($this->abonnesAExporter()), $this->nomFichier('xlsx'));
    }

    public function exportPdf()
    {
        $pdf = Pdf::loadView('gestionnaire.exports.newsletter', [
            'abonnes' => $this->abonnesAExporter(),
            'edite'   => now()->format('d/m/Y à H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->nomFichier('pdf'));
    }

    /**
     * Word : Word ouvre nativement un document HTML servi sous l'extension
     * .doc. Cela évite d'ajouter une bibliothèque de traitement de texte au
     * projet — donc de recharger le dossier vendor à déployer à la main.
     */
    public function exportWord()
    {
        $html = view('gestionnaire.exports.newsletter', [
            'abonnes' => $this->abonnesAExporter(),
            'edite'   => now()->format('d/m/Y à H:i'),
        ])->render();

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-word',
            'Content-Disposition' => 'attachment; filename="' . $this->nomFichier('doc') . '"',
        ]);
    }

    private function nomFichier(string $extension): string
    {
        return 'abonnes-newsletter-' . now()->format('d-m-Y') . '.' . $extension;
    }
}
