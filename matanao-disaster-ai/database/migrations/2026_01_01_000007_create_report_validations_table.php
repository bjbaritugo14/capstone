<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('report_validations')) {
            return;
        }

        Schema::create('report_validations', function (Blueprint $table) {
            $table->id('validation_id');
            $table->foreignId('report_id')
                ->unique()
                ->constrained('disaster_reports', 'report_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('validated_by')
                ->constrained('users', 'user_id')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->enum('validation_status', ['validated', 'rejected']);
            $table->text('remarks')->nullable();
            $table->timestamp('validated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_validations');
    }
};
