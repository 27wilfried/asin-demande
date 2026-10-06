<?php

namespace Tests\Unit;

use App\Rules\Npi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Règle de gestion : le NPI comporte exactement 10 chiffres.
 */
class NpiTest extends TestCase
{
    public static function valeurs(): array
    {
        return [
            '10 chiffres' => ['1234567890', true],
            'zéros en tête' => ['0012345678', true],
            '9 chiffres' => ['123456789', false],
            '11 chiffres' => ['12345678901', false],
            'lettres' => ['12345abcde', false],
            'espace' => ['12345 7890', false],
            'saut de ligne final' => ["1234567890\n", false],
            'chiffres non latins' => ['١٢٣٤٥٦٧٨٩٠', false],
            'vide' => ['', false],
            'nombre au lieu de texte' => [1234567890, false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('valeurs')]
    public function test_le_npi_comporte_exactement_10_chiffres(mixed $valeur, bool $valide): void
    {
        $message = null;
        (new Npi)->validate('npi', $valeur, function (string $erreur) use (&$message) {
            $message = $erreur;
        });

        $this->assertSame($valide, $message === null);
        if (! $valide) {
            $this->assertSame(Npi::MESSAGE, $message);
        }
    }
}
