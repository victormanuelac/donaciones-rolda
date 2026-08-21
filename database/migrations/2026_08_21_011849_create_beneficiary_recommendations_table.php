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
        Schema::create('beneficiary_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_recommendation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_item_id')->constrained();
            $table->unsignedInteger('quantity_recommended');
            $table->string('frequency', 50); // daily, weekly, once, every_6_months...
            $table->unsignedInteger('duration_days')->nullable();
            $table->string('status', 20)->default('pending'); // pending, fulfilled, expired, cancelled
            $table->unsignedInteger('available_stock')->default(0);
            // [{"warehouse_id":1,"name":"Bodega Centro","quantity":10,"distance_km":2.3}]
            $table->json('available_warehouses')->nullable();
            $table->timestamp('recommended_at')->useCurrent();
            $table->foreignId('recommended_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['beneficiary_id', 'protocol_recommendation_id', 'master_item_id'], 'beneficiary_protocol_item_unique');
            $table->index(['beneficiary_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_recommendations');
    }
};
