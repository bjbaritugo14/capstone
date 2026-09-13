<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicular_accidents', 'involved_person_first_name')) {
                $table->string('involved_person_first_name', 75)->nullable()->after('involved_person_name');
            }

            if (! Schema::hasColumn('vehicular_accidents', 'involved_person_last_name')) {
                $table->string('involved_person_last_name', 75)->nullable()->after('involved_person_first_name');
            }
        });

        Schema::table('accident_involved_persons', function (Blueprint $table) {
            if (! Schema::hasColumn('accident_involved_persons', 'first_name')) {
                $table->string('first_name', 75)->nullable()->after('person_name');
            }

            if (! Schema::hasColumn('accident_involved_persons', 'last_name')) {
                $table->string('last_name', 75)->nullable()->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accident_involved_persons', function (Blueprint $table) {
            if (Schema::hasColumn('accident_involved_persons', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('accident_involved_persons', 'first_name')) {
                $table->dropColumn('first_name');
            }
        });

        Schema::table('vehicular_accidents', function (Blueprint $table) {
            if (Schema::hasColumn('vehicular_accidents', 'involved_person_last_name')) {
                $table->dropColumn('involved_person_last_name');
            }

            if (Schema::hasColumn('vehicular_accidents', 'involved_person_first_name')) {
                $table->dropColumn('involved_person_first_name');
            }
        });
    }
};
