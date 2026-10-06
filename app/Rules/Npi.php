<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Règle de gestion : le NPI comporte exactement 10 chiffres.
 *
 * Définie à un seul endroit et utilisée par le dépôt, la liste des demandes et les statistiques.
 * Le modificateur « D » empêche « $ » d'accepter un saut de ligne final (« 1234567890\n »),
 * et [0-9] n'accepte que les chiffres latins.
 */
class Npi implements ValidationRule
{
    public const MESSAGE = 'Le NPI doit comporter exactement 10 chiffres.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[0-9]{10}$/D', $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }
}
