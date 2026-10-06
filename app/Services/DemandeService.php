<?php

namespace App\Services;

use App\Enums\StatutDemande;
use App\Exceptions\TransitionInterditeException;
use App\Models\Demande;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Logique métier des demandes, isolée du contrôleur HTTP.
 */
class DemandeService
{
    /**
     * Dépose une nouvelle demande. Le statut initial est imposé : « déposée ».
     *
     * @param  array{npi: string, type_acte: string, nombre_copies: int}  $donnees
     */
    public function deposer(array $donnees): Demande
    {
        return Demande::create([
            'npi' => $donnees['npi'],
            'type_acte' => $donnees['type_acte'],
            'nombre_copies' => (int) $donnees['nombre_copies'],
            'statut' => StatutDemande::Deposee,
        ]);
    }

    /**
     * Fait avancer une demande dans son cycle de vie.
     *
     * La transition est vérifiée avant le motif : rejeter une demande déjà validée renvoie
     * « elle ne peut plus changer de statut » (409), et non une demande de motif trompeuse.
     *
     * @throws TransitionInterditeException si la transition n'est pas autorisée (HTTP 409)
     * @throws ValidationException si un rejet n'est pas motivé (HTTP 422)
     */
    public function changerStatut(Demande $demande, StatutDemande $cible, ?string $motif = null): Demande
    {
        return DB::transaction(function () use ($demande, $cible, $motif) {
            // Verrou sur la ligne (MySQL, PostgreSQL) : deux agents ne peuvent pas traiter la même
            // demande en même temps. SQLite, lui, sérialise déjà les écritures sur toute la base.
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            // Règle : cycle de vie déposée -> en cours -> validée | rejetée, statuts finaux figés.
            if (! $demande->statut->peutPasserA($cible)) {
                throw new TransitionInterditeException($demande->statut, $cible);
            }

            // Règle : un rejet doit toujours être motivé.
            if ($cible === StatutDemande::Rejetee && blank($motif)) {
                throw ValidationException::withMessages([
                    'motif' => 'Un rejet doit toujours être motivé : le champ motif est obligatoire.',
                ]);
            }

            $demande->statut = $cible;

            if ($cible === StatutDemande::Rejetee) {
                $demande->motif_rejet = trim($motif);
            }

            if ($cible->estFinal()) {
                $demande->traitee_le = now();
            }

            $demande->save();

            return $demande;
        });
    }
}
