<?php

namespace Tests\Unit;

use App\Enums\StatutDemande;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du cycle de vie, défini à un seul endroit : l'enum StatutDemande.
 * Ils vérifient toutes les combinaisons (statut actuel, statut cible) sans passer par HTTP.
 */
class StatutDemandeTest extends TestCase
{
    /** Seules transitions autorisées par le sujet. */
    private const AUTORISEES = [
        'deposee' => ['en_cours'],
        'en_cours' => ['validee', 'rejetee'],
        'validee' => [],
        'rejetee' => [],
    ];

    public static function combinaisons(): array
    {
        $cas = [];
        foreach (StatutDemande::cases() as $actuel) {
            foreach (StatutDemande::cases() as $cible) {
                $cas["{$actuel->value} -> {$cible->value}"] = [$actuel, $cible];
            }
        }

        return $cas;
    }

    #[DataProvider('combinaisons')]
    public function test_seules_les_transitions_du_cycle_de_vie_sont_autorisees(StatutDemande $actuel, StatutDemande $cible): void
    {
        $attendu = in_array($cible->value, self::AUTORISEES[$actuel->value], true);

        $this->assertSame($attendu, $actuel->peutPasserA($cible));
    }

    public function test_seules_les_demandes_validees_ou_rejetees_sont_dans_un_etat_final(): void
    {
        $this->assertFalse(StatutDemande::Deposee->estFinal());
        $this->assertFalse(StatutDemande::EnCours->estFinal());
        $this->assertTrue(StatutDemande::Validee->estFinal());
        $this->assertTrue(StatutDemande::Rejetee->estFinal());
    }
}
