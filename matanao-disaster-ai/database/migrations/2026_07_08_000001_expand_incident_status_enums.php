<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE disaster_reports
            MODIFY status ENUM('pending', 'validated', 'rejected', 'returned')
            NOT NULL DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE vehicular_accidents
            MODIFY status ENUM('recorded', 'verified', 'closed', 'validated', 'returned')
            NOT NULL DEFAULT 'recorded'
        ");

        DB::statement("
            ALTER TABLE report_validations
            MODIFY validation_status ENUM('validated', 'rejected', 'returned')
            NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE disaster_reports
            MODIFY status ENUM('pending', 'validated', 'rejected')
            NOT NULL DEFAULT 'pending'
        ");

        DB::statement("
            ALTER TABLE vehicular_accidents
            MODIFY status ENUM('recorded', 'verified', 'closed')
            NOT NULL DEFAULT 'recorded'
        ");

        DB::statement("
            ALTER TABLE report_validations
            MODIFY validation_status ENUM('validated', 'rejected')
            NOT NULL
        ");
    }
};
