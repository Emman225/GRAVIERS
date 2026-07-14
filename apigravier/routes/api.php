<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AdresseController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\CommandeController;
use App\Http\Controllers\LivraisonController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PaiementController;
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

// routes/api.php — accessible via http://votre-app.com/api/
Route::get('/', function () {
    return response()->json(['message' => 'Bienvenue']);
});


Route::get('get-config', [HomeController::class, 'chargerParametres']);
Route::post('configuration-fne', [HomeController::class, 'configurationFne']);
Route::post('connexion', [UtilisateurController::class, 'connexion']);
Route::post('inscription', [UtilisateurController::class, 'inscription']);
Route::post('renvoyerOtp', [UtilisateurController::class, 'renvoyerOtp']);
Route::post('verifierOtp', [UtilisateurController::class, 'verifierOtp']);
Route::post('demandeReinititPass', [UtilisateurController::class, 'demandeReinititPass']);
Route::post('demande-client-a-terme', [UtilisateurController::class, 'demandeClientATerme']);
Route::post('liste-produit', [ProduitController::class, 'listeProduit']);
Route::post('liste-produit-cat', [ProduitController::class, 'listeProduitSurCategorie']);
Route::post('get-adresses', [AdresseController::class, 'chargerAdresses']);
Route::post('liste-villes', [AdresseController::class, 'listeVilles']);
Route::post('editer-adresse', [AdresseController::class, 'editerAdresse']);
Route::post('enregistrer-devis', [CommandeController::class, 'enregistrerDevis']);
Route::post('liste-devis', [CommandeController::class, 'listeDevis']);
Route::post('details-devis/{id}', [CommandeController::class, 'detailsDevis']);
Route::post('supprimer-devis/{id}', [CommandeController::class, 'supprimerDevis']);
Route::post('enregistrer-commande', [CommandeController::class, 'enregistrerCommande']);
Route::post('liste-commande', [CommandeController::class, 'listeCommande']);
Route::post('resume-commande', [CommandeController::class, 'resumeCommande']);
Route::post('details-commande/{id}', [CommandeController::class, 'detailsCommande']);
Route::post('liste-livraison', [LivraisonController::class, 'listeLivraison']);
Route::post('details-livraison/{id}', [LivraisonController::class, 'detailsLivraison']);
Route::post('resume-demande-livraison', [LivraisonController::class, 'resumeDemandeLivraison']);
Route::post('enregistrer-demande-livraison', [LivraisonController::class, 'enregistrerDemandeLivraison']);
Route::post('liste-demande-livraison', [LivraisonController::class, 'listeDemandeLivraison']);
Route::post('details-demande-livraison/{id}', [LivraisonController::class, 'detailsDemandeLivraison']);
Route::post('modifier-pass', [UtilisateurController::class, 'modifierPass']);
Route::post('infos-utilisateur', [UtilisateurController::class, 'infosUtilisateur']);
Route::post('edit-profil', [UtilisateurController::class, 'editProfil']);
Route::post('enregistrer-note', [ProduitController::class, 'enregistrerNoteProduit']);
Route::post('liste-filleul', [UtilisateurController::class, 'listeFilleul']);
Route::post('ajouter-retirer-liste-souhait/{id}', [ProduitController::class, 'ajouterRetirerListeSouhait']);
Route::post('suppression-compte-client', [UtilisateurController::class, 'supprimerCompteClient']);
Route::post('annuler-commande/{id}', [CommandeController::class, 'annulerCommande']);
Route::post('demande-annuler-commande/{id}', [CommandeController::class, 'demandeAnnulerCommande']);
Route::post('verifier-code-promo', [CommandeController::class, 'verifierCodePromo']);
Route::post('demande-retour-produit', [CommandeController::class, 'demandeRetourProduit']);
Route::post('recuperer-montant-point', [HomeController::class, 'recupererMontantPoint']);

Route::post('enregistrer-location', [LocationController::class, 'enregistrerLocation']);
Route::post('details-location/{id}', [LocationController::class, 'detailsLocation']);
Route::post('demande-annuler-location/{id}', [LocationController::class, 'demandeAnnulerLocation']);

Route::post('callBackPaiement', [PaiementController::class, 'callBackPaiement'])->name("callBackPaiement");
Route::post('liste-facture', [PaiementController::class, 'listeFacture'])->name("listeFacture");
Route::post('liste-paiement', [PaiementController::class, 'listePaiement'])->name("listePaiement");
Route::post('obtenir-lien-paiement', [PaiementController::class, 'obtenirLienPaiement'])->name("obtenirLienPaiement");
Route::post('liste-ligne-paiement-sur-code', [PaiementController::class, 'listeLignePaiementSurCode'])->name("listeLignePaiementSurCode");
