<?php

use App\Models\Paiement;
use Gloudemans\Shoppingcart\Cart;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\CartController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DevisController;
use App\Http\Controllers\ErrorController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\LivreurController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ApporteurController;
use App\Http\Controllers\GrandLivreController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\ConfigurationPrixController;
use App\Http\Controllers\ResetProcessController;
use App\Http\Controllers\CreanceClientTermeController;
use App\Http\Controllers\CommandeComptantController;
use App\Http\Controllers\DetteFournisseurController;
use App\Http\Controllers\DetteLivreurController;
use App\Http\Controllers\DetteApporteurController;
use App\Http\Controllers\RecapGlobalDettesController;
use App\Http\Controllers\AgenceController;
use App\Http\Controllers\RecapCreancesController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/storage-link', function () {

    if (function_exists('symlink')) {
        echo "symlink est activé.";
    } else {
        echo "symlink est désactivé.";
    }
    Artisan::call('storage:link');
});

Route::name('errors.')->controller(ErrorController::class)->group(function () {

    Route::get('/403', function () {
        return view('errors.403');
    })->name('403');

    Route::get('/404', function () {
        return view('errors.404');
    })->name('404');

    Route::get('/500', function () {
        return view('errors.500');
    })->name('500');
});


Route::get('/paiementaprescommande/', [PaiementController::class, 'paiementaprescommande'])->name('paiementaprescommande');

Route::get('/welcome', [UserController::class, 'welcome'])->name('welcome');

Route::get('/redirecting',[UserController::class, 'redirecting'])->name('redirect');

Route::get('/notify', [UserController::class, 'notify'])->name('notify');



Route::get('/pageError',[UserController::class,'error'])->name('notFound');

Route::get('/Site-en-contruction',[UserController::class,'enConstruction'])->name('enConstruction');
Route::get('/a-propos',[UserController::class,'pageAPropos'])->name('aPropos');
Route::get('/nous-contacter',[UserController::class,'pageContact'])->name('contact');
Route::post('/nous-contacter',[UserController::class,'contactStore'])->name('contact.store');
Route::get('/Error/Catch/back',[UserController::class,'errorCatchBack'])->name('errorCatch');
Route::get('/termes-et-conditions', [UserController::class, 'termesConditions'])->name('termesConditions');

// Route::get('/homepage', 'index')->name('index');

Route::get('/login-account', [UserController::class,'login'])->name('show.login');
Route::post('/login-account', [UserController::class,'validLogin']);

Route::get('/action-facture-{commande}-{facture}-{action}-{livraison}', [UserController::class,'actionFacture'])->name('show.actionFacture');
Route::delete('/logout', [UserController::class,'logout'])->name('show.logout');
// demande de paie

Route::get('/demande-de-paie', [UserController::class,'demandeDepaiePage'])->name('show.demandeDepaiePage');
Route::post('/demande-de-paiements', [UserController::class,'demandeDepaie'])->name('show.demandeDepaie');

// Profil utilisateur (accessible à tous les utilisateurs authentifiés)
Route::middleware('auth')->group(function () {
    Route::get('/mon-profil', [UserController::class, 'monProfil'])->name('show.monProfil');
    Route::post('/mon-profil', [UserController::class, 'monProfilUpdate'])->name('show.monProfilUpdate');
    Route::get('/centre-aide', [UserController::class, 'centreAide'])->name('show.centreAide');
});

// Espace AGENT SAV : accessible aux Agents SAV (type 7 = User_agent) EN PLUS des
// Admin/Gestionnaire. (Groupe séparé car le grand groupe show. ci-dessous est réservé
// à Admin,Gestionnaire, ce qui renvoyait un 403 à l'agent.)
Route::name('show.')->controller(UserController::class)->middleware('auth.type:Admin,Gestionnaire,User_agent')->group(function(){
    Route::get('/mes-tickets-sav', 'mesTicketsSAV')->name('mesTicketsSAV');
    Route::get('/traiter-ticket-sav-{ticket}', 'traiterTicketSAVPage')->name('traiterTicketSAVPage');
    Route::post('/traiter-ticket-sav-{ticket}', 'traiterTicketSAV')->name('traiterTicketSAV');
});

Route::name('show.')->controller(UserController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){

    Route::get('/preuve-operation-bancaire-{commande}', 'preuve')->name('preuve');
    Route::post('/preuve-operation-bancaire-{commande}', 'preuveValide')->name('preuveValide');
    Route::get('/admin-applique-tva-{client}', 'appliqueTVA')->name('appliqueTva');

    Route::get('/products', 'products')->name('products');
    Route::get('/bonAttente', 'bonAttente')->name('bonAttente');
    Route::get('/bonValides', 'bonValides')->name('bonValides');
    Route::get('/bon/{enlevement}/apercu', 'bonApercu')->name('bonApercu');
    Route::get('/bon/{enlevement}/telecharger', 'bonTelecharger')->name('bonTelecharger');

    //enregistrer un agent
    Route::get('/enregistrement-d-un-agent', 'AgentRegister')->name('AgentRegister');
    Route::post('/enregistrement-d-un-agent', 'agentRegistred')->name('agentRegistred');
    Route::get('/mis-a-jour-d-un-agent-{user}', 'AgentUpdate')->name('AgentUpdate');
    Route::post('/mis-a-jour-d-un-agent-{user}', 'AgentUpdated')->name('AgentUpdated');
    Route::get('/register-admin', 'registerAdminPage')->name('registerAdmin');
    Route::post('/register-adminn', 'registerAdmin')->name('registerAdminn');
    Route::get('/list-admin', 'listeAdmin')->name('listeAdmin');
    Route::post('/list-admin/{id}/toggle', 'toggleAdminStatus')->name('toggleAdminStatus');
    Route::delete('/list-admin/{id}', 'deleteAdmin')->name('deleteAdmin');


    Route::get('/reapprovisionnement','reapprovisionnement')->name('reapprovisionnement');
    Route::get('/gestionnaire/home', 'home')->name('home');
    Route::get('/list-client', 'listClient')->name('listClient');
    Route::get('/list-client-a-terme', 'listClientATerme')->name('listClientATerme');
    Route::get('/client/{client}/document/{type}/{mode?}', 'clientDocument')
        ->whereIn('type', ['dfe', 'rc'])
        ->whereIn('mode', ['inline', 'download'])
        ->name('clientDocument');
    Route::get('/recap-livraison', 'recapLivraison')->name('recapLivraison');
    Route::get('/etat-de-parrainage', 'etatParrainage')->name('etatParrainage');
    Route::get('/CA-par-famille', 'CAParFamille')->name('CAParFamille');
    Route::get('/CA-detaille', 'CADetaille')->name('CADetaille');
    Route::get('/Balance-agee', 'balanceAgee')->name('balanceAgee');
    Route::get('/client-a-terme', 'clientATerme')->name('clientATerme');
    Route::get('/creance-a-terme-liste', 'creanceATermeListe')->name('creanceATermeListe');

    // Créances clients à terme - écrans dédiés (Factures / Paiements / Relances / Synthèse)
    Route::get('/clients-terme/factures', [CreanceClientTermeController::class, 'factures'])->name('creancesTerme.factures');
    Route::get('/clients-terme/paiements', [CreanceClientTermeController::class, 'paiements'])->name('creancesTerme.paiements');
    Route::post('/clients-terme/paiements', [CreanceClientTermeController::class, 'storePaiement'])->name('creancesTerme.paiements.store');
    Route::post('/clients-terme/paiements/{paiement}/valider', [CreanceClientTermeController::class, 'validerPaiementClient'])->name('creancesTerme.paiements.valider');
    Route::get('/clients-terme/facture/{numero}/historique', [CreanceClientTermeController::class, 'factureHistorique'])->name('creancesTerme.facture.historique');
    Route::get('/clients-terme/recu/{paiement}', [CreanceClientTermeController::class, 'recu'])->name('creancesTerme.recu');
    Route::get('/clients-terme/recu/{paiement}/pdf', [CreanceClientTermeController::class, 'recuPdf'])->name('creancesTerme.recuPdf');
    Route::get('/clients-terme/relances', [CreanceClientTermeController::class, 'relances'])->name('creancesTerme.relances');
    Route::post('/clients-terme/relances', [CreanceClientTermeController::class, 'storeRelance'])->name('creancesTerme.relances.store');
    Route::get('/clients-terme/synthese', [CreanceClientTermeController::class, 'synthese'])->name('creancesTerme.synthese');

    // Commandes comptant (clients ordinaires) - écrans dédiés
    Route::get('/comptant/commandes', [CommandeComptantController::class, 'commandes'])->name('comptant.commandes');
    Route::get('/comptant/encaissements', [CommandeComptantController::class, 'encaissements'])->name('comptant.encaissements');
    Route::post('/comptant/encaissements', [CommandeComptantController::class, 'storeEncaissement'])->name('comptant.encaissements.store');
    Route::post('/comptant/encaissements/{paiement}/valider', [CommandeComptantController::class, 'validerEncaissement'])->name('comptant.encaissements.valider');
    Route::get('/comptant/commande/{numero}/historique', [CommandeComptantController::class, 'commandeHistorique'])->name('comptant.commande.historique');
    Route::get('/comptant/recu/{paiement}', [CommandeComptantController::class, 'recu'])->name('comptant.recu');
    Route::get('/comptant/recu/{paiement}/pdf', [CommandeComptantController::class, 'recuPdf'])->name('comptant.recuPdf');
    Route::get('/comptant/synthese', [CommandeComptantController::class, 'synthese'])->name('comptant.synthese');

    // Dettes fournisseurs - écrans dédiés (Enlèvements / Paiements / Synthèse)
    Route::get('/fournisseurs/enlevements', [DetteFournisseurController::class, 'enlevements'])->name('fournisseurs.enlevements');
    Route::get('/fournisseurs/paiements', [DetteFournisseurController::class, 'paiements'])->name('fournisseurs.paiements');
    Route::post('/fournisseurs/paiements', [DetteFournisseurController::class, 'storePaiement'])->name('fournisseurs.paiements.store');
    Route::post('/fournisseurs/paiements/{id}/valider', [DetteFournisseurController::class, 'validerPaiementFournisseur'])->name('fournisseurs.paiements.valider');
    Route::get('/fournisseurs/enlevement/{id}/historique', [DetteFournisseurController::class, 'enlevementHistorique'])->name('fournisseurs.enlevement.historique');
    Route::get('/fournisseurs/recu/{id}', [DetteFournisseurController::class, 'recu'])->name('fournisseurs.recu');
    Route::get('/fournisseurs/recu/{id}/pdf', [DetteFournisseurController::class, 'recuPdf'])->name('fournisseurs.recuPdf');
    Route::get('/fournisseurs/synthese', [DetteFournisseurController::class, 'synthese'])->name('fournisseurs.synthese');

    // Dettes livreurs - écrans dédiés (Livraisons / Paiements / Synthèse)
    Route::get('/livreurs/livraisons', [DetteLivreurController::class, 'livraisons'])->name('livreurs.livraisons');
    Route::get('/livreurs/paiements', [DetteLivreurController::class, 'paiements'])->name('livreurs.paiements');
    Route::post('/livreurs/paiements', [DetteLivreurController::class, 'storePaiement'])->name('livreurs.paiements.store');
    Route::post('/livreurs/paiements/{id}/valider', [DetteLivreurController::class, 'validerPaiementLivreur'])->name('livreurs.paiements.valider');
    Route::get('/livreurs/livraison/{id}/historique', [DetteLivreurController::class, 'livraisonHistorique'])->name('livreurs.livraison.historique');
    Route::get('/livreurs/recu/{id}', [DetteLivreurController::class, 'recu'])->name('livreurs.recu');
    Route::get('/livreurs/recu/{id}/pdf', [DetteLivreurController::class, 'recuPdf'])->name('livreurs.recuPdf');
    Route::get('/livreurs/synthese', [DetteLivreurController::class, 'synthese'])->name('livreurs.synthese');

    // Dettes apporteurs - écrans dédiés (Commissions / Paiements / Synthèse)
    Route::get('/apporteurs/commissions', [DetteApporteurController::class, 'commissions'])->name('apporteurs.commissions');
    Route::get('/apporteurs/paiements', [DetteApporteurController::class, 'paiements'])->name('apporteurs.paiements');
    Route::post('/apporteurs/paiements', [DetteApporteurController::class, 'storePaiement'])->name('apporteurs.paiements.store');
    Route::post('/apporteurs/paiements/{id}/valider', [DetteApporteurController::class, 'validerPaiementApporteur'])->name('apporteurs.paiements.valider');
    Route::get('/apporteurs/commission/{id}/historique', [DetteApporteurController::class, 'commissionHistorique'])->name('apporteurs.commission.historique');
    Route::get('/apporteurs/recu/{id}', [DetteApporteurController::class, 'recu'])->name('apporteurs.recu');
    Route::get('/apporteurs/recu/{id}/pdf', [DetteApporteurController::class, 'recuPdf'])->name('apporteurs.recuPdf');
    Route::get('/apporteurs/synthese', [DetteApporteurController::class, 'synthese'])->name('apporteurs.synthese');

    // Recap global dettes - 4 écrans (Tableau de bord + détails par catégorie)
    Route::get('/recap-dettes/tableau-bord', [RecapGlobalDettesController::class, 'tableauBord'])->name('recapDettes.tableauBord');
    Route::get('/recap-dettes/detail-fournisseurs', [RecapGlobalDettesController::class, 'detailFournisseurs'])->name('recapDettes.detailFournisseurs');
    Route::get('/recap-dettes/detail-livreurs', [RecapGlobalDettesController::class, 'detailLivreurs'])->name('recapDettes.detailLivreurs');
    Route::get('/recap-dettes/detail-apporteurs', [RecapGlobalDettesController::class, 'detailApporteurs'])->name('recapDettes.detailApporteurs');

    // CRUD Agences
    Route::get('/agences', [AgenceController::class, 'index'])->name('agences.index');
    Route::get('/agences/create', [AgenceController::class, 'create'])->name('agences.create');
    Route::post('/agences', [AgenceController::class, 'store'])->name('agences.store');
    Route::get('/agences/{agence}/edit', [AgenceController::class, 'edit'])->name('agences.edit');
    Route::put('/agences/{agence}', [AgenceController::class, 'update'])->name('agences.update');
    Route::delete('/agences/{agence}', [AgenceController::class, 'destroy'])->name('agences.destroy');
    Route::get('/agences/{agence}/toggle', [AgenceController::class, 'toggleStatut'])->name('agences.toggle');

    // CRUD Types de véhicules livreurs (Chantier B3)
    Route::get('/types-vehicules-livreurs', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'index'])->name('typeVehiculeLivreur.index');
    Route::get('/types-vehicules-livreurs/create', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'create'])->name('typeVehiculeLivreur.create');
    Route::post('/types-vehicules-livreurs', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'store'])->name('typeVehiculeLivreur.store');
    Route::get('/types-vehicules-livreurs/{typeVehiculeLivreur}/edit', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'edit'])->name('typeVehiculeLivreur.edit');
    Route::put('/types-vehicules-livreurs/{typeVehiculeLivreur}', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'update'])->name('typeVehiculeLivreur.update');
    Route::delete('/types-vehicules-livreurs/{typeVehiculeLivreur}', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'destroy'])->name('typeVehiculeLivreur.destroy');
    Route::get('/types-vehicules-livreurs/{typeVehiculeLivreur}/toggle', [App\Http\Controllers\TypeVehiculeLivreurController::class, 'toggleStatut'])->name('typeVehiculeLivreur.toggle');

    // CRUD Statuts métier (Chantier B2)
    Route::get('/statuts-metier', [App\Http\Controllers\StatutMetierController::class, 'index'])->name('statutMetier.index');
    Route::get('/statuts-metier/create', [App\Http\Controllers\StatutMetierController::class, 'create'])->name('statutMetier.create');
    Route::post('/statuts-metier', [App\Http\Controllers\StatutMetierController::class, 'store'])->name('statutMetier.store');
    Route::get('/statuts-metier/{statutMetier}/edit', [App\Http\Controllers\StatutMetierController::class, 'edit'])->name('statutMetier.edit');
    Route::put('/statuts-metier/{statutMetier}', [App\Http\Controllers\StatutMetierController::class, 'update'])->name('statutMetier.update');
    Route::delete('/statuts-metier/{statutMetier}', [App\Http\Controllers\StatutMetierController::class, 'destroy'])->name('statutMetier.destroy');
    Route::get('/statuts-metier/{statutMetier}/toggle', [App\Http\Controllers\StatutMetierController::class, 'toggleStatut'])->name('statutMetier.toggle');

    // Recap Global Créances (consolidation Terme + Comptant)
    Route::get('/recap-creances/tableau-de-bord', [RecapCreancesController::class, 'dashboard'])->name('recapCreances.dashboard');
    Route::get('/recap-creances/detail-terme', [RecapCreancesController::class, 'detailTerme'])->name('recapCreances.detailTerme');
    Route::get('/recap-creances/detail-comptant', [RecapCreancesController::class, 'detailComptant'])->name('recapCreances.detailComptant');

    // Dettes (apporteurs / fournisseurs / livreurs)
    Route::get('/dettes/apporteurs', 'dettesApporteurs')->name('dettesApporteurs');
    Route::get('/dettes/fournisseurs', 'dettesFournisseurs')->name('dettesFournisseurs');
    Route::get('/dettes/livreurs', 'dettesLivreurs')->name('dettesLivreurs');
    Route::post('/dettes/regler', 'reglerDette')->name('reglerDette');

    Route::get('/liste-gestionnaire', 'listeGestionnaire')->name('listeGestionnaire');
    Route::delete('/liste-gestionnaire/{id}', 'deleteGestionnaire')->name('deleteGestionnaire');

    Route::get('/liste-agent', 'listeAgent')->name('listeAgent');
    Route::delete('/liste-agent/{id}', 'deleteAgent')->name('deleteAgent');

    Route::get('/liste-de-demande-de-paiemennt-livreur', 'listeDeDemande')->name('listeDeDemandeLivreur');

    Route::get('/valide-demande-{id}-{type}-{reponse}', 'valideDemande')->name('valideDemande');
    Route::get('/liste-de-demande-de-paiemennt-apporteur', 'listeDeDemandeApporteur')->name('listeDeDemandeApporteur');
    Route::get('/liste-de-demande-de-paiemennt-fournisseur', 'listeDeDemandeFournisseur')->name('listeDeDemandeFournisseur');
    Route::get('/historique-demande-de-paiemennt', 'historiqueDemande')->name('historiqueDemande');

    Route::get('/liste-demande-client-a-terme','listeDemandeClient')->name('listeDemandeClient');
    Route::post('/Validation-{demande}-{rep}','validationDemande')->name('validationDemande')->whereIn('rep', ['1','2']);
    Route::get('/demande-client-terme/{demande}/document/{key}/{mode?}', 'demandeClientTermeDocument')
        ->whereIn('mode', ['inline', 'download'])
        ->name('demandeClientTermeDocument');
    Route::get('/demande-client-terme/{demande}/stats', 'demandeClientTermeStats')
        ->name('demandeClientTermeStats');

    // Demandes d'annulation (ventes + locations) déposées par les clients
    Route::get('/demandes-annulation', [\App\Http\Controllers\DemandeAnnulationController::class, 'liste'])->name('demandesAnnulation');
    Route::post('/demandes-annulation/{demande}/traiter', [\App\Http\Controllers\DemandeAnnulationController::class, 'traiter'])->name('traiterDemandeAnnulation');
    Route::get('liste-de-retour-produit','listeRetourProduit')->name('listeRetourProduit');
    Route::get('/traitement-retour-produit-{retour}','retourTraite')->name('retourTraite');
    Route::post('/valide-retour-{retour}','retourValide')->name('retourValide');
    Route::post('/failed-product-{retour}','refuseRetour')->name('retourRefuse');

    Route::get('/liste-demande-de-livraison','demandeLivraisonlist')->name('demandeLivraisonlist')->middleware('auth');
    Route::get('/liste-demande-de-livraison-traitee','demandeLivraisonTraitee')->name('demandeLivraisonTraitee')->middleware('auth');
    Route::get('/detail-demande-livraison-{demande}','detailDemandeLivraison')->name('detailDemandeLivraison')->middleware('auth');
    Route::get('/traite-livraison-page-{demandeLivraison}','traiteLivraisonPage')->name('traitelivraisonPage')->middleware('auth');
    Route::post('/traite-livraison-page-{demandeLivraison}-{detail}','traiteLivraison')->name('traitementLivraison')->middleware('auth');
    Route::get('selecion-vehicule-{id}-{detail}', 'selectionneVehicule')->name('selectCar')->middleware('auth');
    Route::post('/Valider-selection-vehicule','validerSelectionVehicule')->name('validerSelection');
    Route::get('/bloquer-compte-user-{id}-{type}','bloquerCompte')->name('bloquerCompte');
    Route::get('/commande-d-un-client-a-terme-{user}','clientDetailCommande')->name('clientDetailCommande');

    Route::get('/liste-paiement-par-client-{client}','listePaiementsParClient')->name('listePaiementsParClient');

    Route::get('/modification-gestionnaire-{user}','editGestionnaire')->name('editGestionnaire');
    Route::post('/modification-gestionnaire-{user}','updateGestionnaire')->name('updateGestionnaire');

    Route::get('/afficherStock/{idProduit}/{idFournisseur}', 'afficherStock')->name('afficherStock');

    Route::get('/ticket-SAV','ticketSAV')->name('ticketSAV');
    Route::get('/ticket-SAV-traitement-{ticket}','ticketSAVTraitement')->name('ticketSAVTraitement');
    Route::post('/ticket-SAV-traitement-{ticket}','ticketSAVTraitements')->name('ticketSAVTraitements');
    Route::get('/moderation-de-commentaire','moderationCommentaire')->name('moderationCommentaire');

    Route::get('/annuler-commentaire-client-{id}','annulerCommentaire')->name('annulerCommentaire');
    Route::get('/publier-commentaire-client-{id}','publierCommentaire')->name('publierCommentaire');

    Route::get('/creation-de-Banniere','creationDeBanniere')->name('creationDeBanniere');
    Route::post('/creation-de-Banniere','creationDeBanniereTraitement')->name('creationDeBanniereTraitement');
    Route::get('/liste-des-Bannieres','listeDesBannieres')->name('listeDesBannieres');
    Route::get('/Supprimer-Banniere-{id}-{action}','supprimerPublierBanniere')->name('supprimerPublierBanniere');
    Route::get('/modification-de-Banniere-{id}','modificationDeBannierePage')->name('modificationDeBannierePage');
    Route::post('/modification-de-Banniere-{id}','modificationDeBanniere')->name('modificationDeBanniere');

    Route::get('/creation-de-Blog','creationDeBlog')->name('creationDeBlog');
    Route::post('/creation-de-Blog','creationDeBlogTraitement')->name('creationDeBlogTraitement');
    Route::get('/liste-des-Blogs','listeDesBlogs')->name('listeDesBlogs');
    Route::get('/Supprimer-Blog-{id}','supprimerPublierBlog')->name('supprimerPublierBlog');
    Route::get('/modification-de-Blog-{id}','modificationDeBlogPage')->name('modificationDeBlogPage');
    Route::post('/modification-de-Blog-{id}','modificationDeBlog')->name('modificationDeBlog');
    Route::get('/commentaire-sur-les-blogs-{id}','commentaireBlogs')->name('commentaireBlogs');
    Route::get('/publier-commentaire-blog-{id}','publierCommentaireBlog')->name('publierCommentaireBlog');
    Route::get('/annuler-commentaire-blog-{id}','annulerCommentaireBlog')->name('annulerCommentaireBlog');

    Route::get('/liste-pays','listePays')->name('listePays');
    Route::get('/ajout-pays','ajoutPays')->name('ajoutPays');
    Route::post('/ajout-pays','ajoutPaysTraitement')->name('ajoutPaysTraitement');
    Route::get('/suppression-pays-{pays}','suppressionPays')->name('suppressionPays');
    Route::get('/modification-pays-{pays}','modificationPays')->name('modificationPays');
    Route::post('/modification-pays-{pays}','modificationPaysTraitement')->name('modificationPaysTraitement');

    Route::get('/liste-ville','listeVille')->name('listeVille');
    Route::get('/ajout-ville','ajoutVille')->name('ajoutVille');
    Route::post('/ajout-ville','ajoutVilleTraitement')->name('ajoutVilleTraitement');
    Route::get('/suppression-ville-{ville}','suppressionVille')->name('suppressionVille');
    Route::get('/modification-ville-{ville}','modificationVille')->name('modificationVille');
    Route::post('/modification-ville-{ville}','modificationVilleTraitement')->name('modificationVilleTraitement');

    Route::get('/livraison-en-cours','livraisonEnCours')->name('livraisonEnCours');
    Route::get('/livraison-validees','livraisonValidees')->name('livraisonValidees');
    Route::get('/livraison-historique','livraisonHistorique')->name('livraisonHistorique');

    Route::get('/liste-des-location-en-attente','listeLocationEnAttente')->name('listeLocationEnAttente');
    Route::post('/supprimer-location-{location}','supprimerLocation')->name('supprimerLocation');
    Route::get('/valider-location-page-{location}','validerLocationPage')->name('validerLocationPage');
    Route::post('/valider-location-{location}','validerLocation')->name('validerLocation');
    Route::get('/retour-location-page-{location}','retourLocationPage')->name('retourLocationPage');
    Route::post('/retour-location-{location}','retourLocation')->name('retourLocation');
    Route::get('/locations-traitees','locationsTraitees')->name('locationsTraitees');

    Route::get('/parametre','parametre')->name('parametre');
    Route::post('/parametre','parametreUpdate')->name('parametreUpdate');

    route::post('/modifier-prix_livraison_de_livreur-{livreur}','modifierPrixLivraison')->name('modifierPrixLivraison');
    Route::post('/modifier-zone-intervention-livreur-{livreur}','modifierZoneLivreur')->name('modifierZoneLivreur');

    Route::get('/creation-de-code-promo','creationDeCodePromo')->name('creationDeCodePromo');
    Route::post('/creation-de-code-promo','enregistrementDeCodePromo')->name('enregistrementDeCodePromo');
    Route::get('/suppresion-de-code-promo-{reduction}','suppressionDeCodePromo')->name('suppressionDeCodePromo');
    Route::get('/update-de-code-promo-{reduction}','updateDeCodePromo')->name('updateDeCodePromo');
    Route::post('/updated-de-code-promo-{reduction}','codeUpdated')->name('codeUpdated');

    Route::post('/retour-produit/{livraison}','retourProduit')->name('retourProduit');
    Route::post('/restaure-livraison/{livraison}','restaureLivraison')->name('restaureLivraison');

    Route::get('/les-regions', 'lesRegions')->name('lesRegions');
    Route::post('/les-regions', 'lesRegionsValid')->name('lesRegionsValid');
    Route::get('modifier-region-{region}', 'modifierRegion')->name('modifierRegion');
    Route::post('modifier-region-{region}', 'modifierRegionValid')->name('modifierRegionValid');
    Route::get('supprimer-region-{region}', 'supprimerRegion')->name('supprimerRegion');

    route::get('/sellers-list', 'sellersList')->name('listSeller');
    Route::get('/seller/{fournisseur}/document/{type}/{mode?}', 'fournisseurDocument')
        ->whereIn('type', ['dfe', 'rc'])
        ->whereIn('mode', ['inline', 'download'])
        ->name('fournisseurDocument');
    route::get('/sellers-list-pour-les-bon', 'sellersListPourBon')->name('listSellerPourBon');
    route::get('/seller/register', 'registerSeller')->name('registerSeller');
    route::post('/seller/register', 'store')->name('storeSeller');
    Route::get('/seller/{seller}/modify', 'editSellers')->name('editSellers');
    Route::post('/seller/{seller}/modify', 'updateSeller')->name('updateSeller');
    Route::get('/les-bons-par-fournisseur-{fournisseur}','bonParFournisseur')->name('bonParFournisseur');

    Route::post('/apporteur/pourcentage/{apporteur}', 'pourcentage')->name('pourcentage');
    Route::get('/apporteur/commissions', 'listeCommissionApporteur')->name('commissions');
    Route::get('/apporteur/list',  'listApporteur')->name('listApporteur');
    Route::get('/apporteur/profile/{apporteur}',  'profileApporteur')->name('profileApporteur');
    Route::post('/apporteur/profile/{apporteur}',  'modifPiece')->name('modifPiece');
    Route::get('/apporteur/{apporteur}/piece/{type}/{mode?}', 'apporteurPiece')
        ->whereIn('type', ['recto', 'verso'])
        ->whereIn('mode', ['inline', 'download'])
        ->name('apporteurPiece');


    Route::get('/livreur/list', 'listLivreur')->name('list');
    Route::get('/livreur/register',  'registerLivreur')->name('registerLivreur');
    Route::post('/livreur/register',  'storeLivreur')->name('storeLivreur');
    Route::get('/livreur/{livreur}/profile',  'profileLivreur')->name('profile');
    Route::post('/livreur/{livreur}/vehicule',  'ajoutVehiculeLivreur')->name('ajoutVehiculeLivreur');
    // Confirmation manuelle d'un paiement en ligne par un admin/gestionnaire (filet de sécurité).
    Route::post('/paiement/confirmer-manuel', [\App\Http\Controllers\PaiementEnLigne::class, 'confirmerPaiementManuel'])->name('confirmerPaiementManuel');
    Route::get('/livreur/{livreur}/piece/{type}/{mode?}', 'livreurPiece')
        ->whereIn('type', ['recto', 'verso'])
        ->whereIn('mode', ['inline', 'download'])
        ->name('livreurPiece');
});

// Création de compte gestionnaire (publique — accessible depuis /login-account)
Route::get('/register-account', [UserController::class, 'register'])->name('show.registerGestionnaire');
Route::post('/register-account', [UserController::class, 'storeUser']);

// CONFIGURATION DES PRIX PERSONNALISES
Route::name('configPrix.')->controller(ConfigurationPrixController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    Route::get('/configuration-prix', 'index')->name('index');
    Route::post('/configuration-prix', 'store')->name('store');
    Route::get('/configuration-prix/supprimer-produit/{id}', 'supprimerProduit')->name('supprimerProduit');
    Route::get('/configuration-prix/supprimer-client/{clientId}', 'supprimerClient')->name('supprimerClient');
});

// ROUTE FOR VILLES
route::get('/villes/region/{region}', [DestinationController::class,'villesDeRegion'])->name('villesDeRegion');
route::get('/region/villes/{ville}', [DestinationController::class,'regionVille'])->name('regionVille');
Route::get('/calcul/cout/livraison{long}/{lat}/{region}', [DestinationController::class,'calculCoutLivraison'])->name('calculCoutLivraison');

route::name('dest.')->controller(DestinationController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    Route::get('/les-villes', 'lesVilles')->name('lesVilles');
    Route::post('/les-villes', 'lesVillesValid')->name('lesVillesValid');
    Route::get('modifier-ville-{ville}', 'modifierVille')->name('modifierVille');
    Route::post('modifier-ville-{ville}', 'modifierVilleValid')->name('modifierVilleValid');
    Route::get('supprimer-ville-{ville}', 'supprimerVille')->name('supprimerVille');
});

// ROUTE FOR PRODUCTS
route::get('/{name}-liste-produits', [ProductsController::class, 'produitCategorie'])->name('product.categorie');

Route::name('product.')->controller(ProductsController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    route::get('/products-list', 'productsList')->name('list');
    route::get('/products-category', 'productsCategory')->name('category');
    route::post('/products-category', 'saveCategorie')->name('saveCategorie');
    route::get('/edit-category-{categorie}', 'editCategory')->name('editCategory');
    route::post('/edit-category-{categorie}', 'editCategoryTraitement')->name('editCategoryTraitement');
    route::get('/delete-category-{categorie}', 'deleteCategory')->name('deleteCategory');
    route::get('/products-add', 'productsAdd')->name('add');
    route::post('/products-add', 'saveProduct')->name('saveProduct');
    route::get('/products-edit/{produit}', 'edit')->name('edit');
    route::post('/products-edit/{produit}', 'update')->name('update');
    route::get('/products-delete/{produit}', 'delete')->name('delete');
    route::get('/product-toggle/{produit}', 'toggleStatut')->name('toggle');
});

// ROUTE FOR ORDERS
Route::name('orders.')->controller(OrdersController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    route::get('/orders-list', 'ordersList')->name('list');
    Route::get('/orders/bl/{bl}/{mode?}', 'fichierBlClient')
        ->whereIn('mode', ['inline', 'download'])
        ->name('fichierBlClient');
    Route::get('liste/des/devis','listeDesDevis')->name('listeDesDevis');
    Route::get('detail/devis/{devis}', 'detailDevis')->name('detailDevis');
    route::get('/commande-client-a-terme', 'clientATerme')->name('clientATerme');
    route::get('/commande-traitees', 'commandesTraitees')->name('commandesTraitees');
    route::get('/orders-details/{numero}', 'ordersDetails')->name('details');
    route::get('/orders-be/{numero}', 'BECommande')->name('BECommande');
    route::get('/orders-be/{numero}/pdf', 'BECommandePdf')->name('BECommande.pdf');
    route::post('/generer-facture/{commande}', 'genererFacture')->name('genererFacture');
    route::post('/recertifier-facture/{facture}', 'recertifierFacture')->name('recertifierFacture');
    route::get('/factures-non-validees', 'facturesNonValidees')->name('facturesNonValidees');
    route::get('/factures-validees', 'facturesValidees')->name('facturesValidees');
    route::post('/valider-facture-fne/{facture}', 'validerFactureFne')->name('validerFactureFne');
    // FNE pour les LOCATIONS (équivalent de genererFacture/valider pour les ventes).
    route::post('/generer-facture-location/{location}', 'genererFactureLocation')->name('genererFactureLocation');
    route::get('/facture-location-{facture}-{action?}', 'factureLocation')->name('factureLocation');
    route::get('/documentation/fne-integration', 'documentationFne')->name('documentationFne');
    route::get('/order-item/{id}', 'oderItemPage')->name('item');
    route::post('/order-item/{id}', 'oderItem')->name('oderItem');
    route::get('/transactions', 'transactions')->name('transactions');
    route::get('/traitement/{commande}', 'traitementPage')->name('traitement');
    route::post('/traitement/{commande}', 'traitement')->name('traitement.post');
    route::get('/reduction-{commande}', 'reduction')->name('reduction');
    route::post('/reduction-{commande}', 'reductionTraitement')->name('reductionTraitement');
    route::post('/confirmation/reduction-{commande}', 'confirmationReductionTraitement')->name('confirmationReductionTraitement');

    Route::post('/traitement-item-{commande}-{produit}','traitementItem')->name('traitementItem');
    route::get('/traitement/sans/livraison/{commande}', 'traitementSansLivraison')->name('traitement.sansLivraison');
    route::post('/traitement/sans/livraison/{commande}-{produit}', 'traitementItemSansLivraison')->name('traitement.sansLivraison.post');

    Route::get('/afficher-vehicule-livreur-{id}', 'afficherVehicule')->name('afficherVehicule');

});

// GRAND LIVRE
Route::name('grandLivre.')->controller(GrandLivreController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function (){
    Route::get('/grand-livre-livreur', 'grandLivreLivreur')->name('livreur');
    Route::get('/grand-livre-livreur-detail-{livreur}', 'livreurDetail')->name('livreurDetail');

    Route::get('/grand-livre-client-ordinaire', 'grandLivreClientOrdinaire')->name('clientOrdinaire');
    Route::get('/grand-livre-client-ordinaire-detail-{client}', 'clientOrdinaireDetail')->name('clientOrdinaireDetail');
    Route::get('/grand-livre-client-ordinaire-paiements-{client}', 'clientOrdinairePaiements')->name('clientOrdinairePaiements');
    Route::get('/grand-livre-client-ordinaire-factures-{client}', 'clientOrdinaireFactures')->name('clientOrdinaireFactures');

    Route::get('/grand-livre-client-client-a-terme', 'grandLivreClientATerme')->name('clientATerme');

    Route::get('/grand-livre-fournisseur-detail-{fournisseur}', 'detailFournisseur')->name('bonFournisseur');
    Route::get('/grand-livre-fournisseur', 'grandLivreFournisseur')->name('fournisseur');
});

//ROUTE FOR PAIEMENT
Route::get('/facture/{reference?}-{action?}',[PaiementController::class,'facture'])->name('paye.facture');
Route::post('effectuer/paiement/{client}', [PaiementController::class,'effectuerPaiementTraitement'])->name('paye.effectuerPaiementTraitement');
Route::name('paye.')->controller(PaiementController::class)->middleware('auth.type:Admin,Gestionnaire,client')->group(function (){

    // route::get('/paiement/liste/{commande}')->name('liste');
    route::get('/paiement/create/{commande}', 'paiementPage')->name('create');
    route::post('/paiement/create/{commande}','paiement')->name('store');
    Route::get('/paiement/liste','paiementList')->name('list');
    Route::get('/paiement-location-{location}','paiementLocation')->name('paiementLocation');
    Route::post('/paiement-location-{location}','paiementLocationTraitement')->name('paiementLocationTraitement');

    Route::get('/effectuer/paiement/{client}', 'effectuerPaiement')->name('effectuerPaiement');

    /// route::get()->name('');

});

// ROUTE FOR SELLERS
route::get('/seller/login', [SellerController::class,'loginPage'])->name('sellers.login');
route::post('/seller/login', [SellerController::class,'validLogin']);

Route::name('sellers.')->controller(SellerController::class)->middleware('auth.type:fournisseur')->group(function(){

    Route::get('/demande-de-paiement', 'demandeDepaie')->name('demandeDepaie');
    Route::post('/demande-de-paiement', 'demandeDepaieTraitement')->name('demandeDepaieTraitement');
    Route::get('/liste-des-demande-de-paiement', 'listePaiements')->name('listePaiements');

    Route::get('/demande-de-paiement-fournisseur', 'demandeDepaieFournisseur')->name('demandeDepaieFournisseur');
    Route::post('/demande-de-paiement-fournisseur', 'demandeDepaieFournisseurTraitement')->name('demandeDepaieFournisseurTraitement');

    route::get('/sellers-home', 'home')->name('home');

    Route::get('/seller/stock', 'stock')->name('stock')->middleware('auth');
    Route::get('/seller/add-products', 'addProducts')->name('add');
    Route::get('/seller/livraison', 'livraisons')->name('livraisons');
    Route::get('/seller/bon', 'bons')->name('bons');
    Route::post('/seller/bon', 'formBon')->name('formBon');
    route::get('/seller/bon/details/{code}', 'bonDetail')->name('bon.detail');
    Route::post('/seller/bon/imprime/{code}', 'bonImprime')->name('imprime');
    Route::post('/seller/bon/validation/{code}', 'bonValidation')->name('validate');
    Route::get('/seller/accepte', 'accepte')->name('accepte');
    Route::get('/seller/refuse', 'refuse')->name('refuse');
    Route::get('/seller/{seller}/profile', 'profile')->name('profile');
    Route::get('/seller/{product}/products', 'editProducts')->name('edit');
    Route::post('/seller/{product}/products', 'update')->name('update');

    // Route::post('/seller/update', 'updateSeller')->name('updateSeller');
    Route::get('/fin-de-stock','finDeStock')->name('finDeStock');
    Route::get('/parametre-fournisseur','parametreFournisseur')->name('parametreFournisseur');
    Route::post('/parametre-fournisseur','FournisseurUpdate')->name('FournisseurUpdate');

    // Route::get('/bon-{code}', 'afficheRecu')->name('afficheRecu');
});

//APPORTEUR D'AFFAIRE
Route::get('/apporteur/login',  [ApporteurController::class,'loginPage'])->name('apporteur.login');
Route::post('/apporteur/login',  [ApporteurController::class,'login']);
Route::get('/apporteur/register',  [ApporteurController::class, 'register'])->name('apporteur.register');
Route::post('/apporteur/register',  [ApporteurController::class, 'store'])->name('apporteur.store');
Route::get('/apporteur/code/de/confirmation/',  [ApporteurController::class, 'pageDeCode'])->name('apporteur.pageCode');
Route::post('/apporteur/code/de/confirmation/',  [ApporteurController::class, 'confirmationToken'])->name('apporteur.confirmationToken');
Route::name('apporteur.')->controller(ApporteurController::class)->middleware('auth.type:apporteur')->group(function(){
    Route::get('/apporteur/home', 'home')->name('home');

    Route::get('/apporteur/profile',  'profile')->name('profile');
    Route::get('/apporteur/solde',  'solde')->name('solde');
    Route::get('/apporteur/paiement',  'paiement')->name('paiement');
    Route::get('/confirmation/user_{token}', 'confirmation')->name('confirmePage');
    Route::get('/filleule-liste', 'filleule')->name('filleule');
    Route::get('/parametre-apporteur', 'parametreApporteur')->name('parametreApporteur');
    Route::post('/parametre-apporteur', 'ApporteurUpdate')->name('ApporteurUpdate');



});

//LIVREUR
Route::get('/livreur/login', [LivreurController::class,'loginPage'])->name('livreur.login');
Route::post('/livreur/login', [LivreurController::class,'login']);

Route::name('livreur.')->controller(LivreurController::class)->middleware('auth.type:livreur')->group(function(){

    Route::get('/livreur/bon', 'bon')->name('bon');
    Route::get('/livreur/bon/detail/{enlevement}', 'detailBon')->name('bon.detail');
    Route::post('/livreur/bon/validation/{enlevement}', 'bonValidation')->name('bon.validation');
    Route::post('/livreur/bon/imprime/{enlevement}', 'bonImprime')->name('bon.imprime');
    Route::get('/livreur/bon-validés', 'bonValides')->name('bonValides');
    Route::post('/livreur/bon-recherche', 'bonRecherche')->name('bonRecherche');
    Route::get('/livreur/livraison-validés', 'livraisonValides')->name('livraisonValides');
    Route::get('/livreur/livraison',  'livraison')->name('livraison');
    Route::get('/livreur/home','home')->name('home');

    Route::get('/livreur-disponible',  'livreurDisponible')->name('livreurDisponible');

    Route::get('/bon-{enlevement}',  'afficheBon')->name('afficheBon');
    Route::post('/validation-livraison', 'validationLivraison')->name('validationLivraison');
    Route::get('/liste-des-vehicule', 'listeVehicule')->name('listeVehicule');
    Route::get('/ajout-de-vehicule', 'ajoutVehiculePage')->name('ajoutVehiculePage');
    Route::post('/ajout-de-vehicule', 'ajoutVehicule')->name('ajoutVehicule');
    Route::get('/modification-de-vehicule-{vehicule}', 'modificationVehicule')->name('modificationVehicule');
    Route::post('/modification-de-vehicule-{vehicule}', 'modificationVehiculeTraitement')->name('modificationVehiculeTraitement');
    Route::get('/supression-de-vehicule-{vehicule}', 'supressionVehicule')->name('supressionVehicule');
    Route::get('/liste-des-demandes-de-paiement', 'listeDesDemandesDePaiement')->name('listeDesDemandesDePaiement');
    Route::get('/mis-en-route-{livraison}', 'enRoute')->name('enRoute');
    Route::get('/parametre-livreur', 'parametreLivreur')->name('parametreLivreur');
    Route::post('/parametre-livreur', 'parametreLivreurUpdate')->name('parametreLivreurUpdate');
    Route::get('/disponibilite-de-vehicule-{vehicule}','vehiculeDispo')->name('vehiculeDispo');
    Route::get('action-bon-enlevement-{enlevement}-{action}','actionBonEnlevement')->name('actionBonEnlevement');

});

// CLIENT
Route::get('/', [ClientController::class, 'accueil'])->name('client.index');
Route::get('/client/ajouter/{produit}', [ClientController::class, 'ajouterPanier'])->name('ajout.panier');
// Alias pour les vues Blade qui utilisent le nom `client.ajout.panier`
// (5 vues : home, accueil, infoProduit, search-produit, livewire/add-cart).
// Même handler, URL distincte pour éviter un conflit de routes nommées.
Route::get('/client/panier/ajout/{produit}', [ClientController::class, 'ajouterPanier'])->name('client.ajout.panier');
Route::get('/c-est-mon-panier', [ClientController::class, 'monPanier'])->name('client.monPanier');
Route::get('/search', [ClientController::class, 'search'])->name('client.search');
Route::get('/{produit}/description', [ClientController::class, 'produitInfo'])->name('client.produit.info');

Route::middleware('authorizedAuthUser.type:Client')->group(function () {
    Route::get('client/login', [ClientController::class,'loginPage'])->name('client.login');
    Route::post('client/login/client',[ClientController::class,'login'])->name('client.loginClient');
    Route::get('client/register', [ClientController::class, 'registerPage'])->name('client.register');
    Route::post('client/register', [ClientController::class, 'register'])->name('client.registerClient');
    Route::get('/client/ajouter/{produit}', [ClientController::class, 'ajouterPanier'])->name('ajout.panier');
    Route::get('/c-est-mon-panier', [ClientController::class, 'monPanier'])->name('client.monPanier');
    Route::get('/{produit}/description', [ClientController::class, 'produitInfo'])->name('client.produit.info');
    Route::get('/location-materiel-construction',[ClientController::class,'location'])->name('client.location');
    Route::get('/panier-produit-de-location',[ClientController::class,'panierLocation'])->name('client.panierLocation');
    Route::get('/nettoyer-panier', [ClientController::class,'nettoyerPanier'])->name('client.nettoyer');
    Route::get('/supprimer/{rowId}', [ClientController::class,'supprimerProduit'])->name('client.supprimer.produit');
    Route::post('/mis-a-jour', [ClientController::class,'ProduitUpdate'])->name('client.update.produit');
    Route::get('/Page/confirmation/token',[ClientController::class, 'pageToken'])->name('client.pageToken');
    Route::post('/Page/confirmation/token',[ClientController::class, 'confirmationToken']);
    Route::get('/search',[ClientController::class, 'search'])->name('client.search');
});

Route::controller(DevisController::class)->middleware('auth.type:client')->name('devis.')->group(function(){
    Route::get('/modification-de-devis-{devis}','editDevis')->name('editDevis');
    Route::get('/enregistre/modif/{devis?}','updateDevis')->name('updateDevis');
    Route::get('devis/annuler/modification/{devis}', 'annulerModificationDevis')->name('annulerModificationDevis');
    Route::match(['get', 'post'],'/recapitulatif-devis{devis?}','recapDevis')->name('recapDevis')->middleware('auth');
    Route::get('devis/mode/paiement/{devis}', 'modePaiement')->name('modePaiement');
});

Route::name('client.')->controller(ClientController::class)->middleware('auth.type:client')->group(function(){
        // Note: la syntaxe array [ClientController::class, 'cart'] est utilisée
        // au lieu de 'cart' pour éviter une collision case-insensitive avec
        // l'alias global `Cart` (Darryldecode\Cart\Facades\CartFacade). Sans cela,
        // au 2ème boot Laravel (cf. tests), class_exists('cart') retourne true et
        // Laravel tente de traiter 'cart' comme une classe invokable → exception.
        Route::get('/cart', [ClientController::class, 'cart'])->name('cart');
        Route::get('/client/accueil', 'accueil')->name('accueil');
        Route::get('/client/mon-panier', 'panierPage')->name('panier');
        Route::get('/incremente/{rowId}', 'incremente')->name('incremente');
        Route::get('/decremente/{rowId}', 'decremente')->name('decremente');
        Route::get('/confirmation/client_{token}', 'confirmationEmailClient')->name('confirmationEmailClient');

        Route::get('/blog','blog')->name('blog');
        Route::get('/detail-blog-{id}','detailBlog')->name('detailBlog');
        Route::post('/detail-blog-{id}','commentaireEtNote')->name('commentaireEtNote')->middleware('auth');
        Route::get('/demande-de-livraison','demandeLivraison')->name('demandeLivraison');

        Route::middleware('auth.type:client')->group(function () {
        Route::post('/recap-de-demande-de-livraison','recapLivraison')->name('recapLivraison');
        Route::get('/client/grand-livre', 'grandLivre')->name('grandLivre');
        Route::post('/afficher-montant','afficherMontant')->name('afficherMontant');

        Route::get('/export-commande','exportCommande')->name('exportCommande');
        Route::get('/export-de-demande-de-livraison','exportDemandeDeLivraison')->name('exportDemandeDeLivraison');
        Route::get('/export-de-demande-de-location','exportLocation')->name('exportLocation');

        Route::get('/paiement-valide-{code}','paiementValide')->name('paiementValide')->name('paiementValide');

        Route::get('/le-devis-valide-{devis}','devisValide')->name('devisValide');

        Route::get('/client/home', 'home')->name('home');
        Route::get('/client/devis', 'devis')->name('devis');
        Route::get('/client/commande', 'commande')->name('commande');
        Route::get('/client/liste-des-paiements-{etat}', 'listePaiementCommandeClientBE')->name('listePaiementCommande');

        Route::get('/panier-en-devis', 'panierDevis')->name('panierDevis');
        Route::get('/panier-en-commande{devis?}', 'panierCommande')->name('panierCommande')->middleware('auth');

        Route::get('/enregistrement-location-client','enregistrementLocation')->name('enregistrementLocation')->middleware('auth');
        Route::post('/devis-mode de paiement-{devis}', 'devisModeDePaiement')->name('devisModeDePaiement');
        Route::get('/devis-en-commande/{devis}', 'devisCommande')->name('devisCommande');

        Route::get('/devis-en-adresse-{devis}', 'devisAdresse')->name('devisAdresse')->middleware('auth');
        Route::get('/location-en-adresse', 'locationAdresse')->name('locationAdresse')->middleware('auth');
        Route::get('/detail-de-location-{location}','detailDeLocation')->name('detailDeLocation');

        Route::get('reference-bancaire{devis?}', 'referenceBancaire')->name('referenceBancaire');
        Route::post('reference-bancaire{devis?}', 'validationReference')->name('validationReference');

        Route::match(['get', 'post'],'/choix-des-dates-des-produits','choixDateProduitLocation')->name('choixDateProduitLocation');
        Route::match(['get', 'post'],'/mode-de-paiement-location','choixDateProduitLocationTraitement')->name('choixDateProduitLocationTraitement');
        // Route::post('/mode-de-paiement-location','choixDateProduitLocationTraitement')->name('choixDateProduitLocationTraitement');
        // Route::match(['get','post'],'/choix-des-dates-des-produits-traitement','choixDateProduitLocationTraitement')->name('choixDateProduitLocationTraitement');
        Route::get('/commande-en-adresse', 'commandeAdresse')->name('commandeAdresse')->middleware('auth');
        Route::match(['get','post'],'/recapitulatif-commande','recapCommande')->name('recapCommande')->middleware('auth');
        Route::post('/recapitulatif-commande-venant-dun-devis-{devis}','recapCommandeVenantDunDevis')->name('recapCommandeVenantDunDevis');

        Route::get('/location-validee-{location}','locationValidee')->name('locationValidee');
        Route::post('/recapitulatif-de-la-location','recapLocation')->name('recapLocation')->middleware('auth');
        // Route::post('/recapitulatif-devis-de-la-location','recapDevisLocation')->name('recapDevisLocation')->middleware('auth');

        Route::get('/commande-{numero}-validee','commandeValidee')->name('commandeValidee');
        Route::get('/liste-devis', 'listeDevis')->name('listeDevis');

        // Route::get('/liste-commande-{commande}', 'detailCommande')->name('detailCommande');
        // Route::get('/homepage', 'index')->name('index');

        Route::get('/client/paiement/verifie/{codePaiement}', 'verifiePaiement')->name('verifiePaiement');

        Route::get('/like/{id}','like')->name('like');
        Route::get('/like-plus/{id}','likePlus')->name('likePlus')->middleware('auth');
        Route::get('/wish-list', 'wishList')->name('wishList');

        Route::get('/mon-compte', 'monCompte')->name('monCompte')->middleware('auth');
        Route::get('/liste-des-paiements-{paye}', 'listeDesPaiements')->name('listeDesPaiements')->middleware('auth');
        Route::post('/modification', 'update')->name('update');
        Route::get('/validation-de-livraison-{commande}', 'validationLivraisonPage')->name('validationLivraisonPage');
        Route::get('/recuperation/produit/{commande}', 'recuperationProduit')->name('recuperationProduit');
        Route::get('action/recuperation-{livraison}-{action}', 'actionLivraison')->name('actionLivraison');

        // Route::post('/Validation-de-livraisonn', 'validationLivraison')->name('validationLivraison');
        Route::post('/valide-produit/{commande}/{produit}', 'ValideProduit')->name('ValideProduit');

        Route::get('/detail-demandeDe-livraison-{livraison}','detaiDemandeDeLivraison')->name('detaiDemandeDeLivraison');
        Route::match(['get', 'post'],'/mode-de-paiement', 'modeDePaiement')->name('modeDePaiement');
        Route::post('/commande-en-devis', 'CommandeEnDevis')->name('CommandeEnDevis');

        Route::get('/demande-de-client-a-terme','demandeClientATermePage')->name('demandeClientATermePage')->middleware('auth');
        Route::post('/demande-de-client-a-Terme','demandeClientATerme')->name('demandeClientATerme')->middleware('auth');
        Route::get('/retour-de-produit','retourProduitPage')->name('retourProduitPage')->middleware('auth');
        Route::get('/motif-retour-de-produit-{detail}','motifPage')->name('motifPage')->middleware('auth');
        Route::post('/motif-retour-{detail}','motif')->name('motif')->middleware('auth');

        Route::get('/demande-de-livraison-validee-{livraison}','livraisonValide')->name('livraisonValide')->middleware('auth');
        Route::get('/client-Validation-de-la-demande-de-livraison','valideDemande')->name('valideDemandeLivraison');
        Route::post('/avis-et-note-{produit}','avisNote')->name('avisNote')->middleware('auth');
        Route::get('/demande-de-suppression-de-compte','supprimerCompte')->name('supprimerCompte');
        Route::get('/ticket-de-service-apres-vente','ticketSAV')->name('ticketSAV');
        Route::get('/information-pour-ticket-service-apres-vente-{detail}','infoTicketSAV')->name('infoTicketSAV');
        Route::post('/creation-pour-ticket-service-apres-vente-{detail}','creationTicket')->name('creationTicket');
        Route::get('/mes-tickets-de-service-apres-vente','mesTicketsSAVClient')->name('mesTicketsSAV')->middleware('auth');
        Route::post('/appliquer-code-promo', 'appliquerCodePromo')->name('AppliquerCodePromo');
        Route::post('/appliquer-point-de-reduction', 'AppliquerPointDeReduction')->name('AppliquerPointDeReduction')->middleware('auth');

        Route::get('/validation-location-page','ValidationLocationPage')->name('ValidationLocationPage');
        Route::get('/process-annulation-commandes-{numero}','demandeAnnulationCommande')->name('demandeAnnulationCommande')->middleware('auth');
        Route::post('/process-annulation-commandes-{numero}','demandeAnnulationCommandeTraitement')->name('demandeAnnulationCommandeTraitement');

        Route::get('/modifier-adresse-de-livraison-{commande}','modifierAdresseLivraison')->name('modifierAdresseLivraison');
        Route::post('/modifier-adresse-de-livraison-{commande}','adresseLivraisonModifiee')->name('adresseLivraisonModifiee');

        Route::get('liste-des-facture-{commande}','listeFacture')->name('listeFacture');

        Route::get('/facture-commande-pdf-{numero}', 'nouvelleFacture')->name('nouvelleFacture');
        Route::get('/telecharger-facture-commande-pdf-{numero}', 'techargerFacture')->name('techargerFacture');
        Route::get('/facture-devis-pdf-{numero}', 'factureDevis')->name('factureDevis');
        Route::get('/etat-commande','etatCommande')->name('etatCommande');

        Route::get('/recuperer-les-unites', 'recupererLesUnites')->name('recupererLesUnites');
        Route::get('/facture-test','factureTest')->name('factureTest');

        Route::post('ajout-de-bon-de-commande','ajoutBonCommande')->name('ajoutBonCommande');

        Route::get('/gestion-des-vehicule', 'gestionVehicule')->name('gestionVehicule');
        Route::post('/gestion-des-vehicule','ajoutVehicule')->name('ajoutVehicule');

        Route::get('modifier-vehicule-{vehicule}', 'modifierVehicule')->name('modifierVehicule');
        Route::post('modifier-vehicule-{vehicule}', 'modifierVehiculeTraite')->name('modifierVehiculeTraite');

        Route::get('/supprimer-vehicule-{vehicule}', 'supprimerVehicule')->name('supprimerVehicule');
    });

})->middleware('auth.type:client');

Route::controller(TestController::class)->name('test.')->group(function(){
    Route::post('/recapitulatif-devis-de-la-location-a-partie-du-test','recapDevisLocation')->name('recapDevisLocation')->middleware('auth');

    Route::get('/select-multiple','select')->name('select');
    Route::get('/on-va-tester-un-wey','unWey')->name('unWey');
});

// Processus de mot de passe oublié
Route::controller(ResetProcessController::class)->group(function(){
    // demande et recuperation de l'email
    Route::get('/demandeEmail', 'demandeEmailPage')->name('demandeEmail');
    Route::post('/demandeEmail', 'demandeEmail');

    // demande et recuperation du code
    Route::get('/codeReset', 'codeResetPage')->name('code');
    Route::post('/codeReset', 'codeReset');

    // Recuperation du nouveau mot de passe
    Route::get('/passwordModify', 'passwordModifyPage')->name('passwordModify');
    Route::post('/passwordModify', 'passwordModify');
});

Route::get('/supprimeer', function () {
    // Cart::destroy();
    session_unset();
    \Cart::destroy();
    session()->forget('devisAModifier');
    return redirect()->route('client.index');
})->name('supprimerSession');

Route::post('/panier/update-quantite', [CartController::class, 'updateQuantite'])->name('panier.update.quantite');

Route::post('/panier/update-all', [CartController::class, 'updateAll'])->name('panier.update.all');

