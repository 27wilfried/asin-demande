<?php

namespace App\Http\Resources;

use App\Enums\StatutDemande;
use App\Models\Demande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Format JSON d'une demande renvoyé par l'API.
 *
 * @mixin Demande
 */
class DemandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'npi' => $this->npi,
            'type_acte' => $this->type_acte->value,
            'type_acte_libelle' => $this->type_acte->libelle(),
            'nombre_copies' => $this->nombre_copies,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'motif_rejet' => $this->motif_rejet,
            // Aide le client (et l'agent) à savoir quelle action est possible ensuite.
            'transitions_possibles' => array_map(
                fn (StatutDemande $s) => $s->value,
                $this->statut->transitionsPossibles(),
            ),
            'traitee_le' => $this->traitee_le?->toIso8601String(),
            'cree_le' => $this->created_at?->toIso8601String(),
            'modifie_le' => $this->updated_at?->toIso8601String(),
        ];
    }
}
