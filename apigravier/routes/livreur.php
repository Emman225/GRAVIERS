<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\LivreurController;
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

Route::post('connexion', [LivreurController::class, 'connexion']);
Route::post('renvoyerOtp', [LivreurController::class, 'renvoyerOtp']);
Route::post('verifierOtp', [LivreurController::class, 'verifierOtp']);
Route::post('demandeReinititPass', [LivreurController::class, 'demandeReinititPass']);
Route::post('modifier-pass-livreur', [UtilisateurController::class, 'modifierPass']);
Route::post('infos-utilisateur', [UtilisateurController::class, 'infosUtilisateur']);
Route::post('liste-villes', [AdresseController::class, 'listeVilles']);
Route::post('liste-livraison', [LivraisonController::class, 'listeLivraison']);
Route::post('liste-vehicule', [LivreurController::class, 'listeVehicule']);
Route::post('enregistrer-vehicule', [LivreurController::class, 'enregistrerVehicule']);
Route::post('accepter-livraison', [LivreurController::class, 'accepterLivraison']);
Route::post('refuser-livraison', [LivreurController::class, 'refuserLivraison']);
Route::post('details-livraison/{id}', [LivraisonController::class, 'detailsLivraison']);
Route::post('enregistrer-fin-livraison', [LivreurController::class, 'enregistrerFinLivraison']);
Route::post('lister-demande-paiement', [LivreurController::class, 'listerDemandePaiement']);
Route::post('enregistrer-demande-paiement', [LivreurController::class, 'enregistrerDemandePaiement']);
Route::post('home-livreur', [LivreurController::class, 'homeLivreur']);

// Géolocalisation (point 1) :
// - maj-position : appelée régulièrement par l'app mobile livreur pour pousser sa position GPS
// - livreur-plus-proche : appelée par l'app mobile client pour trouver le livreur le plus proche
//   du point de livraison + obtenir le coût de livraison estimé.
Route::post('maj-position', [LivreurController::class, 'majPosition']);
Route::post('livreur-plus-proche', [LivreurController::class, 'plusProche']);
