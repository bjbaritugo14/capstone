<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('incident_locations', function (Blueprint $table) {
            $table->index('barangay_id', 'incident_locations_barangay_idx');
            $table->index('created_at', 'incident_locations_created_idx');
        });

        Schema::table('disaster_reports', function (Blueprint $table) {
            $table->index('user_id', 'disaster_reports_user_idx');
            $table->index('location_id', 'disaster_reports_location_idx');
            $table->index('status', 'disaster_reports_status_idx');
            $table->index('incident_datetime', 'disaster_reports_incident_dt_idx');
            $table->index('created_at', 'disaster_reports_created_idx');
            $table->index(['status', 'created_at'], 'disaster_reports_status_created_idx');

            if (Schema::hasColumn('disaster_reports', 'archived_at')) {
                $table->index('archived_at', 'disaster_reports_archived_idx');
            }
        });

        Schema::table('vehicular_accidents', function (Blueprint $table) {
            $table->index('user_id', 'vehicular_accidents_user_idx');
            $table->index('location_id', 'vehicular_accidents_location_idx');
            $table->index('status', 'vehicular_accidents_status_idx');
            $table->index('incident_datetime', 'vehicular_accidents_incident_dt_idx');
            $table->index('created_at', 'vehicular_accidents_created_idx');
            $table->index(['status', 'created_at'], 'vehicular_accidents_status_created_idx');

            if (Schema::hasColumn('vehicular_accidents', 'archived_at')) {
                $table->index('archived_at', 'vehicular_accidents_archived_idx');
            }
        });

        Schema::table('affected_families', function (Blueprint $table) {
            $table->index('report_id', 'affected_families_report_idx');
            $table->index('family_head_name', 'affected_families_head_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('affected_families', function (Blueprint $table) {
            $table->dropIndex('affected_families_head_name_idx');
            $table->dropIndex('affected_families_report_idx');
        });

        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (Schema::hasColumn('vehicular_accidents', 'archived_at')) {
                $table->dropIndex('vehicular_accidents_archived_idx');
            }

            $table->dropIndex('vehicular_accidents_status_created_idx');
            $table->dropIndex('vehicular_accidents_created_idx');
            $table->dropIndex('vehicular_accidents_incident_dt_idx');
            $table->dropIndex('vehicular_accidents_status_idx');
            $table->dropIndex('vehicular_accidents_location_idx');
            $table->dropIndex('vehicular_accidents_user_idx');
        });

        Schema::table('disaster_reports', function (Blueprint $table) {
            if (Schema::hasColumn('disaster_reports', 'archived_at')) {
                $table->dropIndex('disaster_reports_archived_idx');
            }

            $table->dropIndex('disaster_reports_status_created_idx');
            $table->dropIndex('disaster_reports_created_idx');
            $table->dropIndex('disaster_reports_incident_dt_idx');
            $table->dropIndex('disaster_reports_status_idx');
            $table->dropIndex('disaster_reports_location_idx');
            $table->dropIndex('disaster_reports_user_idx');
        });

        Schema::table('incident_locations', function (Blueprint $table) {
            $table->dropIndex('incident_locations_created_idx');
            $table->dropIndex('incident_locations_barangay_idx');
        });
    }
};
