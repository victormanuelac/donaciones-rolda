<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('census_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Metadatos de captura (Módulo A) y sincronización offline
            $table->string('form_code')->unique();
            $table->string('phase', 10)->default('fase_1');
            $table->string('form_version', 20)->default('fase_1-v1');
            $table->dateTime('surveyed_at');
            $table->string('surveyor_entity', 60);
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('sync_status', 10)->default('synced'); // synced, pending, conflict

            // Consentimiento LSPP (Módulo B, bloqueante)
            $table->boolean('consent_given');
            $table->string('consent_minors', 15)->nullable();
            $table->string('consent_given_by_name', 150)->nullable();
            $table->string('consent_relationship', 60)->nullable();

            // Conteos agregados del núcleo familiar (Módulo E, versión corta)
            $table->unsignedTinyInteger('total_people');
            $table->unsignedTinyInteger('under_5_count')->default(0);
            $table->unsignedTinyInteger('over_60_count')->default(0);
            $table->unsignedTinyInteger('pregnant_lactating_count')->default(0);
            $table->unsignedTinyInteger('disability_count')->default(0);
            $table->unsignedTinyInteger('chronic_illness_count')->default(0);

            // Seguridad alimentaria (Módulo H, rCSI condicional)
            $table->unsignedTinyInteger('meals_yesterday')->nullable();
            $table->unsignedTinyInteger('rcsi_less_preferred')->nullable();
            $table->unsignedTinyInteger('rcsi_borrow_food')->nullable();
            $table->unsignedTinyInteger('rcsi_reduce_portion')->nullable();
            $table->unsignedTinyInteger('rcsi_reduce_adult_consumption')->nullable();
            $table->unsignedTinyInteger('rcsi_reduce_meals')->nullable();

            // Salud (Módulo I)
            $table->boolean('injured')->default(false);
            $table->boolean('needs_urgent_medical_attention')->default(false);
            $table->boolean('lost_permanent_medication')->default(false);

            // Alojamiento (Módulo J)
            $table->string('sleeping_location', 40);
            $table->string('needs_temporary_shelter', 20);

            // Entorno (Módulo K) y necesidades (Módulo L)
            $table->json('environment_risks')->nullable();
            $table->string('access_passable', 15);
            $table->json('priority_needs');
            $table->string('registered_in_rud', 15);

            // Cierre (Módulo M)
            $table->string('damage_verified', 15);
            $table->boolean('needs_structural_assessment')->default(false);
            $table->string('signature_path')->nullable();

            // Índice de vulnerabilidad calculado (ver App\Services\CensusScoring\VulnerabilityIndexService)
            $table->unsignedTinyInteger('vulnerability_score');
            $table->string('priority_level', 10);
            $table->json('red_flags')->nullable();

            $table->timestamps();

            $table->index(['priority_level', 'created_at']);
            $table->index(['sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('census_entries');
    }
};
