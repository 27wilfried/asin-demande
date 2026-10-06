<?php

namespace App\Exceptions;

use App\Enums\StatutDemande;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Levée quand une action ne respecte pas le cycle de vie d'une demande.
 * Renvoyée au client en HTTP 409 (conflit avec l'état actuel de la ressource).
 */
class TransitionInterditeException extends Exception
{
    public function __construct(
        public readonly StatutDemande $actuel,
        public readonly StatutDemande $cible,
    ) {
        parent::__construct($this->construireMessage());
    }

    private function construireMessage(): string
    {
        if ($this->actuel->estFinal()) {
            return "La demande est déjà « {$this->actuel->libelle()} » : elle ne peut plus changer de statut.";
        }

        $possibles = implode(', ', array_map(
            fn (StatutDemande $s) => "« {$s->libelle()} »",
            $this->actuel->transitionsPossibles(),
        ));

        return "Action interdite : une demande « {$this->actuel->libelle()} » ne peut pas passer "
            ."à « {$this->cible->libelle()} ». Statut(s) possible(s) : {$possibles}.";
    }

    /** Erreur métier attendue : inutile de la journaliser. */
    public function report(): void {}

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'statut_actuel' => $this->actuel->value,
            'transitions_possibles' => array_map(
                fn (StatutDemande $s) => $s->value,
                $this->actuel->transitionsPossibles(),
            ),
        ], 409);
    }
}
