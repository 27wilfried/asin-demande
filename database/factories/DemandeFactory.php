<?php

namespace Database\Factories;

use App\Enums\StatutDemande;
use App\Enums\TypeActe;
use App\Models\Demande;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Génère des demandes de test (tests automatisés et données de démonstration).
 *
 * @extends Factory<Demande>
 */
class DemandeFactory extends Factory
{
    protected $model = Demande::class;

    public function definition(): array
    {
        return [
            'npi' => fake()->numerify('##########'),
            'type_acte' => fake()->randomElement(TypeActe::cases()),
            'nombre_copies' => fake()->numberBetween(1, 5),
            'statut' => StatutDemande::Deposee,
        ];
    }

    public function enCours(): static
    {
        return $this->state(['statut' => StatutDemande::EnCours]);
    }

    public function validee(): static
    {
        return $this->state(['statut' => StatutDemande::Validee, 'traitee_le' => now()]);
    }

    public function rejetee(): static
    {
        return $this->state([
            'statut' => StatutDemande::Rejetee,
            'motif_rejet' => 'Pièce justificative illisible.',
            'traitee_le' => now(),
        ]);
    }
}
