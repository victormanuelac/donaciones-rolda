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
        Schema::table('stock_exits', function (Blueprint $table) {
            // Módulo 6 (Entregas y Seguimiento): vincula la salida al hogar
            // beneficiario registrado en el censo, cuando el destino es un
            // beneficiario conocido en vez de una donación/traslado genérico.
            $table->foreignId('family_id')->nullable()->after('warehouse_id')->constrained('families')->nullOnDelete();
            $table->index(['family_id', 'release_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_exits', function (Blueprint $table) {
            $table->dropIndex(['family_id', 'release_date']);
            $table->dropConstrainedForeignId('family_id');
        });
    }
};
