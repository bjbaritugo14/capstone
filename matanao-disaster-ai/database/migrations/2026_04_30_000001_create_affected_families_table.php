<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('affected_families')) {
            return;
        }

        Schema::create('affected_families', function (Blueprint $table) {
            $table->id('family_id');
            $table->integer('report_id');
            $table->string('family_head_name', 150);
            $table->unsignedInteger('household_members')->default(1);
            $table->string('contact_number', 30)->nullable();
            $table->string('evacuation_status', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affected_families');
    }
};
