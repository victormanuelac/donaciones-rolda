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
        Schema::create('geographic_zones', function (Blueprint $table) {
            $table->id();
            $table->string('municipality', 100)->default('Roldanillo');
            $table->string('zone_type', 20);
            $table->string('name', 150);
            $table->string('code', 20)->nullable()->unique();
            $table->foreignId('parent_zone_id')->nullable()->constrained('geographic_zones')->nullOnDelete();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['municipality', 'zone_type', 'name']);
            $table->index(['is_active', 'municipality']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geographic_zones');
    }
};
