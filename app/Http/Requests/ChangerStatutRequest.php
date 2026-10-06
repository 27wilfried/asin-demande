<?php

namespace App\Http\Requests;

use App\Enums\StatutDemande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation du format d'un changement de statut.
 * Les règles métier (transition autorisée, rejet motivé) sont contrôlées par DemandeService.
 */
class ChangerStatutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'statut' => ['bail', 'required', 'string', Rule::in(StatutDemande::valeurs())],
            // L'obligation de motiver un rejet est vérifiée par DemandeService, après la transition.
            'motif' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        $statuts = implode(', ', StatutDemande::valeurs());

        return [
            'statut.required' => 'Le nouveau statut est obligatoire.',
            'statut.string' => 'Le statut doit être une chaîne de caractères.',
            'statut.in' => "Le statut doit être l'un des suivants : {$statuts}.",
            'motif.string' => 'Le motif doit être un texte.',
            'motif.max' => 'Le motif ne doit pas dépasser 1000 caractères.',
        ];
    }
}
