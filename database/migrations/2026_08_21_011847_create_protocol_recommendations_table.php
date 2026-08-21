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
        Schema::create('protocol_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('protocol_name')->unique();
            $table->string('source', 30); // who, icbf, local_health, municipal, donor
            // Ejemplo: {"age_min":0,"age_max":5} | {"pregnancy":true} | {"chronic_diseases":["Diabetes"]}
            $table->json('trigger_condition');
            // [{"item_id":1,"quantity":1,"frequency":"daily","duration_days":270}]
            $table->json('recommended_items');
            $table->decimal('confidence_level', 3, 2); // 0.00 - 1.00
            $table->boolean('requires_medical_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('protocol_recommendations');
    }
};
