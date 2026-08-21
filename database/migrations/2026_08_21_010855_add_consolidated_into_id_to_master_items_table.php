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
        Schema::table('master_items', function (Blueprint $table) {
            // Módulo 4 (Control Maestro de Ítems): cuando un ítem se marca
            // como duplicado, apunta aquí al ítem aprobado con el que se
            // consolidó (ver ConsolidateMasterItemAction).
            $table->foreignId('consolidated_into_id')->nullable()->after('status')->constrained('master_items')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consolidated_into_id');
        });
    }
};
