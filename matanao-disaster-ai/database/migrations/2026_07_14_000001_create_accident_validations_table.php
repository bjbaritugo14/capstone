<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('accident_validations')) {
            return;
        }

        Schema::create('accident_validations', function (Blueprint $table) {
            $table->id('validation_id');
            $table->foreignId('accident_id')
                ->unique()
                ->constrained('vehicular_accidents', 'accident_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('validated_by')
                ->constrained('users', 'user_id')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->enum('validation_status', ['validated', 'rejected', 'returned']);
            $table->text('remarks')->nullable();
            $table->timestamp('validated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_validations');
    }
};
