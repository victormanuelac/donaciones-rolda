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
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->nullable()->constrained('geographic_zones')->nullOnDelete();

            // Ubicación (Módulo C del formulario de censo)
            $table->string('department', 100)->default('Valle del Cauca');
            $table->string('municipality', 100)->default('Roldanillo');
            $table->string('zone_type', 10); // urbana, rural
            $table->string('neighborhood', 150)->nullable();
            $table->string('address', 255);
            $table->text('phone')->nullable(); // encriptado a nivel de app (PII)
            $table->string('route_code', 50)->nullable();

            // GPS (captura vía Geolocation API, con fallback de pin manual)
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('gps_accuracy_meters')->nullable();
            $table->timestamp('gps_captured_at')->nullable();
            $table->string('facade_photo_path')->nullable();

            // Jefe de hogar (Módulo D)
            $table->string('head_full_name', 150);
            $table->string('head_document_type', 20)->nullable();
            $table->text('head_document_number')->nullable(); // encriptado a nivel de app (PII)
            $table->string('head_sex', 20)->nullable();
            $table->date('head_birthdate')->nullable();
            $table->string('head_gender_identity', 30)->nullable();

            // Vivienda y servicios (Módulos F y G)
            $table->string('housing_damage_level', 30); // destruida, averiada_no_habitable, averiada_habitable, sin_dano
            $table->string('housing_inspection_mark', 20)->nullable(); // semáforo: verde, amarillo, naranja, rojo, sin_marca
            $table->string('tenure_type', 30)->nullable();
            $table->unsignedInteger('monthly_rent')->nullable();
            $table->string('water_access', 20)->nullable();
            $table->string('water_source', 30)->nullable();
            $table->string('electricity_access', 20)->nullable();
            $table->string('sanitation_access', 20)->nullable();
            $table->unsignedTinyInteger('rooms_count')->nullable();

            $table->unsignedTinyInteger('household_size')->default(1);
            $table->boolean('overcrowding')->default(false);

            $table->timestamps();

            $table->index(['zone_type', 'neighborhood']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
