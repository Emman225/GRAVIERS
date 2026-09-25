<?php

use App\Models\Paiement;
use Gloudemans\Shoppingcart\Cart;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuditController;
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
use App\Http\Controllers\PourcentageDalakounController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ApporteurController;
use App\Http\Controllers\GrandLivreController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\ConfigurationPrixController;
use App\Http\Controllers\ResetProcessController;
use App\Http\Controllers\CreanceClientTermeController;
use App\Http\Controllers\CommandeComptantController;
use App\Http\Controllers\AvanceClientController;
use App\Http\Controllers\DemandeLivraisonComptantController;
use App\Http\Controllers\LocationComptantController;
use App\Http\Controllers\GrilleTarifaireController;
use App\Http\Controllers\DetteFournisseurController;
use App\Http\Controllers\DetteLivreurController;
use App\Http\Controllers\DetteApporteurController;
use App\Http\Controllers\ComptabiliteController;
use App\Http\Controllers\RecapGlobalDettesController;
use App\Http\Controllers\DecisionClientTermeController;
use App\Http\Controllers\GrilleLivreurController;
use App\Http\Controllers\RecapVentesLocationsController;
use App\Http\Controllers\AgenceController;
use App\Http\Controllers\ParametrageComptableController;
use App\Http\Controllers\JetonsApiComptableController;
use App\Http\Controllers\EcrituresComptablesController;
use App\Http\Controllers\RapportsComptablesController;
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
})->middleware('auth.type:Admin,Gestionnaire'); // route technique : était PUBLIQUE (commande d'administration déclenchable par n'importe qui)

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

// [ROUTE DE TEST] publique, génère un PDF de la vue « test » et bloque le serveur
// plusieurs dizaines de secondes. Aucun lien ne l'utilise.
// Route::get('/redirecting',[UserController::class, 'redirecting'])->name('redirect');

Route::get('/notify', [UserController::class, 'notify'])->name('notify');



Route::get('/pageError',[UserController::class,'error'])->name('notFound');

Route::get('/Site-en-contruction',[UserController::class,'enConstruction'])->name('enConstruction');
// Lot 114 (19/09/2026) : la page du mode « site en construction » (interrupteur dans Paramètres).
Route::get('/site-en-construction', [UserController::class, 'pageSiteEnConstruction'])->name('siteEnConstruction');
// Lot 110 (17/09/2026) : téléchargement des applications Android (APK posés dans public/telechargements).
Route::get('/telecharger-application/{application}', [UserController::class, 'telechargerApplication'])
    ->name('telechargerApplication')->where('application', 'client|livreur|apporteur');
Route::get('/a-propos',[UserController::class,'pageAPropos'])->name('aPropos');
Route::get('/nous-contacter',[UserController::class,'pageContact'])->name('contact');
Route::post('/nous-contacter',[UserController::class,'contactStore'])->name('contact.store');

// Pages institutionnelles appelées depuis le pied de page. Elles renvoyaient
// toutes vers « Site en construction ».
Route::controller(\App\Http\Controllers\PagesPubliquesController::class)->group(function () {
    Route::get('/informations-livraisons', 'livraisons')->name('infosLivraisons');
    Route::get('/politique-de-confidentialite', 'confidentialite')->name('confidentialite');
    // Distinct de show.centreAide, qui est le centre d'aide du PERSONNEL et
    // exige d'être connecté : celui-ci s'adresse aux visiteurs.
    Route::get('/aide', 'centreAide')->name('centreAidePublic');
    Route::get('/devenir-livreur', 'devenirLivreur')->name('devenirLivreur');
    Route::get('/devenir-fournisseur', 'devenirFournisseur')->name('devenirFournisseur');
});

// Inscription à la lettre d'information depuis le pied de page du site.
// Limitée en cadence : l'adresse est publique et sans authentification.
Route::post('/inscription-lettre-information', [\App\Http\Controllers\NewsletterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.store');
Route::get('/Error/Catch/back',[UserController::class,'errorCatchBack'])->name('errorCatch');
Route::get('/termes-et-conditions', [UserController::class, 'termesConditions'])->name('termesConditions');

// Route::get('/homepage', 'index')->name('index');

Route::get('/login-account', [UserController::class,'login'])->name('show.login');
Route::post('/login-account', [UserController::class,'validLogin']);

// Facture FNE (document fiscal : nom du client, montants, NCC). Cette route était
// déclarée hors de tout groupe d'authentification : n'importe qui pouvait télécharger
// la facture de n'importe quel client en devinant les identifiants. Les seuls liens
// vers cette route sont dans « Mes factures » (client) et le grand livre (admin) —
// aucun email n'y renvoie, la protection ne casse donc aucun parcours.
Route::get('/action-facture-{commande}-{facture}-{action}-{livraison}', [UserController::class,'actionFacture'])
    ->middleware('auth.type:Admin,Gestionnaire,client')
    ->name('show.actionFacture');
Route::delete('/logout', [UserController::class,'logout'])->name('show.logout');
// Demande de paiement (fournisseur, apporteur, livreur).
// Ces deux routes étaient publiques alors que les deux méthodes lisent
// Auth::user()->type_user_id sans garde : un visiteur non connecté obtenait une
// erreur 500, et un POST non authentifié tombait au milieu d'un traitement qui
// débite un solde. Protection par les trois profils réellement concernés.
Route::middleware('auth.type:Fournisseur,Apporteur,Livreur')->group(function () {
    Route::get('/demande-de-paie', [UserController::class,'demandeDepaiePage'])->name('show.demandeDepaiePage');
    Route::post('/demande-de-paiements', [UserController::class,'demandeDepaie'])->name('show.demandeDepaie');
});

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
    // TVA sur le transport, retirable par client (10/09/2026).
    // Chemin distinct de « admin-applique-tva-{client} », déclaré avant et qui capturerait « transport-N ».
    Route::get('/admin-tva-transport-client-{client}', 'appliqueTvaTransport')->name('appliqueTvaTransport');

    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/products', 'products')->name('products');
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
    Route::get('/list-client-en-attente', 'listClientEnAttente')->name('listClientEnAttente');
    Route::get('/list-client-a-terme', 'listClientATerme')->name('listClientATerme');
    // Révision du plafond de crédit et du délai de paiement. En POST : cette
    // valeur engage l'entreprise et bloque les commandes au-delà, elle ne doit
    // pas pouvoir changer sur un simple lien visité.
    Route::post('/client-a-terme-{client}/plafond', 'modifierPlafondCredit')->name('modifierPlafondCredit');
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
    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    Route::post('/clients-terme/paiements/{paiement}/preuve', [CreanceClientTermeController::class, 'preuve'])->name('creancesTerme.paiements.preuve');
    Route::get('/clients-terme/paiements/{paiement}/preuve', [CreanceClientTermeController::class, 'voirPreuve'])->name('creancesTerme.paiements.voirPreuve');
    Route::post('/clients-terme/paiements/{paiement}/effectuer', [CreanceClientTermeController::class, 'effectuer'])->name('creancesTerme.paiements.effectuer');
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
    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    Route::post('/comptant/encaissements/{paiement}/preuve', [CommandeComptantController::class, 'preuve'])->name('comptant.encaissements.preuve');
    Route::get('/comptant/encaissements/{paiement}/preuve', [CommandeComptantController::class, 'voirPreuve'])->name('comptant.encaissements.voirPreuve');
    Route::post('/comptant/encaissements/{paiement}/effectuer', [CommandeComptantController::class, 'effectuer'])->name('comptant.encaissements.effectuer');
    Route::get('/comptant/commande/{numero}/historique', [CommandeComptantController::class, 'commandeHistorique'])->name('comptant.commande.historique');
    Route::get('/recu/{paiement}', [CommandeComptantController::class, 'recu'])->name('recu');
    Route::get('/recu/{paiement}/pdf', [CommandeComptantController::class, 'recuPdf'])->name('recuPdf');
    Route::post('/recu/{paiement}/envoyer', [CommandeComptantController::class, 'envoyerRecu'])->name('recu.envoyer');
    Route::get('/comptant/synthese', [CommandeComptantController::class, 'synthese'])->name('comptant.synthese');

    // Avances clients (point 19, 07/09/2026) : dépôt sans commande, double
    // validation, reçu RA-AAAA-NNN, imputation automatique sur les commandes
    // réglées « en agence ».
    Route::get('/avances', [AvanceClientController::class, 'index'])->name('avances.index');
    Route::post('/avances', [AvanceClientController::class, 'store'])->name('avances.store');
    Route::post('/avances/{id}/valider', [AvanceClientController::class, 'valider'])->name('avances.valider');
    Route::post('/avances/{id}/envoyer-recu', [AvanceClientController::class, 'envoyerRecu'])->name('avances.envoyerRecu');
    Route::get('/avances/{id}/recu', [AvanceClientController::class, 'recu'])->name('avances.recu');
    Route::get('/avances/{id}/recu/pdf', [AvanceClientController::class, 'recuPdf'])->name('avances.recuPdf');
    Route::get('/avances/solde/{client}', [AvanceClientController::class, 'soldeClient'])->name('avances.solde');

    // Demandes de livraison réglées en agence. Écran distinct de celui des
    // commandes : la caisse des ventes filtre en dur sur service = 'COMMANDE'.
    // Le reçu, lui, est partagé — il travaille à partir du paiement.
    Route::get('/comptant/livraisons/encaissements', [DemandeLivraisonComptantController::class, 'encaissements'])->name('comptant.livraisons.encaissements');
    Route::post('/comptant/livraisons/encaissements', [DemandeLivraisonComptantController::class, 'storeEncaissement'])->name('comptant.livraisons.encaissements.store');
    Route::post('/comptant/livraisons/encaissements/{paiement}/valider', [DemandeLivraisonComptantController::class, 'validerEncaissement'])->name('comptant.livraisons.encaissements.valider');
    // Point 20 : preuve du versement et finalisation par un troisième administrateur.
    Route::post('/comptant/livraisons/encaissements/{paiement}/preuve', [DemandeLivraisonComptantController::class, 'preuve'])->name('comptant.livraisons.encaissements.preuve');
    Route::get('/comptant/livraisons/encaissements/{paiement}/preuve', [DemandeLivraisonComptantController::class, 'voirPreuve'])->name('comptant.livraisons.encaissements.voirPreuve');
    Route::post('/comptant/livraisons/encaissements/{paiement}/effectuer', [DemandeLivraisonComptantController::class, 'effectuer'])->name('comptant.livraisons.encaissements.effectuer');

    // Locations réglées en agence. Le règlement se saisissait depuis la fiche de
    // la location, sans guichet, sans agence, sans reçu et SANS seconde
    // signature — seul flux d'encaissement du back-office dans ce cas.
    Route::get('/encaissements/locations', [LocationComptantController::class, 'encaissements'])->name('encaissements.locations');
    Route::post('/encaissements/locations', [LocationComptantController::class, 'storeEncaissement'])->name('encaissements.locations.store');
    Route::post('/encaissements/locations/{paiement}/valider', [LocationComptantController::class, 'validerEncaissement'])->name('encaissements.locations.valider');
    // Point 20 : preuve du versement et finalisation par un troisième administrateur.
    Route::post('/encaissements/locations/{paiement}/preuve', [LocationComptantController::class, 'preuve'])->name('encaissements.locations.preuve');
    Route::get('/encaissements/locations/{paiement}/preuve', [LocationComptantController::class, 'voirPreuve'])->name('encaissements.locations.voirPreuve');
    Route::post('/encaissements/locations/{paiement}/effectuer', [LocationComptantController::class, 'effectuer'])->name('encaissements.locations.effectuer');
    Route::get('/encaissements/locations/{numero}/historique', [LocationComptantController::class, 'locationHistorique'])->name('encaissements.locations.historique');

    // Grille tarifaire des demandes de livraison. Elle ne se modifiait
    // jusqu'ici qu'en base, ce qui la rendait inexploitable par l'entreprise.
    Route::get('/grille-tarifaire', [GrilleTarifaireController::class, 'index'])->name('grilleTarifaire');
    Route::post('/grille-tarifaire', [GrilleTarifaireController::class, 'store'])->name('grilleTarifaire.store');
    Route::post('/grille-tarifaire/{coutLivraison}', [GrilleTarifaireController::class, 'update'])->name('grilleTarifaire.update');
    Route::delete('/grille-tarifaire/{coutLivraison}', [GrilleTarifaireController::class, 'destroy'])->name('grilleTarifaire.destroy');

    // La grille de facturation d'UN livreur : meme structure que celle du
    // client, ce qui permet de lire la marge tranche par tranche.
    // Attribue les 96 tranches du catalogue a un livreur, d'un coup. Deposee
    // AVANT la route parametree : « /livreur-{livreur}/... » n'attraperait pas
    // celle-ci, mais l'ordre garde l'intention lisible.
    Route::post('/grille-livreur/pre-remplir', [GrilleLivreurController::class, 'preRemplir'])
        ->name('grilleLivreur.preRemplir');
    Route::get('/livreur-{livreur}/facturation', [GrilleLivreurController::class, 'index'])->name('grilleLivreur');
    Route::post('/livreur-{livreur}/facturation', [GrilleLivreurController::class, 'store'])->name('grilleLivreur.store');
    Route::post('/livreur-{livreur}/facturation/{tranche}', [GrilleLivreurController::class, 'update'])->name('grilleLivreur.update');
    Route::delete('/livreur-{livreur}/facturation/{tranche}', [GrilleLivreurController::class, 'destroy'])->name('grilleLivreur.destroy');

    // Dettes fournisseurs - écrans dédiés (Enlèvements / Paiements / Synthèse)
    Route::get('/fournisseurs/enlevements', [DetteFournisseurController::class, 'enlevements'])->name('fournisseurs.enlevements');
    Route::get('/fournisseurs/paiements', [DetteFournisseurController::class, 'paiements'])->name('fournisseurs.paiements');
    Route::post('/fournisseurs/paiements', [DetteFournisseurController::class, 'storePaiement'])->name('fournisseurs.paiements.store');
    Route::post('/fournisseurs/paiements/{id}/valider', [DetteFournisseurController::class, 'validerPaiementFournisseur'])->name('fournisseurs.paiements.valider');
    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    Route::post('/fournisseurs/paiements/{id}/preuve', [DetteFournisseurController::class, 'preuve'])->name('fournisseurs.paiements.preuve');
    Route::get('/fournisseurs/paiements/{id}/preuve', [DetteFournisseurController::class, 'voirPreuve'])->name('fournisseurs.paiements.voirPreuve');
    Route::post('/fournisseurs/paiements/{id}/effectuer', [DetteFournisseurController::class, 'effectuer'])->name('fournisseurs.paiements.effectuer');
    Route::get('/fournisseurs/enlevement/{id}/historique', [DetteFournisseurController::class, 'enlevementHistorique'])->name('fournisseurs.enlevement.historique');
    Route::get('/fournisseurs/recu/{id}', [DetteFournisseurController::class, 'recu'])->name('fournisseurs.recu');
    Route::get('/fournisseurs/recu/{id}/pdf', [DetteFournisseurController::class, 'recuPdf'])->name('fournisseurs.recuPdf');
    Route::get('/fournisseurs/synthese', [DetteFournisseurController::class, 'synthese'])->name('fournisseurs.synthese');

    // Dettes livreurs - écrans dédiés (Livraisons / Paiements / Synthèse)
    Route::get('/livreurs/livraisons', [DetteLivreurController::class, 'livraisons'])->name('livreurs.livraisons');
    Route::get('/livreurs/paiements', [DetteLivreurController::class, 'paiements'])->name('livreurs.paiements');
    Route::post('/livreurs/paiements', [DetteLivreurController::class, 'storePaiement'])->name('livreurs.paiements.store');
    Route::post('/livreurs/paiements/{id}/valider', [DetteLivreurController::class, 'validerPaiementLivreur'])->name('livreurs.paiements.valider');
    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    Route::post('/livreurs/paiements/{id}/preuve', [DetteLivreurController::class, 'preuve'])->name('livreurs.paiements.preuve');
    Route::get('/livreurs/paiements/{id}/preuve', [DetteLivreurController::class, 'voirPreuve'])->name('livreurs.paiements.voirPreuve');
    Route::post('/livreurs/paiements/{id}/effectuer', [DetteLivreurController::class, 'effectuer'])->name('livreurs.paiements.effectuer');
    Route::get('/livreurs/livraison/{id}/historique', [DetteLivreurController::class, 'livraisonHistorique'])->name('livreurs.livraison.historique');
    Route::get('/livreurs/recu/{id}', [DetteLivreurController::class, 'recu'])->name('livreurs.recu');
    Route::get('/livreurs/recu/{id}/pdf', [DetteLivreurController::class, 'recuPdf'])->name('livreurs.recuPdf');
    Route::get('/livreurs/synthese', [DetteLivreurController::class, 'synthese'])->name('livreurs.synthese');

    // Dettes apporteurs - écrans dédiés (Commissions / Paiements / Synthèse)
    Route::get('/apporteurs/commissions', [DetteApporteurController::class, 'commissions'])->name('apporteurs.commissions');
    Route::get('/apporteurs/paiements', [DetteApporteurController::class, 'paiements'])->name('apporteurs.paiements');
    Route::post('/apporteurs/paiements', [DetteApporteurController::class, 'storePaiement'])->name('apporteurs.paiements.store');
    Route::post('/apporteurs/paiements/{id}/valider', [DetteApporteurController::class, 'validerPaiementApporteur'])->name('apporteurs.paiements.valider');
    // Point 20 (09/09/2026) : preuve du versement, puis « Effectuée ».
    Route::post('/apporteurs/paiements/{id}/preuve', [DetteApporteurController::class, 'preuve'])->name('apporteurs.paiements.preuve');
    Route::get('/apporteurs/paiements/{id}/preuve', [DetteApporteurController::class, 'voirPreuve'])->name('apporteurs.paiements.voirPreuve');
    Route::post('/apporteurs/paiements/{id}/effectuer', [DetteApporteurController::class, 'effectuer'])->name('apporteurs.paiements.effectuer');
    Route::get('/apporteurs/commission/{id}/historique', [DetteApporteurController::class, 'commissionHistorique'])->name('apporteurs.commission.historique');
    Route::get('/apporteurs/recu/{id}', [DetteApporteurController::class, 'recu'])->name('apporteurs.recu');
    Route::get('/apporteurs/recu/{id}/pdf', [DetteApporteurController::class, 'recuPdf'])->name('apporteurs.recuPdf');
    Route::get('/apporteurs/synthese', [DetteApporteurController::class, 'synthese'])->name('apporteurs.synthese');

    // Recap global dettes - 4 écrans (Tableau de bord + détails par catégorie)
    Route::get('/recap-dettes/tableau-bord', [RecapGlobalDettesController::class, 'tableauBord'])->name('recapDettes.tableauBord');
    Route::get('/recap-dettes/detail-fournisseurs', [RecapGlobalDettesController::class, 'detailFournisseurs'])->name('recapDettes.detailFournisseurs');
    Route::get('/recap-dettes/detail-livreurs', [RecapGlobalDettesController::class, 'detailLivreurs'])->name('recapDettes.detailLivreurs');
    Route::get('/recap-dettes/detail-apporteurs', [RecapGlobalDettesController::class, 'detailApporteurs'])->name('recapDettes.detailApporteurs');

    // Comptabilité - états destinés à la déclaration et au pilotage de la marge.
    Route::get('/comptabilite/tva-collectee', [ComptabiliteController::class, 'tvaCollectee'])->name('comptabilite.tvaCollectee');
    Route::get('/comptabilite/airsi-collecte', [ComptabiliteController::class, 'airsiCollectee'])->name('comptabilite.airsiCollectee');
    Route::get('/comptabilite/benefices-livraisons', [ComptabiliteController::class, 'beneficesLivraisons'])->name('comptabilite.beneficesLivraisons');

    // Les deux récapitulatifs par AFFAIRE : « CA détaillé » répond par produit,
    // ceux-ci répondent par vente et par location — on ne relance pas un
    // produit, on relance un client sur une vente.
    Route::get('/comptabilite/recap-ventes', [RecapVentesLocationsController::class, 'ventes'])->name('comptabilite.recapVentes');
    Route::get('/comptabilite/recap-locations', [RecapVentesLocationsController::class, 'locations'])->name('comptabilite.recapLocations');

    // Les CAUTIONS ont leur propre etat : une caution n'est pas un produit mais
    // un depot detenu. Seule la retenue reste acquise et se declare ; le
    // restitue eteint une dette. Les melanger au recapitulatif des locations
    // aurait fausse la marge.
    Route::get('/comptabilite/etat-cautions', [RecapVentesLocationsController::class, 'cautions'])->name('comptabilite.etatCautions');

    // Seconde validation des décisions de crédit : accorder ou retirer le
    // statut de client à terme, réviser un plafond.
    Route::post('/decision-credit-{decision}/valider', [DecisionClientTermeController::class, 'valider'])->name('decisionCredit.valider');
    Route::post('/decision-credit-{decision}/refuser', [DecisionClientTermeController::class, 'refuser'])->name('decisionCredit.refuser');
    Route::post('/client-{client}/retirer-statut-terme', [DecisionClientTermeController::class, 'demanderRetrait'])->name('decisionCredit.retrait');
    Route::post('/client-{client}/rendre-statut-terme', [DecisionClientTermeController::class, 'demanderReactivation'])->name('decisionCredit.reactivation');

    // Journal d'audit — réservé au superadministrateur et à l'administrateur.
    // Le middleware refuse les autres profils : masquer l'entrée de menu
    // ne protège rien, l'adresse peut être tapée à la main.
    Route::get('/audit', [AuditController::class, 'index'])
        ->middleware('admin.seulement')
        ->name('audit.index');

    // Module « Écritures comptables », phase 1 : le paramétrage (lot 116, 21/09/2026).
    // Réservé aux administrateurs — les écritures sont produites par et pour DALAKOUN.
    Route::prefix('comptabilite')->name('comptabilite.')->middleware('admin.seulement')
        ->controller(ParametrageComptableController::class)->group(function () {
            Route::get('/parametrage', 'index')->name('parametrage');

            Route::get('/comptes/create', 'compteCreate')->name('comptes.create');
            Route::post('/comptes', 'compteStore')->name('comptes.store');
            Route::get('/comptes/{compte}/edit', 'compteEdit')->name('comptes.edit');
            Route::put('/comptes/{compte}', 'compteUpdate')->name('comptes.update');
            Route::post('/comptes/{compte}/basculer', 'compteBasculer')->name('comptes.basculer');
            Route::delete('/comptes/{compte}', 'compteDestroy')->name('comptes.destroy');

            Route::get('/journaux/create', 'journalCreate')->name('journaux.create');
            Route::post('/journaux', 'journalStore')->name('journaux.store');
            Route::get('/journaux/{journal}/edit', 'journalEdit')->name('journaux.edit');
            Route::put('/journaux/{journal}', 'journalUpdate')->name('journaux.update');
            Route::post('/journaux/{journal}/basculer', 'journalBasculer')->name('journaux.basculer');
            Route::delete('/journaux/{journal}', 'journalDestroy')->name('journaux.destroy');

            Route::post('/parametrage/familles', 'famillesUpdate')->name('familles.update');
            Route::post('/parametrage/produits', 'produitsUpdate')->name('produits.update');
            Route::post('/parametrage/produits/en-un-clic', 'produitsEnUnClic')->name('produits.enUnClic');
            Route::post('/parametrage/rubriques', 'rubriquesUpdate')->name('rubriques.update');
            Route::post('/parametrage/tiers', 'tiersUpdate')->name('tiers.update');
            Route::post('/parametrage/tiers/generer', 'tiersGenerer')->name('tiers.generer');
            Route::get('/parametrage/plan-sage', 'exporterPlanSage')->name('plan.sage');
            Route::get('/parametrage/modele-import', 'modeleImport')->name('import.modele');
            Route::post('/parametrage/import/analyser', 'analyserImport')->name('import.analyser');
            Route::post('/parametrage/import/appliquer', 'appliquerImport')->name('import.appliquer');
            Route::post('/parametrage/modes', 'modesUpdate')->name('modes.update');
            Route::post('/parametrage/reglages', 'reglagesUpdate')->name('reglages.update');
        });

    // Phase 4 (lot 121, 22/09/2026) : les jetons de l'API comptable et sa documentation.
    Route::prefix('comptabilite/jetons-api')->name('comptabilite.jetonsApi.')->middleware('admin.seulement')
        ->controller(JetonsApiComptableController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('/{jeton}', 'revoquer')->name('revoquer');
        });

    // Phase 3 (lot 119, 22/09/2026) : le journal des écritures, les anomalies et la transmission.
    // La route de détail vient EN DERNIER : « /anomalies » et « /apercu » lui ressemblent,
    // et un paramètre attrape tout ce qui passe avant lui.
    Route::prefix('comptabilite/ecritures')->name('comptabilite.ecritures.')->middleware('admin.seulement')
        ->controller(EcrituresComptablesController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/anomalies', 'anomalies')->name('anomalies');
            Route::post('/reprendre', 'reprendre')->name('reprendre');
            Route::get('/apercu', 'apercu')->name('apercu');
            Route::post('/transmettre', 'transmettre')->name('transmettre');
            Route::get('/telecharger/{format}', 'telecharger')->name('telecharger');
            Route::get('/deversement-{deversement}/fichier/{format?}', 'telechargerDeversement')->name('telechargerDeversement');
            Route::post('/deversement-{deversement}/accuser', 'accuser')->name('accuser');
            Route::post('/deversement-{deversement}/rejeter', 'rejeter')->name('rejeter');
            Route::get('/deversement-{deversement}/factures', 'facturesDuDeversement')->name('facturesDuDeversement');
            Route::get('/{ecriture}', 'detail')->name('detail');
        });

    // Phase 3b (lot 120, 22/09/2026) : les rapports comptables. Lecture seule.
    Route::prefix('comptabilite/rapports')->name('comptabilite.rapports.')->middleware('admin.seulement')
        ->controller(RapportsComptablesController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/deversements', 'deversements')->name('deversements');
            Route::get('/familles', 'familles')->name('familles');
            Route::get('/soldes', 'soldes')->name('soldes');
            Route::get('/grand-livre', 'grandLivre')->name('grandLivre');
            Route::get('/balance', 'balance')->name('balance');
            Route::get('/consolidee', 'consolidee')->name('consolidee');
            Route::get('/rapprochement', 'rapprochement')->name('rapprochement');
            Route::get('/anomalies', 'anomalies')->name('anomalies');
            Route::get('/ventes', 'ventes')->name('ventes');
            Route::get('/taxes', 'taxes')->name('taxes');
            Route::get('/clients', 'clients')->name('clients');
            Route::get('/tresorerie', 'tresorerie')->name('tresorerie');
            Route::get('/journal-des-ventes', 'journalDesVentes')->name('journalDesVentes');
        });

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

    // Affectation d'un gestionnaire, d'un agent ou d'un administrateur à une
    // agence. C'est ce rattachement qui décide du guichet auquel ses
    // encaissements seront imputés — il n'est plus choisi à la saisie.
    Route::post('/utilisateur/{id}/agence', 'affecterAgence')->name('affecterAgence');

    Route::get('/liste-de-demande-de-paiemennt-livreur', 'listeDeDemande')->name('listeDeDemandeLivreur');

    Route::get('/valide-demande-{id}-{type}-{reponse}', 'valideDemande')->name('valideDemande');
    // Circuit après la 2e validation (point 20) : preuve du versement, puis « Effectuée ».
    Route::post('/demande-paiement/{demande}/preuve', 'joindrePreuveDemande')->name('demandePaiement.preuve');
    Route::get('/demande-paiement/{demande}/preuve', 'voirPreuveDemande')->name('demandePaiement.voirPreuve');
    Route::post('/demande-paiement/{demande}/effectuer', 'effectuerDemande')->name('demandePaiement.effectuer');
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
    // Renvoi du code de validation au client, quand le courriel d'affectation
    // n'est pas arrivé. Même contrôleur, même écran de retour.
    Route::post('/renvoyer-code-livraison-{livraison}','renvoyerCodeDemandeLivraison')->name('renvoyerCodeDemandeLivraison')->middleware('auth');
    Route::get('selecion-vehicule-{id}-{detail}', 'selectionneVehicule')->name('selectCar')->middleware('auth');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/Valider-selection-vehicule','validerSelectionVehicule')->name('validerSelection');
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
    // Suppression définitive (ligne + fichier image), réservée aux bannières
    // déjà mises à la corbeille.
    Route::delete('/Banniere-{id}/suppression-definitive','suppressionDefinitiveBanniere')->name('suppressionDefinitiveBanniere');
    Route::get('/modification-de-Banniere-{id}','modificationDeBannierePage')->name('modificationDeBannierePage');
    Route::post('/modification-de-Banniere-{id}','modificationDeBanniere')->name('modificationDeBanniere');

    // Diapositives du carrousel d'accueil : même jeu de routes que les bannières.
    Route::get('/liste-des-Slides','listeDesSlides')->name('listeDesSlides');
    Route::get('/creation-de-Slide','creationDeSlide')->name('creationDeSlide');
    Route::post('/creation-de-Slide','creationDeSlideTraitement')->name('creationDeSlideTraitement');
    Route::get('/modification-de-Slide-{id}','modificationDeSlidePage')->name('modificationDeSlidePage');
    Route::post('/modification-de-Slide-{id}','modificationDeSlide')->name('modificationDeSlide');
    // {action} optionnel : sans lui = mettre en ligne / retirer,
    // « supprimer » = mise à la corbeille ou restauration.
    Route::get('/Supprimer-Slide-{id}/{action?}','supprimerPublierSlide')->name('supprimerPublierSlide');
    Route::delete('/Slide-{id}/suppression-definitive','suppressionDefinitiveSlide')->name('suppressionDefinitiveSlide');

    Route::get('/creation-de-Blog','creationDeBlog')->name('creationDeBlog');
    Route::post('/creation-de-Blog','creationDeBlogTraitement')->name('creationDeBlogTraitement');
    Route::get('/liste-des-Blogs','listeDesBlogs')->name('listeDesBlogs');
    // {action} optionnel : sans lui = publier/retirer (comportement historique),
    // « supprimer » = mise à la corbeille ou restauration.
    Route::get('/Supprimer-Blog-{id}/{action?}','supprimerPublierBlog')->name('supprimerPublierBlog');
    // Suppression définitive (ligne, images et commentaires), réservée aux blogs
    // déjà mis à la corbeille.
    Route::delete('/Blog-{id}/suppression-definitive','suppressionDefinitiveBlog')->name('suppressionDefinitiveBlog');
    Route::get('/modification-de-Blog-{id}','modificationDeBlogPage')->name('modificationDeBlogPage');
    Route::post('/modification-de-Blog-{id}','modificationDeBlog')->name('modificationDeBlog');
    Route::get('/commentaire-sur-les-blogs-{id}','commentaireBlogs')->name('commentaireBlogs');
    // Modération de tous les commentaires de blog, tous articles confondus :
    // sans elle il fallait ouvrir chaque article pour repérer les nouveaux.
    Route::get('/moderation-commentaires-blog','moderationCommentairesBlog')->name('moderationCommentairesBlog');
    Route::get('/publier-commentaire-blog-{id}','publierCommentaireBlog')->name('publierCommentaireBlog');
    Route::get('/annuler-commentaire-blog-{id}','annulerCommentaireBlog')->name('annulerCommentaireBlog');
    // Corbeille (bascule suppression / restauration) et suppression irréversible.
    Route::get('/corbeille-commentaire-blog-{id}','supprimerCommentaireBlog')->name('supprimerCommentaireBlog');
    Route::delete('/commentaire-blog-{id}/suppression-definitive','suppressionDefinitiveCommentaireBlog')->name('suppressionDefinitiveCommentaireBlog');

    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/liste-pays','listePays')->name('listePays');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/ajout-pays','ajoutPays')->name('ajoutPays');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/ajout-pays','ajoutPaysTraitement')->name('ajoutPaysTraitement');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/suppression-pays-{pays}','suppressionPays')->name('suppressionPays');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/modification-pays-{pays}','modificationPays')->name('modificationPays');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/modification-pays-{pays}','modificationPaysTraitement')->name('modificationPaysTraitement');

    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/liste-ville','listeVille')->name('listeVille');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/ajout-ville','ajoutVille')->name('ajoutVille');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/ajout-ville','ajoutVilleTraitement')->name('ajoutVilleTraitement');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/suppression-ville-{ville}','suppressionVille')->name('suppressionVille');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/modification-ville-{ville}','modificationVille')->name('modificationVille');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/modification-ville-{ville}','modificationVilleTraitement')->name('modificationVilleTraitement');

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
    // Lot 114 : interrupteur du mode « site en construction » (administrateurs seulement).
    Route::post('/parametre/site-en-construction','basculerSiteEnConstruction')->name('basculerSiteEnConstruction');

    route::post('/modifier-prix_livraison_de_livreur-{livreur}','modifierPrixLivraison')->name('modifierPrixLivraison');
    Route::post('/modifier-zone-intervention-livreur-{livreur}','modifierZoneLivreur')->name('modifierZoneLivreur');

    Route::get('/creation-de-code-promo','creationDeCodePromo')->name('creationDeCodePromo');
    Route::post('/creation-de-code-promo','enregistrementDeCodePromo')->name('enregistrementDeCodePromo');
    Route::get('/suppresion-de-code-promo-{reduction}','suppressionDeCodePromo')->name('suppressionDeCodePromo');
    Route::get('/update-de-code-promo-{reduction}','updateDeCodePromo')->name('updateDeCodePromo');
    Route::post('/updated-de-code-promo-{reduction}','codeUpdated')->name('codeUpdated');

    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/retour-produit/{livraison}','retourProduit')->name('retourProduit');
    Route::post('/restaure-livraison/{livraison}','restaureLivraison')->name('restaureLivraison');

    Route::get('/les-regions', 'lesRegions')->name('lesRegions');
    // La saisie a sa propre page : la liste ne recule plus derriere un formulaire.
    Route::get('/nouvelle-region', 'nouvelleRegion')->name('nouvelleRegion');
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

// Création de compte GESTIONNAIRE (back-office).
// Ces deux routes étaient PUBLIQUES : n'importe quel visiteur pouvait ouvrir
// /register-account et créer un compte gestionnaire actif (statut = true), dont les
// identifiants lui étaient envoyés par email — soit un accès complet au back-office.
// Le seul lien existant est celui du menu Admin (« Création de compte ») ; la page de
// connexion n'y renvoie pas. On applique donc la même protection que la liste des
// gestionnaires, sans changer le parcours légitime.
Route::middleware('auth.type:Admin,Gestionnaire')->group(function () {
    Route::get('/register-account', [UserController::class, 'register'])->name('show.registerGestionnaire');
    Route::post('/register-account', [UserController::class, 'storeUser']);
});

// MESSAGES DE CONTACT — reçus depuis la page publique « Nous contacter ».
// Ils étaient enregistrés en base sans qu'aucun écran ne les affiche.
Route::name('messagesContact.')->controller(\App\Http\Controllers\MessageContactController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    Route::get('/messages-de-contact','liste')->name('liste');
    Route::get('/messages-de-contact/{id}/lu','basculerLu')->name('basculerLu');
    Route::get('/messages-de-contact/{id}/supprimer','supprimer')->name('supprimer');
});

// LETTRE D'INFORMATION — gestion des abonnés recueillis sur le site public.
// Les exports contiennent des adresses personnelles : accès réservé aux
// administrateurs et gestionnaires, comme le reste du back-office.
Route::name('newsletter.')->controller(\App\Http\Controllers\NewsletterController::class)->middleware('auth.type:Admin,Gestionnaire')->group(function(){
    Route::get('/lettre-information/abonnes','liste')->name('liste');
    Route::get('/lettre-information/abonne-{id}/statut','basculerStatut')->name('basculerStatut');
    Route::get('/lettre-information/abonne-{id}/supprimer','supprimer')->name('supprimer');
    Route::get('/lettre-information/export/excel','exportExcel')->name('exportExcel');
    Route::get('/lettre-information/export/word','exportWord')->name('exportWord');
    Route::get('/lettre-information/export/pdf','exportPdf')->name('exportPdf');
});

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
    // La saisie a sa propre page : la liste ne recule plus derriere un formulaire.
    Route::get('/nouvelle-ville', 'nouvelleVille')->name('nouvelleVille');
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
    // La saisie a sa propre page : la liste ne recule plus derrière un formulaire.
    route::get('/nouvelle-categorie', 'nouvelleCategorie')->name('nouvelleCategorie');
    route::post('/products-category', 'saveCategorie')->name('saveCategorie');
    route::get('/edit-category-{categorie}', 'editCategory')->name('editCategory');
    route::post('/edit-category-{categorie}', 'editCategoryTraitement')->name('editCategoryTraitement');
    route::get('/delete-category-{categorie}', 'deleteCategory')->name('deleteCategory');
    // LE POURCENTAGE DALAKOUN : la marge ajoutee au prix d'achat pour faire le
    // prix du catalogue. Il n'entre en vigueur qu'apres double validation.
    route::get('/pourcentage-dalakoun', [PourcentageDalakounController::class, 'index'])->name('pourcentage');
    route::post('/pourcentage-dalakoun', [PourcentageDalakounController::class, 'store'])->name('pourcentage.store');
    route::post('/pourcentage-dalakoun-{pourcentage}/valider', [PourcentageDalakounController::class, 'valider'])->name('pourcentage.valider');
    route::post('/pourcentage-dalakoun-{pourcentage}/refuser', [PourcentageDalakounController::class, 'refuser'])->name('pourcentage.refuser');

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
    route::get('/orders-be/{numero}/word', 'BECommandeWord')->name('BECommande.word');
    route::post('/generer-facture/{commande}', 'genererFacture')->name('genererFacture');
    // Facture d'un seul bon : le cas du bon servi partiellement, qui se facture
    // pour ce qu'il a livré sans attendre le reliquat. En POST — elle crée un
    // document fiscal, elle ne doit pas partir sur un simple lien visité.
    route::post('/generer-facture-enlevement/{enlevement}', 'genererFactureEnlevement')->name('genererFactureEnlevement');
    // Tous les bons facturables de la commande, en une seule facture.
    route::post('/generer-facture-totale/{commande}', 'genererFactureTotale')->name('genererFactureTotale');
    route::post('/recertifier-facture/{facture}', 'recertifierFacture')->name('recertifierFacture');
    route::get('/factures-non-validees', 'facturesNonValidees')->name('facturesNonValidees');
    route::get('/factures-validees', 'facturesValidees')->name('facturesValidees');
    route::post('/valider-facture-fne/{facture}', 'validerFactureFne')->name('validerFactureFne');
    // FACTURE D'AVOIR (lot 92, 16/09/2026) : sur une facture certifiée, certifiée elle aussi par la DGI.
    route::get('/facture-avoir/{facture}/nouveau', 'nouvelAvoir')->name('nouvelAvoir');
    route::post('/facture-avoir/{facture}/emettre', 'emettreAvoir')->name('emettreAvoir');
    route::get('/facture-avoir/{facture}/{action?}', 'factureAvoir')->name('factureAvoir');
    // FNE pour les LOCATIONS (équivalent de genererFacture/valider pour les ventes).
    route::post('/generer-facture-location/{location}', 'genererFactureLocation')->name('genererFactureLocation');
    // Facture INTERNE d'un transport : sans elle, un client ayant regle une
    // demande de livraison restait indefiniment en « regle d'avance ».
    route::post('/generer-facture-livraison/{demande}', 'genererFactureLivraison')->name('genererFactureLivraison');
    route::get('/facture-livraison/{facture}/{action?}', 'factureLivraison')->name('factureLivraison');
    route::get('/facture-location-{facture}-{action?}', 'factureLocation')->name('factureLocation');
    route::get('/documentation/fne-integration', 'documentationFne')->name('documentationFne');
    route::get('/order-item/{id}', 'oderItemPage')->name('item');
    route::post('/order-item/{id}', 'oderItem')->name('oderItem');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : route::get('/transactions', 'transactions')->name('transactions');
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
// Le reçu de paiement (paye.facture) et l'enregistrement d'un encaissement
// (effectuerPaiementTraitement) étaient déclarés HORS du groupe ci-dessous : reçu de
// n'importe quel client téléchargeable sans être connecté, et POST d'écriture non
// authentifié. Ils rejoignent le groupe de leurs routes sœurs (mêmes utilisateurs).
Route::name('paye.')->controller(PaiementController::class)->middleware('auth.type:Admin,Gestionnaire,client')->group(function (){

    Route::get('/facture/{reference?}-{action?}', 'facture')->name('facture');
    Route::post('effectuer/paiement/{client}', 'effectuerPaiementTraitement')->name('effectuerPaiementTraitement');

    // route::get('/paiement/liste/{commande}')->name('liste');
    // [RETIRÉ] /paiement/create/{commande} — écran de règlement d'une VENTE.
    // Paiement validé d'un seul clic, sans agence, sans reçu, sans seconde
    // signature, et sans rattachement au service. Remplacé par le guichet
    // /comptant/encaissements pour les clients ordinaires, et par
    // /clients-terme/paiements pour les clients à terme.
    Route::get('/paiement/liste','paiementList')->name('list');
    // [RETIRÉ] /paiement-location-{location} — écran de règlement d'une location.
    // Il écrivait un paiement DÉJÀ VALIDÉ d'un seul clic, sans agence, sans reçu
    // et sans seconde signature : le seul encaissement du back-office dans ce cas.
    // Remplacé par le guichet /encaissements/locations, qui applique la
    // double validation comme les ventes et les demandes de livraison, et qui
    // sert aussi les clients à terme.

    Route::get('/effectuer/paiement/{client}', 'effectuerPaiement')->name('effectuerPaiement');

    /// route::get()->name('');

});

// ROUTE FOR SELLERS
route::get('/seller/login', [SellerController::class,'loginPage'])->name('sellers.login');
route::post('/seller/login', [SellerController::class,'validLogin']);

Route::name('sellers.')->controller(SellerController::class)->middleware('auth.type:fournisseur')->group(function(){

    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/demande-de-paiement', 'demandeDepaie')->name('demandeDepaie');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/demande-de-paiement', 'demandeDepaieTraitement')->name('demandeDepaieTraitement');
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
    // Planning livraison, historique et récap produits (lot 83, 15/09/2026).
    Route::get('/seller/planning-livraison', 'planningLivraison')->name('planningLivraison');
    Route::get('/seller/historique-enlevements', 'historiqueEnlevements')->name('historiqueEnlevements');
    Route::get('/seller/recap-produits', 'recapProduits')->name('recapProduits');
    Route::get('/seller/refuse', 'refuse')->name('refuse');
    Route::get('/seller/{seller}/profile', 'profile')->name('profile');
    Route::get('/seller/{product}/products', 'editProducts')->name('edit');
    Route::post('/seller/{product}/products', 'update')->name('update');

    // Route::post('/seller/update', 'updateSeller')->name('updateSeller');
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/fin-de-stock','finDeStock')->name('finDeStock');
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
    // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/apporteur/solde',  'solde')->name('solde');
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
    // Suppression d'un devis en attente par son client (10/09/2026).
    Route::delete('devis/supprimer/{devis}', 'supprimerDevis')->name('supprimerDevis');
});

/*
 * Pages du site accessibles SANS COMPTE.
 *
 * Ces routes vivaient dans le groupe ci-dessous, qui porte « auth.type:client » :
 * un visiteur non connecté était renvoyé sur la page de connexion avec le message
 * « Votre session a expiré ». Les articles de blog n'étaient donc jamais visibles
 * du grand public, et le lien « Livraison », pourtant affiché à tout le monde dans
 * le menu, menait au même mur.
 *
 * Le groupe contient d'ailleurs un SECOND « auth.type:client » à l'intérieur,
 * appliqué aux routes réellement réservées : la protection du groupe extérieur
 * n'était pas voulue à ce niveau. Les deux contrôleurs concernés le confirment,
 * ils prévoient explicitement le cas du visiteur (« Auth::user() ? … : new Client »).
 *
 * Les ENVOIS restent réservés aux utilisateurs connectés : dépôt d'un commentaire
 * (middleware « auth » ci-dessous) et envoi d'une demande de livraison
 * (client.recapLivraison, resté dans le groupe protégé).
 */
Route::name('client.')->controller(ClientController::class)->group(function(){
    Route::get('/blog','blog')->name('blog');
    Route::get('/detail-blog-{id}','detailBlog')->name('detailBlog');
    Route::post('/detail-blog-{id}','commentaireEtNote')->name('commentaireEtNote')->middleware('auth');
    Route::get('/demande-de-livraison','demandeLivraison')->name('demandeLivraison');
});

Route::name('client.')->controller(ClientController::class)->middleware('auth.type:client')->group(function(){
        // [ROUTE MORTE] /cart n'est référencée nulle part — ni lien, ni include, ni
        // appel AJAX — et la vue client.cart est un fragment de tableau sans
        // @extends : l'ouvrir affichait une page sans mise en page. Le panier réel
        // du site est /client/mon-panier (client.panier).
        // Note historique : la syntaxe array [ClientController::class, 'cart'] était
        // utilisée au lieu de 'cart' pour éviter une collision case-insensitive avec
        // l'alias global `Cart` (Darryldecode\Cart\Facades\CartFacade). Sans cela,
        // au 2ème boot Laravel (cf. tests), class_exists('cart') retourne true et
        // Laravel tente de traiter 'cart' comme une classe invokable → exception.
        // Route::get('/cart', [ClientController::class, 'cart'])->name('cart');
        Route::get('/client/accueil', 'accueil')->name('accueil');
        Route::get('/client/mon-panier', 'panierPage')->name('panier');
        Route::get('/incremente/{rowId}', 'incremente')->name('incremente');
        Route::get('/decremente/{rowId}', 'decremente')->name('decremente');
        Route::get('/confirmation/client_{token}', 'confirmationEmailClient')->name('confirmationEmailClient');


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

        // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::get('/location-validee-{location}','locationValidee')->name('locationValidee');
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
        // [ROUTE MORTE] méthode absente du contrôleur -> erreur 500 : Route::post('/commande-en-devis', 'CommandeEnDevis')->name('CommandeEnDevis');

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
        // La proforma ou la facture de la commande, en PDF (lot 81, 15/09/2026).
        Route::get('/commande-{numero}-document-pdf', 'documentCommandePdf')->name('documentCommandePdf')->middleware('auth');
        // Proforma / facture et factures DGI des locations et demandes de livraison (lot 85, 15/09/2026).
        Route::get('/document-location/{location}/pdf', 'documentLocationPdf')->name('documentLocationPdf')->middleware('auth');
        Route::get('/document-demande-livraison/{demande}/pdf', 'documentLivraisonPdf')->name('documentLivraisonPdf')->middleware('auth');
        Route::get('/liste-des-factures-{service}-{id}', 'listeFactureAffaire')->name('listeFactureAffaire')->middleware('auth')->where('service', 'location|livraison');
        Route::get('/facture-affaire-{facture}-{action}', 'factureAffairePdf')->name('factureAffairePdf')->middleware('auth')->where('action', 'voir|telecharger');

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

// [ROUTES DE TEST DÉSACTIVÉES — audit du 31/07/2026]
// Aucun lien ni formulaire de l'application ne les utilise. 'recapDevisLocation'
// se contentait d'un dd('ok') (page de débogage brute) et '/select-multiple'
// exposait un script de vidage de tables. Le code reste dans TestController pour
// mémoire, mais il n'est plus atteignable depuis le navigateur.
// Route::controller(TestController::class)->name('test.')->group(function(){
//     Route::post('/recapitulatif-devis-de-la-location-a-partie-du-test','recapDevisLocation')->name('recapDevisLocation')->middleware('auth');
//     Route::get('/select-multiple','select')->name('select');
// });

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

