<?php

namespace App\Http\Requests;

use App\Enums\StatutDemande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation des paramètres de liste : NPI (dans l'URL ou en filtre),
 * statut facultatif et pagination (20 demandes par page au maximum).
 */
class ListeDemandesRequest extends FormRequest
{
    public const PAR_PAGE_MAX = 20;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Sur /api/usagers/{npi}/demandes, le NPI vient de l'URL : on le valide comme le reste.
        if ($this->route('npi') !== null) {
            $this->merge(['npi' => $this->route('npi')]);
        }
    }

    public function rules(): array
    {
        return [
            'npi' => ['sometimes', 'bail', 'string', 'regex:/^\d{10}$/'],
            'statut' => ['sometimes', 'nullable', 'string', Rule::in(StatutDemande::valeurs())],
            'par_page' => ['sometimes', 'bail', 'integer', 'between:1,'.self::PAR_PAGE_MAX],
            'page' => ['sometimes', 'bail', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        $statuts = implode(', ', StatutDemande::valeurs());

        return [
            'npi.regex' => 'Le NPI doit comporter exactement 10 chiffres.',
            'npi.string' => 'Le NPI doit être une suite de 10 chiffres.',
            'statut.in' => "Le statut doit être l'un des suivants : {$statuts}.",
            'statut.string' => 'Le statut doit être une chaîne de caractères.',
            'par_page.integer' => 'Le paramètre par_page doit être un nombre entier.',
            'par_page.between' => 'Le paramètre par_page doit être compris entre 1 et '.self::PAR_PAGE_MAX.'.',
            'page.integer' => 'Le numéro de page doit être un nombre entier.',
            'page.min' => 'Le numéro de page doit être supérieur ou égal à 1.',
        ];
    }
}
