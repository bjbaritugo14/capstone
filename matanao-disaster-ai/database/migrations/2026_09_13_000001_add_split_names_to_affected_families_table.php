<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affected_families', function (Blueprint $table) {
            if (! Schema::hasColumn('affected_families', 'first_name')) {
                $table->string('first_name', 75)->nullable()->after('family_head_name');
            }

            if (! Schema::hasColumn('affected_families', 'last_name')) {
                $table->string('last_name', 75)->nullable()->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('affected_families', function (Blueprint $table) {
            if (Schema::hasColumn('affected_families', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('affected_families', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });
    }
};
