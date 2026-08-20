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
        Schema::create('expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('stock_entries')->cascadeOnDelete();
            $table->string('alert_type', 10); // 30_days, 14_days, 7_days, expired
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_action', 20)->nullable(); // used, discarded, returned
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['stock_entry_id', 'alert_type']);
            $table->index(['resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expiry_alerts');
    }
};
