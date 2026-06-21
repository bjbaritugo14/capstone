<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('analytics_summaries')) {
            return;
        }

        Schema::create('analytics_summaries', function (Blueprint $table) {
            $table->id('analytics_id');
            $table->integer('event_id');
            $table->integer('barangay_id');
            $table->unsignedInteger('total_reports')->default(0);
            $table->unsignedInteger('total_affected_families')->default(0);
            $table->json('severity_level_summary')->nullable();
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_summaries');
    }
};
