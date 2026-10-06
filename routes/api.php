<?php

use App\Http\Controllers\DemandeController;
use App\Http\Controllers\StatistiqueController;
use Illuminate\Support\Facades\Route;

/*
| API des demandes d'actes — toutes les routes sont préfixées par /api.
*/

Route::controller(DemandeController::class)->group(function () {
    Route::get('demandes', 'index');
    Route::post('demandes', 'store');
    Route::get('demandes/{demande}', 'show')->whereNumber('demande');
    Route::patch('demandes/{demande}/statut', 'changerStatut')->whereNumber('demande');

    // Demandes d'un usager, de la plus récente à la plus ancienne.
    Route::get('usagers/{npi}/demandes', 'indexUsager');
});

// Bonus : nombre de demandes par statut.
Route::get('statistiques', StatistiqueController::class);
