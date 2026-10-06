<?php

namespace Database\Seeders;

use App\Models\Demande;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour tester rapidement l'API et l'écran.
 * Usager principal : NPI 1234567890.
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
    }
}
