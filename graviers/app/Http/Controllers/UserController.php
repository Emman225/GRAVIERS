<?php

namespace App\Http\Controllers;

use App\Http\Requests\imageRequest;
use App\Http\Requests\LivreurRequest;
use App\Mail\MailAccesUsers;
use App\Models\Admin;
use App\Models\Apporteur;
use App\Models\Banniere;
use App\Models\Slide;
use App\Models\blog_commentaire;
use App\Models\blog;
use App\Models\Categorie;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommissionApporteur;
use App\Models\Configuration;
use App\Models\CoutLivraison;
use App\Models\DemandeCompteClientATerme;
use App\Models\DemandeLivraison;
use App\Models\DemandePaiement;
use App\Models\PaiementFournisseur;
use App\Models\DetailLivraison;
use App\Models\Enlevement;
use App\Models\Facture;
use App\Services\FneService;
use App\Models\Fournisseur;
use App\Models\ImageProduit;
use App\Models\LignePaiement;
use App\Models\Livraison;
use App\Models\Livreur;
use App\Models\Location;
use App\Models\ModePaiement;
use App\Models\NoteProduit;
use App\Models\Paiement;
use App\Models\Pays;
use App\Models\PreuveOperation;
use App\Models\Produit;
use App\Models\Reduction;
use App\Models\Region;
use App\Models\RetourProduit;
use App\Models\StockProduit;
use App\Models\TicketSAV;
use App\Models\TypeUser;
use App\Models\Audit;
use App\Models\User;
use App\Models\Vehicule;
use App\Models\Ville;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Gloudemans\Shoppingcart\Facades\Cart;
use Help;
use Illuminate\Http\Request;
use Illuminate\Support\carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDF;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Bascule de la TVA marchandise d'un client. Au RETRAIT, l'administrateur
     * dit si l'exonération est légale (TVAD) ou conventionnelle (TVAC), lot 82
     * (15/09/2026) : c'est le code de taxe de ses lignes sur la facture DGI.
     * Sans choix (ancien lien), le réglage FNE_EXEMPT_TAX s'applique. Au
     * rétablissement, le code s'efface.
     */
    public function appliqueTva(Request $request, Client $client){
        $retire = (int) $client->applique_tva === 1;
        $code   = strtoupper(trim((string) $request->query('code', '')));
        if (!isset(Client::EXONERATIONS_FNE[$code])) {
            $code = (string) config('fne.defaults.exempt_tax', 'TVAD');
        }

        $client->update([
            'applique_tva' => $retire ? 0 : 1,
        ]);
        if (\Illuminate\Support\Facades\Schema::hasColumn('client', 'code_exoneration_fne')) {
            Client::where('id', $client->id)->update(['code_exoneration_fne' => $retire ? $code : null]);
        }

        return back()->with('success', $retire
            ? 'TVA retirée pour ce client : ' . (Client::EXONERATIONS_FNE[$code] ?? '') . ' (' . $code . ').'
            : 'TVA appliquée de nouveau pour ce client.');
    }

    /**
     * TVA sur le TRANSPORT, retirable par client (10/09/2026) — indépendante de
     * la TVA marchandise. Appliquée par défaut ; ne vaut que si la configuration
     * taxe le transport.
     */
    public function appliqueTvaTransport(Client $client){
        $client->update([
            'applique_tva_transport' => (int) ($client->applique_tva_transport ?? 1) === 1 ? 0 : 1,
        ]);

        return back()->with('success', 'TVA sur le transport ' . ((int) $client->fresh()->applique_tva_transport === 1 ? 'appliquée' : 'retirée') . ' pour ce client.');
    }

    /**
     * Révise le plafond de crédit et le délai de paiement d'un client à terme.
     *
     * Ces deux valeurs n'étaient inscrites qu'une seule fois, à l'approbation de
     * la demande, et plus rien ne permettait d'y revenir : ni relever le plafond
     * d'un client dont l'activité a grandi, ni le baisser pour un mauvais payeur,
     * ni corriger une simple erreur de saisie. Le plafond étant opposable — il
     * bloque les commandes au-delà du montant accordé — cette impossibilité
     * enfermait la gestion.
     *
     * Chaque révision est journalisée avec l'ancienne et la nouvelle valeur, son
     * auteur et sa date : ce montant engage l'entreprise.
     */
    public function modifierPlafondCredit(Request $request, Client $client)
    {
        // Bornes identiques à celles de l'approbation (validationDemande) : une
        // révision ne doit pas pouvoir enregistrer ce qu'un accord initial refuse.
        $request->validate([
            'plafond_credit' => 'required|numeric|min:0',
            'delai_paiement' => 'required|integer|min:1|max:365',
            'motif'          => 'nullable|string|max:255',
        ], [
            'plafond_credit.required' => 'Le plafond de crédit est obligatoire.',
            'plafond_credit.numeric'  => 'Le plafond de crédit doit être un montant.',
            'delai_paiement.required' => 'Le délai de paiement (en jours) est obligatoire.',
            'delai_paiement.max'      => 'Le délai de paiement ne peut excéder 365 jours.',
        ]);

        // Un client ordinaire n'a pas de plafond : lui en fixer un laisserait
        // croire à un encadrement qui ne s'applique nulle part.
        if (!$client->client_a_terme) {
            return back()->with('error', 'Ce client n\'est pas un client à terme : son plafond ne peut pas être révisé.');
        }

        $ancienPlafond = (float) ($client->plafond_credit ?? 0);
        $ancienDelai   = (int) ($client->delai_paiement ?? 0);
        $nouveauPlafond = (float) $request->plafond_credit;
        $nouveauDelai   = (int) $request->delai_paiement;

        if ($ancienPlafond == $nouveauPlafond && $ancienDelai == $nouveauDelai) {
            return back()->with('info', 'Aucun changement : le plafond et le délai sont inchangés.');
        }

        // Encours relevé AVANT l'écriture : il ne dépend pas du plafond, mais le
        // lire d'abord évite toute ambiguïté sur ce qui est comparé à quoi.
        $encours = (float) $client->encoursCredit();

        // LE PLAFOND NE BOUGE PAS ENCORE : la révision attend son second
        // contrôle. C'est le montant que l'entreprise accepte de ne pas
        // encaisser tout de suite ; le relever seul revenait à s'accorder une
        // exposition sans que personne d'autre ne l'ait vue passer.
        if (!\App\Models\DecisionClientTerme::tableExiste()) {
            return back()->with('error',
                'La table des décisions de crédit n\'existe pas encore sur ce serveur : lancez la migration.');
        }

        if ($enCours = \App\Models\DecisionClientTerme::enAttentePour((int) $client->id)) {
            return back()->with('error',
                'Une décision attend déjà sa validation sur ce client : ' . $enCours->libelleType() . '.');
        }

        \App\Models\DecisionClientTerme::create([
            'client_id'         => $client->id,
            'type'              => \App\Models\DecisionClientTerme::PLAFOND,
            'plafond_credit'    => $nouveauPlafond,
            'delai_paiement'    => $nouveauDelai,
            'commentaire'       => $request->motif,
            'ancien_plafond'    => $ancienPlafond,
            'ancien_delai'      => $ancienDelai,
            'user_valide_id'    => Auth::id(),
            'date_validation_1' => now(),
            'statut'            => \App\Models\DecisionClientTerme::EN_ATTENTE,
        ]);

        \Help::ecrireLog(
            'modifierPlafondCredit',
            'Révision du plafond de crédit (saisie, en attente de validation) — ' . $client->display_name,
            sprintf(
                'Client #%d : plafond %s -> %s FCFA ; délai %d -> %d jours (en attente de la seconde validation).%s',
                $client->id,
                number_format($ancienPlafond, 0, ',', ' '),
                number_format($nouveauPlafond, 0, ',', ' '),
                $ancienDelai,
                $nouveauDelai,
                $request->filled('motif') ? ' Motif : ' . $request->motif : ''
            )
            // L'encours du jour est consigné avec la révision : sans lui, on ne
            // peut plus dire après coup si le plafond accordé couvrait ou non ce
            // que le client devait déjà.
            . sprintf(' Encours à la révision : %s FCFA.', number_format($encours, 0, ',', ' ')),
            Auth::id()
        );

        // Le message dit ce qui s'est RÉELLEMENT passé : la révision est
        // enregistrée, pas appliquée. Annoncer « mis à jour » ferait croire le
        // plafond déjà relevé, et le gestionnaire quitterait la page rassuré à
        // tort.
        $confirmation = 'Révision enregistrée : '
            . number_format($ancienPlafond, 0, ',', ' ') . ' → '
            . number_format($nouveauPlafond, 0, ',', ' ') . ' FCFA sur ' . $nouveauDelai . ' jours. '
            . 'Elle prendra effet après validation par un second administrateur.';

        // Ramener le plafond SOUS l'encours déjà consommé est une décision
        // légitime — c'est même le geste attendu face à un mauvais payeur : on
        // ferme la ligne de crédit sans effacer ce qui est dû. La révision est
        // donc enregistrée telle quelle. Mais elle a une conséquence immédiate
        // que le gestionnaire doit connaître avant de quitter la page : le
        // disponible tombe à zéro et le client ne pourra plus rien commander à
        // terme tant qu'il n'aura pas réglé la différence.
        //
        // Clé « avertissement_plafond » et non « warning » : Flasher intercepte
        // warning/success/info/error pour les rejouer en bulle flottante, qui
        // s'efface d'elle-même. Ce message-ci doit rester affiché.
        if ($nouveauPlafond > 0 && $nouveauPlafond < $encours) {
            return back()->with('avertissement_plafond', $confirmation
                . ' Attention : ce plafond sera inférieur à l\'encours déjà engagé ('
                . number_format($encours, 0, ',', ' ') . ' FCFA). Une fois validée, le crédit disponible du client sera nul, '
                . 'et il ne pourra plus commander à terme tant qu\'il n\'aura pas réglé au moins '
                . number_format($encours - $nouveauPlafond, 0, ',', ' ') . ' FCFA.');
        }

        return back()->with('success', $confirmation);
    }

    public function welcome(Request $request){
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'zoom' => 'required|numeric',
        ]);

        notify()->success('Laravel Notify is awesome!');


        return redirect()->route('notify');
    }

    public function preuve(Commande $commande){

        return view('orders.preuve',[

            'commande' => $commande

        ]);
    }

    public function preuveValide(commande $commande){
        // dd($preuve);
        $preuve = $commande->preuve;
        $preuve->statut = 1;
        $preuve->user_id = Auth::user()->id;
        $preuve->update();

        return back()->with('success','Preuve validée !');

    }

    public function afficherStock($idProduit, $idFournisseur){
        $stock = StockProduit::lireCle($idFournisseur, $idProduit);
        return response()->json($stock);
    }

    public function actionFacture(Commande $commande, Facture $facture, $action, $livraison){
        // Le document de la DGI dès que la facture est certifiée (lot 96).
        if ($r = \App\Services\DocumentDgi::reponse($facture, (string) $action)) {
            return $r;
        }
        // Un avoir (lot 92) a son propre document.
        if ($facture->estUnAvoir()) {
            return app(\App\Http\Controllers\OrdersController::class)->factureAvoir($facture, $action);
        }

        // dd($commande, $facture, $action);

        // Mêmes données que la facture envoyée par courriel : un seul jeu,
        // tenu par FacturationCommande::donneesDocument (point 8, 07/09/2026).
        $donnees = \App\Services\FacturationCommande::donneesDocument($facture, $commande);
        // Le lien de la liste dit si c'est la première facture ; on le garde
        // quand il est renseigné, il sert au client comme au back-office.
        if ($livraison !== null && $livraison !== '') {
            $donnees['livraison'] = (int) $livraison;
        }

        $pdf = PDF::loadView('document.factureCommande', $donnees)
            ->setOptions(['isHTML5ParseEnebled' => true, 'defaultPaperOrientation' => 'portait']);

        if($action == 'voir'){
            return $pdf->stream();
        }else{
            return $pdf->download();
        }
    }

    public function lesRegions(){
        $regions = Region::all();
        return view('gestionnaire.lesRegions',[
            'regions' => $regions,
            'laRegion' => new Region
        ]);
    }

    public function lesRegionsValid(Request $request){
        // `regions.nom` porte une contrainte d'UNICITE en base. Sans la
        // controler ici, un nom deja pris ne produisait pas un message mais une
        // ERREUR 500 : MySQL refusait l'insertion et l'exception remontait
        // jusqu'a l'ecran. Les dix-neuf regions de Cote d'Ivoire etant deja
        // enregistrees, c'est le premier mur que rencontre celui qui ressaisit
        // une region existante.
        $request->validate([
            'nom' => 'required|unique:regions,nom',
            'adresse_geo' => 'required',
            'long' => 'required',
            'lat' => 'required',
        ],[
            'nom.required' => 'Le nom est requis',
            'nom.unique' => 'Cette région existe déjà',
            'adresse_geo.required' => 'La description est requise',
            'long.required' => 'La longitude est requise',
            'lat.required' => 'La latitude est requise',
        ]);

        $region = new Region;
        $region->nom = $request->nom;
        $region->description = $request->adresse_geo;
        $region->long = $request->long;
        $region->lat = $request->lat;
        $region->user_id = Auth::user()->id;
        $region->save();

        return redirect()->route('show.lesRegions')->with('success','Région ajoutée');
    }

    /**
     * La page de creation. Le formulaire ne partage plus l'ecran de la liste :
     * on y consultait et on y saisissait au meme endroit, et la liste reculait
     * de toute la hauteur de la carte au premier clic.
     */
    public function nouvelleRegion(){
        return view('gestionnaire.formRegion',[
            'regions' => Region::orderBy('nom','asc')->get(),
            'laRegion' => new Region
        ]);
    }

     public function modifierRegion(Region $region){
        return view('gestionnaire.formRegion',[
            'regions' => Region::orderBy('nom','asc')->get(),
            'laRegion' => $region
        ]);
    }
    public function modifierRegionValid(Request $request, Region $region){
        // LA MODIFICATION NE VALIDAIT RIEN, ET EFFACAIT CE QU'ELLE NE RECEVAIT PAS.
        //
        // La creation exige nom, adresse et coordonnees ; la modification, elle,
        // recopiait sans controle ce qui arrivait. Un formulaire qui ne portait
        // pas les coordonnees — c'etait le cas, faute des champs correspondants —
        // ecrivait donc NULL par-dessus des valeurs justes. Onze des dix-neuf
        // regions se sont retrouvees sans coordonnees ni description.
        //
        // On applique les memes exigences qu'a la creation : mieux vaut refuser
        // un enregistrement incomplet que perdre en silence ce qui etait la.
        $request->validate([
            // « ignore » : la region conserve evidemment le droit de garder son
            // propre nom. Sans cela, tout enregistrement sans changement de nom
            // serait refuse.
            'nom' => 'required|unique:regions,nom,' . $region->id,
            'adresse_geo' => 'required',
            'long' => 'required',
            'lat' => 'required',
        ], [
            'nom.required' => 'Le nom est requis',
            'nom.unique' => 'Une autre région porte déjà ce nom',
            'adresse_geo.required' => 'La description est requise',
            'long.required' => 'La longitude est requise',
            'lat.required' => 'La latitude est requise',
        ]);

        $region->nom = $request->nom;
        $region->long = $request->long;
        $region->lat = $request->lat;
        $region->description = $request->adresse_geo;
        $region->save();

        return redirect()->route('show.lesRegions')->with('success','Région modifiée');
    }
    public function supprimerRegion(Region $region){
        // ERREUR 500 A LA SUPPRESSION — constatee le 27/08/2026 sur la region 13.
        //
        // Cette methode ecrivait `deleted_at`, colonne que la table `regions` NE
        // POSSEDE PAS : « Unknown column 'deleted_at' in 'field list' ». Aucune
        // region ne pouvait donc etre supprimee, et l'ecran restait blanc.
        //
        // Le modele `Ville` pratique la suppression douce, et l'intention etait
        // visiblement la meme ici. On s'en ecarte volontairement, pour deux
        // raisons :
        //
        //   · `regions.nom` porte une contrainte d'UNICITE en base, qui ne
        //     distingue pas une ligne effacee d'une ligne vivante. Une region
        //     seulement marquee supprimee garderait son nom reserve POUR
        //     TOUJOURS : on ne pourrait plus jamais recreer « Gontougo » apres
        //     l'avoir retiree par erreur. C'est un piege certain.
        //
        //   · aucune autre table que `ville` ne reference une region, et il n'y
        //     a donc rien a preserver pour l'historique — ni commande, ni
        //     livraison, ni facture n'en garde trace.
        //
        // La suppression est donc REELLE, mais refusee tant que des villes y
        // sont rattachees : rien ne les relie par une cle etrangere, elles
        // deviendraient orphelines en silence.
        $villes = $region->villes()->count();

        if ($villes > 0) {
            return redirect()->route('show.lesRegions')->with(
                'erreurRegion',
                "Impossible de supprimer « {$region->nom} » : {$villes} ville"
                . ($villes > 1 ? 's y sont rattachées' : ' y est rattachée')
                . ". Rattachez-les à une autre région avant de la supprimer."
            );
        }

        $region->delete();

        return redirect()->route('show.lesRegions')->with('success','Région supprimée');
    }

    public function bloquerCompte($id, $type){

        $user = User::find($id);
        if (!$user) {
            return redirect()->back()->with('error', "Ce compte est introuvable (il a peut-être déjà été supprimé).");
        }
        $nomPrenom = '';
        // Valeurs par défaut : évitent un 500 pour les types sans profil séparé
        // (Super Admin / Admin) ou un type inattendu -> $theUser et $route restent définis.
        $route = 'listeGestionnaire';
        $theUser = $user;
        switch ($user->type_user_id) {
            case 1:
                // dd('gestion');
                $route = 'listeGestionnaire';

                break;

            case 2:
                // dd('gestion');
                $route = 'listeGestionnaire';
                break;

            case 3:
                // gestionnaire
                // dd('gestion');
                $route = 'listeGestionnaire';
                $nomPrenom = strtoupper($user->nom).' '.strtoupper($user->prenom);
                $theUser = $user;
                break;

            case 4:
                // dd('client');
                // client
                // $route = $user->client->statut == 3  ? 'listClientATerme' : 'listClient';
                $route = $user->client->client_a_terme == 1  ? 'listClientATerme' : 'listClient';
                $nomPrenom = strtoupper($user->client->display_name);
                $theUser = $user->client;
                break;

            case 5:
                // fournisseur
                // dd('fournisseur');
                $route = 'listSeller';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->getFournisseur;
                break;

            case 6:
                // apporteur
                // dd('apporteur');
                $route = 'listApporteur';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->getApporteur;
                break;

            case 7:
                // agent SAV
                $route = 'listeAgent';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user;
                break;

            case 8:
                // livreur
                // dd('livreur');
                $route = 'list';
                $nomPrenom = strtoupper($user->nom_prenoms);
                $theUser = $user->livreur;
                break;
        }

        if($type == 'blok'){
            // dd($user);
            switch ($user->statut) {
                case 1:

                    DB::table('users')->where('id', $user->id)->update([
                        'statut' => 2
                    ]);

                    return redirect()->route('show.'.$route)->with('success',"vous avez bloqué le compte de ".strtoupper($nomPrenom));
                    break;

                case 2:
                    DB::table('users')->where('id', $user->id)->update([
                        'statut' => 1
                    ]);
                    return redirect()->route('show.'.$route)->with('success',"vous avez débloqué le compte de ".strtoupper($nomPrenom));
                    break;
            }

            // Aucun cas ne correspond : le compte n'est ni actif ni bloqué —
            // une inscription encore en attente, par exemple. On ne le bascule
            // pas au hasard, mais on ne peut pas non plus sortir sans réponse :
            // la méthode retournait alors NULL, et l'écran restait blanc.
            return redirect()->route('show.'.$route)
                ->with('error', "Le compte de " . strtoupper($nomPrenom)
                    . " n'est ni actif ni bloqué : son état ne permet pas cette action.");

        }else{
            // $user->update([
            //     'deleted_at' => date('Y-m-d H:i:s')
            // ]);
            DB::table('users')->where('id', $user->id)->update([
                        'deleted_at' => date('Y-m-d H:i:s')
                    ]);
            // dd($theUser);
            $theUser->deleted_at = date('Y-m-d H:i:s');
            $theUser->update();

            return redirect()->route('show.'.$route)->with('locked',"vous avez supprimé le compte de ".strtoupper($nomPrenom));

        }
    }

    public function profileApporteur(Apporteur $apporteur){
        return view('apporteur.profile',[
            'apporteur' => $apporteur
        ]);
    }

    public function modifPiece(Request $request, Apporteur $apporteur){

        $request->validate([
            'recto.image' => 'Le fichier recto doit être une image.',
            'recto.mimes' => 'Seules les images JPG et PNG sont autorisées pour le recto.',
            'recto.max'   => 'L’image recto ne doit pas dépasser 2 Mo.',

            'verso.image' => 'Le fichier verso doit être une image.',
            'verso.mimes' => 'Seules les images JPG et PNG sont autorisées pour le verso.',
            'verso.max'   => 'L’image verso ne doit pas dépasser 2 Mo.',
        ]);


        $recto = null;
        $verso = null;

        if($request->hasFile('recto')){
            if($apporteur->piece_recto){
                Storage::disk('public')->delete($apporteur->piece_recto);
            }

            $recto = $request->file('recto')->store('ddd', 'public');

        }

        if($request->hasFile('verso')){
            if($apporteur->piece_verso){
                Storage::disk('public')->deleted($apporteur->piece->verso);
            }
            $verso = $request->file('verso')->store('piecesApporteurs','public');
        }

        $apporteur->piece_recto = $recto;
        $apporteur->piece_verso = $verso;
        $apporteur->update();

        return back()->with('success', 'Image mis à jour !');

    }

    public function demandeLivraisonlist(){
        // UNE DEMANDE NON PAYÉE N'EST PAS UNE DEMANDE.
        //
        // Elle est enregistrée AVANT l'ouverture de la passerelle : le client
        // qui referme celle-ci — pour changer de mode, ou parce qu'il renonce —
        // laisse derrière lui une demande complète, proposée à l'affectation
        // comme les autres. Le 25/08/2026, un client a ainsi produit DEUX
        // demandes pour un seul transport, et le gestionnaire a vu les deux.
        //
        // On ne la SUPPRIME pas : le client peut revenir régler, et une trace
        // vaut mieux qu'un trou. On la tient simplement hors de cet écran tant
        // que l'argent n'est pas arrivé. Le règlement AU GUICHET, lui, est un
        // engagement pris en agence : la demande reste exploitable d'emblée.
        //
        // Le tri se fait en PHP et non en SQL : estExploitable() interroge le
        // mode de paiement et les règlements, ce qu'une clause where ne sait
        // pas exprimer sans dupliquer la règle — et deux copies d'une règle
        // finissent toujours par diverger.
        $livraison = DemandeLivraison::where('statut', 1)
            ->with('ModeDePaiement')
            ->latest()
            ->get()
            ->filter->estExploitable()
            ->values();
        return view('gestionnaire.demandeLivraisonlist',[
            'livraisons' => $livraison
        ]);
    }

    public function detailDemandeLivraison(DemandeLivraison $demande){

        return view('gestionnaire.detailDemandeLivraison',[
            'demande' => $demande
        ]);
    }

    public function traiteLivraisonPage(DemandeLivraison $demandeLivraison, Request $request){

        // Une demande réglée EN AGENCE doit être soldée avant d'être traitée :
        // on n'envoie pas un camion pour un transport que le client n'a pas payé.
        // Les demandes réglées en ligne ne sont pas concernées — la passerelle
        // encaisse avant même que la demande n'arrive ici.
        $blocage = $this->reglementBloquantDemandeLivraison($demandeLivraison);

        if ($blocage) {
            return redirect()->route('show.demandeLivraisonlist')->with('blocage_reglement', $blocage);
        }

        return view('gestionnaire.traiteLivraison',[
            'livraisons' => $demandeLivraison,
            'vehicules' => Vehicule::orderByDesc('capacite')->get()
        ]);

    }

    /**
     * RENVOYER AU CLIENT LE CODE D'UNE COURSE DE TRANSPORT.
     *
     * Le courriel part à l'affectation, et une seule fois. S'il échoue — SMTP
     * indisponible, boîte pleine — ou s'il atterrit dans les indésirables, le
     * client n'a aucun moyen d'obtenir son code, et le livreur ne peut pas
     * clore la course : il faut alors pouvoir le renvoyer sans toucher à la
     * base ni réaffecter le camion.
     *
     * Le résultat est annoncé tel quel, succès comme échec : annoncer un envoi
     * qui n'a pas eu lieu rejouerait exactement le problème qu'on corrige.
     */
    public function renvoyerCodeDemandeLivraison(Livraison $livraison)
    {
        $detail  = $livraison->detail_livraison_id
            ? DetailLivraison::find($livraison->detail_livraison_id)
            : null;

        $demande = $detail?->demandeLivraison;

        if (!$demande) {
            return back()->with('code_non_envoye',
                "Cette course n'est rattachée à aucune demande de livraison : le code ne peut pas être renvoyé.");
        }

        // Une course REFUSÉE porte un code qui ne vaut plus rien : le livreur ne
        // la validera jamais. L'envoyer entretiendrait la confusion.
        if ((int) $livraison->accepte === Livraison::REFUSEE) {
            return back()->with('code_non_envoye',
                "Cette course a été refusée par le livreur : son code est caduc. "
                . "Réaffectez la demande, un nouveau code partira.");
        }

        $client = $demande->client;

        $adresse = $client?->user?->email ?: ($client?->email ?: null);

        if (!$adresse) {
            return back()->with('code_non_envoye',
                "Ce client n'a aucune adresse e-mail : communiquez-lui le code {$livraison->numero} par téléphone.");
        }

        try {
            Mail::send(new \App\Mail\receptionCodeDemandeLivraison(
                $livraison, $demande, $client, $detail
            ));
        } catch (\Throwable $e) {
            \Log::warning('Renvoi du code de validation échoué: ' . $e->getMessage());

            return back()->with('code_non_envoye', sprintf(
                "L'envoi a de nouveau échoué (%s). Communiquez le code %s au client par téléphone.",
                $e->getMessage(),
                $livraison->numero
            ));
        }

        return back()->with('code_renvoye',
            "Code {$livraison->numero} renvoyé à {$adresse}.");
    }

    /**
     * Message de blocage si la demande n'est pas soldée, null si elle peut être
     * traitée. Une seule règle, appelée par l'écran ET par l'enregistrement :
     * bloquer uniquement l'affichage laisserait passer un envoi direct du
     * formulaire.
     */
    private function reglementBloquantDemandeLivraison(DemandeLivraison $demandeLivraison): ?string
    {
        // Un CLIENT À TERME paie après, c'est le sens même de son compte : lui
        // demander de régler avant le départ du camion viderait sa ligne de
        // crédit de sa substance. Les ventes font la même séparation — la caisse
        // comptant ne traite que les clients ordinaires, les clients à terme
        // relèvent des créances.
        if ((int) ($demandeLivraison->client?->client_a_terme ?? 0) === 1) {
            return null;
        }

        if (!$demandeLivraison->reglementEnAgence()) {
            return null;
        }

        $reste = $demandeLivraison->montantRestantDu();

        if ($reste <= 0) {
            return null;
        }

        return sprintf(
            'La demande %s ne peut pas être traitée : elle est réglée en agence et il reste %s FCFA à encaisser sur %s FCFA. '
            . 'Rendez-vous dans Caisse → « Encaissements demandes de livraison ». '
            . 'Un encaissement ne compte qu\'une fois validé par un second administrateur.',
            $demandeLivraison->numero,
            number_format($reste, 0, ',', ' '),
            number_format($demandeLivraison->montantAPayer(), 0, ',', ' ')
        );
    }

    public function selectionneVehicule($id,$detail){
        $car = Vehicule::find($id);
        // Véhicule inexistant (identifiant modifié dans l'URL) ou véhicule non rattaché
        // à un livreur : les lectures ci-dessous tombaient en erreur 500 au moment de
        // sélectionner un véhicule pour une livraison.
        if ($car == null) {
            return response()->json(['erreur' => "Véhicule introuvable"], 404);
        }
        $data = [
            'idCar' => $car->id,
            'marque' => $car->marque,
            'capacite' => $car->capacite,
            'livreur' => [
                'id' => $car->livreur?->id,
                'numero' => $car->livreur?->user?->contact
            ],
            'vehicule_id' => $car->id,
            'immatriculation' => $car->immatriculation,
            'detail' => $detail
        ];
        return $data;
    }

    /**
     * Supprime (soft delete) une location « fantôme » : créée à la validation mais
     * jamais payée. Sécurité : uniquement les locations EN ATTENTE ET non soldées —
     * jamais une location payée (statut 3) ni déjà validée (EN COURS / TERMINE).
     */
    public function supprimerLocation(\App\Models\Location $location){
        // Suppression réservée aux locations SANS AUCUN paiement : dès qu'un
        // acompte est encaissé, on ne supprime plus — il y a de l'argent sur
        // cette location.
        //
        // LA QUESTION SE POSE À L'ARGENT, PAS AU DRAPEAU. `location.statut`
        // reste à 1 quand un chemin de paiement oublie de le poser : cette
        // garde — la seule qui protège vraiment, le bouton n'étant qu'un
        // affichage — laissait alors supprimer une location réellement
        // encaissée, et l'argent restait en caisse sans rien en face.
        if ($location->etatPaiement() !== 'AUCUN') {
            return back()->with('error', 'Un paiement a déjà été enregistré sur cette location : suppression impossible.');
        }
        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE) {
            return back()->with('error', 'Seules les locations EN ATTENTE non payées peuvent être supprimées.');
        }
        $location->delete(); // soft delete (deleted_at)
        return back()->with('success', 'Location non payée supprimée.');
    }

    public function listeLocationEnAttente(){

        // Ne lister QUE les locations réellement EN ATTENTE : dès qu'une location est
        // validée (EN COURS) ou terminée (TERMINE), elle passe sur /locations-traitees.
        // On exclut donc EN COURS / TERMINE (valeurs texte ET entiers 2/3 pour l'historique).
        $locations = Location::with('factureFne')
            ->whereNotIn('etat_location', [Help::$LOCATION_EN_COURS, Help::$LOCATION_TERMINE, 'ANNULEE', 2, 3])
            ->orderByDesc('created_at')
            ->get();

        return view('gestionnaire.listeLocation',[
            'locations' => $locations
        ]);
    }

    /**
     * (c) Écran de validation d'une location EN ATTENTE : affectation livreur + véhicule
     * + saisie de la caution. Réservé aux locations non encore traitées.
     */
    public function validerLocationPage(\App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Cette location est déjà traitée (état : ' . $location->etatLibelle() . ').');
        }
        // Paiement soldé exigé avant validation (même règle que les commandes),
        // sauf client à terme qui paie à crédit.
        // L'ARGENT, PAS LE DRAPEAU : une location payee dont le drapeau etait
        // reste en arriere se voyait refuser sa validation.
        if (!$location->estSoldee() && $location->client->client_a_terme != 1) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('error', 'Le paiement de cette location doit être soldé avant de pouvoir la valider.');
        }
        $location->load('detailLocation.produit', 'client');
        // Caution suggérée = somme (caution unitaire du produit × quantité) des lignes.
        $cautionSuggeree = (float) $location->detailLocation->sum(function ($d) {
            return (float) ($d->produit->caution ?? 0) * (float) $d->qte;
        });

        // LES FOURNISSEURS DE CHAQUE LIGNE, pour que le gestionnaire designe
        // celui qui remettra le materiel.
        //
        // Une location ne creait AUCUN bon d'enlevement : le livreur se
        // presentait chez le fournisseur sans rien a lui montrer, alors que la
        // vente lui donne un code depuis toujours. Un bon exige un fournisseur,
        // et le choix ne peut pas etre devine : les produits de location en
        // comptent jusqu'a cinq.
        $fournisseursParLigne = [];

        foreach ($location->detailLocation as $ligne) {
            $fournisseursParLigne[$ligne->id] = \App\Models\StockProduit::with('fournisseur.user')
                ->where('produit_id', $ligne->produit_id)
                ->where('statut', Help::$STATUT_ACTIF)
                ->whereNull('deleted_at')
                ->where('prix', '>', 0)
                ->orderBy('prix')
                ->get();
        }

        return view('gestionnaire.validerLocation', [
            'location'             => $location,
            'cautionSuggeree'      => $cautionSuggeree,
            'livreurs'             => Livreur::where('statut', Help::$STATUT_ACTIF)->with('user')->get(),
            'vehicules'            => Vehicule::orderByDesc('capacite')->get(),
            'fournisseursParLigne' => $fournisseursParLigne,
        ]);
    }

    /**
     * (c) Traitement de la validation : crée une livraison (provenance=LOCATION) par
     * ligne de location affectée au livreur/véhicule, enregistre la caution, et passe
     * la location EN COURS. Transactionnel.
     */
    public function validerLocation(Request $request, \App\Models\Location $location){
        // LE MODE DE RÉCUPÉRATION N'EST PLUS UN CHOIX DU GESTIONNAIRE.
        //
        // L'écran proposait deux cases à cocher, pré-remplies depuis le choix du
        // client mais modifiables. Le gestionnaire pouvait donc contredire son
        // client sans s'en rendre compte — et le formulaire acceptait « livraison »
        // sur une location sans adresse, ou « retrait » sur une location qui en
        // portait une.
        //
        // Il CONSTATE désormais le mode, il ne le décide plus. La règle vit sur le
        // modèle (Location::estRetraitSurPlace) : l'écran et ce contrôleur la
        // lisent au même endroit, ils ne peuvent plus diverger.
        $estRetrait = $location->estRetraitSurPlace();

        $request->validate([
            // `required_if` visait le champ du formulaire, qui n'existe plus : la
            // règle se construit maintenant à partir du mode réel.
            'livreur'  => [$estRetrait ? 'nullable' : 'required', 'nullable', 'integer', 'exists:livreur,id'],
            'vehicule' => [$estRetrait ? 'nullable' : 'required', 'nullable', 'integer', 'exists:vehicule,id'],
            'caution'  => 'nullable|numeric|min:0',
            // Un bon d'enlevement sans fournisseur n'existe pas, et un bon sans
            // prix vaudrait un du de zero — c'est le defaut deja corrige sur les
            // ventes. On exige donc la designation, ligne par ligne.
            'fournisseur'   => 'required|array',
            'fournisseur.*' => 'required|integer|exists:fournisseur,id',
            // La quantité remise, ligne par ligne. Une quantité nulle ou
            // négative produirait un bon sans objet et un dû faux.
            'qte'           => 'required|array',
            'qte.*'         => 'required|numeric|min:0.01',
        ], [
            'livreur.required'  => 'Veuillez sélectionner un livreur.',
            'vehicule.required' => 'Veuillez sélectionner un véhicule.',
            // « required » et non « required_if » : le fournisseur est exigé dans
            // les DEUX modes depuis que le retrait produit lui aussi un bon.
            'fournisseur.required'   => 'Veuillez désigner le fournisseur de chaque matériel.',
            'fournisseur.*.required' => 'Veuillez désigner le fournisseur de chaque matériel.',
            'qte.required'    => 'Veuillez indiquer la quantité remise pour chaque matériel.',
            'qte.*.required'  => 'Veuillez indiquer la quantité remise pour chaque matériel.',
            'qte.*.min'       => 'La quantité remise doit être supérieure à zéro.',
        ]);


        // Une location dont le livreur a REFUSÉ la course doit pouvoir être
        // reconfiée. Le verrou sur EN ATTENTE l'interdisait : l'affectation fait
        // passer la location EN COURS, si bien qu'un refus la laissait dans un
        // état d'où l'on ne pouvait plus rien faire — matériel jamais livré, et
        // « Cette location est déjà traitée » pour toute réponse.
        $lignesARefaire = $location->lignesALivrerDeNouveau();

        if ($location->etatLibelle() !== Help::$LOCATION_EN_ATTENTE && $lignesARefaire->isEmpty()) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Cette location est déjà traitée.');
        }
        // Paiement soldé exigé avant validation (même règle que les commandes),
        // sauf client à terme qui paie à crédit.
        // L'ARGENT, PAS LE DRAPEAU : une location payee dont le drapeau etait
        // reste en arriere se voyait refuser sa validation.
        if (!$location->estSoldee() && $location->client->client_a_terme != 1) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('error', 'Le paiement de cette location doit être soldé avant de pouvoir la valider.');
        }

        // AUCUNE COURSE DE LIVREUR SANS ADRESSE.
        //
        // Sans adresse, le livreur reçoit une course qu'il ne peut pas faire :
        // son écran affiche « Lieu : null », et la distance retenue pour sa
        // rémunération vaut 0 km. Le cas venait des locations « Retrait sur
        // place » présentées à tort comme livrables (le choix du client
        // n'était pas enregistré) ; il peut aussi venir d'une location du
        // mobile passée sans adresse, ou d'un gestionnaire qui bascule en
        // livraison une location qui n'en prévoyait pas.
        //
        // Le refus est explicite : mieux vaut renvoyer le gestionnaire vers le
        // retrait sur place que d'envoyer un livreur nulle part.
        if (!$estRetrait && !$location->adresse_livraison_id) {
            return back()->withInput()->with(
                'error',
                'Cette location est à livrer, mais ne porte aucune adresse. Faites '
                . 'enregistrer l’adresse de livraison par le client avant de lui '
                . 'affecter un livreur : un livreur envoyé sans adresse ne sait pas '
                . 'où aller, et sa rémunération se calcule sur une distance nulle.'
            );
        }

        $conf    = Configuration::first();
        $livreur = Livreur::find($request->livreur);

        // Distance (adresse de la location -> région) pour la rémunération du livreur.
        $distance = 0;
        $adr = \App\Models\AdresseLivraison::find($location->adresse_livraison_id);
        if ($adr && $adr->ville && $adr->ville->region) {
            $distance = Help::distance($adr->longitude, $adr->latitude, $adr->ville->region->long, $adr->ville->region->lat);
        }

        $livraisonsCreees = [];
        \DB::transaction(function () use ($location, $request, $livreur, $conf, $distance, $estRetrait, $lignesARefaire, &$livraisonsCreees) {
            // RETRAIT SUR PLACE : LE CLIENT AUSSI A BESOIN D'UN BON.
            //
            // Ce cas ne produisait RIEN — ni course, ni bon d'enlevement. Le
            // client se presentait chez le fournisseur sans preuve, et le
            // fournisseur n'avait aucun bon a valider : la quantite remise
            // n'etait enregistree nulle part, et sa dette non plus.
            //
            // La crainte d'origine — une « livraison fantome » qui polluerait la
            // tournee du livreur — etait fondee, mais elle a sa reponse : la
            // colonne `livre_par` (1 = LIVREUR, 2 = CLIENT). Les listes du
            // livreur filtrent toutes sur `livre_par = 1` (LivreurController,
            // lignes 744 et 786) : une course de retrait ne s'y affiche pas.
            // C'est exactement ce que fait deja la VENTE en retrait sur place
            // (OrdersController), et les locations s'y alignent.
            $lignes = $lignesARefaire->isNotEmpty() ? $lignesARefaire : $location->detailLocation;

            $fournisseursChoisis = (array) $request->input('fournisseur', []);
            $quantitesRemises    = (array) $request->input('qte', []);

            foreach ($lignes as $detail) {
                // Le fournisseur designe pour cette ligne. Une ligne sans
                // designation ne doit pas produire un bon orphelin : on s'arrete.
                $fournisseurLigne = $fournisseursChoisis[$detail->id] ?? null;

                // LA QUANTITE RETENUE, declaree AVANT tout emploi.
                //
                // Elle sert a la course comme au bon : la declarer plus bas la
                // laissait vide au moment de creer la course, et la course
                // partait a zero.
                //
                // Repli sur la quantite commandee : une ligne apparue entre
                // l'affichage du formulaire et son envoi n'aurait pas de champ,
                // et un bon a zero vaudrait un du nul.
                $qteRemise = (float) ($quantitesRemises[$detail->id] ?? $detail->qte);

                if (!$fournisseurLigne) {
                    // UN FORMULAIRE INCOMPLET RÉPOND, IL NE CASSE PAS.
                    //
                    // Cette exception remontait en page blanche (erreur 500) :
                    // le gestionnaire ne savait ni ce qui manquait, ni que sa
                    // location n'était pas validée. La validation ci-dessus
                    // exige désormais un fournisseur par ligne ; ce garde-fou
                    // ne sert plus que si une ligne apparaît entre l'affichage
                    // du formulaire et son envoi.
                    throw new \Illuminate\Validation\ValidationException(
                        \Illuminate\Support\Facades\Validator::make([], [])->after(function ($v) use ($detail) {
                            $v->errors()->add('fournisseur', 'Aucun fournisseur désigné pour « '
                                . (optional($detail->produit)->nom ?? 'un matériel')
                                . ' ». La location n’a pas été validée.');
                        })
                    );
                }

                // LOCATION : le matériel loué n'est pas mesuré en tonnes. Le repli de
                // rémunération est le coût d'UN déplacement (distance × coût fixe), SANS
                // facteur "voyages/tonnage" (qui n'a de sens que pour le gravier en vrac).
                // Les modes de tarification du livreur (km / base) priment de toute façon.
                $coutGlobal = (float) $distance * (float) ($conf->cout_liv_fixe ?? 0);
                $tarif = $livreur
                    ? $livreur->tarifLivraison(
                        optional(\App\Models\Produit::find($detail->produit_id))->unite_produit_id,
                        (float) $detail->qte,
                        (float) $distance,
                        $coutGlobal
                    )
                    : ['forfait_base' => $coutGlobal, 'frais_km' => 0.0, 'total' => $coutGlobal];

                $liv = Livraison::create([
                    'numero'               => \Help::genererNumeroUnique('livraison'),
                    // En RETRAIT SUR PLACE, personne ne livre : ni livreur, ni
                    // vehicule. `livre_par = 2` (CLIENT) tient la course hors
                    // des listes du livreur, qui filtrent sur 1.
                    'livreur_id'           => $estRetrait ? null : $request->livreur,
                    'vehicule_id'          => $estRetrait ? null : $request->vehicule,
                    'client_id'            => $location->client_id,
                    'adresse_livraison_id' => $location->adresse_livraison_id,
                    'date_livraison'       => $detail->debut ?? $location->date_location ?? now()->toDateString(),
                    // La quantité RETENUE par le gestionnaire, et non celle
                    // commandée : c'est elle qui part chez le fournisseur.
                    'qte'                  => $qteRemise,
                    // Pour une livraison de LOCATION, detail_commande_id porte l'id du detail_location.
                    'detail_commande_id'   => $detail->id,
                    'provenance'           => Help::$LOCATION,
                    'cout_livraison'       => $tarif['total'],
                    'forfait_base'         => $tarif['forfait_base'],
                    'frais_km'             => $tarif['frais_km'],
                    'source_tarif'         => $tarif['source'] ?? null,
                    'distance_km'          => round((float) $distance, 2),
                    'etat_livraison'       => Help::$LIVRAISON_EN_ATTENTE,
                    'statut'               => Help::$STATUT_ACTIF,
                    'gestionnaire_id'      => Auth::id(),
                    // Une course de retrait n'attend l'acceptation de personne.
                    'accepte'              => $estRetrait ? 1 : 2,
                    'livre_par'            => $estRetrait ? 2 : 1,
                ]);
                // LE BON D'ENLEVEMENT — CE QUE LE LIVREUR MONTRE AU FOURNISSEUR.
                //
                // Il n'en existait aucun pour les locations. La plomberie, elle,
                // etait deja la : la liste du livreur joint `enlevement` en
                // leftJoin et lit `code_enleve`, exactement comme pour une
                // vente. Il ne manquait que la ligne.
                //
                // LE MONTANT PORTE LA DUREE. La dette se calcule
                // `quantite x prix_fournisseur` (Enlevement::montantDu) : un
                // enlevement n'a pas de notion de jours. On inscrit donc dans
                // `prix_fournisseur` le prix d'achat MULTIPLIE PAR LE NOMBRE DE
                // JOURS, de sorte que le du vaille bien
                // `prix d'achat x quantite x jours`. La quantite reste la
                // quantite PHYSIQUE remise, et le du suit la quantite
                // reellement servie en cas de remise partielle.
                $prixAchat = (float) (\App\Models\StockProduit::where('produit_id', $detail->produit_id)
                    ->where('fournisseur_id', $fournisseurLigne)
                    ->where('statut', Help::$STATUT_ACTIF)
                    ->whereNull('deleted_at')
                    ->value('prix') ?? 0);

                $jours = max(1, (int) ($detail->nombre_jour ?? 1));


                $bon = Enlevement::create([
                    'fournisseur_id'   => $fournisseurLigne,
                    'livraison_id'     => $liv->id,
                    'produit_id'       => $detail->produit_id,
                    'qte'              => $qteRemise,
                    'prix_fournisseur' => $prixAchat * $jours,
                    'livreur_id'       => $estRetrait ? null : $request->livreur,
                    'vehicule_id'      => $estRetrait ? null : $request->vehicule,
                    'code_enleve'      => $this->generateCode(),
                    'gestionnaire_id'  => Auth::id(),
                    'statut'           => Help::$STATUT_ACTIF,
                ]);

                // Pour l'envoi du code au client (après commit). Le bon voyage
                // avec la course : en retrait sur place, c'est SON code que le
                // client recevra, et non celui de la livraison.
                $livraisonsCreees[] = [
                    'livraison' => $liv,
                    'produit'   => $detail->produit,
                    'bon'       => $bon,
                ];
            }

            $location->update([
                'livreur_id'    => $estRetrait ? null : $request->livreur,
                'vehicule_id'   => $estRetrait ? null : $request->vehicule,
                'caution'       => (float) ($request->caution ?? 0),
                // On enregistre le mode réellement retenu par le gestionnaire : il fait foi
                // sur le choix initial du client (correction d'un ancien enregistrement,
                // ou changement d'avis).
                'est_livrable'  => $estRetrait ? 0 : 1,
                'etat_location' => Help::$LOCATION_EN_COURS,
            ]);
        });

        // Envoi au client du CODE de validation (= numéro de livraison) qu'il communiquera
        // au livreur pour valider la livraison. NON bloquant : un échec d'email ne doit pas
        // empêcher la validation de la location.
        // L'ÉCHEC D'ENVOI DOIT SE VOIR, PAS SEULEMENT SE JOURNALISER.
        //
        // L'envoi était déjà protégé — une panne de messagerie ne cassait pas la
        // validation — mais il échouait EN SILENCE : le gestionnaire voyait
        // « Location validée » et le client n'avait aucun code. Personne ne
        // pouvait s'en apercevoir avant que le livreur ne se présente.
        $codesManques = 0;

        foreach ($livraisonsCreees as $item) {
            try {
                if ($estRetrait) {
                    // RETRAIT SUR PLACE : c'est le code du BON qui part.
                    //
                    // Le client vient chercher lui-meme le materiel :
                    // personne ne peut lui remettre ce code sur place,
                    // puisqu'il n'y a pas de livreur. Il le presente au
                    // fournisseur, qui valide le bon a la quantite remise.
                    //
                    // Le code de VALIDATION d'une livraison n'aurait ici
                    // aucun sens : il sert au client a confirmer une
                    // reception, et aucune livraison n'aura lieu.
                    \Illuminate\Support\Facades\Mail::send(new \App\Mail\codeEnlevementLocation(
                        $item['bon'],
                        $location,
                        $location->client,
                        $item['produit']
                    ));
                } else {
                    \Illuminate\Support\Facades\Mail::send(new \App\Mail\receptionCodeLivraisonLocation(
                        $item['livraison'],
                        $location,
                        $location->client,
                        $item['produit']
                    ));
                }
            } catch (\Throwable $e) {
                $codesManques++;
                \Log::error("Code de " . ($estRetrait ? "retrait" : "validation")
                    . " location {$location->numero} non envoyé : " . $e->getMessage());
            }
        }

        return redirect()->route('show.listeLocationEnAttente')
            ->with('success', $estRetrait
                ? 'Location validée en RETRAIT SUR PLACE : aucun livreur affecté, le client vient chercher le matériel. Location passée EN COURS.'
                : 'Location validée : livraison(s) créée(s), livreur affecté, location passée EN COURS.')
            // Clé PROPRE : Flasher capte « error » et « warning » pour les rejouer
            // en bulle éphémère, et l'avertissement se perdrait.
            ->with('code_non_envoye', $codesManques === 0 ? null : (
                "La location est bien validée, mais {$codesManques} code(s) "
                . ($estRetrait ? "de RETRAIT " : "de validation ")
                . "n'ont PAS pu être envoyés au client. Retrouvez-les dans « Locations "
                . "traitées » et communiquez-les-lui directement."
            ));
    }

    /**
     * (c) Écran de retour du matériel : saisie de la retenue éventuelle sur caution.
     */
    public function retourLocationPage(\App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_COURS) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Le retour ne concerne qu\'une location EN COURS (état actuel : ' . $location->etatLibelle() . ').');
        }
        return view('gestionnaire.retourLocation', [
            'location' => $location->load('detailLocation.produit', 'client'),
        ]);
    }

    /**
     * (c) Retour du matériel loué : la location EN COURS passe TERMINÉ. On note la date
     * de retour et la RETENUE éventuelle sur la caution (dégâts). Le montant restitué =
     * caution - caution_retenue.
     */
    public function retourLocation(Request $request, \App\Models\Location $location){
        if ($location->etatLibelle() !== Help::$LOCATION_EN_COURS) {
            return redirect()->route('show.listeLocationEnAttente')
                ->with('info', 'Le retour ne concerne qu\'une location EN COURS (état actuel : ' . $location->etatLibelle() . ').');
        }

        $caution = (float) ($location->caution ?? 0);
        $request->validate([
            'caution_retenue' => 'nullable|numeric|min:0|max:' . $caution,
            'motif_retenue'   => 'nullable|string|max:255',
        ], [
            'caution_retenue.max' => 'La retenue ne peut pas dépasser la caution (' . $caution . ' fcfa).',
        ]);

        $retenue = (float) ($request->caution_retenue ?? 0);

        $location->update([
            'etat_location'     => Help::$LOCATION_TERMINE,
            'date_retour'       => now()->toDateString(),
            'caution_retenue'   => $retenue,
            'motif_retenue'     => $retenue > 0 ? $request->motif_retenue : null,
            // caution_restituee = true dès qu'une partie (ou la totalité) est rendue.
            'caution_restituee' => $retenue < $caution,
        ]);

        // Les lignes suivent la location (10/09/2026) : l'application les affiche.
        \App\Models\DetailLocation::where('location_id', $location->id)->update(['etat_location' => Help::$LOCATION_TERMINE]);

        $restitue = max(0, $caution - $retenue);
        return redirect()->route('show.listeLocationEnAttente')
            ->with('success', 'Matériel retourné : location TERMINÉE. Caution restituée : '
                . number_format($restitue, 0, ',', ' ') . ' fcfa'
                . ($retenue > 0 ? ' (retenue : ' . number_format($retenue, 0, ',', ' ') . ' fcfa).' : '.'));
    }

    /**
     * (c) Liste des locations TRAITÉES (déjà validées) : EN COURS ou TERMINÉ.
     * Page distincte de la liste des commandes traitées (évite la confusion).
     */
    public function locationsTraitees(Request $request){
        $locations = Location::whereIn('etat_location', [Help::$LOCATION_EN_COURS, Help::$LOCATION_TERMINE, 2, 3])
            ->with('client', 'livreur.user', 'factureFne')
            ->orderByDesc('updated_at')
            ->get();

        // LES FILTRES.
        //
        // Aucune borne par defaut : l'ecran a toujours montre toutes les
        // locations traitees, et en restreindre l'affichage sans qu'on l'ait
        // demande ferait croire a des locations disparues. Chaque critere ne
        // s'applique donc que s'il est rempli.
        $etat     = trim((string) $request->input('etat'));
        $paiement = trim((string) $request->input('paiement'));
        $client   = trim((string) $request->input('client'));
        $du       = $request->input('du') ?: null;
        $au       = $request->input('au') ?: null;

        if ($etat !== '') {
            $locations = $locations->filter(
                fn ($l) => $l->etatLibelle() === $etat);
        }

        if ($paiement !== '') {
            // Sur l'ARGENT, pas sur le drapeau : meme regle que la colonne.
            $locations = $locations->filter(
                fn ($l) => $l->etatPaiement() === $paiement);
        }

        if ($client !== '') {
            $recherche = mb_strtolower($client);
            $locations = $locations->filter(function ($l) use ($recherche) {
                $nom = mb_strtolower((string) ($l->client?->display_name
                    ?? $l->client?->nom_prenoms ?? $l->client?->nom ?? ''));

                return $nom !== '' && str_contains($nom, $recherche);
            });
        }

        // La periode porte sur la DATE DE RETOUR : c'est la seule date que la
        // liste affiche, et celle qui interesse le gestionnaire.
        if ($du || $au) {
            $locations = $locations->filter(function ($l) use ($du, $au) {
                if (!$l->date_retour) {
                    return false;
                }
                $jour = \Carbon\Carbon::parse($l->date_retour)->format('Y-m-d');

                return (!$du || $jour >= $du) && (!$au || $jour <= $au);
            });
        }

        return view('gestionnaire.locationsTraitees', [
            'locations' => $locations->values(),
            'etat'      => $etat,
            'paiement'  => $paiement,
            'client'    => $client,
            'du'        => $du,
            'au'        => $au,
        ]);
    }

    public function modifierPrixLivraison(Livreur $livreur, Request $request){

        // Le livreur (ou l'admin) choisit son mode de tarification :
        //  - 'base'  : un tarif forfaitaire (cout_livraison)
        //  - 'km'    : un tarif par kilomètre (tarif_km)
        //  - 'mixte' : un fixe (tarif_forfait_base) PLUS le kilométrage
        $mode = in_array($request->mode_tarification, ['base', 'km', 'mixte']) ? $request->mode_tarification : 'base';

        if ($mode === 'mixte') {
            // Les deux parts peuvent être nulles séparément — un fixe sans
            // kilométrage reste un forfait, un kilométrage sans fixe reste du
            // kilométrique — mais pas les deux : le livreur ne serait pas payé.
            if ((int) $request->tarif_forfait_base < 1 && (int) $request->tarif_km < 1) {
                return redirect()->route('show.profile', $livreur->id)
                    ->with('error', 'Tarif mixte : renseignez au moins le fixe ou le tarif par kilomètre.');
            }

            $livreur->update([
                'mode_tarification'  => 'mixte',
                'tarif_forfait_base' => max(0, (int) $request->tarif_forfait_base),
                'tarif_km'           => max(0, (int) $request->tarif_km),
            ]);

            return redirect()->route('show.profile', $livreur->id)->with('success', 'Modification effectuée');
        }

        if ($mode === 'km') {
            if ($request->tarif_km < 1) {
                return redirect()->route('show.profile',$livreur->id)->with('error','Le tarif par KM saisi est incorrect');
            }
            $livreur->update([
                'mode_tarification' => 'km',
                'tarif_km'          => (int) $request->tarif_km,
            ]);

            return redirect()->route('show.profile',$livreur->id)->with('success','Modification effectuée');
        }

        // Mode tarif de base
        if($request->montant < 1){
            return redirect()->route('show.profile',$livreur->id)->with('error','Le montant saisi est incorrect');
        }

        $ancienPrix = (float) ($livreur->cout_livraison ?? 0);
        $nouveauPrix = (float) $request->montant;

        // Trace l'historique uniquement si le prix a réellement changé.
        if ($ancienPrix != $nouveauPrix) {
            \App\Models\HistoriquePrixLivraisonLivreur::create([
                'livreur_id'   => $livreur->id,
                'ancien_prix'  => $ancienPrix,
                'nouveau_prix' => $nouveauPrix,
                'user_id'      => Auth::id(),
                'motif'        => $request->motif,
            ]);
        }

        $livreur->update([
            'mode_tarification' => 'base',
            'cout_livraison'    => $nouveauPrix,
        ]);

        return redirect()->route('show.profile',$livreur->id)->with('success','Modification effectuée');
    }

    /**
     * Met à jour la zone d'intervention d'un livreur (profil admin).
     */
    public function modifierZoneLivreur(Livreur $livreur, Request $request){
        $request->validate([
            'zone_intervention' => 'nullable|string|max:190',
        ]);

        $livreur->update([
            'zone_intervention' => $request->zone_intervention,
        ]);

        return redirect()->route('show.profile', $livreur->id)->with('success', "Zone d'intervention mise à jour");
    }

    public function traitelivraison(DemandeLivraison $demandeLivraison, DetailLivraison $detail, Request $request){

        // Même contrôle qu'à l'affichage : bloquer seulement l'écran laisserait
        // passer un envoi direct du formulaire, qui affecterait un camion à une
        // course impayée.
        $blocage = $this->reglementBloquantDemandeLivraison($demandeLivraison);

        if ($blocage) {
            return redirect()->route('show.demandeLivraisonlist')->with('blocage_reglement', $blocage);
        }

        // LA DATE EST REELLEMENT EXIGEE.
        //
        // L'ecran l'annoncait obligatoire — une etoile rouge — mais rien ne la
        // tenait : ni « required » en HTML, ni controle ici. Le formulaire
        // partait donc vide, et le repli ci-dessous reprenait EN SILENCE la date
        // portee par la demande. Le gestionnaire croyait avoir fixe la date
        // d'affectation ; une autre s'appliquait, sans un mot.
        //
        // Le controle est pose AU SERVEUR et non seulement dans la page : le
        // « required » d'un navigateur se contourne d'un envoi direct.
        // La date de livraison n'est plus saisie ici (10/09/2026) : le client
        // l'a choisie à sa demande, et la course reprend demande.date_livraison
        // (voir la création de la course plus bas). La date postée par l'ancien
        // formulaire n'était lue nulle part.

        // Refus exclus : une course refusee n'a rien transporte, elle ne
        // consomme donc pas la quantite de la ligne (cf. DetailLivraison).
        $qt = $detail->qteRestanteAAffecter();

        // dd($qt,$detail->qte,$detail->livraisons->sum('qte'));
        // dd(!$demandeLivraison->livraisons->isEmpty(),$qt,$detail);

        // (Le repli sur la date de la demande a ete retire : il n'etait plus
        //  atteignable une fois la date exigee, et laisser un repli muet aurait
        //  entretenu l'idee qu'une date vide est acceptable.)

        // Rémunération du livreur.
        //
        // Elle repose sur forfait_base + frais_km (cf. DetteLivreurController) et
        // était laissée à zéro pour les demandes de livraison : un livreur ayant
        // effectué une course de transport apparaissait dû à ZÉRO dans
        // « Dettes livreur ». Les ventes et les locations, elles, la calculent
        // dès l'affectation.
        //
        // La distance retenue est celle réellement parcourue : de la PRISE EN
        // CHARGE à la DESTINATION. Les autres flux mesurent l'adresse du client
        // depuis la région — c'est le même trajet chez eux, la marchandise
        // partant du dépôt. Ici elle part de chez le client : mesurer depuis la
        // région n'aurait aucun rapport avec la route parcourue.
        $conf = Configuration::first();
        $pec  = $demandeLivraison->priseEnCharge;
        $dest = $demandeLivraison->destination;

        $distance = ($pec && $dest)
            ? Help::distance($pec->longitude, $pec->latitude, $dest->longitude, $dest->latitude)
            : 0;

        // Les courses dont le code n'a pas pu partir. Une affectation peut
        // porter plusieurs camions : on les rassemble pour n'avertir qu'une fois.
        $codesNonEnvoyes = [];

        foreach ($request->id as $rang => $camionId) {

            $camion = Vehicule::where('id',$camionId)->first();

            // LA QUANTITÉ CONFIÉE À CE CAMION.
            //
            // Elle était imposée : toujours la capacité du véhicule. Une ligne
            // de 25 sacs et un camion de 20 demandaient donc DEUX affectations,
            // deux codes de validation et deux clôtures — pour un seul travail.
            // Et le calcul de rotations, pourtant présent, ne servait à rien :
            // une quantité plafonnée à la capacité fait toujours un voyage.
            //
            // Le gestionnaire la décide maintenant, comme il le fait déjà sur
            // une vente (cf. OrdersController, qui prend $request->qte tel quel
            // et en déduit le nombre de voyages). Le champ est pré-rempli avec
            // l'ancienne valeur : ne rien toucher donne exactement le
            // comportement d'avant.
            //
            // Bornes : jamais plus que ce qui reste à confier — sans quoi la
            // ligne serait sur-affectée et ne se clôturerait jamais — et jamais
            // moins que rien.
            $parDefaut = min((float) $camion->capacite, (float) $qt);
            $demandee  = $request->qte[$rang] ?? null;

            $qt1 = ($demandee === null || $demandee === '')
                ? $parDefaut
                : max(0, min((float) $demandee, (float) $qt));

            if ($qt1 <= 0) {
                continue;
            }

            $qt = $qt - $qt1;

            //dd($camion);

            // Rotations arrondies au supérieur : un quart de chargement demande
            // un déplacement complet. Et le tarif du livreur est multiplié par
            // ce nombre — trois rotations, c'est trois fois le carburant.
            $voyages    = \App\Models\Livreur::nombreDeVoyages(
                (float) $qt1,
                (float) ($camion->capacite ?? 0),
                (float) ($conf->tonne_moyenne ?? 0),
                $detail->unite_produit_id ?? null
            );
            $coutGlobal = (float) $distance * (float) ($conf->cout_liv_fixe ?? 0) * $voyages;

            $livreurCamion = $camion->livreur;
            $tarif = $livreurCamion
                ? $livreurCamion->tarifLivraison(
                    $detail->unite_produit_id ?? null,
                    (float) $qt1,
                    (float) $distance,
                    $coutGlobal,
                    $voyages
                )
                : ['forfait_base' => $coutGlobal, 'frais_km' => 0.0, 'total' => $coutGlobal];

            // array_push($lesqte,$qt1);

            //$detail = $demandeLivraison->detailLivraison;
            //dd($detail);
            // dd(CoutLivraison::find($detail->cout_livraison_id));
            $livraison = [
                'numero' => \Help::genererNumeroUnique('livraison'),
                'client_id' => $demandeLivraison->client_id,
                'livreur_id' =>  $camion->livreur_id,
                'adresse_livraison_id' => $demandeLivraison->destination->id,
               // 'cout_livraison_id' => $detail->cout_livraison_id,
                'date_livraison' => $demandeLivraison->date_livraison,
                // « LIVRAISON » en toutes lettres : provenance est un ENUM, où un
                // entier désigne la POSITION de la valeur. « 2 » tombe juste
                // aujourd'hui, et c'est sur cette colonne que l'API distingue une
                // course de transport d'une vente — s'y tromper est silencieux.
                'provenance' => Help::$LIVRAISON,
                'detail_livraison_id' => $detail->id,
                'type_livraison_id' => $demandeLivraison->type_livraison_id,
                'qte' => $qt1,
                'gestionnaire_id' => Auth::user()->id,
                'vehicule_id' => intval($camionId),
                'accepte' => 1,
                // Part de course confiée à CE camion : la quantité qu'il emporte
                // rapportée à sa capacité. Même calcul que la commande de
                // recalcul (RecalcCoutLivraison), pour que les deux ne se
                // contredisent pas. Les modes de tarification propres au livreur
                // (au kilomètre ou au forfait) priment de toute façon ; ce coût
                // global n'est qu'un repli quand rien n'est configuré.
                'cout_livraison' => $tarif['total'],
                'forfait_base'   => $tarif['forfait_base'],
                'frais_km'       => $tarif['frais_km'],
                'distance_km'    => round((float) $distance, 2),
            ];


            $l = Livraison::create($livraison);

            // Envoi au client du CODE de validation (= numéro de la livraison),
            // qu'il communiquera au livreur pour clore la course.
            //
            // Les ventes et les locations envoyaient déjà ce courriel ; les
            // demandes de livraison, non. Le client n'avait donc AUCUN moyen
            // d'obtenir son code, et l'application livreur — qui refuse la
            // validation tant que le numéro saisi ne correspond pas — laissait
            // la course ouverte indéfiniment.
            //
            // NON bloquant : un échec d'envoi ne doit pas empêcher le
            // traitement de la demande, déjà enregistré ci-dessus.
            try {
                Mail::send(new \App\Mail\receptionCodeDemandeLivraison(
                    $l,
                    $demandeLivraison,
                    $demandeLivraison->client,
                    $detail
                ));
            } catch (\Throwable $e) {
                \Log::warning('Email code validation demande de livraison non envoyé: ' . $e->getMessage());

                // ET ON LE DIT. L'envoi ne doit pas bloquer l'affectation — un
                // timeout SMTP ne peut pas annuler un camion déjà réservé — mais
                // se taire est pire : le gestionnaire croyait le client prévenu,
                // le client attendait un code qui n'arrivait pas, et le livreur
                // restait bloqué devant une validation qu'il ne pouvait pas
                // passer. Personne ne savait où regarder.
                $codesNonEnvoyes[] = $l->numero;
            }
        }

        // États écrits en toutes lettres, jamais par leur rang. Ces deux colonnes
        // sont des ENUM : MySQL interprète un entier comme la POSITION de la valeur.
        // « 2 » tombait juste par chance, et l'énumération etat_livraison vient
        // justement d'être étendue — une valeur insérée ailleurs qu'à la fin aurait
        // changé le sens de ces deux lignes sans la moindre erreur.
        $demandeLivraison->update([
            'etat_commande' => Help::$COMMANDE_EN_TRAITEMENT,
        ]);

        $detail->update([
            'etat_livraison' => Help::$LIVRAISON_EN_TRAITEMENT,
        ]);

        $camion->update([
            'disponible' => 0
        ]);
        // dd('ok');



        $retour = redirect()->route('show.traitelivraisonPage', $demandeLivraison)
            ->with('success', 'Traitement Validé');

        // Clé PROPRE, pas « error » : Flasher capte success/error/warning/info et
        // les rejoue en bulle éphémère. Un avertissement de cette portée doit
        // rester à l'écran jusqu'à ce que le gestionnaire agisse.
        if (!empty($codesNonEnvoyes)) {
            // Le code lui-même n'est plus écrit à l'écran (10/09/2026) : le client
            // le lit sur Mon compte et dans l'application, et « Renvoyer le
            // code » le lui renvoie sans que le gestionnaire ait à le voir.
            $retour->with('code_non_envoye', sprintf(
                "La course a bien été affectée, mais le code de validation n'a PAS pu être "
                . "envoyé au client (%s). Le client le trouve sur son compte et dans l'application, "
                . "ou utilisez « Renvoyer le code » ci-dessous. Sans ce code, le livreur ne pourra pas clore la course.",
                count($codesNonEnvoyes) > 1 ? 'plusieurs courses' : 'échec de l\'envoi'
            ));
        }

        return $retour;
    }

    public function demandeLivraisonTraitee(){
        // « TERMINEE » en toutes lettres : etat_commande est un ENUM, et « 3 »
        // n'y désignait la bonne valeur que par sa position.
        $demande = DemandeLivraison::where('etat_commande', Help::$COMMANDE_TERMINE)->orderByDesc('updated_at')->get();

        return view('gestionnaire.demandeDeLivraisonTraitee',[
            'livraisons' => $demande
        ]);
    }

    public function livraisonValidees(){
        return view('gestionnaire.livraisonValidees',[
            'livraisons' => Livraison::where('etat_livraison','LIVREE')->orderByDesc('updated_at')->get()
        ]);
    }

    public function restaureLivraison(Request $request, Livraison $livraison){

        $nouvelleLivraison = Livraison::create([
            'numero' => \Help::genererNumeroUnique('livraison'),
            'livreur_id' => $request->livreur,
            'client_id' => $livraison->client_id,
           // 'commande_id' => $livraison->commande_id,
            'adresse_livraison_id' => $livraison->adresse_livraison_id,
            'date_livraison' => $livraison->date_livraison,
            'detail_commande_id' => $livraison->detail_commande_id,
            'qte' => $livraison->qte,
            'type_livraison_id' => $livraison->type_livraison_id,
            'provenance' => $livraison->provenance,
            'gestionnaire_id' => Auth::user()->id,
            'accepte' => 2,
            'date_accord' => date('Y-m-d H:i:s'),
            'vehicule_id' => $request->vehicule,
            'livre_par' => $livraison->livre_par
        ]);

        $enlevement = $livraison->enlevement;

        $nouvelEnlevement = Enlevement::create([
            'fournisseur_id' => $enlevement->fournisseur_id,
            'livraison_id' => $nouvelleLivraison->id,
            'qte' => $enlevement->qte,

            'produit_id' => $enlevement->produit_id,
            'livreur_id' => $request->livreur,
            'code_enleve' => $this->generateCode(),

            'prix_fournisseur' => $enlevement->prix_fournisseur,
            'vehicule_id' => $request->vehicule,
        ]);

        $livraison->update([
            'statut' => '3'
        ]);

        $enlevement->update([
            'statut' => '3'
        ]);

        return redirect()->route('show.livraisonEnCours')->with('success','Livraison restituée');
    }

    public function livraisonEnCours(){
        $livraison = Livraison::distinct()
        ->selectRaw("livraison.*,
        users.nom_prenoms as nom_livreur,
        users.contact as contact_livreur,
        " . \App\Models\Client::sqlNomAffiche() . " as nom_client,
        client.contact1 as contact_client,
        adresse_livraison.affichage as adresse,
        adresse_livraison.complement_adresse,
        type_livraison.libelle as type_livraison,
        enlevement.code_enleve as code_enlevement,
        fournisseur.nom_prenoms as nom_fournisseur,
        fournisseur.contact1 as tel_fournisseur,
        fournisseur.adresse_geo as adresse_fournisseur
        ")
        ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
        ->join('users', 'users.id', '=', 'livreur.user_id')
        ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
        ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
        ->join('client', 'client.id', '=', 'livraison.client_id')
        ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
        ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
        ->orderBy('livraison.id', 'desc')
        ->where('livraison.statut', Help::$STATUT_ACTIF)
        ->limit(1000)
        ->get();
        return view('gestionnaire.livraisonEnCours',[
            'livreurs' => Livreur::where('statut', Help::$STATUT_ACTIF)->get(),
            'livraisons' => $livraison,
        ]);
    }
    public function livraisonHistorique(){
        // $livraison = Livraison::distinct()
        // ->selectRaw("livraison.*,
        // users.nom_prenoms as nom_livreur,
        // users.contact as contact_livreur,
        // concat(client.nom,' ',client.prenom) as nom_client,
        // client.contact1 as contact_client,
        // adresse_livraison.affichage as adresse,
        // adresse_livraison.complement_adresse,
        // type_livraison.libelle as type_livraison,
        // enlevement.code_enleve as code_enlevement,
        // fournisseur.nom_prenoms as nom_fournisseur,
        // fournisseur.contact1 as tel_fournisseur,
        // fournisseur.adresse_geo as adresse_fournisseur
        // ")
        // ->join('livreur', 'livreur.id', '=', 'livraison.livreur_id')
        // ->join('users', 'users.id', '=', 'livreur.user_id')
        // ->join('adresse_livraison', 'adresse_livraison.id', '=', 'livraison.adresse_livraison_id')
        // ->join('type_livraison', 'type_livraison.id', '=', 'livraison.type_livraison_id')
        // ->join('client', 'client.id', '=', 'livraison.client_id')
        // ->leftJoin('enlevement', 'enlevement.livraison_id', '=', 'livraison.id')
        // ->leftJoin('fournisseur', 'fournisseur.id', '=', 'enlevement.fournisseur_id')
        // ->orderBy('livraison.id', 'desc')
        // ->where('livraison.statut', Help::$STATUT_ACTIF)
        // ->limit(1000)
        // ->get();

        $livraison = Livraison::where('deleted_at', null)->get();

        return view('gestionnaire.livraisonHistorique',[
            'livreurs' => Livreur::where('statut', Help::$STATUT_ACTIF)->get(),
            'livraisons' => $livraison,
        ]);
    }

    public function retourTraite(RetourProduit $retour){
        // dd($retour);
        return view('admin.retourTraite',[
            'retour' => $retour
        ]);
    }

    public function termesConditions()
    {
        return view('legal.termes-conditions', [
            'produits'   => \App\Models\Produit::where('statut', \Help::$STATUT_ACTIF)->get(),
            'categories' => \App\Models\Categorie::all(),
            'config'     => Configuration::first(),
        ]);
    }

    public function centreAide()
    {
        $user = Auth::user();
        $typeLabel = match((int) $user->type_user_id) {
            \Help::$USER_SA            => 'Super administrateur',
            \Help::$USER_ADMIN         => 'Administrateur',
            \Help::$USER_GESTIONNAIRE  => 'Gestionnaire',
            \Help::$USER_CLIENT        => 'Client',
            \Help::$USER_FOURNISSEUR   => 'Fournisseur',
            \Help::$USER_APPORTEUR     => 'Apporteur d\'affaire',
            \Help::$USER_AGENT_SAV     => 'Agent SAV',
            \Help::$USER_LIVREUR       => 'Livreur',
            default                    => 'Utilisateur',
        };

        return view('aide.index', [
            'user'      => $user,
            'typeLabel' => $typeLabel,
        ]);
    }

    public function monProfil()
    {
        $user = Auth::user();
        $typeLabel = match((int) $user->type_user_id) {
            \Help::$USER_SA            => 'Super administrateur',
            \Help::$USER_ADMIN         => 'Administrateur',
            \Help::$USER_GESTIONNAIRE  => 'Gestionnaire',
            \Help::$USER_CLIENT        => 'Client',
            \Help::$USER_FOURNISSEUR   => 'Fournisseur',
            \Help::$USER_APPORTEUR     => 'Apporteur d\'affaire',
            \Help::$USER_AGENT_SAV     => 'Agent SAV',
            \Help::$USER_LIVREUR       => 'Livreur',
            default                    => 'Utilisateur',
        };

        return view('profil.index', [
            'user'       => $user,
            'typeLabel'  => $typeLabel,
        ]);
    }

    public function monProfilUpdate(Request $request)
    {
        $user = Auth::user();

        // Bornes alignées sur ce que la base accepte réellement.
        //
        // « contact » autorisait 30 caractères pour une colonne qui n'en tenait
        // que 15 : un numéro avec indicatif et espaces — « +225 07 12 34 56 78 »,
        // 19 caractères — passait toutes les vérifications avant d'être refusé
        // par la base, en pleine écriture. Résultat : page blanche et erreur 500,
        // sans le moindre message. La colonne est passée à 20 par migration.
        $request->validate([
            'nom_prenoms' => 'required|string|max:150',
            'email'       => 'required|email|max:150',
            'contact'     => 'nullable|string|max:20',
            'adresse'     => 'nullable|string|max:200',
            'photo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'contact.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
        ]);

        // Unicité email
        $emailDejaPris = User::where('email', $request->email)
            ->where('id', '!=', $user->id)->exists();
        if ($emailDejaPris) {
            return back()->withInput()->with('emailExiste', 'Cet email est déjà utilisé par un autre compte.');
        }

        // Login non modifiable côté utilisateur (verrouillé par design).
        // Si un changement est nécessaire, il doit passer par un administrateur.

        // Upload de la photo — même destination que registerAdmin/storeUser :
        // directement dans public/storage/imageUser/ (accessible via /storage/imageUser/)
        // pour fonctionner sans dépendance au symlink Laravel.
        if ($request->hasFile('photo')) {
            $destinationDir = public_path('storage/imageUser');
            if (!is_dir($destinationDir)) {
                @mkdir($destinationDir, 0775, true);
            }

            // Supprime l'ancienne si elle existe
            if ($user->photo) {
                $oldPath = str_contains($user->photo, '/')
                    ? public_path('storage/' . $user->photo)
                    : $destinationDir . '/' . $user->photo;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            $ext = $request->file('photo')->getClientOriginalExtension();
            $nomImage = 'image_user_' . $user->id . '_' . date('YmdHis') . '.' . $ext;
            $request->file('photo')->move($destinationDir, $nomImage);
            $user->photo = $nomImage;  // juste le nom de fichier (résolu via imageUser/ côté affichage)
        }

        // Champs simples
        $user->nom_prenoms = $request->nom_prenoms;
        $user->email       = $request->email;
        // Numéro normalisé — espaces retirés — comme le font déjà les
        // formulaires d'inscription. « 07 12 34 56 78 », que l'exemple du champ
        // invite pourtant à saisir, occupe 14 caractères pour 10 chiffres.
        // La colonne n'accepte pas de valeur nulle : on y met une chaîne vide
        // si le champ est laissé libre, jamais null.
        $user->contact     = preg_replace('/\s+/', '', (string) $request->contact);
        $user->adresse     = $request->adresse;

        // Mot de passe (optionnel)
        if ($request->filled('oldPassWord') || $request->filled('newPassWord') || $request->filled('confirmPassWord')) {
            if (!$request->filled('oldPassWord')) {
                return back()->withInput()->with('errorPassword', 'Veuillez saisir votre ancien mot de passe.');
            }
            if (!\Help::HashVerifier($request->oldPassWord, $user->password)) {
                return back()->withInput()->with('errorPassword', 'Ancien mot de passe incorrect.');
            }
            if (!$request->filled('newPassWord')) {
                return back()->withInput()->with('passDifferent', 'Veuillez saisir un nouveau mot de passe.');
            }
            if ($request->newPassWord !== $request->confirmPassWord) {
                return back()->withInput()->with('passDifferent', 'Les deux mots de passe ne correspondent pas.');
            }
            $user->password = \Help::HashPassword($request->newPassWord);
        }

        $user->save();

        return redirect()->route('show.monProfil')->with('success', 'Profil mis à jour avec succès.');
    }

    public function demandeDepaiePage(){
        $authUser = Auth::user();
        $profilLabel = 'Utilisateur';

        switch($authUser->type_user_id){
            case 5:
                $user = Fournisseur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Fournisseur';
                break;
            case 6:
                $user = Apporteur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Apporteur';
                break;
            case 8:
                $user = Livreur::where('user_id', $authUser->id)->first();
                $profilLabel = 'Livreur';
                break;
        }

        $mouvements = $this->mouvementsDuTiers($authUser, $user);

        $recus   = $mouvements->where('statut', 1);
        $attente = $mouvements->where('statut', 0);

        return view('livreur.demandeDePaie', [
            'user'              => $user,
            'profilLabel'       => $profilLabel,
            'modesPaie'         => ModePaiement::liste(),
            'mouvements'        => $mouvements,
            'totalDemandes'     => $mouvements->count(),
            'totalEnAttente'    => $attente->count(),
            'totalPayees'       => $recus->count(),
            'montantEnAttente'  => (float) $attente->sum('montant'),
            'montantRecu'       => (float) $recus->sum('montant'),
        ]);
    }

    /**
     * Tout ce qu'un tiers a touché, quelle qu'en soit l'origine.
     *
     * L'entreprise le paie par DEUX chemins : la demande qu'il initie
     * lui-même, et le règlement qu'un administrateur saisit sur une de ses
     * pièces — un bon pour le fournisseur, une course pour le livreur, une
     * commission pour l'apporteur.
     *
     * Cet écran ne montrait que le premier. Le tiers ne voyait donc nulle part
     * les sommes versées à l'initiative de l'entreprise, et son historique ne
     * retombait pas sur ce qu'il avait réellement reçu.
     *
     * Les règlements issus d'une demande sont EXCLUS : ils portent
     * `demande_paiement_id` et sont déjà listés sous leur demande. Sans cette
     * exclusion, le même versement apparaîtrait deux fois.
     */
    private function mouvementsDuTiers($authUser, $tier)
    {
        $demandes = DemandePaiement::where('user_id', $authUser->id)
            ->with('modePaiement')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DemandePaiement $d) => (object) [
                'reference'     => $d->numero ?: ('#' . $d->id),
                'date'          => $d->created_at,
                'montant'       => (float) $d->montant,
                // 1 = acceptée, 2 = refusée, NULL/0 = en attente.
                'statut'        => (int) ($d->paye ?? 0),
                'mode'          => $d->modePaiement?->libelle,
                'origine'       => 'Vous',
                'detail'        => 'Demande de paiement',
            ]);

        // La table des règlements dépend du profil, et la colonne de liaison
        // vient d'une migration : sans elle, l'écran du tiers tomberait en
        // erreur. On s'en passe plutôt que de le priver de sa page.
        $config = match ((int) $authUser->type_user_id) {
            5 => ['table' => 'paiement_fournisseur', 'modele' => \App\Models\PaiementFournisseur::class,
                  'cle' => 'fournisseur_id', 'lien' => 'enlevement', 'libelle' => 'Bon'],
            6 => ['table' => 'paiement_apporteur',   'modele' => \App\Models\PaiementApporteur::class,
                  'cle' => 'apporteur_id',   'lien' => 'commission',  'libelle' => 'Commission'],
            8 => ['table' => 'paiement_livreur',     'modele' => \App\Models\PaiementLivreur::class,
                  'cle' => 'livreur_id',     'lien' => 'livraison',   'libelle' => 'Course'],
            default => null,
        };

        $reglements = collect();

        if ($config && $tier && isset($tier->id)) {
            $reglements = $config['modele']::with(['modePaiement', $config['lien']])
                ->where($config['cle'], $tier->id)
                ->where('statut', 1)
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn($config['table'], 'demande_paiement_id'),
                    fn ($q) => $q->whereNull('demande_paiement_id')
                )
                ->orderByDesc('date_paiement')
                ->get()
                ->map(function ($p) use ($config) {
                    $piece = $p->{$config['lien']};

                    $numero = $piece?->code_enleve
                        ?? $piece?->numero
                        ?? ($piece ? '#' . $piece->id : null);

                    return (object) [
                        'reference' => $p->reference ?: ('#' . $p->id),
                        'date'      => $p->date_paiement ?? $p->created_at,
                        'montant'   => (float) $p->montant,
                        // Un règlement enregistré est un versement fait.
                        'statut'    => 1,
                        'mode'      => $p->modePaiement?->libelle,
                        'origine'   => "L'entreprise",
                        'detail'    => $numero
                            ? $config['libelle'] . ' ' . $numero
                            : $config['libelle'],
                    ];
                });
        }

        return $demandes->concat($reglements)
            ->sortByDesc(fn ($m) => $m->date)
            ->values();
    }

    public function demandeDepaie(Request $request){
        // dd($request->all());

        // die;
        // return;

        $montant = $request->montant;


        $user = Auth::user();

        if($request->montant == 0){
            return redirect()->route('show.demandeDepaiePage')->with('error','0fcfa n\'est pas autorisé comme montant');
        }

        // Le tiers concerné, selon son profil. Un solde insuffisant arrête tout
        // avant la moindre écriture.
        $tier = match ((int) $user->type_user_id) {
            8 => Livreur::where('user_id', $user->id)->first(),
            6 => Apporteur::where('user_id', $user->id)->first(),
            5 => Fournisseur::where('user_id', $user->id)->first(),
            default => null,
        };

        if (!$tier) {
            return redirect()->back()->with('error', "Votre profil ne permet pas de demander un paiement.");
        }

        if ($montant > (float) $tier->solde) {
            return redirect()->back()->with('error', 'Veuillez entrer un montant inférieur ou égale à votre solde');
        }

        // LE DÉBIT ET LA DEMANDE, OU NI L'UN NI L'AUTRE.
        //
        // Le solde était débité d'abord, la demande créée ensuite. Si la
        // création échouait — une colonne absente en base a suffi — le tiers
        // repartait avec un solde amputé et aucune demande en face : l'argent
        // disparaissait de son tableau de bord sans que personne ne puisse le
        // lui verser, et rien ne le signalait.
        try {
            DB::transaction(function () use ($tier, $montant, $request, $user) {
                $tier->update([
                    'solde' => (float) $tier->solde - $montant,
                ]);

                DemandePaiement::create([
                    'numero'           => Help::getCommandeNo(),
                    'montant'          => $montant,
                    'user_id'          => $user->id,
                    'numero_compte'    => $request->numero,
                    'mode_paiement_id' => $request->modePaie,
                    // Le solde vient d'être débité ci-dessus (réservation) : la 2e
                    // validation ne doit PAS re-débiter (cf. valideDemande).
                    'solde_debite_initiation' => 1,
                ]);
            });
        } catch (\Throwable $e) {
            // La transaction a été annulée : le solde est intact. On le dit,
            // plutôt que de renvoyer une page blanche.
            \Illuminate\Support\Facades\Log::error('Demande de paiement impossible', [
                'user_id' => $user->id,
                'montant' => $montant,
                'erreur'  => $e->getMessage(),
            ]);

            return redirect()->back()->with('error',
                "Votre demande n'a pas pu être enregistrée. Votre solde n'a pas été modifié. "
                . "Signalez-le à l'administrateur.");
        }

        return redirect()->back()->with('success','Votre demande a été envoyée');

    }



    public function retourValide(RetourProduit $retour, Request $request){

        // dd('validé');

        $data = [
            'user_id' => Auth::user()->id,
            'user_paie_id' => Auth::user()->id,
            'date_reception' => date('Y-m-d H:i:s'),
            'observation_reception' => $request->observation,
            'date_rembourssement' => date('Y-m-d H:i:s '),
            'statut' => 2
        ];


        $retour->update($data);

        return redirect()->route('show.listeRetourProduit')->with('ok','Retour approuvé');


    }

    public function refuseRetour(RetourProduit $retour, Request $request){

        // dd('réfusé');

        $data = [
            'user_id' => Auth::user()->id,
            'user_paie_id' => Auth::user()->id,
            'date_reception' => date('Y-m-d H:i:s'),
            'observation_reception' => $request->observation,
            'date_rembourssement' => date('Y-m-d H:i:s '),
            'statut' => 3
        ];


        $retour->update($data);

        return redirect()->route('show.listeRetourProduit')->with('no','Retour réfusé');

    }

    public function notify(Request $request){

        // dd($request->loca);
        // dd(Help::typeCompte());
        // \Mckenziearts\Notify\Facades\LaravelNotify::error('Votre message');


        notify()->success('Welcome to Laravel Notify ⚡️');
        // dd(session('notify_messages'));
        // drakify('success', 'Enregistrement réussi');
        // smilify('success', 'Enregistrement réussi');
        // emotify('success', 'Enregistrement réussi');
        // emotify('success', 'Enregistrement réussi');
        return view('notify');
    }

    public function listeGestionnaire(){

        return view('admin.listeGestionnaire',[
            'gestionnaires' => User::with('agence')->where('type_user_id', 3)->whereNull('deleted_at')->orderByDesc('created_at')->get(),
            'agences'       => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);

    }

    /**
     * Affecte — ou réaffecte — un utilisateur à une agence.
     *
     * L'agence d'un encaissement était choisie dans une liste au moment de la
     * saisie : un caissier pouvait imputer sa recette à un autre guichet que le
     * sien. Elle est désormais une propriété de la personne, décidée ici par
     * l'administrateur, et reprise automatiquement à chaque opération.
     *
     * Réservé aux profils qui encaissent — gestionnaires, agents,
     * administrateurs. Rattacher un client ou un livreur à un guichet n'aurait
     * aucun sens et ouvrirait une porte inutile.
     */
    public function affecterAgence(Request $request, $userId)
    {
        $utilisateur = User::whereNull('deleted_at')->find($userId);

        if (!$utilisateur) {
            return back()->with('error', "Utilisateur introuvable.");
        }

        $profilsAutorises = [
            \Help::$USER_SA, \Help::$USER_ADMIN,
            \Help::$USER_GESTIONNAIRE, \Help::$USER_AGENT_SAV,
        ];

        if (!in_array((int) $utilisateur->type_user_id, $profilsAutorises, true)) {
            return back()->with('error', "Seuls les gestionnaires, agents et administrateurs sont rattachés à une agence.");
        }

        $donnees = $request->validate([
            // Vide = retirer le rattachement, ce qui interdit à nouveau
            // l'encaissement à cette personne.
            'agence_id' => 'nullable|integer|exists:agence,id',
        ], [
            'agence_id.exists' => "Cette agence n'existe pas.",
        ]);

        $utilisateur->agence_id = $donnees['agence_id'] ?: null;
        $utilisateur->save();

        $nomAgence = $utilisateur->agence_id
            ? (\App\Models\Agence::find($utilisateur->agence_id)?->nom ?? '')
            : null;

        return back()->with('success', $nomAgence
            ? "{$utilisateur->nom_prenoms} est rattaché à l'agence {$nomAgence}."
            : "{$utilisateur->nom_prenoms} n'est plus rattaché à aucune agence : il ne peut plus encaisser.");
    }

    /**
     * Liste des comptes administrateur (type_user_id = 2).
     */
    public function listeAdmin(){
        return view('admin.listeAdmin', [
            'admins' => User::with('agence')
                ->where('type_user_id', Help::$USER_ADMIN)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->get(),
            // Les administrateurs se rattachent à une agence comme les autres :
            // sans cela, aucun d'eux ne pourrait encaisser, et la toute
            // première affectation serait impossible à faire.
            'agences' => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);
    }

    /**
     * Activer/Désactiver un compte admin.
     * Garde-fou : interdit sur soi-même + interdit si dernier admin actif.
     */
    public function toggleAdminStatus($id)
    {
        $user = User::where('type_user_id', Help::$USER_ADMIN)
            ->whereNull('deleted_at')
            ->find($id);

        if (!$user) {
            return back()->with('error', 'Administrateur introuvable.');
        }

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }

        // Si on tente de désactiver et qu'il ne reste que cet admin actif
        if ((int) $user->statut === 1) {
            $nbActifs = User::where('type_user_id', Help::$USER_ADMIN)
                ->where('statut', 1)
                ->whereNull('deleted_at')
                ->count();
            if ($nbActifs <= 1) {
                return back()->with('error', 'Impossible de désactiver le dernier administrateur actif.');
            }
        }

        $nouveauStatut = ((int) $user->statut === 1) ? 0 : 1;
        $user->statut = $nouveauStatut;
        $user->save();

        $action = $nouveauStatut === 1 ? 'réactivé' : 'désactivé';
        return back()->with('ok', "Compte de {$user->nom_prenoms} {$action}.");
    }

    /**
     * Supprime (soft delete) un compte admin.
     * Garde-fou : interdit sur soi-même + interdit si dernier admin actif.
     */
    public function deleteAdmin($id)
    {
        $user = User::where('type_user_id', Help::$USER_ADMIN)
            ->whereNull('deleted_at')
            ->find($id);

        if (!$user) {
            return back()->with('error', 'Administrateur introuvable.');
        }

        if ((int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $nbActifs = User::where('type_user_id', Help::$USER_ADMIN)
            ->where('statut', 1)
            ->whereNull('deleted_at')
            ->where('id', '!=', $user->id)
            ->count();
        if ($nbActifs < 1) {
            return back()->with('error', 'Impossible de supprimer le dernier administrateur actif.');
        }

        $nom = $user->nom_prenoms;
        $user->delete(); // Soft delete

        return back()->with('ok', "Compte de {$nom} supprimé.");
    }

    public function listeAgent(){
        return view('gestionnaire.listeAgent',[
            'agents'  => User::with('agence')->where('type_user_id', 7)->whereNull('deleted_at')->orderByDesc('created_at')->get(),
            'agences' => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);
    }

    public function sellersList(){
        // $this->verificationStock();


        return view('fournisseur.sellers-list',[

            'fournisseurs' => Fournisseur::all(),
        ]);
    }

    public function sellersListPourBon(){

        return view('fournisseur.fournisseurPourbon',[

            'fournisseurs' => Fournisseur::all(),
        ]);
    }
      // Modification des informations d'un fournisseur
    public function editSellers($id){
        // $this->verificationStock();
        $fournisseur = Fournisseur::find($id);
        $data = Produit::all();
        return view('fournisseur.edit-sellers',[
            'fournisseur' => $fournisseur,
            'produits' => $data,
        ]);
    }

    public function updateSeller(Request $request){
        // dd($request->produits);
        // $this->verificationStock();



        $fournisseur = Fournisseur::where('id',$request->id)->first();

        $produitsSelectionnes = $request->produits ?? [];

        // IDs déjà rattachés : on préserve leur pivot (prix/qte/seuil déjà configurés).
        $dejaRattaches = $fournisseur->produits->pluck('id')->all();

        $syncData = [];
        foreach ($produitsSelectionnes as $produitId) {
            if (in_array($produitId, $dejaRattaches)) {
                // Produit déjà présent : pivot vide => sync ne modifie pas les valeurs existantes.
                $syncData[$produitId] = [];
            } else {
                $produitModel = Produit::find($produitId);
                if (!$produitModel) {
                    continue;
                }
                // Nouveau produit : on renseigne le pivot (sinon prix/qte restent vides).
                $syncData[$produitId] = [
                    'prix'        => $produitModel->prix_fournisseur ?? $produitModel->prix_moyen ?? 0,
                    'qte'         => 0,
                    'seuil_alert' => 10,
                    'statut'      => Help::$STATUT_ACTIF,
                ];
            }
        }

        // sync() détache les produits décochés (comportement attendu du formulaire à cases).
        $fournisseur->produits()->sync($syncData);

        // Type de fournisseur + produit principal (colonnes affichées dans la liste).
        $fournisseur->type_fournisseur  = $request->type_fournisseur;
        $fournisseur->produit_principal = $request->produit_principal;
        // Régime de TVA : commande le montant reversé au fournisseur (Enlevement::montantDu).
        $fournisseur->assujetti_tva     = $request->boolean('assujetti_tva');
        $fournisseur->save();

        $user = User::where('id',$fournisseur->user_id)->first();


        return redirect()->route('show.editSellers',$fournisseur->id)->with('success','Modification effectuée');

    }

    public function registerSeller(){

        $fournisseur = new Fournisseur();
        $user = new User;
        $data = Produit::all();


        return view('fournisseur.register',[
            'produits' => $data,
            'fournisseur' => $fournisseur,
            'user' => $user,
            'mode' => 'ajout',
        ]);
    }
     // Enregistrement d'un fournisseurs
    public function store(Request $request){
        // dd($request->all());
        $contact = "";

        $request->validate([
            //
            'nom_prenoms'=>'required',
            'email'=> 'required|email|unique:users,email',
            'contact'=> 'required',
            'contact2' => 'nullable' ,
            'long' => 'required' ,
            'lat' => 'required' ,
            'adresse_geo' => 'required',
            'adresse_postale'=>'required',
            'produits' => 'required',
            'type_fournisseur' => 'nullable|string|max:30',
            'produit_principal' => 'nullable|string|max:100',
            'dfe' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'registre_commerce' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ],[
                'nom_prenoms.required' => 'Le nom et prénoms est obligatoire',
                'email.required' => 'L\'email est obligatoire',
                'email.email' => 'L\'email doit être valide',
                'email.unique' => 'L\'email existe déjà.',
                'contact.required' => 'Le contact est obligatoire',
                'long.required' => 'Veuillez selectionner un point sur la carte',
                'lat.required' => 'La latitude est obligatoire',
                'adresse_geo.required' => 'L\'adresse géographique est obligatoire',
                'adresse_postale.required' => 'L\'adresse postale est obligatoire',
                'produits.required' => 'Selectionnez au moins un produit',
            ]);

        $typeUserId = 5;


        $slug = SlugService::createSlug(User::class, 'login', $request->nom_prenoms);
        $user2 = User::withTrashed()->where('login', $slug)->value('login');


        if ($slug == $user2) {
            # code...
            $slug = $slug.''.rand(0,100);

        }




        // Mot de passe TOUJOURS généré automatiquement (jamais saisi par l'admin),
        // envoyé au fournisseur par email via MailAccesUsers plus bas.
        $rawPassword = Help::ChaineAleatoire(8);
        $pwd = Help::HashPassword($rawPassword);

        //Enregistrement dans la table user
        $dataUser = [
            'login' => $slug,
            'password' => $pwd,
            'email' => $request->email,
            'type_user_id' => $typeUserId,
            'nom_prenoms' => $request->nom_prenoms,
            'contact' => $contact,
        ];

        $user = User::create($dataUser);
        $userId = $user->id;
        //Enregistrement dans la table Fournisseur
        $dataFrs = [
            'nom_prenoms' => $request->nom_prenoms,
            'contact1' => $request->contact,
            'contact2' => $request->contact2,
            'adresse_geo' => $request->adresse_geo,
            'adresse_postale' => $request->adresse_postale,
            'longitude' => $request->long,
            'latitude' => $request->lat,
            'user_id' => $userId,
            'email' => $request->email,
            'type_fournisseur' => $request->type_fournisseur,
            'produit_principal' => $request->produit_principal,
        ];

        //dd($dataFrs);

        $frs = Fournisseur::create($dataFrs);
        $frsId = $frs->id;
        $frs = Fournisseur::find($frsId);

        // Enregistrement des documents (DFE + registre du commerce)
        foreach (['dfe', 'registre_commerce'] as $docField) {
            if ($request->hasFile($docField)) {
                $ext  = $request->file($docField)->getClientOriginalExtension();
                $path = $request->file($docField)->storeAs("documents_entreprise/fournisseur_{$frsId}", "{$docField}.{$ext}", 'public');
                $frs->{$docField} = $path;
            }
        }
        $frs->save();

        //Affectation des produits au fournisseur
        $produits = $request->produits ?? [];

        foreach ($produits as $produitId) {
            $produitModel = Produit::find($produitId);
            if (!$produitModel) {
                continue;
            }

            // On renseigne les données du pivot stock_produit (sinon prix/qte/statut
            // restent vides et le produit n'apparaît pas correctement au catalogue).
            // syncWithoutDetaching évite de créer un doublon si le produit est déjà rattaché.
            $frs->produits()->syncWithoutDetaching([
                $produitId => [
                    'prix'        => $produitModel->prix_fournisseur ?? $produitModel->prix_moyen ?? 0,
                    'qte'         => 0,
                    'seuil_alert' => 10,
                    'statut'      => Help::$STATUT_ACTIF,
                ],
            ]);
        }

        $produitList = Produit::all();

        $fournisseur = new Fournisseur;

        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'sellers'));
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email fournisseur: ' . $e->getMessage());
        }

        session()->put(['login' => $slug]);
        return redirect()->route('show.registerSeller')->with('success','Compte fournisseur créé avec succès ! Un email avec vos accès a été envoyé.');
        // return view('fournisseur.register',[
        //     'produits' => ,
        //     'fournisseur' =>
        // ])
    }

    public function bonParFournisseur(Fournisseur $fournisseur){
        // dd('rd');
        // $query = Enlevement::where('fournisseur_id',$fournisseur->id);
        $enlevements = Enlevement::where('fournisseur_id',$fournisseur->id)->orderBy('created_at','desc')->get();
        $enlevementTraite = Enlevement::where('fournisseur_id',$fournisseur->id)->where('qte_servi','!=',null)->get();

        $montantTotal = 0;
        foreach($enlevementTraite as $enlevement){

            // Un seul enlèvement sans livraison (ou sans ligne de commande) faisait
            // tomber toute la page « Enlèvements par fournisseur » en erreur 500.
            $montantTotal += $enlevement->qte_servi * ($enlevement->livraison?->detailCommande?->prix ?? 0);
        }

        // dd($montantTotal);
        return view('fournisseur.enlevementParFournisseur',[
            'enlevements' => $enlevements,
            'montantTotal' => $montantTotal,
            'fournisseur' => $fournisseur
        ]);
    }

    public function listeCommissionApporteur(Request $request){
        $apporteur = $request->apporteur ?? null;
        $type_affaire = $request->type_affaire ?? null;
        $apporteurs = Apporteur::liste();
        $coms = CommissionApporteur::liste(null, $apporteur, $type_affaire);
        return view('apporteur.commission',compact("coms", "apporteurs"));
    }

    public function pourcentage(Apporteur $apporteur, Request $request){
        // dd($request->all());

        if($request->pourcentage > 0 && $request->pourcentage <= 100){
            $apporteur->update([
                'pourcentage' => $request->pourcentage
            ]);
            return redirect()->route('show.listApporteur')->with('success','Le pourcentage a été mis à jour');
        }else{
            return redirect()->back()->with('error','Le pourcentage doit être entre 0 et 100');
        }
    }

    //etats de demande de paiement livreurs
    public function listeDeDemande(){

            //demande de paiement des livreurs
            $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 8)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

            //dd($demandes);


        return view('admin.listeDeDemande',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function listApporteur(){
        $apporteur = Apporteur::with(['user', 'modePaiement'])->orderByDesc('created_at')->get();

        return view('apporteur.list',[
            'apporteurs' => $apporteur
        ]);
    }

    public function listLivreur()
    {
        return view('livreur.list', [
            'livreurs' => Livreur::with('user')->where('deleted_at', null)->orderBy('created_at', 'desc')->get()
        ]);
    }
    public function registerLivreur()
    {
        return view('livreur.register');
    }

     public function storeLivreur(LivreurRequest $request)
    {

        // return view('livreur.store');

        // recuperation du type user
        $typeUserId = TypeUser::where('nom', 'like', '%livreur%')->value('id');

        $slug = SlugService::createSlug(User::class, 'login', $request->nom_prenoms); //Creation de login à partir de la methode SLUGGABLE

        $user2 = User::withTrashed()->where('login', $slug)->value('login');


        if ($slug == $user2) {
            # code...
            $slug = $slug.''.rand(0,100);

        }

        $email = User::where('email', $request->email)->first();
        // dd($email);

        if($email){
            return back()->with('errorEmail', "cet email est déjà utilisé")->withInput();
        }

        // Mot de passe GÉNÉRÉ automatiquement (l'admin ne doit pas le connaître) :
        // il est envoyé au livreur par email via MailAccesUsers ci-dessous.
        $rawPassword = Help::ChaineAleatoire(8);

        // Enregistrement de la table user
        $dataUser = [
            'nom_prenoms' => $request->nom_prenoms,
            'login' => $slug,
            'password' => Help::HashPassword($rawPassword),
            'email' => $request->email,
            'type_user_id' => $typeUserId,
            'contact' => $request->contact,
            'adresse' => $request->adresse
        ];
        $user = User::create($dataUser);
        $userId = $user->id;
        // Enregistremant du livreur
        $photo1 = $request->piece_recto;
        $photo1_path = $photo1->store('imageLivreur', 'public');

        $photo2 = $request->piece_verso;
        $photo2_path = $photo2->store('imagelivreur', 'public');

        $data = [
            'num_piece_identite' => $request->num_piece_identite,
            'piece_recto' => $photo1_path,
            'piece_verso' => $photo2_path,
            'user_id' => $userId,
            // Forfait de base : valeur saisie, sinon le forfait PAR DÉFAUT défini dans
            // /parametre (onglet Livreurs). Modifiable ensuite sur le profil du livreur.
            'cout_livraison' => $request->cout_livraison ?: (Configuration::first()->forfait_base_livreur ?? 0),
            'mode_tarification' => 'base',
            'zone_intervention' => $request->zone_intervention,
        ];

        $livreur = Livreur::create($data);

        // Envoi NON bloquant des identifiants générés (un échec d'email
        // ne doit pas empêcher la création du compte).
        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'livreur'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès livreur: ' . $e->getMessage());
        }

        session()->put(['login' => $user->login]);
        return back()->with('success', 'Livreur enregistré. Ses identifiants de connexion lui ont été envoyés par email.');
    }

    public function livreurPiece(Livreur $livreur, string $type, string $mode = 'inline')
    {
        $path = $type === 'recto' ? $livreur->piece_recto : $livreur->piece_verso;
        $label = $type === 'recto' ? 'Pièce CNI Recto' : 'Pièce CNI Verso';
        if (!$path || $path === 'image.png') {
            return redirect()->route('show.list')->with('error', "Aucun fichier $label n'est associé à ce livreur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.list')->with('error', "Le fichier $label de ce livreur est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($livreur->user?->nom_prenoms ?: ('livreur-'.$livreur->id)));
        $downloadName = ($type === 'recto' ? 'CNI-Recto' : 'CNI-Verso') . '-' . $nom . ($ext ? '.' . $ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function apporteurPiece(Apporteur $apporteur, string $type, string $mode = 'inline')
    {
        $path = $type === 'recto' ? $apporteur->piece_recto : $apporteur->piece_verso;
        $label = $type === 'recto' ? 'Pièce Recto' : 'Pièce Verso';
        if (!$path || $path === 'image.png') {
            return back()->with('error', "Aucun fichier $label n'est associé à cet apporteur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return back()->with('error', "Le fichier $label de cet apporteur est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($apporteur->user?->nom_prenoms ?: ('apporteur-'.$apporteur->id)));
        $downloadName = ($type === 'recto' ? 'Piece-Recto' : 'Piece-Verso') . '-' . $nom . ($ext ? '.' . $ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function fournisseurDocument(Fournisseur $fournisseur, string $type, string $mode = 'inline')
    {
        $path = $type === 'dfe' ? $fournisseur->dfe : $fournisseur->registre_commerce;
        $label = $type === 'dfe' ? 'DFE' : 'Registre de commerce';
        if (!$path) {
            return back()->with('error', "Aucun fichier $label n'est associé à ce fournisseur.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return back()->with('error', "Le fichier $label de ce fournisseur est introuvable sur le serveur (chemin enregistré: $path).");
        }
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($fournisseur->nom_prenoms ?: ('fournisseur-'.$fournisseur->id)));
        $downloadName = ($type === 'dfe' ? 'DFE' : 'Registre-Commerce') . '-' . $nom . ($ext ? '.' . $ext : '');
        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition . '; filename="' . $downloadName . '"',
        ]);
    }

    public function profileLivreur($id)
    {

        $livreur = Livreur::find($id);
        $livraisons = Livraison::where('livreur_id', $id)->get();

        $historiquesPrix = $livreur
            ? $livreur->historiquesPrix()->with('user')->limit(50)->get()
            : collect();

        return view('livreur.profile', [
            'livraisons' => $livraisons,
            'livreur' => $livreur,
            'historiquesPrix' => $historiquesPrix,
            'typesVehicule' => \App\Models\TypeVehicule::all(),
        ]);
    }

    /**
     * Ajout d'un véhicule à un livreur par l'admin / le gestionnaire (point 8).
     */
    public function ajoutVehiculeLivreur(Livreur $livreur, Request $request)
    {
        $request->validate([
            'matricule'     => 'required|string|max:15',
            'nom'           => 'required|string|max:100',
            'type_vehicule' => 'required|integer|exists:type_vehicule,id',
            'marque'        => 'nullable|string|max:100',
            'modele'        => 'nullable|string|max:100',
            'capacite'      => 'required|numeric|min:0.1',
        ]);

        if (\App\Models\Vehicule::where('immatriculation', $request->matricule)->first()) {
            return back()->with('error', 'Cette immatriculation est déjà utilisée.');
        }

        $vehicule = new \App\Models\Vehicule;
        $vehicule->immatriculation = $request->matricule;
        $vehicule->nom = $request->nom;
        $vehicule->marque = $request->marque;
        $vehicule->modele = $request->modele;
        $vehicule->type_vehicule_id = $request->type_vehicule;
        $vehicule->capacite = intval($request->capacite);
        $vehicule->livreur_id = $livreur->id;
        $vehicule->save();

        return redirect()->route('show.profile', $livreur->id)->with('success', 'Véhicule ajouté au livreur.');
    }

    /**
     * Validation d'une demande de paiement (double validation — point 16).
     *
     * Règles :
     *  - Tout administrateur peut valider (pas uniquement les gestionnaires
     *    figés gestionnaire1_id / gestionnaire2_id de la configuration).
     *  - Le 2e validateur ne peut JAMAIS être le même utilisateur que le 1er.
     *  - L'effet métier (paye=true ; décrémentation/restitution du solde) n'est
     *    appliqué qu'au moment de la 2e validation.
     *  - Une demande déjà finalisée (paye != 0/false ET user_valide2_id présent)
     *    n'est plus modifiable.
     *
     * Routes type/reponse :
     *  - $type   : 'livreur' | 'apporteur' | 'fournisseur'
     *  - $reponse: 'accepter' | 'refuser'
     */
    public function valideDemande($id, $type, $reponse){
        $demande = DemandePaiement::find($id);
        if (!$demande) {
            return back()->with('error', 'Demande introuvable.');
        }

        $routeRetour = match ($type) {
            'livreur'     => 'show.listeDeDemandeLivreur',
            'apporteur'   => 'show.listeDeDemandeApporteur',
            'fournisseur' => 'show.listeDeDemandeFournisseur',
            default       => 'show.listeDeDemandeLivreur',
        };

        // Les demandes des fournisseurs se valident aussi depuis le journal des
        // paiements : on y renvoie l'administrateur qui en vient, plutôt que de
        // le déposer sur un autre écran sans qu'il l'ait demandé.
        //
        // Liste fermée : une route reçue en paramètre et suivie telle quelle
        // ouvrirait une redirection vers n'importe où.
        $retoursAutorises = [
            'show.fournisseurs.paiements',
            'show.livreurs.paiements',
            'show.apporteurs.paiements',
        ];

        if (in_array(request('retour'), $retoursAutorises, true)) {
            $routeRetour = request('retour');
        }

        // Demande déjà finalisée : on ne fait rien.
        if ($demande->user_valide_id && $demande->user_valide2_id) {
            return redirect()->route($routeRetour)->with('error', 'Cette demande a déjà été finalisée.');
        }

        // 1re validation
        if (is_null($demande->user_valide_id)) {
            $demande->update([
                'user_valide_id' => Auth::id(),
            ]);
            return redirect()->route($routeRetour)->with('success',
                '1re validation enregistrée. En attente de la 2e validation par un autre administrateur.');
        }

        // 2e validation : bloquer auto-validation
        if ((int) $demande->user_valide_id === (int) Auth::id()) {
            return redirect()->route($routeRetour)->with('error',
                'Vous ne pouvez pas effectuer la 2e validation : vous êtes déjà le 1er validateur.');
        }

        // À ce stade : demande avec 1re validation OK et utilisateur courant ≠ 1er validateur.
        // On applique l'effet métier (accepter/refuser) et on enregistre la 2e validation.
        $accepter = ($reponse === 'accepter');
        $payeFlag = $accepter ? 1 : 2; // 1 = accepté/payé, 2 = refusé (convention historique)
        $rep = $accepter ? 'acceptée et payée' : 'refusée';

        DB::beginTransaction();
        try {
            $tier = match ($type) {
                'livreur'     => Livreur::where('user_id', $demande->user_id)->first(),
                'apporteur'   => Apporteur::where('user_id', $demande->user_id)->first(),
                'fournisseur' => Fournisseur::where('user_id', $demande->user_id)->first(),
                default       => null,
            };

            // Le solde a-t-il déjà été débité à l'INITIATION de la demande ? C'est un FAIT,
            // enregistré à la création (solde_debite_initiation) — plus une heuristique.
            // L'ancienne heuristique (« 1er validateur admin => règlement de dette => débiter »)
            // était fausse : le 1er validateur est TOUJOURS un admin, donc chaque demande
            // mobile acceptée était débitée UNE SECONDE FOIS (solde livreur à 0 au lieu du
            // reliquat). Et le refus restituait sans condition : refuser un reglerDette
            // (jamais débité) aurait CRÉDITÉ le tiers à tort.
            //
            // Repli pour les demandes créées AVANT l'ajout de la colonne (NULL) :
            // seul reglerDette génère un `numero` -> numero IS NULL = initiée par le tiers,
            // dont le flux majoritaire débite à l'initiation.
            $dejaDebite = $demande->solde_debite_initiation !== null
                ? (bool) $demande->solde_debite_initiation
                : is_null($demande->numero);

            if ($tier) {
                if (!$accepter) {
                    // Refus → restituer UNIQUEMENT ce qui avait été réservé à l'initiation.
                    if ($dejaDebite) {
                        $tier->update(['solde' => (float) $tier->solde + (float) $demande->montant]);
                    }
                } else {
                    // Acceptation → débiter UNIQUEMENT si l'initiation ne l'a pas déjà fait.
                    if (!$dejaDebite) {
                        $tier->update(['solde' => max(0, (float) $tier->solde - (float) $demande->montant)]);
                    }

                    // LE TIERS EST PAYÉ : SES PIÈCES DOIVENT LE SAVOIR.
                    //
                    // Ce chemin n'écrivait rien dans `paiement_fournisseur`.
                    // Les bons restaient donc entièrement dus après avoir été
                    // payés : le popup « Enregistrer un paiement fournisseur »
                    // proposait encore la totalité, et un administrateur
                    // pouvait régler une seconde fois ce qui l'était déjà.
                    // La colonne de liaison vient d'une migration. Tant qu'elle
                    // manque, l'imputation échoue sur une erreur SQL brute. On
                    // refuse la validation — plutôt que de l'accepter sans
                    // solder les bons, ce qui rouvrirait le double paiement —
                    // mais on dit pourquoi.
                    $table = match ($type) {
                        'fournisseur' => 'paiement_fournisseur',
                        'livreur'     => 'paiement_livreur',
                        'apporteur'   => 'paiement_apporteur',
                        default       => null,
                    };

                    if ($table
                        && !\Illuminate\Support\Facades\Schema::hasColumn($table, 'demande_paiement_id')) {
                        throw new \RuntimeException(
                            "La base n'est pas à jour : la colonne `{$table}.demande_paiement_id` "
                            . "n'existe pas. Lancez « php artisan migrate --force » sur le serveur, "
                            . "puis recommencez. Aucune modification n'a été enregistrée."
                        );
                    }

                    if ($type === 'fournisseur') {
                        $tier->imputerDemandeSurLesBons($demande, Auth::id());
                    } elseif ($type === 'livreur') {
                        $tier->imputerDemandeSurLesCourses($demande, Auth::id());
                    } elseif ($type === 'apporteur') {
                        $tier->imputerDemandeSurLesCommissions($demande, Auth::id());
                    }
                }
            }

            $demande->update([
                'paye' => $payeFlag,
                'mode_paiement_id' => $demande->mode_paiement_id ?? 6,
                'date_validation' => now(),
                'user_valide2_id' => Auth::id(),
                // Point 20 : acceptée par le 2e validateur, la demande est
                // « À payer » jusqu'à la preuve du versement et sa finalisation.
                'etat_reglement' => $accepter ? DemandePaiement::A_PAYER : null,
            ]);

            DB::commit();
            return redirect()->route($routeRetour)->with('success', 'Demande '.$rep.'.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route($routeRetour)->with('error',
                'Erreur lors de la 2e validation : '.$e->getMessage());
        }
    }

    /**
     * PREUVE DU VERSEMENT (point 20, 07/09/2026).
     *
     * Après la 2e validation, l'agent qui fait le virement joint la preuve
     * (capture, reçu bancaire, PDF). Sans elle, la demande ne peut pas être
     * déclarée effectuée.
     */
    public function joindrePreuveDemande(DemandePaiement $demande, \Illuminate\Http\Request $request)
    {
        $request->validate([
            'preuve' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'preuve.required' => 'Joignez la preuve du paiement (PDF, JPG ou PNG, 5 Mo maximum).',
            'preuve.mimes'    => 'La preuve doit être un PDF ou une image (JPG, PNG).',
            'preuve.max'      => 'La preuve ne doit pas dépasser 5 Mo.',
        ]);

        if ((int) $demande->paye !== 1) {
            return back()->with('error', "Cette demande n'a pas encore reçu ses deux validations : aucune preuve à joindre.");
        }
        if ($demande->etat_reglement === DemandePaiement::EFFECTUEE) {
            return back()->with('error', 'Cette demande est déjà effectuée.');
        }

        // Sécurité (09/09/2026) : un TROISIÈME administrateur, ni le 1er ni le 2e validateur.
        if (!$demande->troisiemeAdministrateur(Auth::user())) {
            return back()->with('error', "Pour des raisons de sécurité, la preuve est téléversée et le paiement finalisé par un troisième administrateur, différent des deux validateurs.");
        }
        $chemin = $request->file('preuve')->store('preuves_paiement', 'public');

        $demande->update([
            'preuve_paiement' => $chemin,
            'date_preuve'     => now(),
            'user_preuve_id'  => Auth::id(),
            'etat_reglement'  => DemandePaiement::PREUVE_JOINTE,
        ]);

        return back()->with('success', 'Preuve de paiement jointe. Vous pouvez maintenant finaliser le paiement.');
    }

    /** La preuve, en consultation (back-office seulement). */
    public function voirPreuveDemande(DemandePaiement $demande)
    {
        if (!$demande->preuve_paiement || !\Illuminate\Support\Facades\Storage::disk('public')->exists($demande->preuve_paiement)) {
            abort(404, 'Aucune preuve jointe.');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->response($demande->preuve_paiement);
    }

    /**
     * FINALISATION : l'opération est « Effectuée », et le livreur, l'apporteur
     * ou le fournisseur le voient. Exige une preuve jointe.
     */
    public function effectuerDemande(DemandePaiement $demande)
    {
        if ((int) $demande->paye !== 1) {
            return back()->with('error', "Cette demande n'a pas encore reçu ses deux validations.");
        }
        if ($demande->etat_reglement === DemandePaiement::EFFECTUEE) {
            return back()->with('info', 'Cette demande est déjà effectuée.');
        }
        if (!$demande->troisiemeAdministrateur(Auth::user())) {
            return back()->with('error', "Pour des raisons de sécurité, la preuve est téléversée et le paiement finalisé par un troisième administrateur, différent des deux validateurs.");
        }
        if ($demande->etat_reglement !== DemandePaiement::PREUVE_JOINTE || !$demande->preuve_paiement) {
            return back()->with('error', "Joignez d'abord la preuve du paiement : sans elle, l'opération ne peut pas être déclarée effectuée.");
        }

        $demande->update([
            'etat_reglement'    => DemandePaiement::EFFECTUEE,
            'date_effectuee'    => now(),
            'user_effectuee_id' => Auth::id(),
        ]);

        // LES BORDEREAUX DES RÈGLEMENTS NÉS DE CETTE DEMANDE partent au
        // partenaire (11/09/2026), après la réponse, sans jamais remettre en
        // cause la finalisation (tout est sous try/catch dans le service).
        \App\Services\RecuDeReglement::envoyerPourLaDemande($demande);

        return back()->with('success', 'Paiement effectué : le bénéficiaire le voit désormais dans son espace.');
    }

    public function listePaiementsParClient(Client $client){

        $paiements = Paiement::where('client_id',$client->id)->get();

        return view('client.listePaiementParClient',[
            'paiements' => $paiements
        ]);

    }

    public function listeDeDemandeApporteur(){

        //demande de paiement des livreurs
        $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 6)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();


        return view('admin.listeDeDemandeApporteur',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function listeDeDemandeFournisseur(){

        //demande de paiement des livreurs
        $demandes = DemandePaiement::join('users', 'users.id', '=', 'demande_paiement.user_id')
            ->where('users.type_user_id', 5)
            ->where('demande_paiement.deleted_at', null)
            ->orderByDesc('demande_paiement.created_at')
            ->select('demande_paiement.*')
            ->get();

        return view('admin.listeDeDemandeFournisseur',[
            'demandes' => $demandes,
            'config' => Configuration::first()
        ]);
    }

    public function clientDetailCommande(User $user){


        $client = Client::where('user_id',$user->id)->first();
        $commandes = Commande::where('client_id',$client->id)->orderByDesc('created_at')->get();


        return view('orders.commandeDunClientATerme',[
            'client' => $client,
            'commandes' => $commandes
        ]);
    }

    public function historiqueDemande(){
        return view('admin.historiqueDemande',[
            'demandes' => DemandePaiement::orderBy('created_at','desc')->get()
        ]);
    }

    public function listeDemandeClient(){
        return view('client.listeDemandeClient',[
            'demandes'   => DemandeCompteClientATerme::all(),
            // Ce qui a été décidé mais n'est pas encore acquis : sans ce bloc,
            // une approbation saisie disparaîtrait de l'écran sans avoir rien
            // changé, et on la saisirait une seconde fois.
            'decisions'  => \App\Models\DecisionClientTerme::enAttente(),
        ]);
    }

    public function validationDemande(\Illuminate\Http\Request $request, DemandeCompteClientATerme $demande, $rep){

        if((int)$rep === 1){
            // APPROBATION : plafond + délai obligatoires
            $request->validate([
                'plafond_credit'    => 'required|numeric|min:0',
                'delai_paiement'    => 'required|integer|min:1|max:365',
                'commentaire_admin' => 'nullable|string|max:1000',
            ], [
                'plafond_credit.required' => 'Le plafond de crédit est obligatoire.',
                'delai_paiement.required' => 'Le délai de paiement (en jours) est obligatoire.',
                'delai_paiement.max'      => 'Le délai de paiement ne peut excéder 365 jours.',
            ]);

            // RIEN N'EST ACCORDÉ ICI : la décision attend son second contrôle.
            //
            // Ouvrir un compte à terme, c'est décider combien l'entreprise
            // accepte de ne pas être payée tout de suite. Cela se décidait seul,
            // d'un clic, et s'appliquait aussitôt — l'e-mail au client partait
            // dans la foulée, ce qui rendait le retour en arrière impossible.
            if (!\App\Models\DecisionClientTerme::tableExiste()) {
                return back()->with('error',
                    'La table des décisions de crédit n\'existe pas encore sur ce serveur : lancez la migration.');
            }

            if ($enCours = \App\Models\DecisionClientTerme::enAttentePour((int) $demande->client_id)) {
                return back()->with('error',
                    'Une décision attend déjà sa validation sur ce client : ' . $enCours->libelleType() . '.');
            }

            \App\Models\DecisionClientTerme::create([
                'client_id'         => $demande->client_id,
                'demande_id'        => $demande->id,
                'type'              => \App\Models\DecisionClientTerme::ACTIVATION,
                'plafond_credit'    => $request->plafond_credit,
                'delai_paiement'    => $request->delai_paiement,
                'commentaire'       => $request->commentaire_admin,
                'ancien_plafond'    => $demande->client?->plafond_credit,
                'ancien_delai'      => $demande->client?->delai_paiement,
                'user_valide_id'    => Auth::user()->id,
                'date_validation_1' => now(),
                'statut'            => \App\Models\DecisionClientTerme::EN_ATTENTE,
            ]);

            return redirect()->route('show.listeDemandeClient')->with('success',
                'Approbation enregistrée. Elle prendra effet — et le client sera prévenu — après validation par un second administrateur.');
        }

        // REFUS : motif obligatoire
        $request->validate([
            'motif_refus' => 'required|string|min:5|max:1000',
        ], [
            'motif_refus.required' => 'Le motif du refus est obligatoire.',
            'motif_refus.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        // Le refus suit la même règle que l'accord : un client qu'on écarte est
        // un client qu'on perd, et l'e-mail qui l'annonce ne se rattrape pas.
        if (!\App\Models\DecisionClientTerme::tableExiste()) {
            return back()->with('error',
                'La table des décisions de crédit n\'existe pas encore sur ce serveur : lancez la migration.');
        }

        if ($enCours = \App\Models\DecisionClientTerme::enAttentePour((int) $demande->client_id)) {
            return back()->with('error',
                'Une décision attend déjà sa validation sur ce client : ' . $enCours->libelleType() . '.');
        }

        \App\Models\DecisionClientTerme::create([
            'client_id'         => $demande->client_id,
            'demande_id'        => $demande->id,
            'type'              => \App\Models\DecisionClientTerme::REFUS_DEMANDE,
            'commentaire'       => $request->motif_refus,
            'user_valide_id'    => Auth::user()->id,
            'date_validation_1' => now(),
            'statut'            => \App\Models\DecisionClientTerme::EN_ATTENTE,
        ]);

        return redirect()->route('show.listeDemandeClient')->with('success',
            'Refus enregistré. Il prendra effet — et le client sera prévenu — après validation par un second administrateur.');
    }

    /**
     * Sert un document joint à une demande de compte à terme (PDF/image)
     * en inline (preview) ou en téléchargement. Réservé aux admin/gestionnaires.
     */
    public function demandeClientTermeDocument(DemandeCompteClientATerme $demande, string $key, string $mode = 'inline')
    {
        $docs = is_array($demande->documents_path) ? $demande->documents_path : [];
        $path = $docs[$key] ?? null;
        if (!$path) {
            return redirect()->route('show.listeDemandeClient')->with('error', "Aucun document '$key' joint à cette demande.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.listeDemandeClient')->with('error', "Le document est introuvable sur le serveur (chemin: $path).");
        }
        $ext = pathinfo($absolute, PATHINFO_EXTENSION);
        $nom = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)($demande->client->nom ?: ('client-'.$demande->client_id)));
        $downloadName = $key.'-'.$nom.($ext ? '.'.$ext : '');
        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition.'; filename="'.$downloadName.'"',
        ]);
    }

    /**
     * Retourne (JSON) un récap des vérifications automatiques sur un client :
     * ancienneté du compte, nombre de commandes, % payées, montant total commandé, etc.
     * Utilisé par le modal de validation côté admin.
     */
    public function demandeClientTermeStats(DemandeCompteClientATerme $demande)
    {
        $client = $demande->client;
        if (!$client || !$client->id) {
            return response()->json(['error' => 'Client introuvable'], 404);
        }

        $anciennete = $client->created_at ? \Carbon\Carbon::parse($client->created_at)->diffInDays(now()) : 0;
        $nbCommandes = \DB::table('commande')->where('client_id', $client->id)->whereNull('deleted_at')->count();
        // Total NET commandé, recalculé depuis les lignes : commande.montant_total
        // contient le HT côté site et le NET côté mobile, le taux de paiement affiché
        // dépassait donc 100 % pour un client ayant commandé depuis l'application.
        $montantTotal = (float) \App\Models\Commande::where('client_id', $client->id)
            ->get()
            ->sum(fn ($commande) => $commande->montantAPayer());
        $nbPaiementsValides = \DB::table('paiement')->where('client_id', $client->id)->where('statut', 1)->whereNull('deleted_at')->count();
        $montantPaye = (float) \DB::table('paiement')->where('client_id', $client->id)->where('statut', 1)->whereNull('deleted_at')->sum('montant_total');
        $tauxPaiement = $montantTotal > 0 ? round(($montantPaye / $montantTotal) * 100, 1) : 0;

        return response()->json([
            'anciennete_jours'    => $anciennete,
            'nb_commandes'        => $nbCommandes,
            'montant_total'       => $montantTotal,
            'nb_paiements_valides'=> $nbPaiementsValides,
            'montant_paye'        => $montantPaye,
            'taux_paiement'       => $tauxPaiement,
            'client_nom'          => ($client?->display_name ?? ''),
            'client_email'        => $client->user->email ?? $client->email ?? '',
            'client_contact'      => $client->contact1 ?? '',
        ]);
    }

    public function listeRetourProduit(){
        return view('admin.retourProduit',[
            'retours' => RetourProduit::orderBy('created_at','desc')->get()
        ]);
    }

    public function creationDeCodePromo(){


        return view('gestionnaire.creationDeCodePromo',[
            'reductions' => Reduction::where('deleted_at',null)->orderByDesc('created_at')->get(),
            'leCode' => new Reduction
        ]);
    }

    public function suppressionDeCodePromo(Reduction $reduction){
        $reduction->update([
            'deleted_at' => date('Y-m-d H:i:s')
        ]);

        return redirect()->route('show.creationDeCodePromo')->with('success','Code supprimé');
    }

    public function updateDeCodePromo(Reduction $reduction){

        return view('gestionnaire.creationDeCodePromo',[
            'reductions' => Reduction::where('deleted_at',null)->orderByDesc('created_at')->get(),
            'leCode' => $reduction
        ]);
    }

    public function codeUpdated(Request $request, Reduction $reduction){

        $debut = $reduction->debut;
        $fin = $reduction->fin;

        if($request->fin != null){
            $fin = $request->fin;
        }

        if($request->debut != null){
            $debut = $request->debut;
        }

        $reduction->update([
            'libelle' => $request->libelle,
            'debut' => $debut,
            'fin' => $fin,
            'taux_reduction' => $request->taux
        ]);

        return redirect()->route('show.updateDeCodePromo',$reduction)->with('success','Code modifié');

    }

    public function enregistrementDeCodePromo (Request $request){
        $data = [
            'code' => Help::ChaineAleatoire(5),
            'libelle'=> $request->libelle,
            'debut' => $request->debut,
            'fin' => $request->fin,
            'taux_reduction' => $request->taux,
            'user_id' => Auth::user()->id,
        ];

        $reduction = Reduction::create($data);

        return redirect()->route('show.creationDeCodePromo')->with('success','Code bien créé ');
    }

    public function redirecting(){

        return $pdf = PDF::loadView('test')->download();
        $lien = 'https://www.facebook.com/';
        $lienSinon = 'http://localhost:8000/welcome';
        return view('redirecting',[
            'lien' => $lien,
            'lienSinon' => $lienSinon
        ]);
    }

    public function enConstruction($titre = null){

        return view('siteEnConstruction', ['titre' => $titre]);
    }

    /**
     * LA PAGE « SITE EN CONSTRUCTION » (lot 114, 19/09/2026). Une personne connectée n'a rien à
     * y faire : elle va à l'accueil. Mode inactif : la page renvoie aussi à l'accueil.
     */
    public function pageSiteEnConstruction()
    {
        if (Auth::check() || !Configuration::siteEnConstruction()) {
            return redirect()->route('client.index');
        }

        return response()->view('siteEnConstructionMode')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** L'interrupteur de Paramètres : réservé aux administrateurs. */
    public function basculerSiteEnConstruction(Request $request)
    {
        abort_unless(in_array((int) Auth::user()->type_user_id, [\Help::$USER_SA, \Help::$USER_ADMIN], true), 403);

        $config = Configuration::first();
        $actif = !$config->site_en_construction;
        $config->update(['site_en_construction' => $actif]);

        return redirect()->route('show.parametre')->with('success', $actif
            ? "Mode « site en construction » ACTIVÉ : le site public n'est visible que des personnes connectées."
            : "Mode « site en construction » désactivé : le site public est de nouveau ouvert à tous.");
    }

    /**
     * LES TROIS APPLICATIONS ANDROID (lot 110, 17/09/2026).
     * Le fichier public/telechargements/mon-gravier-<application>.apk est servi tel quel ; s'il
     * manque, retour à la section « applications » de l'accueil avec un message.
     */
    public static function applicationsMobiles(): array
    {
        $liste = [
            'client'    => ['nom' => 'Mon Gravier Client',    'sous' => 'Pour commander et suivre vos livraisons'],
            'livreur'   => ['nom' => 'Mon Gravier Livreur',   'sous' => 'Pour recevoir et effectuer les courses'],
            'apporteur' => ['nom' => "Mon Gravier Apporteur", 'sous' => "Pour apporter des clients et gagner des commissions"],
        ];
        foreach ($liste as $cle => &$app) {
            $fichier = public_path('telechargements/mon-gravier-' . $cle . '.apk');
            $app['disponible'] = is_file($fichier);
            $app['taille']     = $app['disponible'] ? round(filesize($fichier) / 1048576) . ' Mo' : null;
            $app['date']       = $app['disponible'] ? date('d/m/Y', filemtime($fichier)) : null;
            $app['lien']       = route('telechargerApplication', $cle);
        }
        return $liste;
    }

    public function telechargerApplication(string $application)
    {
        $fichier = public_path('telechargements/mon-gravier-' . $application . '.apk');
        if (!is_file($fichier)) {
            $nom = self::applicationsMobiles()[$application]['nom'] ?? "L'application";
            return redirect(route('client.index') . '#applications')
                ->with('application_indisponible', $nom . " sera disponible au téléchargement très prochainement.");
        }

        return response()->download($fichier, 'mon-gravier-' . $application . '.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }

    public function pageAPropos(){
        // $produits (quickView) et $categories (footer) sont requis par le layout client.main.
        return view('client.aPropos', [
            'produits'   => \App\Models\Produit::where('type_affaire', 'VENTE')->where('statut', 1)->avecFournisseur()->get(),
            'categories' => \App\Models\Categorie::where('statut', 1)->get(),
        ]);
    }

    public function pageContact(){
        return view('client.contact', [
            'produits'   => \App\Models\Produit::where('type_affaire', 'VENTE')->where('statut', 1)->avecFournisseur()->get(),
            'categories' => \App\Models\Categorie::where('statut', 1)->get(),
        ]);
    }

    public function contactStore(Request $request){
        $request->validate([
            'nom_prenoms' => 'required|string|max:150',
            'email'       => 'required|email|max:100',
            'telephone'   => 'required|string|max:15',
            'sujet'       => 'required|string|max:50',
            'message'     => 'required|string',
        ], [
            'nom_prenoms.required' => 'Veuillez renseigner votre nom et prénoms.',
            'email.required'       => 'Veuillez renseigner votre adresse email.',
            'email.email'          => 'Veuillez saisir une adresse email valide.',
            'telephone.required'   => 'Veuillez renseigner votre numéro de téléphone.',
            'telephone.max'        => 'Le numéro de téléphone ne doit pas dépasser 15 caractères.',
            'sujet.required'       => 'Veuillez préciser le sujet de votre message.',
            'message.required'     => 'Veuillez saisir votre message.',
        ]);

        // Chaque message est conservé. L'enregistrement se faisait par
        // updateOrCreate sur l'adresse e-mail, unique en base : un client qui
        // écrivait une seconde fois ÉCRASAIT son message précédent, même des
        // mois plus tard et sur un autre sujet. La contrainte d'unicité a été
        // levée par migration.
        $contact = \App\Models\Contact::create([
            'nom_prenoms' => $request->nom_prenoms,
            'email'       => $request->email,
            'telephone'   => $request->telephone,
            'sujet'       => $request->sujet,
            'message'     => $request->message,
            'lu'          => false,
            'statut'      => \Help::$STATUT_ACTIF,
        ]);

        // Alerte à l'entreprise. Personne n'était prévenu : le message dormait
        // en base, et comme aucun écran ne l'affichait, le visiteur n'obtenait
        // jamais de réponse alors que la page lui en promettait une.
        //
        // Hors du chemin critique : le message est déjà enregistré ici, une
        // messagerie indisponible ne doit pas afficher d'erreur au visiteur.
        try {
            \Mail::to($this->adresseDeContact())->send(new \App\Mail\NouveauMessageContact($contact));
        } catch (\Throwable $e) {
            \Log::error('Contact : alerte non envoyée pour le message #' . $contact->id . ' — ' . $e->getMessage());
        }

        return redirect()->route('contact')
            ->with('success', 'Votre message a bien été envoyé. Notre équipe vous répondra dans les plus brefs délais.');
    }

    /**
     * Destinataire des alertes de la page « Nous contacter ».
     *
     * On privilégie l'adresse renseignée dans les paramètres de l'entreprise,
     * mais seulement si c'en est une : ce champ contient parfois autre chose
     * (un numéro, une référence) selon la façon dont la configuration a été
     * saisie. Repli sur l'expéditeur configuré, puis sur l'adresse du domaine.
     */
    private function adresseDeContact(): string
    {
        $config = Configuration::first();

        foreach ([$config->email_entreprise ?? null, config('mail.from.address')] as $candidat) {
            $candidat = trim((string) $candidat);
            if ($candidat !== '' && filter_var($candidat, FILTER_VALIDATE_EMAIL)) {
                return $candidat;
            }
        }

        return \Help::emailContact();
    }

    public function error (){


        return view('error');

    }

    public function login (){
        return view('compte.account-login');
    }


    public function logout(){
        $type = Auth::user()?->type_user_id;

        // Avant Auth::logout() : ensuite, plus personne n'est identifié et la
        // trace serait anonyme.
        if (in_array((int) $type, [1, 2, 3, 7], true)) {
            Audit::log('Déconnexion — utilisateur');
        }

        Auth::logout();

        switch($type){
            case 2 :
                return redirect()->route('show.login');
                break;
            case 3 :
                return redirect()->route('show.login');
                break;
            case 5 :
                return redirect()->route('sellers.login');
                break;
            case 8 :
                return redirect()->route('livreur.login');
                break;
            case 6 :
                return redirect()->route('apporteur.login');
                break;
            case 4 :
                Cart::destroy();
                return redirect()->route('client.index');
                break;
            default:
                return redirect()->route('client.index');
        }

    }

    public function bonAttente(){
        $enlevements = Enlevement::Where('qte_servi','=',null)
                                ->orderByDesc('created_at')
                                ->get();
        // dd($enlevements);
        return view('gestionnaire.bonEnAttente',[
            'enlevements' => $enlevements
        ]);
    }

    /**
     * Aperçu PDF d'un bon d'enlèvement (admin/gestionnaire) — fonctionne aussi
     * pour un bon en statut "en attente" (qte_servi null).
     */
    public function bonApercu(Enlevement $enlevement)
    {
        $pdf = \PDF::loadView('livreur.bonImprime', ['enlevement' => $enlevement]);
        return $pdf->stream($enlevement->code_enleve . '.pdf');
    }

    /**
     * Téléchargement PDF d'un bon d'enlèvement (admin/gestionnaire) — fonctionne aussi
     * pour un bon en statut "en attente" (qte_servi null).
     */
    public function bonTelecharger(Enlevement $enlevement)
    {
        $pdf = \PDF::loadView('livreur.bonImprime', ['enlevement' => $enlevement]);
        return $pdf->download($enlevement->code_enleve . '.pdf');
    }

    public function bonvalides(){
        $enlevements = Enlevement::Where('qte_servi','!=',null)
                                ->orderByDesc('created_at')
                                ->get();
        // dd($enlevements);
        // dd($enlevements);
        return view('gestionnaire.bonValides',[

            'enlevements' => $enlevements

        ]);
    }

    // TRAITEMENT DE L'AUTHENTIFICATION
    public function validLogin(Request $request){


        $user = User::where('login', $request->login)
    ->where(function ($query) {
        $query->where('type_user_id', 1)
              ->orWhere('type_user_id', 2)
              ->orWhere('type_user_id', 3)
              ->orWhere('type_user_id', 4)
              ->orWhere('type_user_id', 7); // Agent SAV : se connecte via /login-account
    })
    ->first();
// dd($user);
        if($user){
            $validInfo = $request->validate([
                'login' => 'required',
                'password' => 'required|min:4'
            ]);
            if(Help::HashVerifier($request->password, $user->password)){

                $test = $request->session()->regenerate();

                Auth::login($user);

                // Trace de connexion. Le middleware d'audit ne peut pas la
                // produire : il ne saurait pas distinguer une identification
                // réussie d'un mot de passe refusé.
                if (in_array((int) $user->type_user_id, [1, 2, 3, 7], true)) {
                    Audit::log('Connexion — utilisateur', ['login' => $user->login], $request);
                }

                // Redirection selon le type d'utilisateur
                $typeId = $user->type_user_id;
                // On redirige TOUJOURS vers l'accueil du rôle (et non intended()), pour
                // éviter qu'une URL d'un autre rôle mémorisée précédemment (accès refusé)
                // ne renvoie l'utilisateur vers une page interdite (403) après connexion.
                if (in_array($typeId, [1, 2, 3])) {
                    // Super Admin, Administrateur, Gestionnaire
                    return redirect()->route('show.home')->with('connected');
                } elseif ($typeId == 4) {
                    // Client
                    return redirect()->route('client.index')->with('connected');
                } elseif ($typeId == 5) {
                    // Fournisseur
                    return redirect()->route('sellers.home')->with('connected');
                } elseif ($typeId == 6) {
                    // Apporteur d'affaires
                    return redirect()->route('apporteur.home')->with('connected');
                } elseif ($typeId == 7) {
                    // Agent SAV : atterrit sur ses tickets assignés (7 = Agent SAV, PAS Livreur=8).
                    return redirect()->route('show.mesTicketsSAV')->with('connected');
                } else {
                    return redirect()->route('client.index')->with('connected');
                }
            }else{
                return redirect()->route('show.login')->with('fail','mot de passe incorrect');
            }

        }else{
            // connectify('errox', 'Connexion trouvée', 'Vous êtes connecté(e)');
            return redirect()->route('show.login')->with('fail','login incorrect');
        }

    }

    public function register(){
        $villes = Ville::select('id','nom')->get();
        $pays = Pays::select('id','nom')->get();
        $typeUsers = TypeUser::select('id','nom')->get();
        // dd($typeUsers);
        return view('compte.account-register', [
            'villes' => $villes,
            'pays' => $pays,
            'typeUsers' => $typeUsers,
            // Agences proposées dès la création : sans rattachement, le
            // gestionnaire ne pourra encaisser nulle part.
            'agences' => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);

    }

    public function storeUser(Request $request){

        // Validation SERVEUR (le required côté formulaire peut être contourné) :
        // mêmes règles que agentRegistred. Login et mot de passe ne sont pas
        // validés ici car générés automatiquement plus bas.
        $request->validate([
            'nom_prenoms' => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'contact'     => 'required|string|max:15',
            'adresse'     => 'required|string|max:255',
            // Facultative : elle peut être décidée plus tard depuis la liste
            // des gestionnaires. Tant qu'elle manque, il ne peut pas encaisser.
            'agence_id'   => 'nullable|integer|exists:agence,id',
        ], [
            'nom_prenoms.required' => 'Le nom et prénoms est obligatoire.',
            'email.required'       => 'L\'adresse email est obligatoire.',
            'email.email'          => 'L\'adresse email n\'est pas valide.',
            'contact.required'     => 'Le numéro de téléphone est obligatoire.',
            'adresse.required'     => 'L\'adresse est obligatoire.',
        ]);

        // Construire le numéro complet SANS redoubler l'indicatif : si l'utilisateur
        // a déjà saisi le numéro au format international (+225...) ou avec l'indicatif
        // en tête (225...), on ne re-préfixe pas. Évite "+225+225..." qui dépassait
        // varchar(15) -> erreur "Data too long" (1406) -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) $request->indicatif, '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = $request->indicatif . $saisi;
        }

        $typeUserId = TypeUser::where('nom', 'like', '%gestionnaire%')->value('id');

        // Initialiser à null : sans cette ligne, si aucune photo n'est envoyée,
        // $nomImage reste indéfini et son usage plus bas (User::create) déclenche
        // un warning "Undefined variable" converti par Laravel en ErrorException -> 500.
        $nomImage = null;

        if ($request->hasFile('photo')) {
            $request->validate([
                'photo' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // Exemple de validation
            ]);

            $nomImage ='image_'.$request->nom_prenoms.'.'.$request->file('photo')->getClientOriginalExtension();

            $request->file('photo')->move(public_path('storage/imageUser'), $nomImage);


        }

        $email = User::where('email', $request->email)->where('deleted_at', null)->first();
        if($email){
            return back()->with('errorEmail', "Cet email est déjà utilisé");
        }

        // IDENTIFIANTS GÉNÉRÉS AUTOMATIQUEMENT (comme livreur/fournisseur) :
        // le login (slug depuis le nom) et le mot de passe ne sont plus saisis
        // par l'admin ; ils sont envoyés au gestionnaire par email via MailAccesUsers.
        $slug = SlugService::createSlug(User::class, 'login', $request->nom_prenoms);
        $existant = User::withTrashed()->where('login', $slug)->value('login');
        if ($slug == $existant) {
            $slug = $slug . '' . rand(0, 100);
        }

        $rawPassword = Help::ChaineAleatoire(8);
        $password = Help::HashPassword($rawPassword);

        $user = User::create([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $contact,
            'login' => $slug,
            'password' => $password,
            'photo' => $nomImage,
            'adresse' => $request->adresse,
            'type_user_id' => $typeUserId,
            // Agence de rattachement : elle décide du guichet auquel ses
            // encaissements seront imputés. Vide = il ne peut pas encaisser.
            'agence_id' => $request->agence_id ?: null,
            'statut' => true,
        ]);
        // $user->assignRole('gestionnaire');

        // Envoi NON bloquant des identifiants générés (un échec d'email ne doit
        // pas empêcher la création du compte). Le gestionnaire se connecte via
        // /login-account -> route('show.login'), d'où le type 'show'.
        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'show'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès gestionnaire: ' . $e->getMessage());
        }

        return redirect()->route('show.registerGestionnaire')
            ->with('success', 'Gestionnaire enregistré. Ses identifiants de connexion lui ont été envoyés par email.');
    }

    public function AgentRegister(){

        // Agences proposées dès la création : sans rattachement, l'agent ne
        // pourra encaisser nulle part et il faudra revenir le lui affecter.
        return view('compte.agentRegister', [
            'agences' => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);

    }

    public function agentRegistred(Request $request){
        $request->validate([
            'nom_prenoms' => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'contact'     => 'required',
            'login'       => 'required|string|max:150|unique:users,login',
            // Facultative : elle peut être décidée plus tard depuis la liste
            // des agents. Tant qu'elle manque, l'agent ne peut pas encaisser.
            'agence_id'   => 'nullable|integer|exists:agence,id',
            // 'password' retiré : généré automatiquement et envoyé par email.
        ], [
            'nom_prenoms.required' => 'Le nom et prénoms est obligatoire.',
            'email.required'       => 'L\'adresse email est obligatoire.',
            'email.unique'         => 'Cette adresse email est déjà utilisée.',
            'contact.required'     => 'Le numéro de téléphone est obligatoire.',
            'login.required'       => 'Le login est obligatoire.',
            'login.unique'         => 'Ce login est déjà utilisé, veuillez en choisir un autre.',
        ]);

        // Numéro complet sans redoubler l'indicatif (cf. storeUser/registerAdmin) :
        // évite "+225+225..." qui dépasse varchar(15) -> "Data too long" -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) ($request->indicatif ?: ''), '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = ($request->indicatif ?: '') . $saisi;
        }

        $typeUserId = TypeUser::where('nom', 'like', '%agent%')->value('id');
        $photo_path = "";
        if($request->hasFile('photo')){
            $photo = $request->photo;
            $photo_path = $photo->store('imageAgent','public');
        }

        // Mot de passe GÉNÉRÉ automatiquement (l'admin ne doit pas le connaître),
        // envoyé à l'agent par email. Hachage via Help::HashPassword (avec sel),
        // car la connexion vérifie via Help::HashVerifier.
        $rawPassword = Help::ChaineAleatoire(8);
        $password = \Help::HashPassword($rawPassword);

        $user = User::create([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $contact,
            'login' => $request->login,
            'password' => $password,
            'photo' => $photo_path,
            'adresse' => $request->adresse,
            'type_user_id' => $typeUserId,
            // Agence de rattachement : c'est elle qui décide du guichet auquel
            // ses encaissements seront imputés. Vide = il ne peut pas encaisser
            // tant qu'un administrateur ne l'a pas affecté.
            'agence_id' => $request->agence_id ?: null,
            'statut' => true,
        ]);

        // Envoi NON bloquant des identifiants générés. L'agent se connecte via
        // /login-account -> route('show.login'), d'où le type 'show' pour le bouton du mail.
        try {
            Mail::send(new MailAccesUsers($request->nom_prenoms, $user->login, $rawPassword, $user->email, 'show'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès agent: ' . $e->getMessage());
        }

        return redirect()->route('show.AgentRegister')->with('success','Agent enregistré. Ses identifiants de connexion lui ont été envoyés par email.');
    }

    public function agentUpdate(User $user){
        return view('compte.agentUpdate',[
            'agent' => $user
        ]);
    }

    public function AgentUpdated(Request $request, User $user){


        $user->update([
            'nom_prenoms' => $request->nom_prenoms,
            'email' => $request->email,
            'contact' => $request->contact,
            'adresse' => $request->adresse,
        ]);


        if($request->hasFile('photo')){
            Storage::disk('public')->delete($user->photo);
            $image = $request->photo;
            $imagePath = $image->store('imageBlog','public');

            $user->update([
                'photo' => $imagePath
            ]);
        }

        return redirect()->route('show.AgentUpdate',$user)->with('success','Modification effectuée');
            // dd('ok');

    }

    // ------------------------------------------------------------------
    // LES BLOGS — création, liste, modification, publication,
    // mise à la corbeille, restauration et suppression définitive.
    //
    // Avant : aucune validation (un blog sans titre ni image partait en base),
    // la création plantait si une seule des deux images manquait, la seconde
    // image écrasait la première à la modification (les deux écrivaient dans
    // la colonne `image`), et la liste ne montrait pas les blogs supprimés —
    // ils n'étaient donc ni restaurables ni définitivement effaçables.
    // ------------------------------------------------------------------

    private function reglesBlog(bool $imagesObligatoires): array
    {
        $regleImage = ($imagesObligatoires ? 'required|' : 'nullable|') . 'image|mimes:jpg,jpeg,png,webp|max:2048';

        return [
            'titre'        => 'required|string|max:190',
            'description'  => 'required|string',
            'image'        => $regleImage,
            'image_detail' => str_replace('required|', 'nullable|', $regleImage),
        ];
    }

    private function messagesBlog(): array
    {
        return [
            'titre.required'       => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'image.required'       => 'Une image de couverture est obligatoire.',
            'image.max'            => "L'image ne doit pas dépasser 2 Mo.",
            'image.mimes'          => 'Formats acceptés : jpg, jpeg, png, webp.',
            'image_detail.max'     => "L'image de détail ne doit pas dépasser 2 Mo.",
            'image_detail.mimes'   => 'Formats acceptés : jpg, jpeg, png, webp.',
        ];
    }

    public function creationDeBlog(){
        return view('gestionnaire.blog',['blog' => new blog]);
    }

    public function creationDeBlogTraitement(Request $request){

        $donnees = $request->validate($this->reglesBlog(true), $this->messagesBlog());

        blog::create([
            'image'          => $request->file('image')->store('imageBlog','public'),
            // L'image de détail est facultative : l'ancienne version plantait
            // lorsqu'elle n'était pas fournie.
            'image_detail'   => $request->hasFile('image_detail')
                ? $request->file('image_detail')->store('imageBlog','public')
                : null,
            'titre'          => $donnees['titre'],
            'description'    => $donnees['description'],
            'user_publie_id' => Auth::id(),
            'publie'         => 1,
        ]);

        return redirect()->route('show.listeDesBlogs')->with('success','Blog créé avec succès');
    }

    public function listeDesBlogs(){

        // withTrashed : sans cela un blog mis à la corbeille disparaît de la liste
        // et ne peut plus être ni restauré ni supprimé définitivement.
        // withCount : le nombre de commentaires et surtout ceux en attente sont
        // affichés dans la liste, pour repérer d'un coup d'œil ce qui doit être
        // modéré — sans une requête par ligne.
        return view('gestionnaire.listBlog',[
            'blogs' => blog::withTrashed()
                ->withCount([
                    'commentaires as nb_commentaires',
                    'commentaires as nb_commentaires_attente' => fn($q) => $q->where('statut', blog_commentaire::EN_ATTENTE),
                ])
                ->orderByDesc('created_at')->get()
        ]);
    }

    public function supprimerPublierBlog($id, $action = 'publier'){

        $blog = blog::withTrashed()->find($id);

        if (!$blog) {
            return redirect()->route('show.listeDesBlogs')->with('error','Blog introuvable');
        }

        if ($action === 'supprimer') {
            if ($blog->trashed()) {
                $blog->restore();
                $libelle = 'restauré';
            } else {
                $blog->delete();
                $libelle = 'mis à la corbeille';
            }
        } else {
            $blog->update(['publie' => $blog->publie == 1 ? 0 : 1]);
            $libelle = $blog->publie == 1 ? 'republié' : 'retiré de l\'affichage';
        }

        return redirect()->route('show.listeDesBlogs')->with('success',"Blog $libelle avec succès");
    }

    /**
     * Suppression DÉFINITIVE : la ligne, ses images et ses commentaires
     * disparaissent. Réservée aux blogs déjà mis à la corbeille.
     */
    public function suppressionDefinitiveBlog($id){

        $blog = blog::withTrashed()->find($id);

        if (!$blog) {
            return redirect()->route('show.listeDesBlogs')->with('error','Blog introuvable');
        }

        if (!$blog->trashed()) {
            return redirect()->route('show.listeDesBlogs')
                ->with('error','Mettez d\'abord ce blog à la corbeille avant de le supprimer définitivement.');
        }

        foreach ([$blog->image, $blog->image_detail] as $fichier) {
            if ($fichier) {
                Storage::disk('public')->delete($fichier);
            }
        }

        // Les commentaires pointent sur le blog par une clé étrangère : les laisser
        // ferait échouer la suppression.
        // forceDelete + withTrashed : depuis que blog_commentaire applique
        // SoftDeletes, un simple delete() ne ferait que marquer les lignes, qui
        // resteraient en base et bloqueraient la contrainte de clé étrangère.
        \App\Models\blog_commentaire::withTrashed()->where('blog_id', $blog->id)->forceDelete();
        $blog->forceDelete();

        return redirect()->route('show.listeDesBlogs')->with('success','Blog supprimé définitivement');
    }

    public function modificationDeBlogPage($id){
        $blog = blog::withTrashed()->find($id);

        if (!$blog) {
            return redirect()->route('show.listeDesBlogs')->with('error','Blog introuvable');
        }

        return view('gestionnaire.blogUpdate',['blog' => $blog]);
    }

    public function modificationDeBlog(Request $request, $id){

        $blog = blog::withTrashed()->find($id);

        if (!$blog) {
            return redirect()->route('show.listeDesBlogs')->with('error','Blog introuvable');
        }

        $donnees = $request->validate($this->reglesBlog(false), $this->messagesBlog());

        $blog->update([
            'titre'       => $donnees['titre'],
            'description' => $donnees['description'],
        ]);

        // Chaque image va dans SA colonne : l'ancienne version enregistrait
        // l'image de détail dans la colonne `image`, écrasant la couverture.
        foreach (['image' => 'image', 'image_detail' => 'image_detail'] as $champ => $colonne) {
            if ($request->hasFile($champ)) {
                $ancienne = $blog->{$colonne};
                $blog->update([
                    $colonne => $request->file($champ)->store('imageBlog','public')
                ]);
                // L'ancienne image n'est effacée qu'APRÈS l'enregistrement de la nouvelle.
                if ($ancienne) {
                    Storage::disk('public')->delete($ancienne);
                }
            }
        }

        return redirect()->route('show.listeDesBlogs')->with('success','Blog modifié avec succès');
    }
    // ------------------------------------------------------------------
    // LES BANNIÈRES — création, liste, modification, publication,
    // mise à la corbeille, restauration et suppression définitive.
    //
    // Avant : la création plantait si aucune image n'était jointe (variable
    // $nomImage non définie), la modification n'enregistrait jamais la date de
    // décompte, les images étaient rangées à deux endroits différents selon
    // qu'on créait ou qu'on modifiait, et une bannière supprimée n'apparaissait
    // plus dans la liste — le bouton « restaurer » était donc inatteignable.
    // ------------------------------------------------------------------

    /** Règles communes à la création et à la modification. */
    private function reglesBanniere(bool $imageObligatoire): array
    {
        return [
            'titre'          => 'required|string|max:150',
            'sous_titre'     => 'nullable|string|max:255',
            'num_ordre'      => 'required|integer|min:0|max:999',
            // 13/09/2026 : POPUP (fenêtre publicitaire de l'accueil) est un type valide depuis le 23/08
            'type_banniere'  => 'required|in:TOP,FLASH,BOTTOM,POPUP',
            'heure_decompte' => 'nullable|date',
            'image'          => ($imageObligatoire ? 'required|' : 'nullable|') . 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }

    private function messagesBanniere(): array
    {
        return [
            'titre.required'         => 'Le titre est obligatoire.',
            'num_ordre.required'     => "Le numéro d'ordre est obligatoire.",
            'type_banniere.required' => 'Choisissez le type de bannière.',
            'type_banniere.in'       => 'Le type de bannière doit être Top, Flash, Bottom ou Popup.',
            'image.required'         => 'Une image est obligatoire pour créer une bannière.',
            'image.max'              => "L'image ne doit pas dépasser 2 Mo.",
            'image.mimes'            => 'Formats acceptés : jpg, jpeg, png, webp.',
        ];
    }

    public function creationDeBanniere(){
        return view('gestionnaire.banniere',['banniere' => new Banniere]);
    }

    public function creationDeBanniereTraitement(Request $request){

        $donnees = $request->validate($this->reglesBanniere(true), $this->messagesBanniere());

        // Toutes les images de bannière vont dans storage/app/public/imageBanniere,
        // servi par le lien symbolique public/storage. L'ancienne création les
        // déposait ailleurs (productsBanniere) que la modification.
        $chemin = $request->file('image')->store('imageBanniere', 'public');

        Banniere::create([
            'titre'               => $donnees['titre'],
            'sous_titre'          => $donnees['sous_titre'] ?? null,
            'image'               => $chemin,
            'num_ordre'           => $donnees['num_ordre'],
            'type_banniere'       => $donnees['type_banniere'],
            'date_heure_decompte' => $donnees['heure_decompte'] ?? null,
            'statut'              => 1,
        ]);

        return redirect()->route('show.listeDesBannieres')->with('success','Bannière créée avec succès');
    }

    public function listeDesBannieres(){

        // withTrashed : les bannières mises à la corbeille doivent rester visibles,
        // sinon elles ne peuvent plus être ni restaurées ni supprimées définitivement.
        return view('gestionnaire.listBanniere',[
            'bannieres' => Banniere::withTrashed()
                ->orderBy('type_banniere')
                ->orderBy('num_ordre')
                ->get()
        ]);
    }

    public function supprimerPublierBanniere($id, $action){

        $banniere = Banniere::withTrashed()->find($id);

        if (!$banniere) {
            return redirect()->route('show.listeDesBannieres')->with('error','Bannière introuvable');
        }

        if($action == 'supprimer'){
            if($banniere->trashed()){
                $banniere->restore();
                $libelle = 'restaurée';
            } else {
                $banniere->delete();
                $libelle = 'mise à la corbeille';
            }
        } else {
            $banniere->update(['statut' => $banniere->statut == 1 ? 0 : 1]);
            $libelle = $banniere->statut == 1 ? 'republiée' : 'retirée de l\'affichage';
        }

        return redirect()->route('show.listeDesBannieres')->with('success',"Bannière $libelle avec succès");
    }

    /**
     * Suppression DÉFINITIVE : la ligne et son image disparaissent. Réservée aux
     * bannières déjà mises à la corbeille, pour éviter une perte en un seul clic.
     */
    // ==================================================================
    // DIAPOSITIVES DU CARROUSEL D'ACCUEIL
    // Même mécanique que les bannières : liste avec corbeille, création,
    // modification, retrait de l'affichage, suppression définitive.
    // ==================================================================

    private function reglesSlide(bool $imageObligatoire): array
    {
        return [
            'titre'            => 'required|string|max:150',
            'titre_accent'     => 'nullable|string|max:150',
            'description'      => 'nullable|string|max:1000',
            'badge_texte'      => 'nullable|string|max:100',
            'badge_type'       => 'required|in:NEUTRE,NEW,PROMO,HOT',
            'caracteristiques' => 'nullable|string|max:600',
            'deco_valeur'      => 'nullable|string|max:40',
            'deco_libelle'     => 'nullable|string|max:80',
            'bouton1_texte'    => 'nullable|string|max:60',
            'bouton1_lien'     => 'nullable|string|max:255',
            'bouton2_texte'    => 'nullable|string|max:60',
            'bouton2_lien'     => 'nullable|string|max:255',
            'num_ordre'        => 'required|integer|min:0|max:999',
            'image'            => ($imageObligatoire ? 'required|' : 'nullable|') . 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ];
    }

    private function messagesSlide(): array
    {
        return [
            'titre.required'      => 'Le titre est obligatoire.',
            'badge_type.required' => 'Choisissez un type de pastille.',
            'badge_type.in'       => 'Type de pastille invalide.',
            'num_ordre.required'  => "L'ordre d'affichage est obligatoire.",
            'num_ordre.integer'   => "L'ordre d'affichage doit être un nombre entier.",
            'image.required'      => "L'image de fond est obligatoire.",
            'image.image'         => 'Le fichier envoyé doit être une image.',
            'image.mimes'         => 'Formats acceptés : JPG, JPEG, PNG ou WEBP.',
            'image.max'           => "L'image ne doit pas dépasser 4 Mo.",
        ];
    }

    public function listeDesSlides(){

        // withTrashed : sans cela une diapositive mise à la corbeille disparaît
        // de la liste et ne peut plus être ni restaurée ni supprimée.
        return view('gestionnaire.listSlide',[
            'slides' => Slide::withTrashed()->orderBy('num_ordre')->orderBy('id')->get()
        ]);
    }

    public function creationDeSlide(){

        return view('gestionnaire.formSlide',[
            'slide' => new Slide(['badge_type' => 'NEUTRE', 'num_ordre' => (int) Slide::max('num_ordre') + 1]),
        ]);
    }

    public function creationDeSlideTraitement(Request $request){

        $donnees = $request->validate($this->reglesSlide(true), $this->messagesSlide());

        $donnees['image']  = $request->file('image')->store('imageSlide', 'public');
        $donnees['statut'] = 1;

        Slide::create($donnees);

        return redirect()->route('show.listeDesSlides')->with('success','Diapositive créée avec succès');
    }

    public function modificationDeSlidePage($id){

        $slide = Slide::withTrashed()->find($id);

        if (!$slide) {
            return redirect()->route('show.listeDesSlides')->with('error','Diapositive introuvable');
        }

        return view('gestionnaire.formSlide', ['slide' => $slide]);
    }

    public function modificationDeSlide(Request $request, $id){

        $slide = Slide::withTrashed()->find($id);

        if (!$slide) {
            return redirect()->route('show.listeDesSlides')->with('error','Diapositive introuvable');
        }

        $donnees = $request->validate($this->reglesSlide(false), $this->messagesSlide());

        if ($request->hasFile('image')) {
            // L'ancienne image n'est effacée que si elle avait été téléversée :
            // les visuels livrés avec le thème (frontend/...) sont partagés et
            // ne doivent jamais être supprimés.
            if ($slide->image && !str_starts_with($slide->image, 'frontend/')) {
                Storage::disk('public')->delete($slide->image);
            }
            $donnees['image'] = $request->file('image')->store('imageSlide', 'public');
        } else {
            unset($donnees['image']);
        }

        $slide->update($donnees);

        return redirect()->route('show.listeDesSlides')->with('success','Diapositive modifiée avec succès');
    }

    public function supprimerPublierSlide($id, $action = 'publier'){

        $slide = Slide::withTrashed()->find($id);

        if (!$slide) {
            return redirect()->route('show.listeDesSlides')->with('error','Diapositive introuvable');
        }

        if ($action == 'supprimer') {
            if ($slide->trashed()) {
                $slide->restore();
                $libelle = 'restaurée';
            } else {
                $slide->delete();
                $libelle = 'mise à la corbeille';
            }
        } else {
            $slide->update(['statut' => $slide->statut == 1 ? 0 : 1]);
            $libelle = $slide->statut == 1 ? 'remise en ligne' : 'retirée du carrousel';
        }

        return redirect()->route('show.listeDesSlides')->with('success',"Diapositive $libelle avec succès");
    }

    public function suppressionDefinitiveSlide($id){

        $slide = Slide::withTrashed()->find($id);

        if (!$slide) {
            return redirect()->route('show.listeDesSlides')->with('error','Diapositive introuvable');
        }

        if (!$slide->trashed()) {
            return redirect()->route('show.listeDesSlides')
                ->with('error','Mettez d\'abord cette diapositive à la corbeille avant de la supprimer définitivement.');
        }

        // Là encore, on ne touche pas aux visuels du thème.
        if ($slide->image && !str_starts_with($slide->image, 'frontend/')) {
            Storage::disk('public')->delete($slide->image);
        }
        $slide->forceDelete();

        return redirect()->route('show.listeDesSlides')->with('success','Diapositive supprimée définitivement');
    }

    public function suppressionDefinitiveBanniere($id){

        $banniere = Banniere::withTrashed()->find($id);

        if (!$banniere) {
            return redirect()->route('show.listeDesBannieres')->with('error','Bannière introuvable');
        }

        if (!$banniere->trashed()) {
            return redirect()->route('show.listeDesBannieres')
                ->with('error','Mettez d\'abord cette bannière à la corbeille avant de la supprimer définitivement.');
        }

        if ($banniere->image) {
            Storage::disk('public')->delete($banniere->image);
        }
        $banniere->forceDelete();

        return redirect()->route('show.listeDesBannieres')->with('success','Bannière supprimée définitivement');
    }

    public function modificationDeBannierePage($id){
        $banniere = Banniere::withTrashed()->find($id);

        if (!$banniere) {
            return redirect()->route('show.listeDesBannieres')->with('error','Bannière introuvable');
        }

        return view('gestionnaire.banniereUpdate',['banniere' => $banniere]);
    }

    public function modificationDeBanniere(Request $request, $id){

        $banniere = Banniere::withTrashed()->find($id);

        if (!$banniere) {
            return redirect()->route('show.listeDesBannieres')->with('error','Bannière introuvable');
        }

        $donnees = $request->validate($this->reglesBanniere(false), $this->messagesBanniere());

        $banniere->update([
            'titre'               => $donnees['titre'],
            'sous_titre'          => $donnees['sous_titre'] ?? null,
            'num_ordre'           => $donnees['num_ordre'],
            'type_banniere'       => $donnees['type_banniere'],
            // La date de décompte n'était jamais enregistrée à la modification.
            'date_heure_decompte' => $donnees['heure_decompte'] ?? null,
        ]);

        if($request->hasFile('image')){
            $ancienne = $banniere->image;
            $banniere->update([
                'image' => $request->file('image')->store('imageBanniere', 'public')
            ]);
            // L'ancienne image n'est effacée qu'APRÈS l'enregistrement de la nouvelle.
            if ($ancienne) {
                Storage::disk('public')->delete($ancienne);
            }
        }

        return redirect()->route('show.listeDesBannieres')->with('success','Bannière modifiée avec succès');
    }
    /**
     * [MÉTHODE MORTE] Aucune route ni aucun lien n'y mène (une bannière ne porte
     * pas de commentaire) ; elle affichait la vue des commentaires de blog.
     * Conservée par prudence, elle délègue simplement à la bonne méthode pour ne
     * pas tomber en erreur si un appel oublié refaisait surface.
     */
    public function commentaireBannieres($id){

        return $this->commentaireBlogs($id);
    }

    /**
     * Commentaires d'un article de blog (back-office).
     *
     * La méthode n'existait PAS alors que la route 'show.commentaireBlogs', le lien
     * de la liste des blogs ET les deux redirections de modération ci-dessous y
     * renvoyaient : chaque clic aboutissait à une erreur 500
     * (« Method commentaireBlogs does not exist »). La vue, elle, existait déjà.
     */
    public function commentaireBlogs($id){

        // Casse EXACTE de la classe (app/Models/blog.php déclare « class blog ») :
        // « Blog » passerait en local Windows mais donnerait « Class not found » en
        // production Linux.
        // withTrashed : on veut pouvoir consulter les commentaires d'un article mis
        // à la corbeille avant de le supprimer définitivement.
        $blog = blog::withTrashed()->find($id);

        if($blog == null){
            return redirect()->route('show.listeDesBlogs')->with('fail','Cet article de blog est introuvable');
        }

        // withTrashed sur les commentaires : sinon un commentaire mis à la
        // corbeille disparaît de l'écran et ne peut plus être restauré.
        $commentaires = $blog->commentaires()->withTrashed()->with('client')->latest()->get();

        return view('gestionnaire.commentaireBlog',[
            'blog' => $blog,
            'commentaires' => $commentaires,
        ]);
    }

    /**
     * Modération de tous les commentaires de blog, tous articles confondus.
     *
     * Il n'existait qu'un écran par article : pour savoir si un commentaire
     * attendait une validation, il fallait ouvrir chaque blog un par un. Les
     * nouveaux commentaires arrivent ici, en attente par défaut.
     */
    public function moderationCommentairesBlog(Request $request){

        $statut = $request->query('statut');

        // withTrashed sur le blog : un commentaire peut porter sur un article mis
        // à la corbeille, et la relation renverrait alors null.
        $requete = blog_commentaire::with(['client', 'blog' => fn($q) => $q->withTrashed()]);

        if ($statut === 'corbeille') {
            $requete->onlyTrashed();
        } elseif (in_array($statut, ['1','2','3'], true)) {
            $requete->where('statut', (int) $statut);
        }

        return view('gestionnaire.moderationCommentaireBlog',[
            'commentaires' => $requete->orderByDesc('created_at')->get(),
            'statutFiltre' => $statut,
            'nbEnAttente'  => blog_commentaire::enAttente()->count(),
            'nbPublies'    => blog_commentaire::publies()->count(),
            'nbRefuses'    => blog_commentaire::where('statut', blog_commentaire::REFUSE)->count(),
            'nbCorbeille'  => blog_commentaire::onlyTrashed()->count(),
        ]);
    }

    public function publierCommentaireBlog($id){

        $commentaire = blog_commentaire::find($id);

        // Commentaire déjà supprimé : ->update() sur null renvoyait une erreur 500.
        if($commentaire == null){
            return back()->with('fail','Ce commentaire est introuvable');
        }

        $commentaire->update([
            'statut' => blog_commentaire::PUBLIE
        ]);

        toastr()->success('Commentaire publié !');

        // back() plutôt qu'une redirection vers l'article : l'action est désormais
        // lancée depuis deux écrans (article et modération générale), et
        // $commentaire->blog->id tombait en erreur si l'article était à la corbeille.
        return back();
    }

    public function annulerCommentaireBlog($id){

        $commentaire = blog_commentaire::find($id);

        if($commentaire == null){
            return back()->with('fail','Ce commentaire est introuvable');
        }

        $commentaire->update([
            'statut' => blog_commentaire::REFUSE
        ]);

        toastr()->success('Commentaire refusé : il n\'est plus visible sur le site.');

        return back();
    }

    /**
     * Met un commentaire à la corbeille, ou l'en ressort.
     *
     * Distinct du refus : un commentaire refusé reste consultable en modération,
     * un commentaire à la corbeille n'apparaît plus dans les listes courantes.
     */
    public function supprimerCommentaireBlog($id){

        $commentaire = blog_commentaire::withTrashed()->find($id);

        if($commentaire == null){
            return back()->with('fail','Ce commentaire est introuvable');
        }

        if($commentaire->trashed()){
            $commentaire->restore();
            toastr()->success('Commentaire restauré.');
        } else {
            $commentaire->delete();
            toastr()->success('Commentaire mis à la corbeille.');
        }

        return back();
    }

    /** Suppression irréversible, réservée aux commentaires déjà à la corbeille. */
    public function suppressionDefinitiveCommentaireBlog($id){

        $commentaire = blog_commentaire::withTrashed()->find($id);

        if($commentaire == null){
            return back()->with('fail','Ce commentaire est introuvable');
        }

        if(!$commentaire->trashed()){
            return back()->with('fail','Mettez d\'abord ce commentaire à la corbeille.');
        }

        $commentaire->forceDelete();

        toastr()->success('Commentaire supprimé définitivement.');

        return back();
    }

    // Commentaire sur les produits
    public function publierCommentaire($id){
        $note = NoteProduit::find($id);

        $note->update([
            'statut' => 2
        ]);
        return redirect()->route('show.moderationCommentaire')->with('ok','Vous avez publié le commentaire');
    }

    public function annulerCommentaire($id){
        $note = NoteProduit::find($id);

        $note->update([
            'statut' => 3
        ]);
        return redirect()->route('show.moderationCommentaire')->with('no','Vous avez annulé le commentaire. Il ne sera pas vu par tout le monde');
    }

    public function moderationCommentaire(){
        return view('gestionnaire.moderationCommentaire',[
            'notes' => Noteproduit::all()
        ]);
    }

    public function ticketSAV(){
        // Relations chargées d'avance : la vue lit le client, la commande, le
        // produit et le destinataire de CHAQUE ligne. Sans cela, une liste de
        // cinquante tickets déclenchait deux cents requêtes.
        // Les plus récents d'abord : la liste sortait dans l'ordre des
        // identifiants, si bien qu'un ticket du jour se retrouvait en dernier.
        return view('gestionnaire.ticketSAV',[
            'tickets' => TicketSAV::with([
                    'client',
                    'detailCommande.commande',
                    'detailCommande.produit',
                    'agent',
                ])
                ->orderByDesc('created_at')
                ->get()
        ]);
    }

    public function ticketSAVTraitement(ticketSAV $ticket){
        // Bug : on listait type_user_id=8 (LIVREURS) au lieu de 7 (AGENTS SAV) pour
        // l'assignation d'un ticket SAV. Corrigé.
        //
        // La liste ne retenait ensuite QUE les agents SAV. Sans agent enregistré,
        // elle était vide : le ticket ne pouvait être assigné à personne, et
        // restait bloqué « en attente » sans que rien n'explique pourquoi.
        //
        // Elle comprend désormais aussi les administrateurs et les
        // gestionnaires. Ce n'est pas un contournement : l'espace de traitement
        // des tickets leur est DÉJÀ ouvert par le middleware
        // « auth.type:Admin,Gestionnaire,User_agent ». Un ticket qui leur est
        // assigné peut donc réellement être traité par eux.
        $roles = [
            \Help::$USER_AGENT_SAV   => 'Agents SAV',
            \Help::$USER_ADMIN       => 'Administrateurs',
            \Help::$USER_GESTIONNAIRE => 'Gestionnaires',
        ];

        // Groupés par rôle : la personne qui assigne doit savoir à qui elle
        // confie le ticket. L'ordre suit celui de $roles — agents SAV d'abord,
        // puisque c'est leur métier — et non l'ordre des identifiants de type,
        // qui aurait placé les administrateurs en tête.
        $ordre = array_flip(array_keys($roles));

        $agents = User::whereIn('type_user_id', array_keys($roles))
            ->whereNull('deleted_at')
            ->orderBy('nom_prenoms')
            ->get()
            ->groupBy('type_user_id')
            ->sortBy(fn ($personnes, $typeId) => $ordre[$typeId] ?? 99);

        return view('gestionnaire.ticcketTraite',[
            'agents' => $agents,
            'roles'  => $roles,
            'ticket' => $ticket
        ]);
    }

    public function ticketSAVTraitements(Request $request, ticketSAV $ticket){
        // dd($ticket, $request->agent);

        // L'identifiant était repris tel quel : une requête envoyée directement
        // pouvait confier le ticket à n'importe qui — un client, un livreur —
        // qui n'aurait jamais eu accès à l'espace de traitement. Le ticket
        // aurait alors disparu de la liste sans que personne ne puisse le
        // clore.
        $request->validate([
            'agent' => 'required|integer',
        ], [
            'agent.required' => 'Veuillez choisir la personne à qui confier ce ticket.',
        ]);

        $destinataire = User::whereIn('type_user_id', [
                \Help::$USER_AGENT_SAV, \Help::$USER_ADMIN, \Help::$USER_GESTIONNAIRE,
            ])
            ->whereNull('deleted_at')
            ->find($request->agent);

        if (!$destinataire) {
            return back()->with('error', "Cette personne ne peut pas traiter un ticket : choisissez un agent SAV, un administrateur ou un gestionnaire.");
        }

        $ticket->update([
            'user_id' => $destinataire->id,
            'statut' => 2
        ]);

        return redirect()->route('show.ticketSAV')
            ->with('success', 'Ticket confié à ' . $destinataire->nom_prenoms . '.');
    }

    // ===================== ESPACE AGENT SAV =====================
    // L'agent (type 7) voit les tickets qui LUI sont assignés et les clôture
    // (saisie de la solution + passage à "résolu").

    public function mesTicketsSAV(){
        return view('agent.mesTicketsSAV', [
            'tickets' => TicketSAV::with('client', 'detailCommande')
                ->where('user_id', Auth::id())
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function traiterTicketSAVPage(ticketSAV $ticket){
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            return redirect()->route('show.mesTicketsSAV')->with('error', "Ce ticket ne vous est pas assigné.");
        }
        $ticket->load('client', 'detailCommande');
        return view('agent.traiterTicketSAV', ['ticket' => $ticket]);
    }

    public function traiterTicketSAV(Request $request, ticketSAV $ticket){
        if ((int) $ticket->user_id !== (int) Auth::id()) {
            return redirect()->route('show.mesTicketsSAV')->with('error', "Ce ticket ne vous est pas assigné.");
        }
        $request->validate(
            ['solution' => 'required|string|min:3'],
            ['solution.required' => 'Veuillez décrire la solution apportée au client.']
        );
        $ticket->update([
            'solution_trouvee' => $request->solution,
            'est_traite'       => 1,
            'statut'           => 3, // résolu / clôturé
        ]);
        return redirect()->route('show.mesTicketsSAV')->with('success', 'Ticket clôturé : solution enregistrée.');
    }

    /**
     * Etat de livraison : les bons d'enlevement et ce qui a ete servi.
     *
     * La vue traverse, pour chaque bon, la livraison, la ligne de commande, la
     * commande, le client, le livreur et le fournisseur avec leurs comptes.
     * Rien n'etait precharge : chaque ligne du tableau declenchait sept
     * requetes, sur la totalite des bons jamais emis.
     *
     * Les bons annules n'ont rien a faire dans un etat de livraison.
     */
    public function reapprovisionnement(){

        $enlevements = Enlevement::with([
                'livraison.detailCommande.commande',
                'livraison.client',
                'livreur.user',
                'fournisseur.user',
            ])
            ->where('statut', Help::$STATUT_ACTIF)
            ->orderByDesc('created_at')
            ->get();

        // LE RÉAPPROVISIONNEMENT, qui donne son nom à l'écran et n'y figurait
        // pas : ni stock, ni seuil d'alerte. Le seuil est pourtant saisi sur
        // chaque ligne de stock, et 51 des 56 lignes actives en portent un.
        //
        // Un produit retiré du catalogue ne se réapprovisionne pas : son stock
        // n'est plus vendable, l'alerter n'apprendrait rien.
        $aReapprovisionner = StockProduit::with(['produit', 'fournisseur'])
            ->where('statut', Help::$STATUT_ACTIF)
            ->whereColumn('qte', '<=', 'seuil_alert')
            ->whereHas('produit', fn ($q) => $q->where('statut', Help::$STATUT_ACTIF))
            ->get()
            // Les ruptures d'abord, puis le plus gros manque : c'est l'ordre
            // dans lequel on passe les commandes.
            ->sortBy(fn ($s) => [$s->estEnRupture() ? 0 : 1, -$s->manquePourAtteindreLeSeuil()])
            ->values();

        return view('admin.livraisonR',[
            'enlevements'       => $enlevements,
            'aReapprovisionner' => $aReapprovisionner,
            'nbRuptures'        => $aReapprovisionner->filter->estEnRupture()->count(),
        ]);
    }

    public function listClient(){

        return view('admin.listClient',[
            'clients' => Client::where('statut',1)->where('client_a_terme',0)->get(),
            // Solde d'avance par client (point 19), en une requête.
            'soldesAvance' => \App\Models\AvanceClient::soldesParClient(),
        ]);
    }

    /**
     * Inscriptions commencées mais jamais confirmées.
     *
     * L'inscription depuis l'application mobile crée le compte ET la fiche client
     * en statut inactif, puis envoie un code par e-mail ; les deux passent à actif
     * quand le client saisit ce code. Tant qu'il ne l'a pas fait, sa fiche
     * n'apparaissait NULLE PART au back-office — les listes ne montrent que les
     * clients actifs.
     *
     * Personne ne pouvait donc savoir qu'un client s'était arrêté en chemin, ni le
     * relancer. Pire : son adresse restait prise, et une tentative de le
     * réinscrire se heurtait à « Cet Email est déjà utilisé » sans explication.
     *
     * Cet écran les rend visibles, avec leur ancienneté — c'est elle qui distingue
     * l'inscription d'il y a dix minutes, encore en cours, de celle d'il y a trois
     * semaines, définitivement abandonnée.
     */
    public function listClientEnAttente(){

        $clients = Client::with('user')
            ->where('statut', Help::$STATUT_INACTIF)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.listClientEnAttente', [
            'clients' => $clients,
        ]);
    }

    public function clientDocument(Client $client, string $type, string $mode = 'inline'){
        $path = $type === 'dfe' ? $client->dfe : $client->registre_commerce;
        $label = $type === 'dfe' ? 'DFE' : 'Registre de commerce';
        if (!$path) {
            return redirect()->route('show.listClient')->with('error', "Aucun fichier $label n'est associé à ce client.");
        }
        $absolute = Client::resolveStoragePath($path);
        if (!$absolute) {
            return redirect()->route('show.listClient')->with('error', "Le fichier $label de ce client est introuvable sur le serveur (chemin enregistré: $path).");
        }

        $original = basename($absolute);
        $clientName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $client->nom ?: ('client-'.$client->id));
        $labelFile = $type === 'dfe' ? 'DFE' : 'RegistreCommerce';
        $ext = pathinfo($original, PATHINFO_EXTENSION);
        $downloadName = $labelFile.'-'.$clientName.($ext ? '.'.$ext : '');

        $disposition = $mode === 'download' ? 'attachment' : 'inline';
        return response()->file($absolute, [
            'Content-Disposition' => $disposition.'; filename="'.$downloadName.'"',
        ]);
    }

    public function listClientATerme(){

        // LES CLIENTS DONT LE STATUT A ÉTÉ RETIRÉ RESTENT DANS LA LISTE.
        //
        // Sans eux, un client retiré disparaîtrait de l'écran — et le bouton
        // qui permet de lui rendre son statut deviendrait introuvable. Ils
        // figurent donc ici, marqués « Statut retiré ».
        $retires = [];

        if (\App\Models\DecisionClientTerme::tableExiste()) {
            $retires = \App\Models\DecisionClientTerme::where('type', \App\Models\DecisionClientTerme::DESACTIVATION)
                ->where('statut', \App\Models\DecisionClientTerme::APPLIQUEE)
                ->pluck('client_id')->unique()->all();
        }

        return view('admin.listClientATerme',[
            'clients'   => Client::where('statut', 1)
                ->where(fn ($q) => $q->where('client_a_terme', 1)->orWhereIn('id', $retires ?: [0]))
                ->get(),
            'decisions' => \App\Models\DecisionClientTerme::enAttente(),
            // Solde d'avance par client (point 19), en une requête.
            'soldesAvance' => \App\Models\AvanceClient::soldesParClient(),
        ]);
    }

    public function recapLivraison(Request $request){
        $du = $request->du ?? date('Y-01-01');
        $au = $request->au ?? date('Y-m-d');
        $enlevements = Enlevement::liste(null, null, null, $du, $au);
        return view('admin.recapLivraison',compact('enlevements'));
    }

    /**
     * État de parrainage : ce que les filleuls ont payé, regroupé par apporteur.
     *
     * L'écran listait une ligne par client sans jamais totaliser par apporteur —
     * alors que c'est la seule question qu'on lui pose : combien chaque
     * apporteur a-t-il fait entrer. Il n'avait pas davantage de total général.
     *
     * Sans période demandée, on montre tout l'historique : c'est ce que
     * l'écran faisait déjà, son filtre étant resté en commentaire.
     */
    public function etatParrainage(Request $request){
        $du = $request->input('du') ?: null;
        $au = $request->input('au') ?: null;

        $lignes = Paiement::statPaiementFilleule($du, $au);

        $parApporteur = $lignes->groupBy('apporteurId')->map(function ($clients) {
            $premier = $clients->first();

            return (object) [
                'id'      => $premier->apporteurId,
                'code'    => $premier->codeApporteur,
                'nom'     => $premier->apporteur,
                'clients' => $clients->sortByDesc(fn ($c) => (float) $c->total)->values(),
                'total'   => (float) $clients->sum(fn ($c) => (float) $c->total),
            ];
        })
            ->sortByDesc('total')
            ->values();

        return view('admin.etatparrainage', [
            'apporteurs'   => $parApporteur,
            'du'           => $du,
            'au'           => $au,
            'nbFilleuls'   => $lignes->count(),
            'totalGeneral' => (float) $lignes->sum(fn ($l) => (float) $l->total),
        ]);
    }

    /**
     * Chiffre d'affaires par famille de produits.
     *
     * L'écran s'appelle « par famille » : il regroupe donc par famille, avec un
     * sous-total par famille dont la somme retombe sur le total général.
     *
     * UN PRODUIT N'EST COMPTÉ QU'UNE FOIS. Vingt produits appartiennent à
     * plusieurs familles ; les faire figurer dans chacune ferait des sous-totaux
     * dont la somme dépasserait le chiffre d'affaires réel. Chaque produit est
     * donc rattaché à sa PREMIÈRE famille par ordre alphabétique — la colonne
     * « Famille » les cite toutes, pour que le rattachement reste lisible.
     *
     * CE QUI EST COMPTÉ. Seuls les bons VALIDÉS par le fournisseur, encore
     * actifs, à leur quantité SERVIE (Enlevement::quantiteAPayer) et au prix
     * réellement facturé — celui de la ligne de commande, qui porte le prix
     * personnalisé du client ; le prix moyen du produit ne sert que de repli.
     *
     * LA TVA. Elle était écrite en dur à 0 % dans la vue, et le « Montant TTC »
     * recopiait le HT : l'écran annonçait 16 750 F de TTC là où le taux
     * configuré (18 %) en donne 19 765. Le taux vient maintenant de la
     * configuration, comme partout ailleurs.
     *
     * UNE SEULE SOURCE PAR CHIFFRE. Le bandeau sommait `stock_produit` pendant
     * que la colonne « Quantité » sommait le pivot `produit_fournisseur` sans
     * filtrer ni le statut ni les lignes supprimées : les deux divergeaient dès
     * la première ligne de stock désactivée. Tout part désormais d'une seule
     * requête filtrée, et le bandeau est littéralement la somme des lignes.
     *
     * QUELS PRODUITS. Les produits actifs, plus tout produit désactivé ayant
     * vendu sur la période — un produit retiré du catalogue garde son chiffre
     * d'affaires, et le filtrer ferait disparaître de l'argent réellement
     * encaissé.
     */
    public function CAParFamille(Request $request){

        // Le fait générateur du chiffre d'affaires est le SERVICE du bon, pas sa
        // création : on filtre donc sur la date de validation du fournisseur.
        $du = $request->input('du') ?: null;
        $au = $request->input('au') ?: null;

        $tauxTva = (float) (Configuration::first()?->tva ?? 18);

        // MÊME RÈGLE QUE « CA DÉTAILLÉ ».
        //
        // Les locations n'entrent pas dans le chiffre d'affaires, et un bon
        // sans ligne de commande n'a pas de prix de vente : lui prêter celui du
        // catalogue gonflerait le CA d'une recette jamais facturée.
        $bonsServis = \App\Support\BonsDeVente::servis($du, $au)
            ->with(['livraison.detailCommande', 'produit'])
            ->get();

        $sansPrix = ['bons' => 0, 'qte' => 0.0];

        $ventes = [];

        foreach ($bonsServis as $bon) {
            if (!$bon->produit_id) {
                continue;
            }

            $qte = $bon->quantiteAPayer();

            $prix = \App\Support\BonsDeVente::prixFacture($bon);

            if ($prix === null) {
                $sansPrix['bons']++;
                $sansPrix['qte'] += $qte;
                continue;
            }

            $id = $bon->produit_id;

            $ventes[$id] = [
                'qte'     => ($ventes[$id]['qte'] ?? 0) + $qte,
                'montant' => ($ventes[$id]['montant'] ?? 0) + $qte * (float) $prix,
            ];
        }

        // Le stock en une requête, filtré, et servant à la fois au bandeau et à
        // la colonne : impossible qu'ils se contredisent.
        $stockParProduit = StockProduit::where('statut', Help::$STATUT_ACTIF)
            ->selectRaw('produit_id, SUM(qte) AS qte')
            ->groupBy('produit_id')
            ->pluck('qte', 'produit_id');

        $produits = Produit::with('categories')
            ->where(function ($q) use ($ventes) {
                $q->where('statut', Help::$STATUT_ACTIF)
                    ->orWhereIn('id', array_keys($ventes));
            })
            ->orderBy('nom')
            ->get();

        $familles = [];

        foreach ($produits as $produit) {
            $noms = $produit->categories->pluck('nom')->filter()->sort()->values();

            // Les produits sans famille ne disparaissent pas : ils sont
            // regroupés à part, faute de quoi leur chiffre d'affaires
            // manquerait au total.
            $principale = $noms->first() ?: 'Sans famille';

            $vente     = $ventes[$produit->id] ?? ['qte' => 0, 'montant' => 0];
            $qteStock  = (float) ($stockParProduit[$produit->id] ?? 0);
            $ht        = (float) $vente['montant'];

            if (!isset($familles[$principale])) {
                $familles[$principale] = [
                    'nom'       => $principale,
                    'lignes'    => [],
                    'qte'       => 0.0,
                    'qteVendue' => 0.0,
                    'ht'        => 0.0,
                ];
            }

            $familles[$principale]['lignes'][] = (object) [
                'id'        => $produit->id,
                'nom'       => $produit->nom,
                'familles'  => $noms->isEmpty() ? '—' : $noms->implode(', '),
                'qte'       => $qteStock,
                'qteVendue' => (float) $vente['qte'],
                'ht'        => $ht,
                'tva'       => $ht * $tauxTva / 100,
                'ttc'       => $ht * (1 + $tauxTva / 100),
            ];

            $familles[$principale]['qte']       += $qteStock;
            $familles[$principale]['qteVendue'] += (float) $vente['qte'];
            $familles[$principale]['ht']        += $ht;
        }

        ksort($familles, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($familles as $nom => $f) {
            $familles[$nom]['tva'] = $f['ht'] * $tauxTva / 100;
            $familles[$nom]['ttc'] = $f['ht'] * (1 + $tauxTva / 100);
        }

        $totalHt = array_sum(array_column($familles, 'ht'));

        $bonsDeLocation = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', Help::$STATUT_ACTIF)
            ->whereHas('livraison', fn ($q) => $q->where('provenance', Help::$LOCATION))
            ->when($du, fn ($q) => $q->where('fournisseur_validation', '>=', $du . ' 00:00:00'))
            ->when($au, fn ($q) => $q->where('fournisseur_validation', '<=', $au . ' 23:59:59'))
            ->count();

        return view('admin.chiffreDaffaire', [
            'sansPrix'        => $sansPrix,
            'bonsDeLocation'  => $bonsDeLocation,
            'familles'   => $familles,
            'du'         => $du,
            'au'         => $au,
            'tauxTva'    => $tauxTva,
            'qteTotal'   => array_sum(array_column($familles, 'qte')),
            'qteVendue'  => array_sum(array_column($familles, 'qteVendue')),
            'totalHt'    => $totalHt,
            'totalTva'   => $totalHt * $tauxTva / 100,
            'totalTtc'   => $totalHt * (1 + $tauxTva / 100),
        ]);
    }

    /**
     * Chiffre d'affaires détaillé, produit par produit, avec la marge.
     *
     * L'écran comptait les COMMANDES, à leur quantité DEMANDÉE, sans jamais
     * regarder si la marchandise était sortie. Il annonçait 910 255 F pour 56
     * unités là où « CA par famille » — qui, lui, ne compte que le servi — en
     * annonçait 16 750 pour 8. Deux écrans de chiffre d'affaires, un facteur 54
     * entre les deux. Les deux partent maintenant des mêmes bons servis.
     *
     * LA MARGE ÉTAIT UNE FICTION. Le coût fournisseur venait de
     * `detail_commande.prix_fournisseur`, colonne renseignée sur 2 lignes sur
     * 38 : trente-cinq lignes ressortaient donc en bénéfice intégral. Et l'une
     * des deux lignes renseignées porte 258 020 F l'unité pour un produit vendu
     * 50 F, ce qui écrasait à elle seule le total. Le coût vient désormais du
     * BON lui-même (Enlevement::montantHt), c'est-à-dire de ce que l'entreprise
     * doit réellement au fournisseur, à la quantité qu'il a servie.
     *
     * LA QUANTITÉ DEMANDÉE RESTE VISIBLE, en face de la quantité servie :
     * l'écart entre les deux est l'information que l'ancien écran noyait.
     *
     * Le tout HORS TAXES, et hors transport : le transport est facturé pour le
     * compte de l'entreprise et ne relève pas de la marge produit.
     */
    public function CADetaille(Request $request){

        // Même axe de temps que « CA par famille » : la date à laquelle le
        // fournisseur a servi le bon, et non celle de la prise de commande.
        $du = $request->input('du') ?: null;
        $au = $request->input('au') ?: null;

        // CET ÉCRAN MESURE LES VENTES.
        //
        // Les locations en sont écartées, et un bon dont la ligne de commande
        // a disparu n'a pas de prix de vente connu : la règle tient dans
        // App\Support\BonsDeVente, pour que les trois écrans qui mesurent le
        // chiffre d'affaires ne puissent plus diverger.
        $bonsServis = \App\Support\BonsDeVente::servis($du, $au)
            ->with(['livraison.detailCommande', 'produit.categories'])
            ->get();

        // CE QU'ON MET DE CÔTÉ, ET QU'ON ANNONCE.
        //
        // Ces marchandises sont bien sorties et bien payées au fournisseur,
        // mais on ne sait pas à quelle vente les rattacher. Leur prêter le prix
        // du catalogue inventait une recette : c'est ce qui affichait
        // « Marge brute HT −732 020 fcfa » le 04/09/2026.
        $sansPrix = ['bons' => 0, 'qte' => 0.0, 'cout' => 0.0];

        $lignes = [];

        foreach ($bonsServis as $bon) {
            if (!$bon->produit_id) {
                continue;
            }

            $qteServie = $bon->quantiteAPayer();

            // Le prix réellement facturé au client, qui porte son prix
            // personnalisé quand il en a un. Jamais celui du catalogue.
            $prix = \App\Support\BonsDeVente::prixFacture($bon);

            if ($prix === null) {
                $sansPrix['bons']++;
                $sansPrix['qte']  += $qteServie;
                $sansPrix['cout'] += $bon->montantHt();
                continue;
            }

            $id = $bon->produit_id;

            if (!isset($lignes[$id])) {
                $lignes[$id] = [
                    'id'          => $id,
                    'nom'         => $bon->produit?->nom ?? '—',
                    'categories'  => $bon->produit
                        ? ($bon->produit->categories->pluck('nom')->filter()->sort()->implode(', ') ?: '—')
                        : '—',
                    'qteDemandee' => 0.0,
                    'qteServie'   => 0.0,
                    'vente'       => 0.0,
                    'cout'        => 0.0,
                ];
            }

            $lignes[$id]['qteDemandee'] += (float) $bon->qte;
            $lignes[$id]['qteServie']   += $qteServie;
            $lignes[$id]['vente']       += $qteServie * (float) $prix;
            // Ce que le fournisseur est en droit de réclamer sur ce bon.
            $lignes[$id]['cout']        += $bon->montantHt();
        }

        // Le stock, filtré comme partout ailleurs : statut actif et lignes
        // vivantes. La requête brute ignorait `deleted_at`.
        $stock = StockProduit::where('statut', Help::$STATUT_ACTIF)
            ->selectRaw('produit_id, SUM(qte) AS qte')
            ->groupBy('produit_id')
            ->pluck('qte', 'produit_id');

        foreach ($lignes as $id => $ligne) {
            $lignes[$id]['dispo'] = (float) ($stock[$id] ?? 0);
            $lignes[$id]['marge'] = $ligne['vente'] - $ligne['cout'];
        }

        $lignes = array_values($lignes);
        usort($lignes, fn ($a, $b) => strnatcasecmp($a['nom'], $b['nom']));

        $stats = array_map(fn ($l) => (object) $l, $lignes);

        $totalVente = array_sum(array_column($lignes, 'vente'));
        $totalCout  = array_sum(array_column($lignes, 'cout'));

        // LES LOCATIONS, COMPTÉES POUR ÊTRE ANNONCÉES.
        //
        // On ne les additionne pas ici — elles ont leur propre écran — mais on
        // dit combien ont été mises de côté : un chiffre qui disparaît sans un
        // mot est un chiffre qu'on croit perdu.
        $locations = Enlevement::whereNotNull('fournisseur_validation')
            ->where('statut', Help::$STATUT_ACTIF)
            ->whereHas('livraison', fn ($q) => $q->where('provenance', Help::$LOCATION))
            ->when($du, fn ($q) => $q->where('fournisseur_validation', '>=', $du . ' 00:00:00'))
            ->when($au, fn ($q) => $q->where('fournisseur_validation', '<=', $au . ' 23:59:59'))
            ->count();

        return view('admin.CAdetaille', [
            'stats'            => $stats,
            'du'               => $du,
            'au'               => $au,
            'sansPrix'         => $sansPrix,
            'bonsDeLocation'   => $locations,
            'totalQteDemandee' => array_sum(array_column($lignes, 'qteDemandee')),
            'totalQteServie'   => array_sum(array_column($lignes, 'qteServie')),
            'totalDispo'       => array_sum(array_column($lignes, 'dispo')),
            'totalVente'       => $totalVente,
            'totalCout'        => $totalCout,
            'totalMarge'       => $totalVente - $totalCout,
        ]);
    }

    /**
     * Lignes de créance des clients à terme, pour l'état « Client à terme » et
     * la « Balance âgée ».
     *
     * LE DÛ EST CELUI DE LA FACTURE. Il était recalculé ici depuis TOUTE la
     * commande — quantité COMMANDÉE × prix, plus la totalité des frais de
     * livraison, remise ignorée. Or une facture est émise sur les enlèvements
     * réellement SERVIS, et une commande peut en produire plusieurs. La
     * créance dépassait donc ce qui avait été réclamé au client, se comptait
     * autant de fois que la commande avait de factures, et un reste subsistait
     * quoi qu'il paie : sa dette était insoldable.
     *
     * Le correctif existait déjà sur l'écran « Créances / Factures » sans avoir
     * jamais été reporté ici. Les deux écrans appellent désormais la même
     * méthode, Facture::totalAPayer(), pour qu'ils ne puissent plus diverger.
     *
     * LES CLIENTS SUSPENDUS RESTENT DUS. Le filtre sur le statut du client
     * faisait disparaître de l'état la créance d'un client désactivé : le
     * suspendre effaçait sa dette de la balance.
     */
    private function lignesCreanceTerme()
    {
        $clientsTerme = Client::where('client_a_terme', 1)->pluck('id');

        $factures = Facture::with(['client.user', 'commande.detailCommande', 'commande.client.user', 'paiements'])
            ->whereIn('client_id', $clientsTerme)
            ->orderByDesc('created_at')
            ->get();

        return $factures->map(function (Facture $f) {
            // Le client vient de la FACTURE : sur une facture de location,
            // `service_id` désigne une location et la relation commande()
            // ramènerait une commande sans rapport.
            $client = $f->client ?? $f->commande?->client;

            $totalAPayer = $f->totalAPayer();
            $totalPaye   = $f->montantPaye();

            return (object) [
                'facture'       => $f,
                'date'          => $f->created_at,
                'client'        => $client,
                'client_nom'    => $client?->display_name ?? '-',
                'client_id'     => $client?->id,
                'numero'        => $f->numero,
                'total_a_payer' => $totalAPayer,
                'montant_paye'  => $totalPaye,
                'reste'         => max(0, $totalAPayer - $totalPaye),
                'date_echeance' => $f->echeance(),
                'jours_retard'  => $f->joursRetard(),
                'est_echue'     => $f->estEchue(),
            ];
        });
    }

    public function clientATerme(){
        $lignes = $this->lignesCreanceTerme();

        return view('admin.clientATerme', [
            'lignes'       => $lignes,
            'totalFacture' => (float) $lignes->sum('total_a_payer'),
            'totalRegle'   => (float) $lignes->sum('montant_paye'),
            'totalSolde'   => (float) $lignes->sum('reste'),
        ]);
    }

    /** Les tranches d'ancienneté, dans l'ordre où elles s'affichent. */
    private const TRANCHES_BALANCE = [
        'non_echu', 't1_30', 't31_60', 't61_90', 't91_120', 't121_180', 't181_360', 't360_plus',
    ];

    /**
     * Balance âgée : les créances ouvertes, ventilées par ancienneté du retard.
     *
     * TROIS DÉFAUTS CORRIGÉS.
     *
     * 1. LE NON ÉCHU ÉTAIT COMPTÉ COMME DU RETARD. Une facture pas encore due a
     *    zéro jour de retard, tout comme une facture échue du jour : les deux
     *    tombaient dans « 0 à 30 jours ». La première tranche mélangeait donc
     *    ce qui est en souffrance et ce qui n'est même pas exigible. Le non
     *    échu a désormais sa colonne, et la première tranche de retard
     *    commence à 1 jour.
     *
     * 2. LE REGROUPEMENT SE FAISAIT SUR LE NOM DU CLIENT. Deux homonymes
     *    fusionnaient en une seule ligne, et toutes les factures dont le client
     *    est introuvable se retrouvaient agrégées sous « - ». On groupe sur
     *    l'identifiant, seul discriminant fiable.
     *
     * 3. AUCUN TOTAL PAR TRANCHE. L'état ne disait pas combien dormait à plus
     *    de 360 jours, ce qui est pourtant la question qu'on lui pose.
     */
    public function balanceAgee(){
        $lignes = $this->lignesCreanceTerme()->filter(fn($l) => (float) $l->reste > 0);

        $parClient = $lignes->groupBy('client_id')->map(function ($items) {
            $b = array_fill_keys(self::TRANCHES_BALANCE, 0.0);

            foreach ($items as $it) {
                $r = (float) $it->reste;

                if (!$it->est_echue) {
                    // Pas encore exigible : ce n'est pas du retard.
                    $b['non_echu'] += $r;
                    continue;
                }

                $j = max(1, (int) $it->jours_retard);

                if ($j <= 30)        $b['t1_30']     += $r;
                elseif ($j <= 60)    $b['t31_60']    += $r;
                elseif ($j <= 90)    $b['t61_90']    += $r;
                elseif ($j <= 120)   $b['t91_120']   += $r;
                elseif ($j <= 180)   $b['t121_180']  += $r;
                elseif ($j <= 360)   $b['t181_360']  += $r;
                else                 $b['t360_plus'] += $r;
            }

            $premiere = $items->first();

            $b['client']    = $premiere->client_nom;
            $b['client_id'] = $premiere->client_id;
            $b['total']     = array_sum(array_intersect_key($b, array_flip(self::TRANCHES_BALANCE)));

            return (object) $b;
        })
            // Le plus gros débiteur en tête : c'est l'ordre dans lequel on lit
            // une balance.
            ->sortByDesc('total')
            ->values();

        // Total de chaque tranche : sans lui, l'état ne répond pas à la seule
        // question qu'on lui pose vraiment.
        $totaux = [];
        foreach (self::TRANCHES_BALANCE as $tranche) {
            $totaux[$tranche] = (float) $parClient->sum($tranche);
        }

        return view('admin.balanceAgee', [
            'lignes'       => $parClient,
            'totaux'       => $totaux,
            'totalGeneral' => (float) $parClient->sum('total'),
        ]);
    }

    /**
     * Liste des dettes envers les apporteurs d'affaires.
     * Source de la dette : `apporteur->solde` (cumulé via commissions non payées).
     * Historique : commissions liées (CommissionApporteur).
     */
    public function dettesApporteurs(){
        $apporteurs = Apporteur::with(['user', 'commissions'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesApporteurs', [
            'apporteurs' => $apporteurs,
        ]);
    }

    /**
     * Liste des dettes envers les fournisseurs.
     * Source de la dette : `fournisseur->solde` (cumulé via enlèvements servis).
     */
    public function dettesFournisseurs(){
        $fournisseurs = Fournisseur::with(['user', 'enlevements.produit'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesFournisseurs', [
            'fournisseurs' => $fournisseurs,
        ]);
    }

    /**
     * Liste des dettes envers les livreurs.
     * Source de la dette : `livreur->solde` (cumulé via livraisons effectuées).
     */
    public function dettesLivreurs(){
        $livreurs = Livreur::with(['user', 'livraisons'])
            ->where('solde', '>', 0)
            ->orderByDesc('solde')
            ->get();

        return view('admin.dettesLivreurs', [
            'livreurs' => $livreurs,
        ]);
    }

    /**
     * Règlement total ou partiel d'une dette envers un apporteur, fournisseur ou livreur.
     *
     * Point 16 — Double validation : l'admin qui initie le règlement enregistre la
     * DemandePaiement avec paye=false et user_valide_id=Auth::id(). Le solde N'EST
     * PAS décrémenté tout de suite : il faudra qu'un AUTRE admin ouvre l'écran
     * "Demandes de paiement" et la valide en 2e (voir valideDemande).
     *
     * Inputs attendus :
     *  - type : 'apporteur' | 'fournisseur' | 'livreur'
     *  - tier_id : id de l'apporteur/fournisseur/livreur
     *  - montant : montant à régler
     *  - mode_paiement_id (optionnel) : id du mode de paiement
     *  - numero_compte (optionnel) : numéro de compte/téléphone utilisé
     */
    public function reglerDette(Request $request){
        $request->validate([
            'type' => 'required|in:apporteur,fournisseur,livreur',
            'tier_id' => 'required|integer',
            'montant' => 'required|numeric|min:0.01',
        ]);

        switch ($request->type) {
            case 'apporteur':
                $tier = Apporteur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesApporteurs';
                break;
            case 'fournisseur':
                $tier = Fournisseur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesFournisseurs';
                break;
            case 'livreur':
                $tier = Livreur::find($request->tier_id);
                $userId = $tier?->user_id;
                $route = 'show.dettesLivreurs';
                break;
            default:
                return back()->with('error', 'Type de tier invalide.');
        }

        if (!$tier) {
            return back()->with('error', 'Bénéficiaire introuvable.');
        }

        $montant = (float) $request->montant;

        if ($montant > (float) $tier->solde) {
            return back()->with('error',
                'Le montant à régler ('.number_format($montant, 0, ',', ' ').') dépasse la dette actuelle ('
                .number_format($tier->solde, 0, ',', ' ').').');
        }

        // Création d'une DemandePaiement EN ATTENTE de 2e validation.
        // Le solde du tier n'est décrémenté qu'au moment de la 2e validation
        // (cf. valideDemande), conformément au point 16.
        // L'agence vient de la personne connectée, jamais d'un choix : un
        // décaissement doit sortir de la caisse où il a réellement été fait.
        $agenceId = Auth::user()?->agence_id;
        if (!$agenceId) {
            return back()->with('error',
                "Vous n'êtes rattaché à aucune agence : un administrateur doit vous affecter à un guichet avant que vous puissiez régler une dette.");
        }

        DemandePaiement::create([
            'montant' => $montant,
            'numero' => Help::genererNumeroUnique('demande_paiement'),
            'mode_paiement_id' => $request->mode_paiement_id,
            'user_id' => $userId,
            'agence_id' => $agenceId,
            'user_valide_id' => Auth::id(),  // 1re validation (initiateur)
            'user_valide2_id' => null,       // en attente
            'date_validation' => null,
            'paye' => false,
            // Le solde du tiers N'est PAS débité ici : il le sera à la 2e validation.
            'solde_debite_initiation' => 0,
            'numero_compte' => $request->numero_compte,
        ]);

        return redirect()->route($route)->with('success',
            'Règlement de '.number_format($montant, 0, ',', ' ').' fcfa initié. '
            .'En attente de la 2e validation par un autre administrateur '
            .'(écran « Demandes de paiement »).');
    }

    /**
     * Liste des créances à terme : pour chaque client à terme, on calcule
     * le montant total facturé non réglé (créance) à partir de ses factures
     * et paiements rattachés.
     */
    public function creanceATermeListe(){
        $clientsATerme = Client::where('client_a_terme', 1)->with(['user'])->get();

        $lignes = $clientsATerme->map(function ($client) {
            $totalFacture = (float) Facture::where('client_id', $client->id)->sum('montant');
            $totalPaye    = (float) Paiement::where('client_id', $client->id)
                                ->where('statut', 1)
                                ->sum('montant_total');
            $solde        = $totalFacture - $totalPaye;
            return (object) [
                'client'        => $client,
                'totalFacture'  => $totalFacture,
                'totalPaye'     => $totalPaye,
                'solde'         => $solde,
            ];
        })->filter(fn($l) => $l->solde > 0)->values();

        return view('admin.creanceATermeListe', [
            'lignes' => $lignes,
        ]);
    }

    public function registerAdminPage(){
        // $roleAdmin = Role::where('name', 'admin')->first();
        // $roleAdmin->givePermissionTo("admin");

        // $roleUser = Role::where('name','gestionnaire')->first();
        // $roleUser->givePermissionTo("gest");

        // Agences proposées dès la création : sans rattachement, ce nouvel
        // administrateur ne pourra effectuer aucun encaissement.
        return view('admin.register', [
            'agences' => \App\Models\Agence::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get(),
        ]);
    }

    public function editGestionnaire(User $user){
        return view('admin.updateGestionnaire',[
            'user' => $user
        ]);
    }

    public function updateGestionnaire(Request $request, User $user){

        $contact = $request->contact;
        $typeUserId = TypeUser::where('nom', 'like', '%gestionnaire%')->value('id');

        if($request->hasFile('photo')){
            Storage::disk('public')->delete($user->photo);
            $photo = $request->validated('photo');
            $photo_path = $photo->store('imageUser','public');
        }

        if (isset($request->login)) {
            # code...
            $user->update([
                'login' => $request->login
            ]);
        }
        if (isset($request->email)) {
            # code...
            $user->update([
                'email' => $request->email
            ]);
        }
        if (isset($request->password)) {
            # code...
            $user->update([
                'password' => Help::HashPassword($request->password),
            ]);
        }

        // Ne mettre à jour que les champs effectivement fournis pour éviter
        // d'écraser une colonne NOT NULL (ex. contact) avec null si le form
        // a été soumis avec un champ vide.
        $payload = [];
        if ($request->filled('nom_prenoms')) $payload['nom_prenoms'] = $request->nom_prenoms;
        // Numéro normalisé — espaces retirés — puis borné à la taille de la
        // colonne : un numéro saisi avec indicatif et espaces la dépassait et
        // faisait échouer l'enregistrement en base par une erreur 500.
        if ($request->filled('contact'))     $payload['contact']     = mb_substr(preg_replace('/\s+/', '', (string) $request->contact), 0, 20);
        if ($request->filled('adresse'))     $payload['adresse']     = $request->adresse;
        if (!empty($payload)) {
            $user->update($payload);
        }




        // dd($contact);
       return redirect()->route('show.editGestionnaire',$user)->with('success','Modification effectuée');
    }

    public function registerAdmin(Request $request){

        $request->validate([
            'nom'      => 'required|string|max:100',
            'prenom'   => 'required|string|max:100',
            'contact'  => 'required|string|max:15',
            'email'    => 'required|email|unique:users,email',
            // 'login' retiré (09/09/2026) : généré automatiquement, comme le mot
            // de passe, et envoyé au nouvel administrateur par courriel.
            // Facultative : elle peut être décidée plus tard depuis la liste des
            // administrateurs. Tant qu'elle manque, il ne peut pas encaisser.
            'agence_id'=> 'nullable|integer|exists:agence,id',
            // 'password' retiré : généré automatiquement et envoyé par email.
            'photo'    => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
        ], [
            'email.unique'    => 'Cet email est déjà utilisé.',
            'contact.required'=> 'Le numéro de téléphone est obligatoire.',
            'photo.mimes'     => 'La photo doit être au format JPG, JPEG ou PNG.',
            'photo.max'       => 'La photo ne doit pas dépasser 2 Mo.',
        ]);

        // type_user_id = 2 (Admin) — utilise la constante au lieu d'une requête
        // qui pouvait renvoyer NULL si le libellé ne correspondait pas exactement.
        $typeUserId = Help::$USER_ADMIN;
        $nom_prenom = trim((string) ($request->display_name ?: ($request->prenom . ' ' . $request->nom)));

        // L'IDENTIFIANT DE CONNEXION EST GÉNÉRÉ (09/09/2026), comme pour un
        // fournisseur ou un livreur : le prénom et le nom, en minuscules sans
        // accent ni espace, et un suffixe tant que l'identifiant existe déjà
        // (comptes supprimés compris : un identifiant ne se réattribue pas).
        // Str::slug plutôt que le service « sluggable » du modèle, dont la
        // configuration tronque au premier mot (« ange » pour « Ange Émilie Kouassi »).
        $login = \Illuminate\Support\Str::slug(mb_substr($nom_prenom, 0, 60)) ?: 'admin';
        $base  = $login;
        $n     = 1;
        while (User::withTrashed()->where('login', $login)->exists()) {
            $login = $base . '-' . (++$n);
        }

        // Construire le numéro complet SANS redoubler l'indicatif : si l'utilisateur
        // a déjà saisi le numéro au format international (+225...) ou avec l'indicatif
        // en tête (225...), on ne re-préfixe pas. Évite "+225+225..." qui dépassait
        // varchar(15) -> erreur "Data too long" (1406) -> 500.
        $saisi           = preg_replace('/\s+/', '', (string) $request->contact);
        $digitsIndicatif = ltrim((string) ($request->indicatif ?: ''), '+');
        if (str_starts_with($saisi, '+')) {
            $contact = $saisi;
        } elseif ($digitsIndicatif !== '' && str_starts_with($saisi, $digitsIndicatif)) {
            $contact = '+' . $saisi;
        } else {
            $contact = ($request->indicatif ?: '') . $saisi;
        }

        // Upload optionnel de la photo de profil
        $nomImage = null;
        if ($request->hasFile('photo')) {
            $ext = $request->file('photo')->getClientOriginalExtension();
            $nomImage = 'image_admin_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $login)
                      . '_' . date('YmdHis') . '.' . $ext;
            $request->file('photo')->move(public_path('storage/imageUser'), $nomImage);
        }

        // Mot de passe GÉNÉRÉ automatiquement (celui qui crée le compte ne doit
        // pas le connaître) : envoyé au nouvel admin par email ci-dessous.
        $rawPassword = Help::ChaineAleatoire(8);

        $user = User::create([
            'nom_prenoms'  => $nom_prenom,
            'email'        => $request->email,
            'contact'      => $contact,
            'login'        => $login,
            // Important : utiliser Help::HashPassword (avec préfixe/suffixe sel)
            // car validLogin() vérifie via Help::HashVerifier qui attend ce format.
            // Hash::make() seul produirait un hash que HashVerifier refuserait.
            'password'     => Help::HashPassword($rawPassword),
            'photo'        => $nomImage,
            'type_user_id' => $typeUserId,
            // Agence de rattachement : elle décide du guichet auquel ses
            // encaissements seront imputés. Vide = il ne peut pas encaisser.
            'agence_id'    => $request->agence_id ?: null,
            'statut'       => 1,
        ]);

        // Envoi NON bloquant des identifiants générés. L'admin se connecte via
        // /login-account -> route('show.login'), d'où le type 'show' pour le bouton du mail.
        try {
            Mail::send(new MailAccesUsers($nom_prenom, $user->login, $rawPassword, $user->email, 'show'));
        } catch (\Throwable $e) {
            \Log::error('Erreur envoi email accès admin: ' . $e->getMessage());
        }

        // assignRole nécessite le package Spatie + le rôle 'admin' en DB.
        // On enveloppe dans un try/catch pour ne pas casser la création
        // si le rôle n'existe pas (l'utilisateur reste créé en DB avec type_user_id=2).
        try {
            $user->assignRole('admin');
        } catch (\Throwable $e) {
            // Silencieux : le compte est créé, type_user_id=2 suffit pour l'auth.
        }

        return redirect()->route('show.registerAdmin')
            ->with('ok', "Compte administrateur créé avec succès. L'identifiant « {$user->login} » et le mot de passe de {$nom_prenom} lui ont été envoyés par email.");
    }

    public function home (){

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonth()->endOfMonth();
        $today = $now->copy()->startOfDay();

        // DIX LIGNES NE SE CHERCHENT PAS, ET NE SE PAGINENT PAS.
        //
        // Le tableau porte desormais une recherche et une pagination : sur dix
        // lignes deja toutes visibles, l'une comme l'autre ne servaient a rien.
        // On en charge cinquante — assez pour que chercher ait un sens, assez
        // peu pour que le tableau de bord reste leger. « Voir tout » mene
        // toujours a la liste complete.
        $data['commandes'] = Commande::with(['client:id,nom,prenom'])
            ->where('statut', Help::$STATUT_ACTIF)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        $data['totalCommandes'] = Commande::where('statut', Help::$STATUT_ACTIF)->count();

        $data['gainMensuel'] = (float) LignePaiement::whereMonth('created_at', $now->format('m'))
            ->whereYear('created_at', $now->format('Y'))
            ->sum('montant');
        $data['gainMoisPrecedent'] = (float) LignePaiement::whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('montant');
        $data['evolutionGain'] = $data['gainMoisPrecedent'] > 0
            ? (($data['gainMensuel'] - $data['gainMoisPrecedent']) / $data['gainMoisPrecedent']) * 100
            : ($data['gainMensuel'] > 0 ? 100 : 0);

        $data['revenu'] = (float) LignePaiement::where('statut', Help::$STATUT_ACTIF)->sum('montant');
        $data['produit'] = Produit::count();
        $data['categorie'] = Categorie::count();

        // Stats commandes plus précises
        $data['commandesJour']        = Commande::whereDate('created_at', $today)->count();
        $data['commandesEnAttente']   = Commande::where('etat_commande', 'EN ATTENTE')->count();
        $data['commandesEnTraitement']= Commande::where('etat_commande', 'EN TRAITEMENT')->count();
        $data['commandesTerminees']   = Commande::where('etat_commande', 'TERMINEE')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Comptes
        $data['totalClients']      = Client::where('statut', 1)->count();
        $data['totalLivreurs']     = \App\Models\Livreur::count();
        $data['totalFournisseurs'] = \App\Models\Fournisseur::count();
        $data['totalApporteurs']   = \App\Models\Apporteur::count();

        // Top 5 produits les plus commandés
        $data['topProduits'] = \App\Models\DetailCommande::selectRaw('produit_id, SUM(qte) as total_qte')
            ->where('statut', 1)
            ->groupBy('produit_id')
            ->orderByDesc('total_qte')
            ->limit(5)
            ->with('produit')
            ->get();

        return view('layout.index',$data);

    }

    public function parametre(){

        $config = Configuration::first();

        // Données pour l'onglet "Prix personnalisés" (anciennement /configuration-prix)
        $clients = \App\Models\Client::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get();
        $produits = \App\Models\Produit::where('statut', \Help::$STATUT_ACTIF)->orderBy('nom')->get();
        $prixPersonnalises = \App\Models\PrixPersonnalise::with(['client', 'produit'])->whereHas('client')->get();

        // Prix de référence = le prix de vente officiel, celui du catalogue.
        // On y lisait le fournisseur le moins cher, sans marge : l'écran
        // comparait donc les tarifs négociés à un prix qui n'existait nulle part.
        $prixFournisseur = \App\Models\Produit::prixVenteParProduit();

        $clientsAvecPrix = $prixPersonnalises->groupBy('client_id')->map(function ($items) {
            $client = $items->first()->client;
            return (object) [
                'client' => $client,
                'produits' => $items->map(function ($item) {
                    return (object) [
                        'id' => $item->id,
                        'produit' => $item->produit,
                        'prix' => $item->prix,
                    ];
                }),
            ];
        });

        // ONGLET « AUDIT » — RÉSERVÉ AU SUPERADMINISTRATEUR ET À L'ADMINISTRATEUR.
        //
        // Le journal occupait un menu principal ; il est devenu un onglet de
        // cet écran le 29/08/2026. Sa lecture reste conditionnée au profil,
        // comme l'était le middleware de l'ancienne route : « Paramètre »
        // s'ouvre aussi au gestionnaire, qui n'a jamais eu accès au journal.
        //
        // La requête n'est lancée QUE pour ces profils : elle rapporte
        // jusqu'à 300 lignes, et rien ne justifie de la faire peser sur les
        // autres, qui ne verront jamais l'onglet.
        $peutVoirAudit = in_array(
            (int) Auth::user()->type_user_id,
            [(int) \Help::$USER_SA, (int) \Help::$USER_ADMIN],
            true
        );

        $journal = $peutVoirAudit
            ? \App\Models\Audit::journal(request()->only(['user_id', 'action', 'du', 'au', 'recherche']))
            : null;

        return view('layout.parametre', array_merge([
            'config' => $config,
            'user' => Auth::user(),
            'gestionnaires' => User::where('type_user_id', 3)->orWhere('type_user_id', 2)->orderByDesc('created_at')->get(),
            'clients' => $clients,
            'produits' => $produits,
            'clientsAvecPrix' => $clientsAvecPrix,
            'prixFournisseur' => $prixFournisseur,
            'peutVoirAudit' => $peutVoirAudit,
            // L'onglet à rouvrir au chargement (« ?onglet=audit »), pour que
            // filtrer le journal ne ramène pas sur « Configuration générale ».
            'ongletDemande' => request('onglet'),
        ], $journal ?? []));
    }

    public function parametreUpdate(Request $request){
        // dd($request->all());
        $config = Configuration::first();

        // Une case décochée n'est pas transmise : sans ce repli, désactiver la
        // TVA sur le transport serait impossible — le champ absent laisserait
        // l'ancienne valeur en place.
        $request->merge(['tva_transport' => $request->boolean('tva_transport') ? 1 : 0]);

        $config->update($request->only([
            'tva',
            'tva_transport',
            // Taux de l'AIRSI (10/09/2026).
            'taux_airsi',
            'montant_point',
            'montant_pour_un_point',
            'montant_minimum_a_payer',
            'email_tresorier',
            'email_directeur_marketing',
            'gestionnaire1_id',
            'gestionnaire2_id',
            'devise',
            'prixKm',
            'cout_livraison_min',
            // Mode de tarification du transport des ventes et locations.
            'livraison_sur_grille',
            'tonne_moyenne',
            'cout_liv_fixe',
            // Créances clients à terme
            'delai_relance_standard',
            'seuil_alerte_retard',
            // Comptant / agence
            'delai_max_paiement_agence',
            'delai_annulation_auto',
            // Livreurs
            'frequence_paiement_livreur',
            'jour_paiement_livreur',
            'forfait_base_livreur',
            // Apporteurs
            'taux_commission_standard',
            'delai_paiement_commission',
            // Contenu légal paramétrable (termes & conditions)
            'termes_conditions',
            // MENTIONS LEGALES DE L'ENTREPRISE.
            //
            // Ces colonnes existaient en base et les factures les LISAIENT
            // depuis toujours, mais aucun ecran ne permettait de les saisir :
            // les lignes s'imprimaient vides chez le client, et les valeurs
            // enregistrees s'etaient decalees d'une case sans que personne ne
            // puisse les corriger.
            //
            // Le NCC est le plus sensible : FneService en prefixe le numero de
            // chaque facture normalisee, et il est encode dans le QR code que
            // l'administration scanne.
            'raison_sociale',
            'ncc',
            'regime_imposition',
            'centre_impots',
            'rccm',
            'ref_bancaires',
            'adresse_siege',
            'telephone',
            'email_entreprise',
            'capital_social',
            'cnps',
        ]));

        return redirect()->route('show.parametre')->with('success','Les changements ont été appliqué avec succès ');
    }

    function deleteGestionnaire($id){
        $user = User::findOrFail($id);
        $user->delete(); // Soft delete

        return response()->json(['message' => 'Utilisateur désactivé avec succès.']);
    }

    function deleteAgent($id){

        $this->deleteUser($id);

        return response()->json(['message' => 'Utilisateur désactivé avec succès.']);
    }


    /*********************************************** Partie Private ********************************************** */
    private function generateCode(){

        $gCode = Help::ChaineAleatoire(5);
        $deja = Enlevement::where('code_enleve',  $gCode)->first();
        if(! is_null($deja)){
            // Le « return » manquait : en cas de collision, la fonction renvoyait null
            // et l'enlèvement était créé sans code -> bon invalidable par le fournisseur.
            return $this->generateCode();
        }else{
            return Str::start($gCode, 'ENV');
        }
    }

    private  function deleteUser($id){
        $user = User::findOrFail($id);
        $user->delete();
    }

    public function errorCatchBack(){
        return view('layout.errorCatchBack');
    }



}
