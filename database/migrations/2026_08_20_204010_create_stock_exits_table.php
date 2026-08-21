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
        Schema::create('stock_exits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained('stock_entries');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('released_by_user_id')->constrained('users');
            $table->text('received_by_name')->nullable(); // encriptado a nivel de app (PII)
            $table->foreignId('destination_zone_id')->nullable()->constrained('geographic_zones')->nullOnDelete();
            $table->text('destination_description')->nullable();
            $table->string('exit_reason', 30); // donation, subsidized_sale, emergency_assistance, other
            $table->unsignedInteger('quantity_released');
            $table->timestamp('release_date')->useCurrent();
            $table->boolean('signed_by_receiver')->default(false);
            $table->string('signature_path')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();

            $table->index(['release_date']);
            $table->index(['warehouse_id', 'release_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_exits');
    }
};
