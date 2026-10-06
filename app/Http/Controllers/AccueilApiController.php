<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /api — point d'entrée de l'API : liste des routes disponibles,
 * pour découvrir l'API directement depuis un navigateur.
 */
class AccueilApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'message' => "API de suivi des demandes d'actes. Toutes les routes sont décrites dans le README.",
            'routes' => [
                ['methode' => 'POST', 'route' => '/api/demandes', 'role' => 'Déposer une demande (npi, type_acte, nombre_copies)'],
                ['methode' => 'GET', 'route' => '/api/usagers/{npi}/demandes', 'role' => "Demandes d'un usager, de la plus récente à la plus ancienne (?statut=, ?page=, ?par_page=)"],
                ['methode' => 'PATCH', 'route' => '/api/demandes/{id}/statut', 'role' => 'Faire avancer une demande dans son cycle de vie (statut, motif si rejet)'],
                ['methode' => 'GET', 'route' => '/api/demandes/{id}', 'role' => "Détail d'une demande"],
                ['methode' => 'GET', 'route' => '/api/demandes', 'role' => 'Toutes les demandes, vue agent (?npi=, ?statut=, ?page=, ?par_page=)'],
                ['methode' => 'GET', 'route' => '/api/statistiques', 'role' => 'Nombre de demandes par statut (?npi=)'],
            ],
            // Liens directs vers les données de démonstration (php artisan migrate --seed).
            'exemples' => [
                url('/api/usagers/1234567890/demandes'),
                url('/api/usagers/1234567890/demandes?statut=en_cours'),
                url('/api/usagers/1111111111/demandes?page=2'),
                url('/api/statistiques?npi=1234567890'),
            ],
        ]);
    }
}
