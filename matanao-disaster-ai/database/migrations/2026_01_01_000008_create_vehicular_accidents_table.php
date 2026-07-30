<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vehicular_accidents')) {
            return;
        }

        Schema::create('vehicular_accidents', function (Blueprint $table) {
            $table->id('accident_id');
            $table->foreignId('user_id')
                ->constrained('users', 'user_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('location_id')
                ->constrained('incident_locations', 'location_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('accident_type', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('vehicles_involved')->default(1);
            $table->unsignedInteger('injured_count')->default(0);
            $table->unsignedInteger('fatality_count')->default(0);
            $table->dateTime('incident_datetime');
            $table->enum('status', ['recorded', 'verified', 'closed', 'validated', 'returned'])->default('recorded');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicular_accidents');
    }
};
