<?php

namespace App\Http\Requests;

use App\Enums\TypeActe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du dépôt d'une demande (règles de gestion du sujet).
 */
class StoreDemandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Un NPI envoyé comme nombre JSON est converti en texte pour un format homogène.
        if (is_int($this->input('npi'))) {
            $this->merge(['npi' => (string) $this->input('npi')]);
        }
    }

    public function rules(): array
    {
        return [
            // Règle : le NPI comporte exactement 10 chiffres.
            'npi' => ['bail', 'required', 'string', 'regex:/^\d{10}$/'],
            // Règle : l'un des trois types d'actes cités.
            'type_acte' => ['bail', 'required', 'string', Rule::in(TypeActe::valeurs())],
            // Règle : entre 1 et 5 copies. « numeric » écarte les booléens, que « integer » accepterait (true = 1).
            'nombre_copies' => ['bail', 'required', 'numeric', 'integer', 'between:1,5'],
        ];
    }

    public function messages(): array
    {
        $types = implode(', ', TypeActe::valeurs());

        return [
            'npi.required' => 'Le NPI est obligatoire.',
            'npi.string' => 'Le NPI doit être une suite de 10 chiffres.',
            'npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
            'type_acte.required' => "Le type d'acte est obligatoire.",
            'type_acte.string' => "Le type d'acte doit être une chaîne de caractères.",
            'type_acte.in' => "Le type d'acte doit être l'un des suivants : {$types}.",
            'nombre_copies.required' => 'Le nombre de copies est obligatoire.',
            'nombre_copies.numeric' => 'Le nombre de copies doit être un nombre entier.',
            'nombre_copies.integer' => 'Le nombre de copies doit être un nombre entier.',
            'nombre_copies.between' => 'Le nombre de copies doit être compris entre 1 et 5.',
        ];
    }
}
