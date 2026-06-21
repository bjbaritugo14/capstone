<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('relief_distributions')) {
            return;
        }

        Schema::create('relief_distributions', function (Blueprint $table) {
            $table->id('distribution_id');
            $table->integer('recommendation_id');
            $table->integer('distributed_by');
            $table->dateTime('date_distributed');
            $table->enum('status', ['Planned', 'Completed', 'Partial'])->default('Planned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relief_distributions');
    }
};
