<?php

namespace App\Enums;

/**
 * Statuts d'une demande et règles de son cycle de vie :
 *
 *   deposee ──► en_cours ──► validee
 *                        └─► rejetee
 *
 * Une demande validée ou rejetée est dans un état final : elle ne change plus.
 */
enum StatutDemande: string
{
    case Deposee = 'deposee';
    case EnCours = 'en_cours';
    case Validee = 'validee';
    case Rejetee = 'rejetee';

    public function libelle(): string
    {
        return match ($this) {
            self::Deposee => 'Déposée',
            self::EnCours => 'En cours de traitement',
            self::Validee => 'Validée',
            self::Rejetee => 'Rejetée',
        };
    }

    /**
     * Statuts vers lesquels la demande peut passer depuis le statut actuel.
     * C'est l'unique endroit où le cycle de vie est défini.
     *
     * @return self[]
     */
    public function transitionsPossibles(): array
    {
        return match ($this) {
            self::Deposee => [self::EnCours],
            self::EnCours => [self::Validee, self::Rejetee],
            self::Validee, self::Rejetee => [],
        };
    }

    public function peutPasserA(self $cible): bool
    {
        return in_array($cible, $this->transitionsPossibles(), true);
    }

    /** Un statut final n'a plus aucune transition possible. */
    public function estFinal(): bool
    {
        return $this->transitionsPossibles() === [];
    }

    /** @return string[] */
    public static function valeurs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
