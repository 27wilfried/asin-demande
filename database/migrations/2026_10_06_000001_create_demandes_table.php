<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            // Le NPI est stocké en texte pour conserver d'éventuels zéros en tête.
            $table->string('npi', 10);
            $table->string('type_acte', 30);
            $table->unsignedTinyInteger('nombre_copies');
            $table->string('statut', 20)->default('deposee');
            // Obligatoire uniquement pour un rejet (contrôlé par l'application).
            $table->text('motif_rejet')->nullable();
            // Date de la décision finale (validation ou rejet).
            $table->timestamp('traitee_le')->nullable();
            $table->timestamps();

            // Index adaptés aux requêtes de l'API : liste d'un usager triée par date, filtre par statut.
            $table->index(['npi', 'created_at']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
