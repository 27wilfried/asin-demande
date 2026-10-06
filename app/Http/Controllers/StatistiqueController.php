<?php

namespace App\Http\Controllers;

use App\Enums\StatutDemande;
use App\Models\Demande;
use App\Rules\Npi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/statistiques — nombre de demandes par statut (bonus).
 * Filtre facultatif ?npi=XXXXXXXXXX pour un seul usager.
 */
class StatistiqueController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['npi' => ['sometimes', new Npi]]);

        // Un seul GROUP BY en base plutôt qu'une requête par statut.
        $compteurs = Demande::query()
            ->when($request->query('npi'), fn ($q, $npi) => $q->where('npi', $npi))
            ->toBase()
            ->selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        // Tous les statuts sont présents dans la réponse, même à 0.
        $parStatut = [];
        foreach (StatutDemande::cases() as $statut) {
            $parStatut[$statut->value] = (int) ($compteurs[$statut->value] ?? 0);
        }

        return response()->json([
            'data' => [
                'total' => array_sum($parStatut),
                'par_statut' => $parStatut,
            ],
        ]);
    }
}
