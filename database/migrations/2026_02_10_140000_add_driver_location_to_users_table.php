<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('is_active');
            $table->decimal('last_latitude', 10, 8)->nullable()->after('is_online');
            $table->decimal('last_longitude', 11, 8)->nullable()->after('last_latitude');
            $table->timestamp('last_location_at')->nullable()->after('last_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'last_latitude', 'last_longitude', 'last_location_at']);
        });
    }
};
