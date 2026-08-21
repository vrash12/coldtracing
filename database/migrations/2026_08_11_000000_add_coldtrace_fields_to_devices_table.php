<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devices', 'truck_id')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->foreignId('truck_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('trucks')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('devices', 'device_code')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('device_code')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('devices', 'mqtt_topic')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('mqtt_topic')->nullable()->unique();
            });
        }

        if (! Schema::hasColumn('devices', 'status')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->string('status')->default('inactive')->index();
            });
        }

        if (! Schema::hasColumn('devices', 'last_seen_at')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->timestamp('last_seen_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // This compatibility migration may add only a subset of the columns
        // to an existing installation, so automatic removal is intentionally
        // avoided to protect pre-existing device data and assignments.
    }
};
