<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('disaster_reports', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('created_at');
            }
        });

        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicular_accidents', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('disaster_reports', function (Blueprint $table) {
            if (Schema::hasColumn('disaster_reports', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });

        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (Schema::hasColumn('vehicular_accidents', 'archived_at')) {
                $table->dropColumn('archived_at');
            }
        });
    }
};
