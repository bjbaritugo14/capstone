<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add vehicle_type column to vehicular_accidents
        if (!Schema::hasColumn('vehicular_accidents', 'vehicle_type')) {
            Schema::table('vehicular_accidents', function (Blueprint $table) {
                $table->string('vehicle_type', 100)->nullable()->after('accident_type');
            });
        }

        // Create table for multiple involved persons
        if (!Schema::hasTable('accident_involved_persons')) {
            Schema::create('accident_involved_persons', function (Blueprint $table) {
                $table->id('person_id');
                $table->unsignedBigInteger('accident_id');
                $table->string('person_name', 150);
                $table->string('role', 50)->nullable(); // driver, passenger, pedestrian
                $table->string('contact_number', 30)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('accident_id')
                    ->references('accident_id')
                    ->on('vehicular_accidents')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_involved_persons');

        if (Schema::hasColumn('vehicular_accidents', 'vehicle_type')) {
            Schema::table('vehicular_accidents', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
            });
        }
    }
};
