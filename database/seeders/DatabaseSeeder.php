<?php

namespace Database\Seeders;

use App\Models\Demande;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour tester rapidement l'API et l'écran.
 * Usager principal : NPI 1234567890. Pagination : NPI 1111111111.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $npi = '1234567890';

        // Dates échelonnées pour pouvoir vérifier le tri (plus récente en premier).
        Demande::factory()->count(3)->sequence(
            ['created_at' => now()->subDays(1)],
            ['created_at' => now()->subDays(2)],
            ['created_at' => now()->subDays(3)],
        )->create(['npi' => $npi]);

        Demande::factory()->enCours()->count(2)->create(['npi' => $npi, 'created_at' => now()->subDays(5)]);
        Demande::factory()->validee()->create(['npi' => $npi, 'created_at' => now()->subDays(10)]);
        Demande::factory()->rejetee()->create(['npi' => $npi, 'created_at' => now()->subDays(12)]);

        // Un second usager, pour vérifier que les listes ne se mélangent pas.
        Demande::factory()->count(2)->create(['npi' => '0987654321']);

        // Un usager avec 25 demandes, pour voir la pagination : 20 en page 1, 5 en page 2.
        Demande::factory()->count(25)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subHours($sequence->index + 1)])
            ->create(['npi' => '1111111111']);
    }
}
