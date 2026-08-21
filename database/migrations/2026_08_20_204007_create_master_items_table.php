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
        Schema::create('master_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('unit_of_measure', 30);
            $table->text('description')->nullable();
            $table->boolean('requires_cold_chain')->default(false);
            $table->string('status', 20)->default('approved'); // approved, under_review, rejected
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['category_id']);
            $table->index(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_items');
    }
};
