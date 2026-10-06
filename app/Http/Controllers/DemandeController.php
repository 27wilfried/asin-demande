<?php

namespace App\Http\Controllers;

use App\Enums\StatutDemande;
use App\Http\Requests\ChangerStatutRequest;
use App\Http\Requests\ListeDemandesRequest;
use App\Http\Requests\StoreDemandeRequest;
use App\Http\Resources\DemandeResource;
use App\Models\Demande;
use App\Services\DemandeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Points d'entrée HTTP des demandes. Le contrôleur reste fin :
 * validation dans les FormRequest, règles métier dans DemandeService.
 */
class DemandeController extends Controller
{
    public function __construct(private readonly DemandeService $service) {}

    /** GET /api/demandes — toutes les demandes (vue agent), filtres facultatifs npi et statut. */
    public function index(ListeDemandesRequest $request): AnonymousResourceCollection
    {
        return $this->lister($request);
    }

    /** GET /api/usagers/{npi}/demandes — demandes d'un usager, filtre facultatif par statut. */
    public function indexUsager(ListeDemandesRequest $request, string $npi): AnonymousResourceCollection
    {
        // Le NPI de l'URL a été injecté et validé par ListeDemandesRequest.
        return $this->lister($request);
    }

    /** POST /api/demandes — dépôt d'une demande. */
    public function store(StoreDemandeRequest $request): JsonResponse
    {
        $demande = $this->service->deposer($request->validated());

        return (new DemandeResource($demande))->response()->setStatusCode(201);
    }

    /** GET /api/demandes/{demande} — détail d'une demande. */
    public function show(Demande $demande): DemandeResource
    {
        return new DemandeResource($demande);
    }

    /** PATCH /api/demandes/{demande}/statut — avancement dans le cycle de vie. */
    public function changerStatut(ChangerStatutRequest $request, Demande $demande): DemandeResource
    {
        $demande = $this->service->changerStatut(
            $demande,
            StatutDemande::from($request->validated('statut')),
            $request->validated('motif'),
        );

        return new DemandeResource($demande);
    }

    /**
     * Requête commune aux listes : tri de la plus récente à la plus ancienne,
     * pagination de 20 demandes par page au maximum.
     */
    private function lister(ListeDemandesRequest $request): AnonymousResourceCollection
    {
        $filtres = $request->validated();

        $demandes = Demande::query()
            ->when($filtres['npi'] ?? null, fn ($q, $npi) => $q->where('npi', $npi))
            ->when($filtres['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->orderByDesc('id') // départage les demandes créées à la même seconde
            ->paginate((int) ($filtres['par_page'] ?? ListeDemandesRequest::PAR_PAGE_MAX))
            ->withQueryString();

        return DemandeResource::collection($demandes);
    }
}
