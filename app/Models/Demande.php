<?php

namespace App\Models;

use App\Enums\StatutDemande;
use App\Enums\TypeActe;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Demande d'acte administratif déposée par un usager (identifié par son NPI).
 *
 * @property int $id
 * @property string $npi
 * @property TypeActe $type_acte
 * @property int $nombre_copies
 * @property StatutDemande $statut
 * @property string|null $motif_rejet
 * @property Carbon|null $traitee_le
 */
class Demande extends Model
{
    use HasFactory;

    protected $fillable = [
        'npi',
        'type_acte',
        'nombre_copies',
        'statut',
        'motif_rejet',
        'traitee_le',
    ];

    /** Toute nouvelle demande démarre au statut « déposée ». */
    protected $attributes = [
        'statut' => 'deposee',
    ];

    protected function casts(): array
    {
        return [
            'type_acte' => TypeActe::class,
            'statut' => StatutDemande::class,
            'nombre_copies' => 'integer',
            'traitee_le' => 'datetime',
        ];
    }
}
