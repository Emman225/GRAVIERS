<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaiementEnLigne;

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
Route::post('verifierPaiement', [PaiementEnLigne::class, 'verifierPaiement'])->name("verifierPaiement");
