<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('permanent_delivery_address')->nullable();
            $table->decimal('permanent_delivery_lat', 10, 7)->nullable();
            $table->decimal('permanent_delivery_lng', 10, 7)->nullable();
            $table->string('status')->default('active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'role_id',
                'phone',
                'permanent_delivery_address',
                'permanent_delivery_lat',
                'permanent_delivery_lng',
                'status',
            ]);
        });
    }
};
