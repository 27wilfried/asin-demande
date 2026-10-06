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
     * @throws TransitionInterditeException si la transition n'est pas autorisée
     * @throws ValidationException si un rejet n'est pas motivé
     */
    public function changerStatut(Demande $demande, StatutDemande $cible, ?string $motif = null): Demande
    {
        // Double contrôle côté métier, même si la requête HTTP l'a déjà vérifié.
        if ($cible === StatutDemande::Rejetee && blank($motif)) {
            throw ValidationException::withMessages([
                'motif' => 'Un rejet doit toujours être motivé : le champ motif est obligatoire.',
            ]);
        }

        return DB::transaction(function () use ($demande, $cible, $motif) {
            // Verrou sur la ligne : deux agents ne peuvent pas traiter la même demande en même temps.
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            if (! $demande->statut->peutPasserA($cible)) {
                throw new TransitionInterditeException($demande->statut, $cible);
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
