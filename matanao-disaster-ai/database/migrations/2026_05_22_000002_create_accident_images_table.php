<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accident_images', function (Blueprint $table) {
            $table->id('image_id');
            $table->unsignedBigInteger('accident_id');
            $table->string('image_path', 255);
            $table->timestamp('uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_images');
    }
};
