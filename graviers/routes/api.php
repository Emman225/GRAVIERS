<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaiementEnLigne;
use App\Http\Controllers\Api\EcrituresComptablesApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('callBackPaiement', [PaiementEnLigne::class, 'callBackPaiement'])->name("callBackPaiement");
// Vérification (pull) du statut d'un paiement — filet de sécurité si le callback se perd.
// Throttle dédié (plus strict que throttle:api) : la route est publique et chaque
// appel déclenche une requête sortante vers PaySecure -> limite l'amplification
// et l'énumération de codePaiement.
Route::post('verifierPaiement', [PaiementEnLigne::class, 'verifierPaiement'])->middleware('throttle:10,1')->name("verifierPaiement");

// LE REÇU DES PAIEMENTS FAITS DEPUIS L'APPLICATION.
//
// L'API des mobiles confirme les paiements de l'application mais ne sait pas
// produire de PDF. Elle demande donc au site d'envoyer le reçu, en présentant
// le jeton partagé JETON_INTERNE (même valeur dans les deux .env). Sans jeton
// configuré côté site, la route refuse tout ; l'API se rabat alors sur un
// reçu en texte, dans le corps du courriel.
Route::post('interne/recu-paiement/{paiement}', [PaiementEnLigne::class, 'envoyerRecuInterne'])
    ->middleware('throttle:30,1')
    ->name('interne.recuPaiement');

// LA PROFORMA (OU LA FACTURE) D'UNE COMMANDE PASSÉE DEPUIS L'APPLICATION (lot 81,
// 15/09/2026) : même mécanique, même jeton ; le site produit le PDF et l'envoie.
Route::post('interne/commande/{numero}/document', [\App\Http\Controllers\ClientController::class, 'envoyerDocumentInterne'])
    ->middleware('throttle:30,1')
    ->name('interne.documentCommande');

// LE BON DE LIVRAISON D'UNE COURSE CLÔTURÉE DEPUIS L'APPLICATION DES LIVREURS
// (lot 84, 15/09/2026) : même mécanique, même jeton.
Route::post('interne/livraison/{livraison}/bon-de-livraison', [\App\Http\Controllers\LivreurController::class, 'envoyerBonInterne'])
    ->middleware('throttle:30,1')
    ->name('interne.bonDeLivraison');

// « MES PAIEMENTS » DU CLIENT POUR L'APPLICATION (lot 89, 16/09/2026) : la liste du
// site (règlements effectués et paiements en attente), même règle que la page web.
Route::post('interne/client/{client}/paiements', [\App\Http\Controllers\ClientController::class, 'paiementsInterne'])
    ->middleware('throttle:60,1')
    ->name('interne.paiementsClient');

// LES FACTURES DGI DU CLIENT ET LEUR PDF POUR L'APPLICATION (lot 95, 16/09/2026).
Route::post('interne/client/{client}/factures', [\App\Http\Controllers\ClientController::class, 'facturesInterne'])
    ->middleware('throttle:60,1')
    ->name('interne.facturesClient');
Route::post('interne/facture/{facture}/pdf', [\App\Http\Controllers\ClientController::class, 'facturePdfInterne'])
    ->middleware('throttle:60,1')
    ->name('interne.facturePdf');

// LE PDF DU REÇU D'UN PAIEMENT POUR L'APPLICATION (lot 88, 15/09/2026) : le reçu
// affiché sur le mobile est celui du site, même gabarit ; l'API le relaie.
Route::post('interne/recu-paiement/{paiement}/pdf', [PaiementEnLigne::class, 'recuPdfInterne'])
    ->middleware('throttle:60,1')
    ->name('interne.recuPaiementPdf');

// MODULE « ÉCRITURES COMPTABLES », PHASE 4 (lot 121, 22/09/2026).
//
// Réservée à l'administrateur du site DALAKOUN : jeton Sanctum (aptitude
// « comptabilite:lecture », « comptabilite:ecriture » en plus pour le seul
// point d'écriture), chaque appel journalisé (journal.appel.api.comptable).
// La route d'export vient AVANT celle du détail : « export » n'est pas un
// identifiant d'écriture, mais un paramètre attrape tout ce qui passe avant lui.
Route::prefix('comptabilite')->name('api.comptabilite.')
    ->middleware(['auth:sanctum', 'admin.seulement.api', 'journal.appel.api.comptable'])
    ->controller(EcrituresComptablesApiController::class)->group(function () {
        Route::get('/ecritures/export', 'export')->middleware('throttle:120,1')->name('ecritures.export');
        Route::get('/ecritures/{ecriture:identifiant}', 'show')->middleware('throttle:120,1')->name('ecritures.show');
        Route::get('/ecritures', 'index')->middleware('throttle:120,1')->name('ecritures.index');
        Route::get('/deversements/{deversement:numero}/fichier/{format?}', 'deversementFichier')->middleware('throttle:120,1')->name('deversements.fichier');
        Route::get('/deversements/{deversement:numero}', 'deversementDetail')->middleware('throttle:120,1')->name('deversements.show');
        Route::get('/deversements', 'deversements')->middleware('throttle:120,1')->name('deversements.index');
        Route::post('/deversements', 'accuser')->middleware(['admin.seulement.api:comptabilite:ecriture', 'throttle:10,1'])->name('deversements.store');
    });
