<?php

namespace Tests\Feature;

use App\Models\Demande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests automatisés des règles de gestion de l'API des demandes.
 * Lancement : php artisan test
 */
class DemandeApiTest extends TestCase
{
    use RefreshDatabase;

    private const NPI = '1234567890';

    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'npi' => self::NPI,
            'type_acte' => 'acte_naissance',
            'nombre_copies' => 2,
        ], $surcharge);
    }

    // ---------- Dépôt ----------

    public function test_un_usager_peut_deposer_une_demande(): void
    {
        $this->postJson('/api/demandes', $this->donnees())
            ->assertCreated()
            ->assertJsonPath('data.npi', self::NPI)
            ->assertJsonPath('data.type_acte', 'acte_naissance')
            ->assertJsonPath('data.nombre_copies', 2)
            ->assertJsonPath('data.statut', 'deposee')
            ->assertJsonStructure(['data' => ['id', 'cree_le', 'transitions_possibles']]);

        $this->assertDatabaseHas('demandes', ['npi' => self::NPI, 'statut' => 'deposee']);
    }

    public function test_le_statut_envoye_par_le_client_est_ignore_au_depot(): void
    {
        $this->postJson('/api/demandes', $this->donnees(['statut' => 'validee']))
            ->assertCreated()
            ->assertJsonPath('data.statut', 'deposee');
    }

    public static function saisiesInvalides(): array
    {
        return [
            'NPI absent' => [['npi' => null], 'npi'],
            'NPI de 9 chiffres' => [['npi' => '123456789'], 'npi'],
            'NPI de 11 chiffres' => [['npi' => '12345678901'], 'npi'],
            'NPI avec lettres' => [['npi' => '12345abcde'], 'npi'],
            "type d'acte inconnu" => [['type_acte' => 'passeport'], 'type_acte'],
            "type d'acte absent" => [['type_acte' => null], 'type_acte'],
            'zéro copie' => [['nombre_copies' => 0], 'nombre_copies'],
            'six copies' => [['nombre_copies' => 6], 'nombre_copies'],
            'copies non entières' => [['nombre_copies' => 'deux'], 'nombre_copies'],
            'copies décimales' => [['nombre_copies' => 2.5], 'nombre_copies'],
            'copies booléennes' => [['nombre_copies' => true], 'nombre_copies'],
        ];
    }

    #[DataProvider('saisiesInvalides')]
    public function test_une_saisie_invalide_est_refusee_avec_un_message(array $surcharge, string $champ): void
    {
        $this->postJson('/api/demandes', $this->donnees($surcharge))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($champ);

        $this->assertDatabaseCount('demandes', 0);
    }

    public function test_les_bornes_1_et_5_copies_sont_acceptees(): void
    {
        $this->postJson('/api/demandes', $this->donnees(['nombre_copies' => 1]))->assertCreated();
        $this->postJson('/api/demandes', $this->donnees(['nombre_copies' => 5]))->assertCreated();
    }

    public function test_les_messages_sont_renvoyes_avec_les_accents_en_clair(): void
    {
        $this->postJson('/api/demandes', $this->donnees())
            ->assertCreated()
            ->assertSee('"statut_libelle":"Déposée"', false);
    }

    // ---------- Consultation ----------

    public function test_les_demandes_d_un_usager_sont_triees_de_la_plus_recente_a_la_plus_ancienne(): void
    {
        $ancienne = Demande::factory()->create(['npi' => self::NPI, 'created_at' => now()->subDays(3)]);
        $recente = Demande::factory()->create(['npi' => self::NPI, 'created_at' => now()->subDay()]);
        Demande::factory()->create(['npi' => '9999999999']); // autre usager, ne doit pas apparaître

        $this->getJson('/api/usagers/'.self::NPI.'/demandes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $recente->id)
            ->assertJsonPath('data.1.id', $ancienne->id);
    }

    public function test_la_liste_peut_etre_filtree_par_statut(): void
    {
        Demande::factory()->create(['npi' => self::NPI]);
        Demande::factory()->enCours()->create(['npi' => self::NPI]);

        $this->getJson('/api/usagers/'.self::NPI.'/demandes?statut=en_cours')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.statut', 'en_cours');
    }

    public function test_un_filtre_ou_un_npi_invalide_est_refuse(): void
    {
        $this->getJson('/api/usagers/'.self::NPI.'/demandes?statut=inconnu')
            ->assertUnprocessable()->assertJsonValidationErrors('statut');

        $this->getJson('/api/usagers/123/demandes')
            ->assertUnprocessable()->assertJsonValidationErrors('npi');
    }

    public function test_la_liste_est_paginee_a_20_demandes_maximum(): void
    {
        Demande::factory()->count(25)->create(['npi' => self::NPI]);

        $this->getJson('/api/usagers/'.self::NPI.'/demandes')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/usagers/'.self::NPI.'/demandes?page=2')->assertJsonCount(5, 'data');

        $this->getJson('/api/usagers/'.self::NPI.'/demandes?par_page=50')
            ->assertUnprocessable()->assertJsonValidationErrors('par_page');
    }

    // ---------- Cycle de vie ----------

    public function test_cycle_de_vie_complet_jusqu_a_la_validation(): void
    {
        $demande = Demande::factory()->create();

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'en_cours'])
            ->assertOk()->assertJsonPath('data.statut', 'en_cours');

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'validee'])
            ->assertOk()->assertJsonPath('data.statut', 'validee')
            ->assertJsonPath('data.transitions_possibles', []);

        $this->assertNotNull($demande->fresh()->traitee_le);
    }

    public function test_une_demande_deposee_ne_peut_pas_etre_validee_directement(): void
    {
        $demande = Demande::factory()->create();

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'validee'])
            ->assertStatus(409)
            ->assertJsonPath('statut_actuel', 'deposee');

        $this->assertSame('deposee', $demande->fresh()->statut->value);
    }

    public function test_un_rejet_sans_motif_est_refuse(): void
    {
        $demande = Demande::factory()->enCours()->create();

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'rejetee'])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'rejetee', 'motif' => '   '])
            ->assertUnprocessable()->assertJsonValidationErrors('motif');

        $this->assertSame('en_cours', $demande->fresh()->statut->value);
    }

    public function test_un_rejet_motive_est_enregistre(): void
    {
        $demande = Demande::factory()->enCours()->create();

        $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => 'rejetee', 'motif' => 'Pièce manquante'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'rejetee')
            ->assertJsonPath('data.motif_rejet', 'Pièce manquante');
    }

    public function test_une_demande_validee_ou_rejetee_ne_change_plus(): void
    {
        $demandes = [Demande::factory()->validee()->create(), Demande::factory()->rejetee()->create()];

        foreach ($demandes as $demande) {
            foreach (['deposee', 'en_cours', 'validee', 'rejetee'] as $cible) {
                $this->patchJson("/api/demandes/{$demande->id}/statut", ['statut' => $cible, 'motif' => 'test'])
                    ->assertStatus(409);
            }
        }
    }

    public function test_une_demande_inexistante_renvoie_404(): void
    {
        $this->getJson('/api/demandes/9999')->assertNotFound()->assertJsonPath('message', 'Demande introuvable.');
        $this->patchJson('/api/demandes/9999/statut', ['statut' => 'en_cours'])->assertNotFound();
    }

    // ---------- Statistiques ----------

    public function test_le_nombre_de_demandes_par_statut_est_affiche(): void
    {
        Demande::factory()->count(2)->create(['npi' => self::NPI]);
        Demande::factory()->rejetee()->create(['npi' => self::NPI]);
        Demande::factory()->create(['npi' => '9999999999']);

        $this->getJson('/api/statistiques?npi='.self::NPI)
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.par_statut', ['deposee' => 2, 'en_cours' => 0, 'validee' => 0, 'rejetee' => 1]);

        $this->getJson('/api/statistiques')->assertJsonPath('data.total', 4);
    }

    // ---------- Point d'entrée et données de démonstration ----------

    public function test_le_point_d_entree_de_l_api_liste_les_routes(): void
    {
        $this->get('/api')
            ->assertOk()
            ->assertJsonStructure(['message', 'routes' => [['methode', 'route', 'role']], 'exemples'])
            ->assertJsonFragment(['methode' => 'POST', 'route' => '/api/demandes']);

        // /api est traité comme le reste de l'API : erreur en JSON, même sans en-tête Accept.
        $this->delete('/api')->assertStatus(405)->assertJsonPath('message', 'Méthode HTTP non autorisée pour cette route.');
    }

    public function test_les_donnees_de_demonstration_permettent_de_voir_la_pagination(): void
    {
        $this->seed();

        $this->getJson('/api/usagers/1111111111/demandes')
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/usagers/1111111111/demandes?page=2')->assertJsonCount(5, 'data');
        $this->getJson('/api/usagers/1234567890/demandes')->assertJsonPath('meta.total', 7);
    }

    // ---------- Écran ----------

    public function test_l_ecran_de_depot_et_de_consultation_s_affiche(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Déposer une demande')
            ->assertSee("Demandes d'un usager", false)
            ->assertSee('Certificat de résidence')
            ->assertSee('En cours de traitement');
    }
}
