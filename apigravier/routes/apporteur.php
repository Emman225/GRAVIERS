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

Route::get('test-mail', function () {
    try {
        \Illuminate\Support\Facades\Mail::to('reply@gravierci.com')->send(
            new \App\Mail\CodeInscriptionMail('Test', '1234', 'Ceci est un test d\'envoi de mail')
        );
        return response()->json(['status' => 'Email envoyé avec succès']);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'Erreur',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});
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
