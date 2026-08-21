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
        Schema::table('stock_entries', function (Blueprint $table) {
            // Marca un lote como creado por un ajuste positivo de conteo físico
            // (se encontraron más unidades de las que registraba el sistema),
            // en vez de una recepción real. Ver RegisterStockCountAction.
            $table->foreignId('adjustment_stock_count_id')->nullable()->after('transferred_from_stock_entry_id')->constrained('stock_counts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjustment_stock_count_id');
        });
    }
};
