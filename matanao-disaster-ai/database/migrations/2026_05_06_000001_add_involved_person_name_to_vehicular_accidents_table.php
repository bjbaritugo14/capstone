<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicular_accidents', 'involved_person_name')) {
                $table->string('involved_person_name', 150)->nullable()->after('accident_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (Schema::hasColumn('vehicular_accidents', 'involved_person_name')) {
                $table->dropColumn('involved_person_name');
            }
        });
    }
};
