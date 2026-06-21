<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add per-family fields to affected_families table
        Schema::table('affected_families', function (Blueprint $table) {
            $table->text('description')->nullable()->after('evacuation_status');
            $table->string('damage_severity', 20)->nullable()->after('description');
            $table->decimal('latitude', 10, 7)->nullable()->after('damage_severity');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Add family_id to report_images so photos can be linked per family
        Schema::table('report_images', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id')->nullable()->after('report_id');
        });
    }

    public function down(): void
    {
        Schema::table('affected_families', function (Blueprint $table) {
            $table->dropColumn(['description', 'damage_severity', 'latitude', 'longitude']);
        });

        Schema::table('report_images', function (Blueprint $table) {
            $table->dropColumn('family_id');
        });
    }
};
