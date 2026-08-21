<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\ApporteurController;
use App\Http\Controllers\UtilisateurController;

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

// [ROUTE DE TEST SUPPRIMÉE — audit du 31/07/2026]
// Cette route était PUBLIQUE : n'importe qui pouvait déclencher un envoi d'email
// depuis le serveur (relais à spam) et, en cas d'échec, la réponse affichait le
// chemin complet des fichiers du serveur et le numéro de ligne. Aucun code de
// l'application ne l'utilise.
// Route::get('test-mail', ...);
Route::get('get-config', [ApporteurController::class, 'chargerParametres']);
Route::post('connexion', [ApporteurController::class, 'connexion']);
Route::post('inscription', [ApporteurController::class, 'inscription']);
Route::post('renvoyerOtp', [ApporteurController::class, 'renvoyerOtp']);
Route::post('verifierOtp', [ApporteurController::class, 'verifierOtp']);
Route::post('demandeReinititPass', [ApporteurController::class, 'demandeReinititPass']);
Route::post('lister-demande-paiement', [ApporteurController::class, 'listerDemandePaiement']);
Route::post('enregistrer-demande-paiement', [ApporteurController::class, 'enregistrerDemandePaiement']);
Route::post('home-apporteur', [ApporteurController::class, 'homeApporteur']);
Route::post('modifier-pass-apporteur', [UtilisateurController::class, 'modifierPass']);
Route::post('infos-utilisateur', [UtilisateurController::class, 'infosUtilisateur']);
Route::post('liste-villes', [AdresseController::class, 'listeVilles']);
Route::post('liste-filleule', [ApporteurController::class, 'listeFilleule']);
Route::post('liste-paiement-filleule', [ApporteurController::class, 'listePaiementFilleule']);
Route::post('liste-commissions', [ApporteurController::class, 'listeCommission']);


Route::post('enregistrer-vehicule', [ApporteurController::class, 'enregistrerVehicule']);
Route::post('accepter-livraison', [ApporteurController::class, 'accepterLivraison']);
Route::post('refuser-livraison', [ApporteurController::class, 'refuserLivraison']);
Route::post('enregistrer-fin-livraison', [ApporteurController::class, 'enregistrerFinLivraison']);
Route::post('liste-livraison', [LivraisonController::class, 'listeLivraison']);
Route::post('details-livraison/{id}', [LivraisonController::class, 'detailsLivraison']);
