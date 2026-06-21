<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('recommendations')) {
            return;
        }

        Schema::create('recommendations', function (Blueprint $table) {
            $table->id('recommendation_id');
            $table->foreignId('barangay_id')
                ->constrained('barangays', 'barangay_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('report_id')
                ->constrained('disaster_reports', 'report_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('generated_by')
                ->constrained('users', 'user_id')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->decimal('cash_assistance', 12, 2)->default(0);
            $table->unsignedInteger('food_packs')->default(0);
            $table->unsignedInteger('medicine_kits')->default(0);
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
