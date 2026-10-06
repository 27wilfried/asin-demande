<?php

namespace App\Enums;

/**
 * Types d'actes administratifs qu'un usager peut demander.
 * La valeur (string) est celle stockée en base et échangée via l'API.
 */
enum TypeActe: string
{
    case ActeNaissance = 'acte_naissance';
    case CasierJudiciaire = 'casier_judiciaire';
    case CertificatResidence = 'certificat_residence';

    /** Libellé lisible, renvoyé dans les réponses de l'API. */
    public function libelle(): string
    {
        return match ($this) {
            self::ActeNaissance => 'Acte de naissance',
            self::CasierJudiciaire => 'Casier judiciaire',
            self::CertificatResidence => 'Certificat de résidence',
        };
    }

    /** @return string[] Valeurs acceptées, utilisées par la validation. */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
