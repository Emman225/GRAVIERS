<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaiementController;

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

// routes/web.php
Route::get('/', function () {
    return view('welcome');
});

Route::get('/ouvre-app/{codePaiement}', [PaiementController::class, 'ouvreApp'])->name("ouvreApp");