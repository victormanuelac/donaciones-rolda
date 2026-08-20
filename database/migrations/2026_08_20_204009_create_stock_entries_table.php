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
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_item_id')->constrained('master_items');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('registered_by_user_id')->constrained('users');
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('lot_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('status', 20)->default('available'); // pending_arrival, available, expired, withdrawn
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
            $table->index(['expiry_date']);
            $table->index(['master_item_id', 'warehouse_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
    }
};
