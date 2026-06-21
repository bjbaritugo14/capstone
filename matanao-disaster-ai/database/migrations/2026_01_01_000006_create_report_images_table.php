<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('report_images')) {
            return;
        }

        Schema::create('report_images', function (Blueprint $table) {
            $table->id('image_id');
            $table->foreignId('report_id')
                ->constrained('disaster_reports', 'report_id')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('image_path');
            $table->timestamp('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_images');
    }
};
