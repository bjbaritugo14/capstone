<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('disaster_reports')) {
            return;
        }

        Schema::create('disaster_reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('location_id')
                ->constrained('incident_locations', 'location_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('disaster_type', 100);
            $table->text('description')->nullable();
            $table->enum('damage_severity', ['minor', 'moderate', 'severe']);
            $table->unsignedInteger('affected_families')->default(0);
            $table->unsignedInteger('affected_structures')->default(0);
            $table->dateTime('incident_datetime');
            $table->enum('status', ['pending', 'validated', 'rejected', 'returned'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disaster_reports');
    }
};
