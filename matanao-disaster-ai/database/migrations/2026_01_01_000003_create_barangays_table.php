<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('barangays')) {
            return;
        }

        Schema::create('barangays', function (Blueprint $table) {
            $table->id('barangay_id');
            $table->string('barangay_name', 100);
            $table->string('municipality', 100)->default('Matanao');
            $table->string('province', 100)->default('Davao del Sur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
