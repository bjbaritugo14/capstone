<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('disaster_events')) {
            return;
        }

        Schema::create('disaster_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name');
            $table->string('disaster_type', 100);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disaster_events');
    }
};
