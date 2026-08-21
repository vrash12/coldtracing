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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('min_temp', 8, 2);
            $table->decimal('max_temp', 8, 2);
            $table->decimal('initial_shelf_life_hours', 12, 2);
            $table->decimal('reference_storage_temp_celsius', 8, 2)->nullable();
            $table->decimal('activation_energy_j_per_mol', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
