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
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('census_entry_id')->nullable()->constrained()->nullOnDelete();

            $table->string('full_name', 150);
            $table->string('document_type', 20)->nullable();
            $table->text('document_number')->nullable(); // encriptado a nivel de app (PII)
            $table->string('relationship_to_head', 60)->nullable();
            $table->string('sex', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->boolean('is_household_head')->default(false);

            $table->timestamps();

            $table->index(['family_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
